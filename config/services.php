<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'recaptcha' => [
        'url' => env('RECAPTCHA_URL'),
        'v3-recaptcha-site-key' => env('V3_RECAPTCHA_SITE_KEY'),
        'v3-recaptcha-secret-key' => env('V3_RECAPTCHA_SECRET_KEY'),
        // Minimum score (0.0–1.0) to consider a submission human. Lower =
        // more lenient (accepts more submissions), higher = stricter.
        'v3_threshold' => (float) env('V3_RECAPTCHA_THRESHOLD', 0.5),
    ],

    'shiprocket' => [
        'email' => env('SHIPROCKET_EMAIL'),
        'password' => env('SHIPROCKET_PASSWORD'),
        'pickup_location' => env('SHIPROCKET_PICKUP_LOCATION', 'Primary'),
        'pickup_pincode' => env('SHIPROCKET_PICKUP_PINCODE'),
        'channel_id' => env('SHIPROCKET_CHANNEL_ID'),
        'webhook_token' => env('SHIPROCKET_WEBHOOK_TOKEN'),
        'default_length' => env('SHIPROCKET_DEFAULT_LENGTH', 10),
        'default_breadth' => env('SHIPROCKET_DEFAULT_BREADTH', 10),
        'default_height' => env('SHIPROCKET_DEFAULT_HEIGHT', 5),
        'default_weight' => env('SHIPROCKET_DEFAULT_WEIGHT', 0.5),
    ],

    'razorpay' => [
        'key' => env('RAZORPAY_KEY'),
        'secret' => env('RAZORPAY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'returns' => [
        'window_days' => env('RETURNS_WINDOW_DAYS', 14),
    ],

    'inventory' => [
        'low_stock_threshold' => env('LOW_STOCK_THRESHOLD', 5),
    ],

    // Admin Reports module — knobs that shape P&L and export presentation.
    'reports' => [
        // If we don't have an explicit payments.gateway_fee row, apply this
        // percentage against the paid amount as the estimated processing fee.
        'default_gateway_fee_rate' => (float) env('REPORT_DEFAULT_GATEWAY_FEE_RATE', 0.02),
        // Multiplier applied to charged shipping to estimate real carrier cost.
        // 1.0 = whatever we charged the customer; 0.0 = shipping is pure revenue.
        'shipping_cost_ratio' => (float) env('REPORT_SHIPPING_COST_RATIO', 1.0),
        'currency' => env('REPORT_CURRENCY', 'INR'),
        'currency_symbol' => env('REPORT_CURRENCY_SYMBOL', '₹'),
    ],

    'auth' => [
        // Toggle for the "Sign in with phone (OTP)" flow on the customer login page.
        // Backend endpoints stay wired; this only hides the UI. Flip to true once
        // real SMS credentials are configured and the flow has been tested.
        'phone_otp_login_enabled' => (bool) env('PHONE_OTP_LOGIN_ENABLED', false),
    ],

    'shipping' => [
        // Delivery estimate shown on the order confirmation page. The page used
        // to hardcode "3-7 business days" in prose while promising nothing
        // concrete up top; marketplaces lead with a date, so both the headline
        // estimate and the prose now read from here and cannot drift apart.
        'eta_min_days' => (int) env('DELIVERY_ETA_MIN_DAYS', 3),
        'eta_max_days' => (int) env('DELIVERY_ETA_MAX_DAYS', 7),
    ],

    'tracking' => [
        // Operator kill switch for customer-facing order tracking. When false
        // every entry point disappears — footer, nav, product page, order
        // confirmation, profile — and the /track routes stop resolving, so a
        // hidden feature is not still reachable by URL. Leave it true and
        // OrderStatusHelper still hides tracking when no courier is active,
        // since a tracking page with no courier behind it shows nothing.
        'enabled' => (bool) env('ORDER_TRACKING_ENABLED', true),
    ],

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'), // none | log | msg91 | twilio | fast2sms
        'msg91' => [
            'auth_key' => env('MSG91_AUTH_KEY'),
            'template_id' => env('MSG91_TEMPLATE_ID'),
            // DLT-registered sender header. SmsService has always read this
            // key; it was never declared here, so it resolved to null and
            // every message went out without a sender.
            'sender' => env('MSG91_SENDER'),
        ],
        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],
        'fast2sms' => [
            'api_key' => env('FAST2SMS_API_KEY'),
        ],
    ],

];
