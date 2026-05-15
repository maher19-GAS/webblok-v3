<?php
/**
 * WebBook SaaS — Public Routes (no auth required)
 *
 *  GET  /public/{bookId}/mdcache   → full markdown content for offline caching
 *  GET  /public/{bookId}/info      → book metadata (title, cover, theme, …)
 *  GET  /public/{bookId}/chapters  → chapter list (titles + file_ids, no content)
 */

require_once __DIR__ . '/../lib/MainDB.php';
require_once __DIR__ . '/../lib/BookDB.php';

function route_public(string $method, array $seg): void
{
    $bookId = $seg[0] ?? '';
    $action = $seg[1] ?? '';

    if (!$bookId) wb_error('bookId required', 422);

    // Verify book is publicly accessible (visibility: public or unlisted)
    // Unlisted books also accept a valid api_key via X-WB-Book header
    $db   = wb_init_main_db();
    $book = wb_select_one($db, 'SELECT * FROM books WHERE id = ?', [$bookId]);
    if (!$book || $book['status'] !== 'published')
        wb_error('Book not found or not published', 404);

    $isPublic   = $book['visibility'] === 'public';
    $isUnlisted = $book['visibility'] === 'unlisted';
    $apiKey     = $_SERVER['HTTP_X_WB_BOOK'] ?? $_GET['api_key'] ?? '';
    $keyOk      = $apiKey && hash_equals($book['api_key'], $apiKey);

    if (!$isPublic && !($isUnlisted && $keyOk))
        wb_error('Access denied', 403);

    match (true) {
        $method === 'GET' && $action === 'mdcache'   => pub_mdcache($bookId),
        $method === 'GET' && $action === 'info'      => pub_info($book),
        $method === 'GET' && $action === 'chapters'  => pub_chapters($bookId),
        default => wb_error('Not found', 404),
    };
}

// ── GET /public/{bookId}/info ─────────────────────────
function pub_info(array $book): void
{
    // Return only safe public fields
    $safe = [
        'id'           => $book['id'],
        'title'        => $book['title'],
        'description'  => $book['description'],
        'language'     => $book['language'],
        'direction'    => $book['direction'],
        'cover_url'    => $book['cover_url'],
        'theme'        => $book['theme'],
        'total_slides' => $book['total_slides'],
        'total_words'  => $book['total_words'],
        'published_at' => $book['published_at'],
        'reader_url'   => $book['reader_url'],
    ];
    wb_json($safe);
}

// ── GET /public/{bookId}/chapters ─────────────────────
function pub_chapters(string $bookId): void
{
    $bdb  = wb_init_book_db($bookId);
    $rows = wb_select($bdb,
        'SELECT id, file_id, file_path, part, title, sort_order, word_count, is_published
         FROM chapters
         WHERE book_id = ? AND is_published = 1
         ORDER BY sort_order ASC',
        [$bookId]
    );
    wb_json(['chapters' => $rows, 'total' => count($rows)]);
}

// ── GET /public/{bookId}/mdcache ──────────────────────
function pub_mdcache(string $bookId): void
{
    $bdb = wb_init_book_db($bookId);

    // Only chapters that are published
    $rows = wb_select($bdb,
        'SELECT m.id, m.content, m.ts
         FROM reader_mdcache m
         INNER JOIN chapters c ON c.file_id = m.id AND c.book_id = m.book_id
         WHERE m.book_id = ? AND c.is_published = 1
         ORDER BY c.sort_order ASC',
        [$bookId]
    );

    // Allow clients to request only changed since ts
    $since = (int)($_GET['since'] ?? 0);
    if ($since > 0) {
        $rows = array_values(array_filter($rows, fn($r) => (int)$r['ts'] > $since));
    }

    // Add ETags / cache headers
    $etag = md5(json_encode(array_column($rows, 'ts')));
    header('ETag: "' . $etag . '"');
    header('Cache-Control: public, max-age=300');   // 5-min CDN cache

    if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === '"' . $etag . '"') {
        http_response_code(304);
        exit;
    }

    wb_json([
        'book_id' => $bookId,
        'mdcache' => $rows,
        'total'   => count($rows),
        'etag'    => $etag,
    ]);
}
