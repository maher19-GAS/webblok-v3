<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BlokNestingDepthExceeded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Persists the authoritative blok tree for a page. Performs server-side
 * validation of nesting depth before writing, so the client tree can never
 * exceed the platform limit even if tampered with.
 */
final class BuilderTreeService
{
    private const int MAX_DEPTH = 10;

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $sections
     */
    public function persist(string $pageId, array $sections): void
    {
        DB::connection('tenant')->transaction(function () use ($pageId, $sections): void {
            DB::connection('tenant')->table('blok_instances')->where('page_id', $pageId)->delete();

            foreach ($sections as $section => $nodes) {
                $order = 0;
                foreach ($nodes as $node) {
                    $this->writeNode($pageId, $section, $node, null, $order, 1);
                    $order++;
                }
            }
        });
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function writeNode(string $pageId, string $section, array $node, ?string $parentId, int $sortOrder, int $depth): void
    {
        if ($depth > self::MAX_DEPTH) {
            throw new BlokNestingDepthExceeded("Tree depth {$depth} exceeds maximum of ".self::MAX_DEPTH.'.');
        }

        $id = isset($node['id']) && is_string($node['id']) && Str::isUuid($node['id'])
            ? $node['id']
            : (string) Str::uuid();

        /** @var array<string, mixed> $config */
        $config = is_array($node['config'] ?? null) ? $node['config'] : [];
        /** @var array<string, mixed> $localeConfig */
        $localeConfig = is_array($node['locale_config'] ?? null) ? $node['locale_config'] : [];

        $blokKey = isset($node['blok_key']) && is_string($node['blok_key']) ? $node['blok_key'] : '';

        DB::connection('tenant')->table('blok_instances')->insert([
            'id' => $id,
            'page_id' => $pageId,
            'parent_id' => $parentId,
            'slot_name' => isset($node['slot_name']) && is_string($node['slot_name']) ? $node['slot_name'] : null,
            'blok_key' => $blokKey,
            'section' => $section,
            'config' => json_encode($config, JSON_THROW_ON_ERROR),
            'locale_config' => json_encode($localeConfig, JSON_THROW_ON_ERROR),
            'sort_order' => $sortOrder,
            'is_visible' => (bool) ($node['is_visible'] ?? true),
            'anchor_id' => isset($node['anchor_id']) && is_string($node['anchor_id']) ? $node['anchor_id'] : null,
            'css_classes' => isset($node['css_classes']) && is_string($node['css_classes']) ? $node['css_classes'] : null,
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ]);

        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        $childOrder = 0;
        foreach ($children as $child) {
            if (is_array($child)) {
                /** @var array<string, mixed> $child */
                $this->writeNode($pageId, $section, $child, $id, $childOrder, $depth + 1);
                $childOrder++;
            }
        }
    }
}
