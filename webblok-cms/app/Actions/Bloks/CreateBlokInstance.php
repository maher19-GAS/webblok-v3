<?php

declare(strict_types=1);

namespace App\Actions\Bloks;

use App\Exceptions\BlokNestingDepthExceeded;
use App\Exceptions\InvalidBlokSchemaException;
use App\Models\Platform\BlokDefinition;
use App\Models\Tenant\BlokInstance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateBlokInstance
{
    private const int MAX_DEPTH = 10;

    /**
     * @param  array{blok_key: string, section: string, sort_order: int, parent_id: string|null, slot_name: string|null}  $input
     */
    public function execute(string $pageId, array $input): BlokInstance
    {
        $parentId = $input['parent_id'];

        if ($parentId !== null) {
            $depth = $this->depthOf($parentId);
            if ($depth + 1 > self::MAX_DEPTH) {
                throw new BlokNestingDepthExceeded('Maximum nesting depth of 10 exceeded.');
            }
            $this->assertSlotHasRoom($parentId, $input['slot_name']);
        }

        /** @var BlokInstance $instance */
        $instance = BlokInstance::query()->create([
            'id' => (string) Str::uuid(),
            'page_id' => $pageId,
            'parent_id' => $parentId,
            'slot_name' => $input['slot_name'],
            'blok_key' => $input['blok_key'],
            'section' => $input['section'],
            'config' => '{}',
            'locale_config' => '{}',
            'sort_order' => $input['sort_order'],
            'is_visible' => true,
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ]);

        return $instance;
    }

    private function depthOf(string $instanceId): int
    {
        $depth = 0;
        $current = $instanceId;

        while ($current !== null) {
            $row = DB::connection('tenant')->table('blok_instances')
                ->select('parent_id')->where('id', $current)->first();

            if ($row === null) {
                break;
            }
            $depth++;
            /** @var string|null $parent */
            $parent = $row->parent_id ?? null;
            $current = $parent;

            if ($depth > self::MAX_DEPTH + 1) {
                throw new BlokNestingDepthExceeded('Cycle or excessive depth detected.');
            }
        }

        return $depth;
    }

    private function assertSlotHasRoom(string $parentId, ?string $slotName): void
    {
        $parent = DB::connection('tenant')->table('blok_instances')
            ->select('blok_key')->where('id', $parentId)->first();

        if ($parent === null) {
            throw new InvalidBlokSchemaException('Parent blok not found.');
        }

        /** @var string $parentBlokKey */
        $parentBlokKey = $parent->blok_key ?? '';

        /** @var BlokDefinition|null $def */
        $def = BlokDefinition::query()->where('blok_key', $parentBlokKey)->first();

        if ($def === null || ! $def->accepts_children) {
            throw new InvalidBlokSchemaException('Parent blok does not accept children.');
        }

        if ($slotName !== null && $def->slots !== null) {
            /** @var list<array{name: string, max_children: int}> $slots */
            $slots = $def->slots;
            foreach ($slots as $slot) {
                if ($slot['name'] === $slotName) {
                    $count = DB::connection('tenant')->table('blok_instances')
                        ->where('parent_id', $parentId)->where('slot_name', $slotName)->count();
                    if ($count >= $slot['max_children']) {
                        throw new InvalidBlokSchemaException("Slot '{$slotName}' is full.");
                    }
                }
            }
        }
    }
}
