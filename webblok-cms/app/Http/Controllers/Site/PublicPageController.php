<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Enums\PageStatus;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Page;
use App\Services\LocaleService;
use App\Services\PageRenderer;
use App\Services\SitemapGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;

/**
 * Serves the public website for the active tenant (resolved by tenant.db
 * middleware). Renders pages by their full_path with the active theme.
 */
final class PublicPageController extends Controller
{
    public function __construct(
        private readonly PageRenderer $renderer,
        private readonly LocaleService $locales,
        private readonly SitemapGenerator $sitemap,
    ) {}

    public function show(Request $request, string $path = ''): Response
    {
        $locale = $this->resolveLocale($request);
        App::setLocale($locale);

        $page = $path === ''
            ? Page::query()->where('is_homepage', true)->first()
            : Page::query()->where('full_path', trim($path, '/'))->first();

        abort_if($page === null, 404, 'Page not found.');

        if ($page->status !== PageStatus::PUBLISHED && $request->query('preview') === null) {
            abort(404, 'Page not found.');
        }

        $request->attributes->set('page_id', $page->id);

        $localeRecord = $page->locale($locale);
        $title = $localeRecord !== null ? $localeRecord->title : config('webblok.name', 'WebBlok');
        $html = $this->renderer->renderDocument($page, $locale, is_string($title) ? $title : 'WebBlok');

        return new Response($html);
    }

    public function sitemap(Request $request): Response
    {
        $xml = $this->sitemap->generate($request->getSchemeAndHttpHost());

        return new Response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    private function resolveLocale(Request $request): string
    {
        $locale = $request->query('lang', $this->locales->fallback());
        $locale = is_string($locale) ? $locale : $this->locales->fallback();

        return $this->locales->isSupported($locale) ? $locale : $this->locales->fallback();
    }
}
