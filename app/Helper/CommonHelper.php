<?php

namespace App\Helper;

use App\Models\PasswordReset;
use App\Notifications\SendOtpNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;

class CommonHelper
{
    /**
     * asset() with a ?v=<mtime> cache-buster appended.
     *
     * For static files we replace in place (the page-title band, the product
     * placeholder): the URL never changes, so browsers and any CDN in front of
     * us keep serving the previous bytes until their cache expires. Keying the
     * query string off the file's mtime means a redeploy invalidates it for
     * everyone, not just people who hard-refresh. Same trick the client layout
     * already uses for mobile-fixes.css.
     */
    public static function assetV(string $path): string
    {
        $mtime = @filemtime(public_path($path));

        return asset($path) . ($mtime ? '?v=' . $mtime : '');
    }

    /**
     * Intrinsic pixel size of an uploaded image, as a ready-to-print
     * `width="1734" height="907"` attribute pair (empty string if unknown).
     *
     * Uploads keep whatever aspect ratio the admin gave them — categories are
     * square, banners are wide, review shots are portrait — so the dimensions
     * cannot be hardcoded in the templates. Without them the browser reserves
     * no space and the image shoves the page down when it decodes, which is
     * both a layout shift and a Lighthouse "unsized-images" failure.
     *
     * Memoised per request; the underlying read is a header-only parse, and
     * upload filenames are content-unique so a cached size can never go stale.
     */
    /**
     * Inline the contents of small stylesheets from public/.
     *
     * Every stylesheet in the document head is render-blocking, and this origin
     * still answers over HTTP/1.1 — so those files queue against a six-connection
     * limit that the LCP image is also competing for. For a stylesheet of a few
     * KB the round trip costs far more than the bytes, so the small ones ride
     * along in the HTML instead. Inlining @font-face this way has a second
     * payoff: the browser learns about fonts.gstatic.com from the initial
     * response rather than after a separate CSS download.
     *
     * Only safe for files with no relative url() references — an inlined
     * stylesheet resolves those against the document, not the CSS file.
     * client/fonts/font-icons.css is the notable exclusion.
     */
    public static function inlineCss(array $publicPaths): string
    {
        static $memo = [];

        $key = implode('|', $publicPaths);
        if (array_key_exists($key, $memo)) {
            return $memo[$key];
        }

        $out = '';
        foreach ($publicPaths as $path) {
            $full = public_path(ltrim($path, '/'));
            if (is_file($full)) {
                $out .= file_get_contents($full) . "\n";
            }
        }

        return $memo[$key] = $out;
    }

    public static function imageSizeAttrs(string $storagePath): string
    {
        static $memo = [];

        if (! array_key_exists($storagePath, $memo)) {
            $full = storage_path('app/public/' . ltrim($storagePath, '/'));
            $size = is_file($full) ? @getimagesize($full) : false;
            $memo[$storagePath] = ($size && $size[0] > 0 && $size[1] > 0)
                ? 'width="' . (int) $size[0] . '" height="' . (int) $size[1] . '"'
                : '';
        }

        return $memo[$storagePath];
    }

    /**
     * `srcset` for an uploaded image, built from whatever WebP derivatives
     * `php artisan images:optimize` has generated beside it.
     *
     * Returns an empty string when none exist, so a template can always emit
     * src="<original>" and simply gain a srcset once the command has run —
     * there is no broken intermediate state and no deploy-order dependency.
     */
    public static function srcsetFor(string $storagePath): string
    {
        static $memo = [];

        if (array_key_exists($storagePath, $memo)) {
            return $memo[$storagePath];
        }

        $relative = ltrim($storagePath, '/');
        $full     = storage_path('app/public/' . $relative);
        $stem     = preg_replace('/\.[^.]+$/', '', $relative);

        $entries = [];
        // Keep in step with OptimizeImages::WIDTHS — a width listed there but
        // missing here is generated on disk and then never offered to anyone.
        foreach ([240, 400, 640, 768, 960, 1440] as $width) {
            if (is_file(storage_path('app/public/' . $stem . '-' . $width . '.webp'))) {
                $entries[] = asset('storage/' . $stem . '-' . $width . '.webp') . ' ' . $width . 'w';
            }
        }

        // Include the original as the widest candidate so large viewports and
        // high-DPR screens still have something better than the biggest
        // derivative to pick from.
        if ($entries !== [] && is_file($full)) {
            $size = @getimagesize($full);
            if ($size && $size[0] > 0) {
                $entries[] = asset('storage/' . $relative) . ' ' . (int) $size[0] . 'w';
            }
        }

        return $memo[$storagePath] = implode(', ', $entries);
    }

    public static function makeSlug(string $string): string
    {
        return preg_replace('/\s+/u', '-', trim($string));
    }

    public static function uploadFile(UploadedFile $file, $path, $oldFile = ''): string
    {
        if (! empty($file)) {
            // Remove Old file
            if (! empty($oldFile)) {
                Storage::delete('public/'.$path.'/'.$oldFile);  // Delete file from local
            }
            // Upload image. Visibility must be explicit: this writes to the
            // `local` disk, which declares none and so treats new directories
            // as private — Laravel then creates them 0700. Apache cannot
            // traverse those through the public/storage symlink, so every
            // image in a newly created folder 403s until someone chmods it by
            // hand. 'public' yields 0755 directories and 0644 files.
            $path = $file->store('public/'.$path, ['visibility' => 'public']);
            $parts = explode('/', $path);

            return end($parts);
        }

        return '';
    }

