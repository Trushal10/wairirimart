<?php

use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\ClientAuthMiddleware;
use App\Http\Middleware\EnsureTrackingEnabled;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders()
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        // channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->redirectUsersTo(AppServiceProvider::HOME);

        $middleware->web(\App\Http\Middleware\HandleInertiaRequests::class);
        $middleware->web(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->api(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->throttleApi();

        $middleware->replace(\Illuminate\Http\Middleware\TrustProxies::class, \App\Http\Middleware\TrustProxies::class);

        $middleware->alias([
            'client_auth' => ClientAuthMiddleware::class,
            'admin' => AdminOnly::class,
            'tracking_enabled' => EnsureTrackingEnabled::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/razorpay',
            'webhooks/shiprocket',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Render a branded Inertia error page for HTTP errors inside the
        // admin panel. The public storefront keeps its default Laravel
        // error pages so it doesn't pull in the admin Vue bundle.
        //
        // IMPORTANT: We must derive the status code from the exception itself,
        // NOT by calling `Handler::render(...)` — doing so re-invokes every
        // registered render callback (including this one), which produced an
        // infinite recursion that OOM'd every admin request that raised any
        // exception (including plain ValidationException on the login form).
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if (! $request->is('admin', 'admin/*')) {
                return null; // storefront keeps default Laravel error pages
            }

            // Never intercept exceptions that Laravel has purpose-built
            // handling for — otherwise validation errors turn into an
            // "Error 422" page instead of inline field errors, auth failures
            // don't redirect to /login, and CSRF misses become opaque.
            if ($e instanceof \Illuminate\Validation\ValidationException
                || $e instanceof \Illuminate\Auth\AuthenticationException
                || $e instanceof \Illuminate\Session\TokenMismatchException) {
                return null;
            }

            // Derive status directly from the exception. No handler re-entry.
            $status = 500;
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                $status = $e->getStatusCode();
            } elseif ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                $status = 404;
            } elseif ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                $status = 403;
            }

            if (! in_array($status, [403, 404, 419, 429, 500, 503], true)) {
                return null;
            }

            // Prefer the framework's HTTP-exception message when set explicitly
            // (e.g. abort(403, 'Nope')); otherwise use the generic copy from Vue.
            $message = '';
            $defaultText = \Symfony\Component\HttpFoundation\Response::$statusTexts[$status] ?? '';
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                && $e->getMessage() !== ''
                && $e->getMessage() !== $defaultText) {
                $message = $e->getMessage();
            }

            return \Inertia\Inertia::render('Error', [
                'status'    => $status,
                'message'   => $message,
                'requestId' => (string) $request->headers->get('X-Request-Id', ''),
                'inAdmin'   => true,
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();
