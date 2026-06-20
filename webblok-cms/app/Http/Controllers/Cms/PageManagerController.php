<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cms;

use App\Enums\PageStatus;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Page;
use App\Models\Tenant\PageLocale;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Manages the whole site page tree: create/rename/reorder pages, set parent
 * relationships (recomputing full_path), mark a homepage, schedule publishing,
 * and edit per-locale SEO.
 */
final class PageManagerController extends Controller
{
    public function index(): View
    {
        $pages = Page::query()->orderBy('full_path')->get();

        return view('cms.pages.index', ['pages' => $pages]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var array{slug: string, title: string, parent_id: string|null, template: string|null} $v */
        $v = $request->validate([
            'slug' => ['required', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'string'],
            'template' => ['nullable', 'string'],
        ]);

        $slug = Str::slug($v['slug']);
        $fullPath = $this->buildFullPath($slug, $v['parent_id']);

        $page = Page::query()->create([
            'id' => (string) Str::uuid(),
            'parent_id' => $v['parent_id'],
            'slug' => $slug,
            'full_path' => $fullPath,
            'status' => PageStatus::DRAFT->value,
            'template' => $v['template'] ?? 'default',
            'is_homepage' => false,
            'requires_auth' => false,
            'sort_order' => 0,
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ]);

        PageLocale::query()->create([
            'id' => (string) Str::uuid(),
            'page_id' => $page->id,
            'locale' => 'en',
            'title' => $v['title'],
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ]);

        return redirect()->route('cms.builder.edit', $page->id);
    }

    public function publish(string $pageId): RedirectResponse
    {
        $page = Page::query()->findOrFail($pageId);
        $page->forceFill([
            'status' => PageStatus::PUBLISHED->value,
            'published_at' => now()->toISOString(),
        ])->save();

        return back();
    }

    public function setHomepage(string $pageId): RedirectResponse
    {
        Page::query()->update(['is_homepage' => false]);
        Page::query()->whereKey($pageId)->update(['is_homepage' => true]);

        return back();
    }

    public function destroy(string $pageId): RedirectResponse
    {
        Page::query()->findOrFail($pageId)->delete();

        return redirect()->route('cms.pages.index');
    }

    private function buildFullPath(string $slug, ?string $parentId): string
    {
        if ($parentId === null) {
            return $slug;
        }

        $parent = Page::query()->find($parentId);

        return $parent !== null ? trim($parent->full_path.'/'.$slug, '/') : $slug;
    }
}