    public static function removeOldFile($oldFile): void
    {
        // Remove Old file
        if (! empty($oldFile)) {
            // Storage::disk('s3')->delete($oldFile);    // Delete file from s3
            Storage::delete($oldFile);                      // Delete file from local
        }
    }

    public static function getImageValidationRule(string $key): array
    {
        if (request()->hasFile($key)) {
            return [File::types(['jpeg', 'jpg', 'png', 'webp'])->max(5 * 1024)];
        }

        return ['string'];
    }

    public static function getFileValidationRule(string $key, $types, $size = (1 * 500)): array
    {
        if (request()->hasFile($key)) {
            return [File::types($types)->max($size)];
        }

        return ['string'];
    }

    public static function sanitizeHtml(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        $allowed = '<p><br><b><strong><i><em><u><ul><ol><li><a><h1><h2><h3><h4><h5><h6><span><blockquote><code><pre><hr><table><thead><tbody><tr><td><th><img>';
        $clean = strip_tags($html, $allowed);

        // strip event handler attributes ( onclick=, onerror=, onload= etc. )
        $clean = preg_replace('/\s(on\w+)\s*=\s*(["\']).*?\2/i', '', $clean);
        // strip javascript: URIs
        $clean = preg_replace('/(href|src)\s*=\s*(["\'])\s*javascript:.*?\2/i', '$1="#"', $clean);
        // strip data: URIs on scriptable attrs (allow inline images)
        $clean = preg_replace('/(href)\s*=\s*(["\'])\s*data:.*?\2/i', '$1="#"', $clean);

        return $clean;
    }

    /**
     * Payment badges to display on the storefront (footer + product page).
     *
     * Source of truth is the payment_gateways table: whatever the admin has
     * marked active determines which badges show. Razorpay implies the
     * card / UPI / net-banking rail; COD gets a text chip; PayPal / Stripe
     * pull their own logos. The admin can also opt-in extra badges via
     * Settings → Storefront content → Footer payment badges — those are
     * unioned in on top.
     *
     * Returns an array of ['key','label','image','is_cod'] entries. When
     * `image` is null the caller should render a text chip (used for COD).
     */
    public static function activePaymentBadges(?\App\Models\Setting $settings = null): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        // The theme ships six card logos (img-1..img-6) — labels below match
        // the actual artwork, not the alt-tags in the original template.
        $catalog = [
            'visa'       => ['label' => 'Visa',             'image' => 'img-1.webp'],
            'mastercard' => ['label' => 'Mastercard',       'image' => 'img-2.webp'],
            'amex'       => ['label' => 'American Express', 'image' => 'img-3.webp'],
            'paypal'     => ['label' => 'PayPal',           'image' => 'img-4.webp'],
            'diners'     => ['label' => 'Diners Club',      'image' => 'img-5.webp'],
            'discover'   => ['label' => 'Discover',         'image' => 'img-6.webp'],
            'applepay'   => ['label' => 'Apple Pay',        'image' => 'applePay.webp'],
            // Custom SVG badges for the Indian rail + COD (no theme artwork).
            'upi'        => ['label' => 'UPI',              'image' => 'upi.svg'],
            'netbanking' => ['label' => 'Net banking',      'image' => 'netbanking.svg'],
            'rupay'      => ['label' => 'RuPay',            'image' => 'rupay.svg'],
            'cod'        => ['label' => 'Cash on Delivery', 'image' => 'cod.svg', 'is_cod' => true],
        ];

        // What each active gateway implies visually. Only reference catalog
        // keys we can actually render on the current theme.
        $gatewayMap = [
            'razorpay' => ['visa', 'mastercard', 'amex', 'rupay', 'upi', 'netbanking'],
            'stripe'   => ['visa', 'mastercard', 'amex'],
            'paypal'   => ['paypal'],
            'cod'      => ['cod'],
        ];

        $enabled = [];

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('payment_gateways')) {
                $active = \App\Models\PaymentGateway::query()
                    ->where('is_active', true)
                    ->pluck('code')
                    ->all();
                foreach ($active as $code) {
                    foreach (($gatewayMap[$code] ?? []) as $key) {
                        $enabled[$key] = true;
                    }
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // Admin overrides from Settings → Storefront content — union in.
        try {
            $manual = $settings?->content('payment_methods') ?? [];
            foreach ($manual as $row) {
                if (is_array($row) && ! empty($row['enabled']) && ! empty($row['key'])) {
                    $enabled[$row['key']] = true;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // Preserve catalog order and skip anything without a working entry.
        $out = [];
        foreach ($catalog as $key => $meta) {
            if (empty($enabled[$key])) {
                continue;
            }
            $out[] = [
                'key'    => $key,
                'label'  => $meta['label'],
                'image'  => $meta['image'] ?? null,
                'chip'   => $meta['chip']   ?? null,
                'is_cod' => ! empty($meta['is_cod']),
            ];
        }

        return $cache = $out;
    }

    public static function sendOtpEmail($email = '', $name = '', $token = '')
    {
        $token = ! empty($token) ? $token : Str::random(50);
        $data = [
            'email' => $email,
            'otp' => random_int(100000, 999999),
            'token' => $token,
        ];
        PasswordReset::query()->updateOrCreate(
            ['email' => $email],
            $data,
        );
        $data['name'] = $name;
        Notification::route('mail', [
            $email => $name,
        ])->notify(new SendOtpNotification($data));

        return $token;
    }
}
