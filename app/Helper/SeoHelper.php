<?php

namespace App\Helper;

use App\Models\Setting;

class SeoHelper
{
    /**
     * Organization structured data — appears once site-wide in the head.
     * Google uses this to enrich the sitelinks + knowledge panel.
     */
    public static function organizationJsonLd(?Setting $settings = null): string
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => config('app.name'),
            'url' => url('/'),
            'logo' => asset('client/images/logo/logo.svg'),
        ];

        if ($settings) {
            $contact = [];
            if (! empty($settings->phone)) $contact['telephone'] = $settings->phone;
            if (! empty($settings->email)) $contact['email'] = $settings->email;
            if (! empty($contact)) {
                $contact['@type'] = 'ContactPoint';
                $contact['contactType'] = 'customer support';
                $data['contactPoint'] = [$contact];
            }

            $sameAs = array_values(array_filter([
                $settings['social_links']['facebook'] ?? null,
                $settings['social_links']['instagram'] ?? null,
                $settings['social_links']['twiter'] ?? null,
                $settings['social_links']['youtube'] ?? null,
            ]));
            if (! empty($sameAs)) $data['sameAs'] = $sameAs;
        }

        return self::render($data);
    }

    /**
     * WebSite structured data with a SearchAction — enables the search box
     * in Google's sitelinks.
     */
    public static function websiteJsonLd(): string
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('app.name'),
            'url' => url('/'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => url('/shop') . '?search={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ];
        return self::render($data);
    }

    /**
     * BreadcrumbList structured data. Pass an ordered array of ['name' => …, 'url' => …].
     */
    public static function breadcrumbsJsonLd(array $items): string
    {
        $list = [];
        foreach (array_values($items) as $i => $item) {
            $list[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['name'] ?? '',
                'item' => $item['url'] ?? url('/'),
            ];
        }
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $list,
        ];
        return self::render($data);
    }

    protected static function render(array $data): string
    {
        return '<script type="application/ld+json">'
            . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . '</script>';
    }
}
