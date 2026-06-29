<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Models\Tenant\Page;
use App\Models\Tenant\PageRevision;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreatePageRevision
{
    public function execute(string $pageId, ?string $summary = null): PageRevision
    {
        /** @var Page $page */
        $page = Page::query()
            ->with(['locales', 'blokInstances'])
            ->findOrFail($pageId);

        $maxRevision = DB::connection('tenant')->table('page_revisions')
            ->where('page_id', $pageId)
            ->max('revision_number');
        $next = (is_numeric($maxRevision) ? (int) $maxRevision : 0) + 1;

        $snapshot = [
            'page' => $page->only(['slug', 'full_path', 'status', 'template', 'is_homepage']),
            'locales' => $page->locales->map(fn ($l): array => $l->attributesToArray())->all(),
            'bloks' => $page->blokInstances->map(fn ($b): array => $b->attributesToArray())->all(),
        ];

        $userId = Auth::id();

        /** @var PageRevision $revision */
        $revision = PageRevision::query()->create([
            'id' => (string) Str::uuid(),
            'page_id' => $pageId,
            'revision_number' => $next,
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'change_summary' => $summary,
            'created_by' => is_string($userId) ? $userId : null,
            'created_at' => now()->toISOString(),
        ]);

        return $revision;
    }
}
