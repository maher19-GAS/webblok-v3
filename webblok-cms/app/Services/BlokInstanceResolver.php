<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant\BlokInstance;
use Illuminate\Database\Eloquent\Collection;

/**
 * Resolves a page's blok instances into a nested, sectioned tree suitable
 * for the builder canvas and the public renderer.
 */
final class BlokInstanceResolver
{
    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function sectionedTree(string $pageId, string $locale = 'en'): array
    {
        /** @var Collection<int, BlokInstance> $all */
        $all = BlokInstance::query()
            ->where('page_id', $pageId)
            ->orderBy('sort_order')
            ->get();

        /** @var array<string, list<array<string, mixed>>> $sections */
        $sections = ['header' => [], 'body' => [], 'footer' => [], 'sidebar' => []];

        foreach ($all->whereNull('parent_id') as $root) {
            $section = $root->section->value;
            $sections[$section][] = $this->nodeToArray($root, $all, $locale);
        }

        return $sections;
    }

    /**
     * @param  Collection<int, BlokInstance>  $all
     * @return array<string, mixed>
     */
    private function nodeToArray(BlokInstance $node, Collection $all, string $locale): array
    {
        $localeConfig = $node->locale_config[$locale] ?? [];

        $children = [];
        foreach ($all->where('parent_id', $node->id) as $child) {
            $children[] = $this->nodeToArray($child, $all, $locale);
        }

        return [
            'id' => $node->id,
            'blok_key' => $node->blok_key,
            'section' => $node->section->value,
            'slot_name' => $node->slot_name,
            'sort_order' => $node->sort_order,
            'is_visible' => $node->is_visible,
            'config' => array_merge($node->config, $localeConfig),
            'anchor_id' => $node->anchor_id,
            'css_classes' => $node->css_classes,
            'children' => $children,
        ];
    }
}
