<?php

namespace App\Jobs;

use App\Models\Shipment;
use App\Services\ShipmentService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncActiveShipmentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Cap per run so we don't hammer providers. */
    public int $batchLimit = 100;

    /** Only sync shipments whose last update is older than this many minutes. */
    public int $staleAfterMinutes = 30;

    /** Cap task lifetime; the scheduler will retry next tick if we timeout. */
    public int $timeout = 240;

    /** Per-provider throttle (max shipments per provider per run). */
    protected int $perProviderCap = 40;

    public function __construct(?int $batchLimit = null, ?int $staleAfterMinutes = null)
    {
        if ($batchLimit) $this->batchLimit = $batchLimit;
        if ($staleAfterMinutes) $this->staleAfterMinutes = $staleAfterMinutes;
    }

    public function handle(ShipmentService $service): void
    {
        $cutoff = Carbon::now()->subMinutes($this->staleAfterMinutes);
        $terminal = Shipment::TERMINAL_STATUSES;

        // Only shipments that (a) aren't done, (b) have gone stale, (c) have
        // a live provider row, and (d) have something the courier can look up.
        $query = Shipment::query()
            ->with('deliveryPartner')
            ->whereNotIn('status', $terminal)
            ->where('updated_at', '<', $cutoff)
            ->whereHas('deliveryPartner', fn ($q) => $q->where('is_active', true)->where('is_third_party', true))
            ->where(function ($q) {
                $q->whereNotNull('awb_code')->orWhereNotNull('provider_shipment_id');
            })
            ->orderBy('updated_at')
            ->limit($this->batchLimit);

        $counts = [];
        $ok = 0;
        $failed = 0;

        foreach ($query->cursor() as $shipment) {
            $provider = $shipment->provider ?: 'unknown';
            $counts[$provider] = ($counts[$provider] ?? 0) + 1;
            if ($counts[$provider] > $this->perProviderCap) {
                continue;
            }

            try {
                $service->syncTracking($shipment);
                $ok++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('SyncActiveShipmentsJob: shipment sync failed', [
                    'shipment_id' => $shipment->id,
                    'provider' => $provider,
                    'message' => $e->getMessage(),
                ]);
            }

            // Small breather so we don't get rate-limited by upstream providers.
            usleep(200_000); // 200 ms
        }

        Log::info('SyncActiveShipmentsJob completed', [
            'ok' => $ok,
            'failed' => $failed,
            'per_provider' => $counts,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SyncActiveShipmentsJob permanently failed: ' . $e->getMessage());
    }
}
