<?php

declare(strict_types=1);

namespace App\Services\Ai\Drivers;

use App\Data\SiteBundleBlokData;
use App\Data\SiteBundleData;
use App\Data\SiteBundlePageData;
use App\Services\Ai\Contracts\SiteGeneratorDriver;

/**
 * Default offline-safe driver. Builds a sensible 3-page starter from the prompt
 * WITHOUT calling any external API, so the platform builds & passes CI offline.
 * Swap to a real LLM driver via config('webblok.ai_driver').
 */
final class StubSiteGeneratorDriver implements SiteGeneratorDriver
{
    /**
     * @param  list<string>  $locales
     */
    public function generate(string $prompt, array $locales, ?string $logoMediaId): SiteBundleData
    {
        $default = $locales[0] ?? 'en';
        $trimmed = mb_substr(trim($prompt), 0, 60);
        $name = $trimmed !== '' ? $trimmed : 'My Website';

        $home = new SiteBundlePageData(
            slug: 'home',
            isHomepage: true,
            template: 'default',
            locales: $this->localeMap($locales, $name, 'Welcome'),
            sections: [
                'body' => [
                    new SiteBundleBlokData('hero-section', 0, ['min_height' => 70], [
                        $default => ['headline' => $name, 'cta_label' => 'Get Started'],
                    ]),
                    new SiteBundleBlokData('rich-text', 1, [], [
                        $default => ['html' => '<p>'.e($prompt).'</p>'],
                    ]),
                ],
            ],
        );

        $about = new SiteBundlePageData(
            'about',
            false,
            'default',
            $this->localeMap($locales, 'About', 'About'),
            ['body' => [new SiteBundleBlokData('rich-text', 0)]],
        );

        $contact = new SiteBundlePageData(
            'contact',
            false,
            'default',
            $this->localeMap($locales, 'Contact', 'Contact'),
            ['body' => [new SiteBundleBlokData('contact-form', 0)]],
        );

        return new SiteBundleData(
            bundleVersion: 'webblok-site-bundle/1',
            name: $name,
            defaultLocale: $default,
            locales: $locales,
            themeSlug: 'light-clean',
            pages: [$home, $about, $contact],
        );
    }

    /**
     * @param  list<string>  $locales
     * @return array<string, array<string, string|null>>
     */
    private function localeMap(array $locales, string $title, string $metaPrefix): array
    {
        /** @var array<string, array<string, string|null>> $map */
        $map = [];
        foreach ($locales as $locale) {
            $map[$locale] = ['title' => $title, 'meta_title' => $metaPrefix];
        }

        return $map;
    }
}
