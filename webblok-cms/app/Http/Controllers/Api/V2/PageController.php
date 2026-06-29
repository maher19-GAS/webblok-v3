<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V2;

use App\Enums\PageStatus;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * V2 REST API — pages CRUD scoped to the authenticated tenant.
 */
final class PageController extends Controller
{
    public function index(): JsonResponse
    {
        $pages = Page::query()->orderBy('full_path')->get()
            ->map(fn (Page $p): array => $this->present($p))->all();

        return response()->json(['data' => $pages]);
    }

    public function show(string $slug): JsonResponse
    {
        $page = Page::query()->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => $this->present($page)]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var array{slug: string, full_path: string|null, template: string|null} $v */
        $v = $request->validate([
            'slug' => ['required', 'string', 'max:120'],
            'full_path' => ['nullable', 'string'],
            'template' => ['nullable', 'string'],
        ]);

        $slug = Str::slug($v['slug']);

        $page = Page::query()->create([
            'id' => (string) Str::uuid(),
            'slug' => $slug,
            'full_path' => $v['full_path'] ?? $slug,
            'status' => PageStatus::DRAFT->value,
            'template' => $v['template'] ?? 'default',
            'is_homepage' => false,
            'requires_auth' => false,
            'sort_order' => 0,
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ]);

        return response()->json(['data' => $this->present($page)], 201);
    }

    public function publish(string $id): JsonResponse
    {
        $page = Page::query()->findOrFail($id);
        $page->forceFill([
            'status' => PageStatus::PUBLISHED->value,
            'published_at' => now()->toISOString(),
        ])->save();

        return response()->json(['data' => $this->present($page)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Page $page): array
    {
        return [
            'id' => $page->id,
            'slug' => $page->slug,
            'full_path' => $page->full_path,
            'status' => $page->status->value,
            'template' => $page->template,
            'is_homepage' => $page->is_homepage,
            'published_at' => $page->published_at?->toAtomString(),
        ];
    }
}
