<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cms;

use App\Actions\Bloks\CreateBlokInstance;
use App\Actions\Pages\CreatePageRevision;
use App\Http\Controllers\Controller;
use App\Models\Platform\BlokDefinition;
use App\Models\Tenant\Page;
use App\Services\BlokInstanceResolver;
use App\Services\BuilderTreeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Drag-and-drop page builder. The Blade shell hosts the Alpine.js store and
 * SortableJS canvas; these endpoints are the authoritative server side that
 * enforces nesting/slot rules and persists the tree + revisions.
 */
final class BuilderController extends Controller
{
    public function __construct(
        private readonly BlokInstanceResolver $resolver,
        private readonly BuilderTreeService $tree,
        private readonly CreateBlokInstance $createBlok,
        private readonly CreatePageRevision $createRevision,
    ) {}

    public function edit(string $pageId): View
    {
        $page = Page::query()->findOrFail($pageId);

        return view('cms.builder', [
            'page' => $page,
            'definitions' => BlokDefinition::query()
                ->where('is_active', true)
                ->orderBy('category')
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function tree(string $pageId, Request $request): JsonResponse
    {
        $locale = $this->localeFrom($request);

        return response()->json([
            'data' => ['sections' => $this->resolver->sectionedTree($pageId, $locale)],
        ]);
    }

    public function storeBlok(string $pageId, Request $request): JsonResponse
    {
        /** @var array{blok_key: string, section: string, sort_order: int, parent_id: string|null, slot_name: string|null} $v */
        $v = $request->validate([
            'blok_key' => ['required', 'string'],
            'section' => ['required', 'string'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'parent_id' => ['nullable', 'string'],
            'slot_name' => ['nullable', 'string'],
        ]);

        $instance = $this->createBlok->execute($pageId, $v);

        return response()->json(['data' => ['id' => $instance->id]], 201);
    }

    public function persistTree(string $pageId, Request $request): JsonResponse
    {
        /** @var array{sections: array<string, array<int, array<string, mixed>>>} $v */
        $v = $request->validate(['sections' => ['required', 'array']]);

        $this->tree->persist($pageId, $v['sections']);

        return response()->json(['ok' => true]);
    }

    public function revision(string $pageId): JsonResponse
    {
        $this->createRevision->execute($pageId, 'Manual save from builder');

        return response()->json(['ok' => true]);
    }

    private function localeFrom(Request $request): string
    {
        $locale = $request->query('locale', 'en');

        return is_string($locale) ? $locale : 'en';
    }
}
