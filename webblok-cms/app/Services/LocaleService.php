<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Central authority for supported locales, their text direction and display
 * labels. Reads from config/webblok.php so adding a language is config-only.
 */
final class LocaleService
{
    /**
     * @return array<string, array{dir: string, font: string, label: string}>
     */
    public function all(): array
    {
        $locales = config('webblok.locales');

        if (! is_array($locales)) {
            return ['en' => ['dir' => 'ltr', 'font' => 'Inter', 'label' => 'English']];
        }

        /** @var array<string, array{dir: string, font: string, label: string}> $result */
        $result = [];
        foreach ($locales as $code => $meta) {
            if (! is_string($code) || ! is_array($meta)) {
                continue;
            }
            $result[$code] = [
                'dir' => isset($meta['dir']) && is_string($meta['dir']) ? $meta['dir'] : 'ltr',
                'font' => isset($meta['font']) && is_string($meta['font']) ? $meta['font'] : 'Inter',
                'label' => isset($meta['label']) && is_string($meta['label']) ? $meta['label'] : $code,
            ];
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_keys($this->all());
    }

    public function isSupported(string $locale): bool
    {
        return array_key_exists($locale, $this->all());
    }

    public function direction(string $locale): string
    {
        $all = $this->all();

        return $all[$locale]['dir'] ?? 'ltr';
    }

    public function isRtl(string $locale): bool
    {
        return $this->direction($locale) === 'rtl';
    }

    public function fallback(): string
    {
        $codes = $this->codes();

        return $codes[0] ?? 'en';
    }
}
