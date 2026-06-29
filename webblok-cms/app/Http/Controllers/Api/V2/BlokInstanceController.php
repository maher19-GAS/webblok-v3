<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V2;

use App\Actions\Bloks\CreateBlokInstance;
use App\Actions\Bloks\ReorderBlokInstances;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Page;
use App\Services\BlokInstanceResolver;
use App\Services\PageRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * V2 REST API — blok instances belonging to a page.
 */
final class BlokInstanceController extends Controller
{
    public function __construct(
        private readonly BlokInstanceResolver $resolver,
        private readonly CreateBlokInstance $create,
        private readonly ReorderBlokInstances $reorder,
        private readonly PageRenderer $renderer,
    ) {}

    public function index(string $pageId, Request $request): JsonResponse
    {
        $locale = $request->query('locale', 'en');
        $locale = is_string($locale) ? $locale : 'en';

        return response()->json([
            'data' => ['sections' => $this->resolver->sectionedTree($pageId, $locale)],
        ]);
    }

    public function store(string $pageId, Request $request): JsonResponse
    {
        /** @var array{blok_key: string, section: string, sort_order: int, parent_id: string|null, slot_name: string|null} $v */
        $v = $request->validate([
            'blok_key' => ['required', 'string'],
            'section' => ['required', 'string'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'parent_id' => ['nullable', 'string'],
            'slot_name' => ['nullable', 'string'],
        ]);

        $instance = $this->create->execute($pageId, $v);

        return response()->json(['data' => ['id' => $instance->id]], 201);
    }

    public function reorder(Request $request): JsonResponse
    {
        /** @var array{ordered_ids: list<string>} $v */
        $v = $request->validate([
            'ordered_ids' => ['required', 'array'],
            'ordered_ids.*' => ['required', 'string'],
        ]);

        $this->reorder->execute($v['ordered_ids']);

        return response()->json(['ok' => true]);
    }

    public function renderPage(string $pageId, Request $request): JsonResponse
    {
        $locale = $request->query('locale', 'en');
        $locale = is_string($locale) ? $locale : 'en';

        $page = Page::query()->findOrFail($pageId);

        return response()->json(['data' => ['html' => $this->renderer->renderBody($page, $locale)]]);
    }
}
