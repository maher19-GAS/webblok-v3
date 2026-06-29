<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant\Page;

/**
 * Assembles a complete HTML document for a public page by rendering each blok
 * in document order (header → body → sidebar → footer) and wrapping the result
 * in the active theme's layout shell.
 */
final class PageRenderer
{
    public function __construct(
        private readonly BlokInstanceResolver $resolver,
        private readonly BlokRenderer $blokRenderer,
        private readonly ThemeManager $themes,
    ) {}

    /**
     * Render the page body (all sections) to HTML for the given locale.
     */
    public function renderBody(Page $page, string $locale = 'en'): string
    {
        $sections = $this->resolver->sectionedTree($page->id, $locale);

        $html = '';
        foreach (['header', 'body', 'sidebar', 'footer'] as $section) {
            $nodes = $sections[$section] ?? [];
            if ($nodes === []) {
                continue;
            }

            $html .= "<section class=\"wb-section wb-section--{$section}\">";
            foreach ($nodes as $node) {
                $html .= $this->renderNode($node, $locale);
            }
            $html .= '</section>';
        }

        return $html;
    }

    /**
     * Render a full standalone HTML document (used for preview and static export).
     */
    public function renderDocument(Page $page, string $locale = 'en', string $title = 'WebBlok'): string
    {
        $body = $this->renderBody($page, $locale);
        $themeCss = $this->themes->activeCss();
        $dir = $this->isRtl($locale) ? 'rtl' : 'ltr';
        $safeTitle = e($title);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="{$locale}" dir="{$dir}">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>{$safeTitle}</title>
            <style>{$themeCss}</style>
        </head>
        <body class="wb-page">
        {$body}
        </body>
        </html>
        HTML;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function renderNode(array $node, string $locale): string
    {
        if (isset($node['is_visible']) && $node['is_visible'] === false) {
            return '';
        }

        $blokKey = isset($node['blok_key']) && is_string($node['blok_key']) ? $node['blok_key'] : 'unknown';
        /** @var array<string, mixed> $config */
        $config = isset($node['config']) && is_array($node['config']) ? $node['config'] : [];

        $childrenHtml = '';
        if (isset($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) {
                if (is_array($child)) {
                    /** @var array<string, mixed> $child */
                    $childrenHtml .= $this->renderNode($child, $locale);
                }
            }
        }

        $config['__children_html'] = $childrenHtml;

        return $this->blokRenderer->render($blokKey, $config, $locale);
    }

    private function isRtl(string $locale): bool
    {
        $locales = config('webblok.locales');
        if (! is_array($locales) || ! isset($locales[$locale]) || ! is_array($locales[$locale])) {
            return false;
        }

        return ($locales[$locale]['dir'] ?? 'ltr') === 'rtl';
    }
}
