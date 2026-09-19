<?php

use App\Jobs\SyncActiveShipmentsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Poll every 15 minutes for shipments that haven't seen an update in > 30 min.
 * Providers with real webhooks (Shiprocket) get updates instantly; this job is
 * the safety-net + primary channel for providers that don't push.
 */
Schedule::job(new SyncActiveShipmentsJob())
    ->name('sync-active-shipments')
    ->everyFifteenMinutes()
    ->withoutOverlapping(15)
    ->onOneServer()
    ->description('Poll couriers for tracking updates on active shipments.');
