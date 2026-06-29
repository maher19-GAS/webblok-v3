<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PageStatus;
use App\Models\Tenant\Page;
use Illuminate\Database\Eloquent\Collection;

/**
 * Builds a sitemap.xml document from a tenant's published pages.
 */
final class SitemapGenerator
{
    public function generate(string $baseUrl): string
    {
        $baseUrl = rtrim($baseUrl, '/');

        /** @var Collection<int, Page> $pages */
        $pages = Page::query()
            ->where('status', PageStatus::PUBLISHED->value)
            ->orderBy('full_path')
            ->get();

        $entries = '';
        foreach ($pages as $page) {
            $loc = $baseUrl.'/'.ltrim($page->full_path, '/');
            $lastmod = $page->updated_at?->toAtomString() ?? now()->toAtomString();
            $priority = $page->is_homepage ? '1.0' : '0.7';

            $entries .= "  <url>\n";
            $entries .= '    <loc>'.e($loc)."</loc>\n";
            $entries .= '    <lastmod>'.e($lastmod)."</lastmod>\n";
            $entries .= "    <changefreq>weekly</changefreq>\n";
            $entries .= '    <priority>'.$priority."</priority>\n";
            $entries .= "  </url>\n";
        }

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            ."<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n"
            .$entries
            .'</urlset>';
    }
}
