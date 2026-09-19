<?php

namespace App\Http\Middleware;

use App\Helper\OrderStatusHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the /track routes when order tracking is off for this storefront —
 * either the operator's kill switch / missing courier
 * (OrderStatusHelper::trackingEnabled()) or the admin's "Show track order"
 * setting (OrderStatusHelper::publicTrackingVisible()).
 *
 * Hiding the links alone is not enough: the URLs are guessable, they get
 * bookmarked, and they go out in old order emails. A shop that has turned
 * tracking off should answer 404 rather than serve a page of em-dashes with an
 * auto-refresh polling an endpoint that has nothing behind it.
 */
class EnsureTrackingEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(OrderStatusHelper::publicTrackingVisible(), 404);

        return $next($request);
    }
}
