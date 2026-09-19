<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\Process\Process;

class DatabaseController extends Controller
{
    /**
     * The exact string the admin must type before Clear runs.
     */
    protected const CONFIRM_PHRASE = 'DELETE EVERYTHING';

    public function index()
    {
        return Inertia::render('Admin/Database/Index', [
            'db'      => $this->connectionSummary(),
            'backups' => $this->listBackups(),
            'confirm_phrase' => self::CONFIRM_PHRASE,
        ]);
    }

    /**
     * Stream a mysqldump of the current MySQL DB as a .sql download.
     */
    public function backup()
    {
        $path = $this->createBackupFile('manual');

        if (! $path) {
            return back()->with('error', 'Backup failed. Check the Laravel log for mysqldump errors.');
        }

        Log::info('DB backup created via admin panel', [
            'user_id' => Auth::id(),
            'file'    => basename($path),
        ]);

        return response()->download($path, basename($path), [
            'Content-Type' => 'application/sql',
        ])->deleteFileAfterSend(false);
    }

    /**
     * Full reset — takes an auto-backup, then runs migrate:fresh so the schema
     * is left in the exact state a brand-new install produces. The admin who
     * initiated the clear is re-inserted afterwards so they aren't locked out.
     */
    public function clear(Request $request)
    {
        $data = $request->validate([
            'confirm' => ['required', 'string'],
        ]);

        if ($data['confirm'] !== self::CONFIRM_PHRASE) {
            return back()->with('error', 'Confirmation phrase did not match. Nothing was deleted.');
        }

        $adminBefore = Auth::user();
        if (! $adminBefore) {
            return back()->with('error', 'Session expired. Please sign in again before running destructive operations.');
        }

        // Snapshot the exact admin row so we can re-insert it AFTER migrate:fresh
        // drops the users table. This keeps the admin logged in and preserves
        // their id / password hash so their session cookie still validates.
        $adminSnapshot = DB::table('users')->where('id', $adminBefore->id)->first();

        // 1) Auto-backup — no going back after this, so we make one no matter what.
        $backupPath = $this->createBackupFile('pre-clear');
        if (! $backupPath) {
            return back()->with('error', 'Auto-backup failed. Refusing to clear the database without a safety backup.');
        }

        // 2) migrate:fresh — drops all tables, re-runs every migration, runs
        // seed-style migrations (baseline attributes) automatically.
        try {
            Artisan::call('migrate:fresh', ['--force' => true]);
        } catch (\Throwable $e) {
            Log::error('DB clear failed during migrate:fresh: ' . $e->getMessage());
            return back()->with('error', 'Clear failed during migration. A pre-clear backup was saved at ' . basename($backupPath));
        }

        // 3) Re-insert the admin so they aren't locked out. The session cookie
        // survives on the client and re-validates against the restored user row.
        if ($adminSnapshot) {
            DB::table('users')->insert((array) $adminSnapshot);
        }

        Log::warning('DB CLEAR (migrate:fresh) executed via admin panel', [
            'user_id'     => $adminBefore->id,
            'email'       => $adminBefore->email,
            'backup_file' => basename($backupPath),
        ]);

        return back()->with('success', 'Database cleared. A pre-clear backup was saved to storage/backups/' . basename($backupPath));
    }

    /**
     * Download a previously created backup from storage/backups/.
     */
    public function download(string $filename)
    {
        // Guard against path traversal — filename must be a simple .sql basename.
        if (! preg_match('/^[A-Za-z0-9._-]+\.sql$/', $filename)) {
            abort(404);
        }
        $path = storage_path('backups/' . $filename);
        if (! File::exists($path)) {
            abort(404);
        }
        return response()->download($path, $filename, ['Content-Type' => 'application/sql']);
    }

    /* -------------------- helpers -------------------- */

    protected function connectionSummary(): array
    {
        $conn = config('database.default');
        $cfg  = config("database.connections.$conn", []);

        $tableCount = 0;
        $sizeMb = null;
        try {
            $tableCount = collect(DB::select('SHOW TABLES'))->count();
        } catch (\Throwable) {}

        try {
            $row = DB::selectOne('
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS mb
                FROM information_schema.tables WHERE table_schema = ?
            ', [$cfg['database'] ?? null]);
            $sizeMb = $row->mb ?? null;
        } catch (\Throwable) {}

        return [
            'driver'      => $cfg['driver'] ?? $conn,
            'database'    => $cfg['database'] ?? null,
            'host'        => $cfg['host'] ?? null,
            'port'        => $cfg['port'] ?? null,
            'table_count' => $tableCount,
            'size_mb'     => $sizeMb,
        ];
    }

    /**
     * @return array<int, array{name:string,size_kb:float,created_at:string}>
     */
    protected function listBackups(): array
    {
        $dir = storage_path('backups');
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        return collect(File::files($dir))
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.sql'))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->take(20)
            ->map(fn ($f) => [
                'name'       => $f->getFilename(),
                'size_kb'    => round($f->getSize() / 1024, 1),
                'created_at' => date('c', $f->getMTime()),
            ])
            ->values()
            ->all();
    }

    /**
     * Run mysqldump against the current connection and return the path to the
     * .sql file (in storage/backups/). Returns null on failure.
     */
    protected function createBackupFile(string $label): ?string
    {
        $cfg = config('database.connections.mysql');
        if (! $cfg || $cfg['driver'] !== 'mysql') {
            Log::warning('DB backup skipped: only MySQL is supported by the admin backup tool.');
            return null;
        }

        $dir = storage_path('backups');
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $stamp = date('Y-m-d_His');
        $filename = "{$label}-{$stamp}.sql";
        $path = $dir . DIRECTORY_SEPARATOR . $filename;

        $binary = $cfg['dump_binary'] ?? 'mysqldump';

        $args = [
            $binary,
            '-h', (string) $cfg['host'],
            '-P', (string) $cfg['port'],
            '-u', (string) $cfg['username'],
        ];
        if (! empty($cfg['password'])) {
            // -p<password> attached form; --password= risks logging.
            $args[] = '-p' . $cfg['password'];
        }
        $args = array_merge($args, [
            $cfg['database'],
            '--routines',
            '--triggers',
            '--single-transaction',
            '--skip-lock-tables',
            '--add-drop-table',
            '--skip-add-drop-database',
            '--disable-keys',
            '--no-tablespaces',
        ]);

        try {
            $process = new Process($args);
            $process->setTimeout(120);
            $process->run();

            if (! $process->isSuccessful()) {
                Log::error('mysqldump failed', [
                    'exit'   => $process->getExitCode(),
                    'stderr' => $process->getErrorOutput(),
                    'binary' => $binary,
                ]);
                return null;
            }

            File::put($path, $process->getOutput());
            return $path;
        } catch (\Throwable $e) {
            Log::error('mysqldump exception: ' . $e->getMessage(), ['binary' => $binary]);
            return null;
        }
    }
}
