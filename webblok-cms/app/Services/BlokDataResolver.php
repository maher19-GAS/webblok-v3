<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * V1 embed-API data resolver: merges base slide JSON with per-tenant
 * content overrides and runtime overrides (runtime > tenant > base).
 */
final class BlokDataResolver
{
    /**
     * @param  array<string, mixed>  $runtimeOverrides
     * @return array<string, mixed>
     */
    public function resolve(string $blokKey, string $lang, array $runtimeOverrides = []): array
    {
        $padded = str_pad(str_replace('slide-', '', $blokKey), 3, '0', STR_PAD_LEFT);
        $path = base_path("slides/data/slide-{$padded}.{$lang}.json");

        if (! file_exists($path)) {
            $path = base_path("slides/data/slide-{$padded}.en.json");
        }

        /** @var array<string, mixed> $base */
        $base = file_exists($path)
            ? (array) json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)
            : [];

        $tenantOverrides = $this->loadTenantOverrides($blokKey, $lang);

        return $this->deepMerge($base, $tenantOverrides, $runtimeOverrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadTenantOverrides(string $blokKey, string $lang): array
    {
        try {
            $rows = DB::connection('tenant')
                ->table('content_overrides')
                ->where('blok_key', $blokKey)
                ->where('lang', $lang)
                ->get();

            $result = [];

            foreach ($rows as $row) {
                /** @var string $keyPath */
                $keyPath = $row->key_path ?? '';
                $value = $row->value ?? null;
                data_set($result, $keyPath, $value);
            }

            /** @var array<string, mixed> $result */
            return $result;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  ...$arrays
     * @return array<string, mixed>
     */
    private function deepMerge(array ...$arrays): array
    {
        /** @var array<string, mixed> $result */
        $result = [];

        foreach ($arrays as $arr) {
            foreach ($arr as $key => $value) {
                if (is_array($value) && isset($result[$key]) && is_array($result[$key])) {
                    /** @var array<string, mixed> $existing */
                    $existing = $result[$key];
                    /** @var array<string, mixed> $incoming */
                    $incoming = $value;
                    $result[$key] = $this->deepMerge($existing, $incoming);
                } else {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }
}
