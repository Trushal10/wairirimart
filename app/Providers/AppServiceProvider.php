<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Setting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public const HOME = '/admin';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Share $settings + $categories with the client layout AND every
        // client-facing view + shared components. Individual pages (product,
        // home, contact, etc.) can then reference $settings->content('...')
        // without each controller having to pass it manually.
        // This composer fires once per *matching view*, not once per request:
        // the layout, the page, the header, the footer and every client.*
        // partial each trigger it. On the homepage that meant the same three
        // lookups — including a Schema::hasTable() information_schema probe,
        // which is the slowest of the three — running 4x, and once more for
        // every hero slide rendered through client.partials.hero-banner.
        // The data is identical for all of them, so resolve it lazily on the
        // first call and hand the same result to every view after that.
        $shared = null;
        $resolveShared = function () use (&$shared) {
            if ($shared !== null) {
                return $shared;
            }

            // Schema::hasTable() is an information_schema probe — a genuinely
            // slow query on shared MySQL, and it ran on every storefront
            // render purely to stay safe on a not-yet-migrated database.
            // Reading the table and treating "no such table" as "no settings"
            // costs nothing on the normal path and behaves identically on the
            // abnormal one.
            try {
                $settings = Setting::query()->first();
            } catch (QueryException $e) {
                $settings = null;
            }

            return $shared = [
                'settings' => $settings,
                'categories' => Category::query()->where('status', 1)->limit(4)->get(),
                'paymentBadges' => \App\Helper\CommonHelper::activePaymentBadges($settings),
            ];
        };

        View::composer(
            ['layouts.client', 'client.*', 'components.header', 'components.footer'],
            function (\Illuminate\View\View $view) use ($resolveShared) {
                $view->with($resolveShared());
            }
        );
    }
}
