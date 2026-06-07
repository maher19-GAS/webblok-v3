<?php

declare(strict_types=1);

namespace App\Actions\Bloks;

use App\Models\Tenant\BlokInstance;
use Illuminate\Support\Str;

final class CloneBlokInstance
{
    public function execute(string $instanceId): BlokInstance
    {
        /** @var BlokInstance $source */
        $source = BlokInstance::query()->findOrFail($instanceId);

        return $this->copy($source, $source->parent_id);
    }

    private function copy(BlokInstance $source, ?string $parentId): BlokInstance
    {
        /** @var BlokInstance $clone */
        $clone = BlokInstance::query()->create([
            'id' => (string) Str::uuid(),
            'page_id' => $source->page_id,
            'parent_id' => $parentId,
            'slot_name' => $source->slot_name,
            'blok_key' => $source->blok_key,
            'section' => $source->section->value,
            'config' => json_encode($source->config, JSON_THROW_ON_ERROR),
            'locale_config' => json_encode($source->locale_config, JSON_THROW_ON_ERROR),
            'sort_order' => $source->sort_order + 1,
            'is_visible' => $source->is_visible,
            'css_classes' => $source->css_classes,
            'anchor_id' => null,
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ]);

        foreach ($source->children as $child) {
            $this->copy($child, $clone->id);
        }

        return $clone;
    }
}
