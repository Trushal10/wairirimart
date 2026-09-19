@php
    // Pull the number from settings. Prefer an explicit WhatsApp override
    // stored in social_links (some businesses use a different WA number),
    // fall back to the general contact phone.
    $__waRaw = null;
    if (! empty($settings)) {
        $__waRaw = data_get($settings->social_links ?? [], 'whatsapp')
            ?: ($settings->phone ?? null);
    }

    // Strip everything that isn't a digit. wa.me expects the full
    // international number without spaces or symbols.
    $__waDigits = $__waRaw ? preg_replace('/[^\d]/', '', (string) $__waRaw) : '';

    // If the number is 10 digits, assume India (+91). For other regions
    // save the full international format (e.g. "+15551234567") in
    // Settings → social_links.whatsapp.
    if ($__waDigits && strlen($__waDigits) === 10) {
        $__waDigits = '91' . $__waDigits;
    }

    $__waMessage = urlencode('Hi ' . config('app.name') . ', I have a question about your products.');
@endphp

@if ($__waDigits)
<a
    href="https://wa.me/{{ $__waDigits }}?text={{ $__waMessage }}"
    target="_blank"
    rel="noopener"
    class="wa-chat-btn"
    aria-label="Chat with us on WhatsApp"
    title="Chat with us on WhatsApp"
>
    <span class="wa-chat-btn__pulse" aria-hidden="true"></span>
    <svg class="wa-chat-btn__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" aria-hidden="true">
        <path fill="currentColor" d="M19.11 17.79c-.29-.15-1.71-.85-1.98-.94-.27-.1-.46-.15-.66.15-.19.29-.75.94-.92 1.13-.17.19-.34.22-.63.07-.29-.15-1.22-.45-2.32-1.43-.86-.77-1.44-1.72-1.61-2-.17-.29-.02-.45.13-.6.13-.13.29-.34.44-.51.15-.17.19-.29.29-.48.1-.19.05-.36-.02-.51-.07-.15-.66-1.59-.9-2.18-.24-.57-.48-.5-.66-.51l-.56-.01c-.19 0-.51.07-.78.36-.27.29-1.02 1-1.02 2.44 0 1.44 1.05 2.83 1.19 3.02.15.19 2.07 3.16 5.02 4.43.7.3 1.25.48 1.68.62.7.22 1.34.19 1.85.12.56-.08 1.71-.7 1.96-1.38.24-.68.24-1.26.17-1.38-.07-.12-.27-.19-.56-.34zM16.04 27.02h-.01c-1.87 0-3.71-.5-5.31-1.45l-.38-.23-3.94 1.03 1.05-3.84-.25-.4A11.05 11.05 0 0 1 5.02 16C5.02 9.94 9.98 5 16.04 5c2.94 0 5.7 1.15 7.78 3.23A10.94 10.94 0 0 1 27.05 16c0 6.06-4.96 11.02-11.01 11.02zm9.37-20.4A13.14 13.14 0 0 0 16.04 3C8.83 3 3.02 8.81 3.02 16.01c0 2.31.6 4.55 1.74 6.53L3 30l7.66-2.01a13.13 13.13 0 0 0 5.38 1.16h.01C23.25 29.15 29 23.34 29 16.13c0-3.55-1.4-6.85-3.59-9.51z"/>
    </svg>
</a>

<style>
    /* Push the theme's scroll-to-top button up so it doesn't collide
       with the WhatsApp button + its pulse ring. Only applies when this
       component actually renders (i.e. a WhatsApp number is configured). */
    #scroll-top { bottom: 96px !important; }
    #scroll-top.type-1 { bottom: 156px !important; }
    @media (max-width: 767.98px) {
        #scroll-top { bottom: 148px !important; }
    }

    /* Circular icon-only WhatsApp button — fixed bottom-right,
       above the mobile bottom-nav on phones. */
    .wa-chat-btn {
        position: fixed;
        right: 20px;
        bottom: 20px;
        z-index: 200;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 56px;
        height: 56px;
        padding: 0;
        border-radius: 50%;
        background: #25D366;
        color: #fff;
        text-decoration: none;
        box-shadow: 0 8px 24px rgba(37, 211, 102, 0.35),
                    0 2px 6px rgba(31, 24, 17, 0.10);
        transition: transform var(--ds-t-med, .2s ease),
                    box-shadow var(--ds-t-med, .2s ease),
                    background var(--ds-t-fast, .12s ease);
    }
    .wa-chat-btn:hover,
    .wa-chat-btn:focus-visible {
        background: #20BA5A;
        color: #fff;
        text-decoration: none;
        transform: translateY(-2px);
        box-shadow: 0 12px 28px rgba(37, 211, 102, 0.45),
                    0 3px 8px rgba(31, 24, 17, 0.12);
    }
    .wa-chat-btn__icon {
        width: 30px;
        height: 30px;
        color: #fff;
        display: block;
        position: relative;
        z-index: 1;
    }
    /* Pulse ring — a friendly nudge that the button is interactive.
       Kept small (10px max) so it doesn't overlap neighbouring UI. */
    .wa-chat-btn__pulse {
        position: absolute;
        inset: 0;
        border-radius: 50%;
        pointer-events: none;
        box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.6);
        animation: wa-chat-pulse 2.4s ease-out infinite;
    }
    @keyframes wa-chat-pulse {
        0%   { box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.5); }
        70%  { box-shadow: 0 0 0 10px rgba(37, 211, 102, 0); }
        100% { box-shadow: 0 0 0 0 rgba(37, 211, 102, 0); }
    }
    @media (prefers-reduced-motion: reduce) {
        .wa-chat-btn__pulse { animation: none; }
        .wa-chat-btn { transition: background var(--ds-t-fast, .12s ease); }
        .wa-chat-btn:hover { transform: none; }
    }

    /* Mobile — sits above the .tf-toolbar-bottom nav */
    @media (max-width: 767.98px) {
        .wa-chat-btn {
            right: 16px;
            bottom: 84px;
            width: 52px;
            height: 52px;
        }
        .wa-chat-btn__icon {
            width: 26px;
            height: 26px;
        }
    }
</style>
@endif
