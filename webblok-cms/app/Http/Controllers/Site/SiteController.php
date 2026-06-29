<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Enums\PageStatus;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Page;
use App\Services\LocaleService;
use App\Services\PageRenderer;
use App\Services\SitemapGenerator;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Public-facing site renderer. The tenant connection has already been switched
 * by the SetTenantDatabaseConnection middleware before these actions run.
 */
final class SiteController extends Controller
{
    public function __construct(
        private readonly PageRenderer $renderer,
        private readonly SitemapGenerator $sitemap,
        private readonly LocaleService $locales,
        private readonly TenantContext $context,
    ) {}

    public function show(Request $request, string $path = ''): SymfonyResponse
    {
        $locale = $this->resolveLocale($request);

        $page = $path === ''
            ? Page::query()->where('is_homepage', true)->first()
            : Page::query()->where('full_path', trim($path, '/'))->first();

        if ($page === null || $page->status !== PageStatus::PUBLISHED) {
            abort(404, 'Page not found.');
        }

        if ($page->requires_auth && $request->user() === null) {
            return redirect()->route('login');
        }

        $request->attributes->set('page_id', $page->id);

        $localeRecord = $page->locale($locale);
        $title = $localeRecord !== null ? $localeRecord->title : $this->context->require()->name;
        $html = $this->renderer->renderDocument($page, $locale, $title);

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function sitemap(Request $request): SymfonyResponse
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
