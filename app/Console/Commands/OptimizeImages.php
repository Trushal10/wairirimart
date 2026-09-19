<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Generates down-scaled WebP derivatives for uploaded images.
 *
 * Uploads are stored at whatever resolution the admin happened to have — a
 * 1920px banner for a slot that is 430px wide on a phone, a 135KB PNG for a
 * 184px circle. Lighthouse measured 3.3MB of the homepage's 4.4MB as pixels
 * the browser throws away, which is the bulk of the mobile LCP.
 *
 * Originals are never touched. Each derivative is written alongside its source
 * as "<name>-<width>.webp", which is what CommonHelper::srcsetFor() looks for;
 * when no derivative exists the templates fall back to the original, so this
 * command is safe to run late, partially, or not at all.
 */
class OptimizeImages extends Command
{
    protected $signature = 'images:optimize
                            {--dir=* : Limit to these storage subdirectories}
                            {--force : Rebuild derivatives that already exist}';

    protected $description = 'Generate responsive WebP derivatives for uploaded images';

    /**
     * Covers a 184px circle at 2x through a full-bleed desktop banner.
     *
     * 768 exists for the phones that decide the LCP score: a 412px-wide
     * viewport at DPR 1.75 wants 721px for a full-bleed hero. srcset picks the
     * smallest candidate that is still >= what it needs, so a 720w step misses
     * by a single pixel and the browser falls through to 960w — about 40% more
     * bytes than it can display. 768 is the first round step that clears 721,
     * and it also covers the very common 360px @2x (720px).
     */
    private const WIDTHS = [240, 400, 640, 768, 960, 1440];

    private const DIRS = ['category', 'slider', 'product', 'review', 'banner', 'blog', 'setting'];

    public function handle(): int
    {
        if (! function_exists('imagewebp')) {
            $this->error('GD is missing WebP support (imagewebp unavailable).');

            return self::FAILURE;
        }

        $dirs = $this->option('dir') ?: self::DIRS;
        $made = 0;
        $skipped = 0;
        $failed = 0;
        $savedBytes = 0;

        foreach ($dirs as $dir) {
            $base = storage_path('app/public/' . trim($dir, '/'));
            if (! is_dir($base)) {
                $this->line("  <comment>skip</comment> {$dir} (no such directory)");
                continue;
            }

            $files = glob($base . '/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE) ?: [];
            $this->info("{$dir}: " . count($files) . ' source image(s)');

            foreach ($files as $file) {
                // Never treat a derivative as a source, or each run would
                // compound scaling on the previous run's output.
                if (preg_match('/-\d+\.webp$/i', $file)) {
                    continue;
                }

                $size = @getimagesize($file);
                if (! $size) {
                    $failed++;
                    continue;
                }
                [$srcW, $srcH] = $size;

                foreach (self::WIDTHS as $width) {
                    // Upscaling adds bytes without adding detail.
                    if ($width >= $srcW) {
                        continue;
                    }

                    $target = preg_replace('/\.[^.]+$/', '', $file) . '-' . $width . '.webp';
                    if (is_file($target) && ! $this->option('force') && filemtime($target) >= filemtime($file)) {
                        $skipped++;
                        continue;
                    }

                    $before = is_file($target) ? filesize($target) : 0;
                    if ($this->resize($file, $target, $width, (int) round($srcH * ($width / $srcW)))) {
                        $made++;
                        $savedBytes += max(0, filesize($file) - filesize($target)) - $before;
                    } else {
                        $failed++;
                    }
                }
            }
        }

        $this->newLine();
        $this->info("Created {$made}, skipped {$skipped} (already current), failed {$failed}.");
        if ($savedBytes > 0) {
            $this->info('Smallest-variant saving vs original: ~' . round($savedBytes / 1048576, 1) . ' MB.');
        }

        return self::SUCCESS;
    }

    private function resize(string $source, string $target, int $width, int $height): bool
    {
        $image = $this->open($source);
        if (! $image) {
            return false;
        }

        $canvas = imagecreatetruecolor($width, $height);
        // Preserve transparency: PNG logos and cut-outs would otherwise get a
        // black box behind them once flattened into WebP.
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
        $ok = imagewebp($canvas, $target, 82);

        imagedestroy($canvas);
        imagedestroy($image);

        return $ok;
    }

    private function open(string $path): mixed
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            'png'         => @imagecreatefrompng($path),
            'webp'        => @imagecreatefromwebp($path),
            default       => false,
        };
    }
}
