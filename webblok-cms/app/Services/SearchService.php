<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Full-text search over a tenant's pages using the SQLite FTS5 virtual table
 * (`pages_fts`). Indexing is incremental: callers re-index a page whenever its
 * content changes.
 */
final class SearchService
{
    /**
     * Index (or re-index) a single page for a locale.
     */
    public function indexPage(string $pageId, string $locale, string $title, string $content): void
    {
        $conn = DB::connection('tenant');

        $conn->table('pages_fts')
            ->where('page_id', $pageId)
            ->where('locale', $locale)
            ->delete();

        $conn->table('pages_fts')->insert([
            'page_id' => $pageId,
            'locale' => $locale,
            'title' => $title,
            'content' => $content,
        ]);
    }

    public function removePage(string $pageId): void
    {
        DB::connection('tenant')->table('pages_fts')->where('page_id', $pageId)->delete();
    }

    /**
     * Search published content. Returns matching page ids ranked by relevance.
     *
     * @return list<array{page_id: string, locale: string, title: string, snippet: string}>
     */
    public function search(string $query, string $locale = 'en', int $limit = 20): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        try {
            $rows = DB::connection('tenant')->select(
                'SELECT page_id, locale, title, '
                ."snippet(pages_fts, 3, '<mark>', '</mark>', '…', 12) AS snippet "
                .'FROM pages_fts WHERE pages_fts MATCH ? AND locale = ? '
                .'ORDER BY rank LIMIT ?',
                [$this->sanitize($query), $locale, $limit],
            );
        } catch (Throwable) {
            return [];
        }

        $results = [];
        foreach ($rows as $row) {
            if (! is_object($row)) {
                continue;
            }

            $pageId = property_exists($row, 'page_id') && is_string($row->page_id) ? $row->page_id : '';
            $rowLocale = property_exists($row, 'locale') && is_string($row->locale) ? $row->locale : $locale;
            $title = property_exists($row, 'title') && is_string($row->title) ? $row->title : '';
            $snippet = property_exists($row, 'snippet') && is_string($row->snippet) ? $row->snippet : '';

            $results[] = [
                'page_id' => $pageId,
                'locale' => $rowLocale,
                'title' => $title,
                'snippet' => $snippet,
            ];
        }

        return $results;
    }

    /**
     * Escape an FTS5 query into a safe phrase match to avoid syntax errors and
     * injection of FTS operators from untrusted input.
     */
    private function sanitize(string $query): string
    {
        $escaped = str_replace('"', '""', $query);

        return '"'.$escaped.'"';
    }
}
