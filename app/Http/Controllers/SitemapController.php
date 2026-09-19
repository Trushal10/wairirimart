<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Response;

class SitemapController extends Controller
{
    private const CACHE_TTL_MINUTES = 60;

    public function index()
    {
        $xml = Cache::remember('sitemap.xml', now()->addMinutes(self::CACHE_TTL_MINUTES), function () {
            return $this->buildXml();
        });

        return Response::make($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    private function buildXml(): string
    {
        $urls = [];

        // Static pages
        foreach ([
            ['loc' => route('client.home'), 'priority' => '1.0', 'change' => 'daily'],
            ['loc' => route('client.shop'), 'priority' => '0.9', 'change' => 'daily'],
            ['loc' => route('client.category'), 'priority' => '0.8', 'change' => 'weekly'],
            ['loc' => route('client.about'), 'priority' => '0.5', 'change' => 'monthly'],
            ['loc' => route('client.contact'), 'priority' => '0.5', 'change' => 'monthly'],
        ] as $row) {
            $urls[] = $row + ['lastmod' => now()->toAtomString()];
        }

        // Categories
        Category::query()
            ->where('status', 1)
            ->select('slug', 'updated_at')
            ->orderBy('id')
            ->chunk(200, function ($chunk) use (&$urls) {
                foreach ($chunk as $category) {
                    if (empty($category->slug)) continue;
                    $urls[] = [
                        'loc' => route('client.category', ['category' => $category->slug]),
                        'priority' => '0.7',
                        'change' => 'weekly',
                        'lastmod' => optional($category->updated_at)->toAtomString() ?? now()->toAtomString(),
                    ];
                }
            });

        // Products
        Product::query()
            ->where('status', 1)
            ->select('slug', 'updated_at')
            ->orderBy('id')
            ->chunk(500, function ($chunk) use (&$urls) {
                foreach ($chunk as $product) {
                    if (empty($product->slug)) continue;
                    $urls[] = [
                        'loc' => route('client.product', ['productSlug' => $product->slug]),
                        'priority' => '0.8',
                        'change' => 'weekly',
                        'lastmod' => optional($product->updated_at)->toAtomString() ?? now()->toAtomString(),
                    ];
                }
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</loc>\n";
            $xml .= '    <lastmod>' . $url['lastmod'] . "</lastmod>\n";
            $xml .= '    <changefreq>' . $url['change'] . "</changefreq>\n";
            $xml .= '    <priority>' . $url['priority'] . "</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>' . "\n";
        return $xml;
    }
}
