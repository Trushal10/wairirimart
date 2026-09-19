<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'social_links' => 'array',
        'storefront_content' => 'array',
    ];

    /**
     * Merged storefront content: user overrides + baked-in fallbacks so the
     * storefront always has something to render even before admin has saved.
     */
    public function content(?string $key = null, $default = null)
    {
        $merged = array_replace_recursive(self::defaultContent(), $this->storefront_content ?? []);

        if ($key === null) return $merged;
        return data_get($merged, $key, $default);
    }

    public static function defaultContent(): array
    {
        return [
            // Offers order tracking to shoppers at all. Off removes it from the
            // entire storefront — nav, footer, product page, order confirmation,
            // the customer's profile and the links in order mails — and the
            // /track routes then answer 404, so nothing dead-ends. Leave it on
            // if customers should keep self-serve tracking of their own orders.
            'show_track_order' => true,
            // Small strings shown on the product detail page
            'product' => [
                'delivery_estimate' => '3–5 business days across India',
                'return_window'     => 'Return within 14 days of delivery.',
                'shipping_copy'     => "One flat delivery fee across India. Moulds and casting supplies are cushioned so they arrive ready to use. Free returns within 14 days on unused items in original packaging.",
                'return_copy'       => "If a mould or kit isn't right for your project, send it back within 14 days for a full refund to your original payment method. Items must be unused and in their original packaging. Contact support to start a return.",
            ],
            // Top-bar rotating promo messages (shown above the header)
            'topbar_messages' => [
                'Free shipping on orders over ₹999',
                '14-day easy returns · Non-toxic craft materials · Fast India delivery',
            ],
            // Feature/trust badges shown on both the Home page and About page.
            'features' => [
                ['icon' => 'icon-sealCheck', 'title' => 'Premium Quality Moulds', 'description' => 'Food-grade silicone that holds fine detail, cast after cast.'],
                ['icon' => 'icon-shipping',  'title' => 'Fast Delivery',          'description' => 'Ships across India — most orders arrive in 3–5 days.'],
                ['icon' => 'icon-return',    'title' => 'Easy Returns',           'description' => 'Changed your mind? 14-day returns on unused items.'],
                ['icon' => 'icon-headset',   'title' => 'Maker Support',          'description' => 'Real crafters on chat + WhatsApp, 7 days a week.'],
            ],
            // Footer payment badges — fixed catalog of methods the storefront
            // knows how to render. All start disabled: the storefront shows
            // nothing until the admin explicitly ticks a method in
            // Settings → Storefront content → Footer payment badges.
            // Order here is the display order.
            'payment_methods' => [
                ['key' => 'visa',       'label' => 'Visa',             'image' => 'img-1.webp',    'enabled' => false],
                ['key' => 'mastercard', 'label' => 'Mastercard',       'image' => 'img-2.webp',    'enabled' => false],
                ['key' => 'amex',       'label' => 'American Express', 'image' => 'img-3.webp',    'enabled' => false],
                ['key' => 'paypal',     'label' => 'PayPal',           'image' => 'img-4.webp',    'enabled' => false],
                ['key' => 'diners',     'label' => 'Diners Club',      'image' => 'img-5.webp',    'enabled' => false],
                ['key' => 'discover',   'label' => 'Discover',         'image' => 'img-6.webp',    'enabled' => false],
                ['key' => 'applepay',   'label' => 'Apple Pay',        'image' => 'applePay.webp', 'enabled' => false],
            ],
            // About page copy: banner heading, main heading, CTA, and 1–6 tabs.
            'about' => [
                'page_title' => 'About Our Store',
                'main_title' => 'Premium moulds and DIY kits for every maker.',
                'cta_label'  => 'Shop moulds & kits',
                'tabs' => [
                    ['label' => 'Our Story',         'content' => "We started with one simple goal — making it easy for anyone to cast something beautiful. From silicone and resin moulds to concrete casting materials, DIY painting kits, candle moulds, T-light holders and flower pots, every product here is chosen so beginners and professionals get the same clean result."],
                    ['label' => 'What We Stand For', 'content' => "Durable materials, honest descriptions, and unique designs. Our moulds are food-grade, non-toxic and reusable — no flaking, no warping after a few pours, and no exaggerated claims about what a mould can do."],
                    ['label' => 'How We Choose',     'content' => "Every mould and kit is test-cast before it goes on the site. We check detail retention, release, flexibility and finish, so what comes out of the mould looks like the photo on the listing."],
                    ['label' => 'The Promise',       'content' => "Fast, tracked delivery across India. 14-day easy returns on unused items. Real crafters on WhatsApp for casting tips, bulk orders and help picking the right mould."],
                ],
            ],
        ];
    }
}
