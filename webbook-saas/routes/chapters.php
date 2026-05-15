<?php
/**
 * WebBook SaaS — Chapters (Markdown) Routes
 *
 *  GET    /books/{bookId}/chapters                          → list chapters
 *  POST   /books/{bookId}/chapters                          → create chapter
 *  GET    /books/{bookId}/chapters/{chapterId}              → get chapter (with content)
 *  PUT    /books/{bookId}/chapters/{chapterId}              → full update
 *  PATCH  /books/{bookId}/chapters/{chapterId}              → partial update
 *  DELETE /books/{bookId}/chapters/{chapterId}              → delete chapter
 *  GET    /books/{bookId}/chapters/{chapterId}/revisions    → revision history
 *  POST   /books/{bookId}/chapters/reorder                  → reorder (bulk sort_order)
 */

require_once __DIR__ . '/../lib/MainDB.php';
require_once __DIR__ . '/../lib/BookDB.php';

function route_chapters(string $method, array $params): void
{
    $bookId    = $params['bookId']    ?? '';
    $chapterId = $params['chapterId'] ?? null;
    $sub       = $params['sub']       ?? null;

    // Authenticate every chapters route
    $auth = wb_require_auth();
    if (!wb_can_access_book($bookId, $auth['uid'], 'viewer'))
        wb_error('Access denied', 403);

    $canWrite = wb_can_access_book($bookId, $auth['uid'], 'editor');

    match (true) {
        !$chapterId && $method === 'GET'                              => ch_list($bookId),
        !$chapterId && $method === 'POST' && !$sub                   => ($canWrite ? ch_create($bookId, $auth) : wb_error('Forbidden',403)),
        // reorder endpoint: POST /chapters/reorder  (chapterId == "reorder")
        $chapterId === 'reorder' && $method === 'POST'               => ($canWrite ? ch_reorder($bookId) : wb_error('Forbidden',403)),
        $chapterId  && $method === 'GET'  && !$sub                   => ch_get($bookId, $chapterId),
        $chapterId  && $method === 'PUT'  && !$sub                   => ($canWrite ? ch_update($bookId, $chapterId, $auth) : wb_error('Forbidden',403)),
        $chapterId  && $method === 'PATCH' && !$sub                  => ($canWrite ? ch_update($bookId, $chapterId, $auth) : wb_error('Forbidden',403)),
        $chapterId  && $method === 'DELETE' && !$sub                 => ($canWrite ? ch_delete($bookId, $chapterId) : wb_error('Forbidden',403)),
        $chapterId  && $sub === 'revisions' && $method === 'GET'     => ch_revisions($bookId, $chapterId),
        default => wb_error('Not found', 404),
    };
}

// ── GET /books/{bookId}/chapters ───────────────────────
function ch_list(string $bookId): void
{
    $bdb  = wb_init_book_db($bookId);
    $rows = wb_select($bdb,
        'SELECT id, book_id, file_id, file_path, part, title,
                sort_order, word_count, is_published, created_at, updated_at
         FROM chapters WHERE book_id = ? ORDER BY sort_order ASC, created_at ASC',
        [$bookId]
    );
    wb_json(['chapters' => $rows, 'total' => count($rows)]);
}

// ── POST /books/{bookId}/chapters ──────────────────────
function ch_create(string $bookId, array $auth): void
{
    $b   = wb_body();
    $bdb = wb_init_book_db($bookId);

    $title   = wb_str($b['title'] ?? '');
    $fileId  = wb_str($b['file_id'] ?? _slugify_id($title ?: 'chapter'));
    $content = $b['content'] ?? '';

    if (!$fileId) wb_error('file_id required', 422);
    if (mb_strlen($content) > WB_MAX_MD_SIZE)
        wb_error('Content exceeds ' . (WB_MAX_MD_SIZE / 1024) . ' KB limit', 422);

    // Unique file_id per book
    if (wb_select_one($bdb, 'SELECT id FROM chapters WHERE book_id = ? AND file_id = ?', [$bookId, $fileId]))
        wb_error("file_id '$fileId' already exists in this book", 409);

    // Next sort order
    $maxOrder = (int)(wb_select_one($bdb,
        'SELECT MAX(sort_order) AS m FROM chapters WHERE book_id = ?',
        [$bookId])['m'] ?? -1);

    $now  = wb_ms();
    $id   = wb_uuid();
    $wc   = wb_word_count($content);
    $fp   = 'books/' . $bookId . '/' . $fileId . '.md';

    wb_exec($bdb,
        'INSERT INTO chapters
            (id, book_id, file_id, file_path, part, title, sort_order,
             content, word_count, is_published, created_at, updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,0,?,?)',
        [$id, $bookId, $fileId, $fp,
         wb_str($b['part'] ?? ''),
         $title,
         (int)($b['sort_order'] ?? $maxOrder + 1),
         $content, $wc, $now, $now]
    );

    // Save initial revision
    _save_revision($bdb, $id, $content, $wc, $auth['uid'], 'Initial version');

    // Sync mdcache
    _sync_mdcache($bdb, $bookId, $fileId, $content);

    // Update book word count
    _update_book_wordcount($bookId);

    $row = wb_select_one($bdb, 'SELECT * FROM chapters WHERE id = ?', [$id]);
    wb_json($row, 201);
}

// ── GET /books/{bookId}/chapters/{chapterId} ───────────
function ch_get(string $bookId, string $chapterId): void
{
    $bdb = wb_init_book_db($bookId);
    $row = wb_select_one($bdb,
        'SELECT * FROM chapters WHERE id = ? AND book_id = ?',
        [$chapterId, $bookId]);
    if (!$row) wb_error('Chapter not found', 404);
    wb_json($row);
}

// ── PUT/PATCH /books/{bookId}/chapters/{chapterId} ─────
function ch_update(string $bookId, string $chapterId, array $auth): void
{
    $bdb = wb_init_book_db($bookId);
    $row = wb_select_one($bdb,
        'SELECT * FROM chapters WHERE id = ? AND book_id = ?',
        [$chapterId, $bookId]);
    if (!$row) wb_error('Chapter not found', 404);

    $b = wb_body();

    $fields = [];
    $params = [];
    $contentChanged = false;

    if (array_key_exists('title', $b)) {
        $fields[] = 'title = ?'; $params[] = wb_str($b['title'], 255);
    }
    if (array_key_exists('part', $b)) {
        $fields[] = 'part = ?'; $params[] = wb_str($b['part'], 100);
    }
    if (array_key_exists('sort_order', $b)) {
        $fields[] = 'sort_order = ?'; $params[] = (int)$b['sort_order'];
    }
    if (array_key_exists('is_published', $b)) {
        $fields[] = 'is_published = ?'; $params[] = $b['is_published'] ? 1 : 0;
    }
    if (array_key_exists('content', $b)) {
        $content = $b['content'];
        if (mb_strlen($content) > WB_MAX_MD_SIZE)
            wb_error('Content exceeds ' . (WB_MAX_MD_SIZE / 1024) . ' KB limit', 422);
        $wc = wb_word_count($content);
        $fields[] = 'content = ?';    $params[] = $content;
        $fields[] = 'word_count = ?'; $params[] = $wc;
        $contentChanged = true;
    }

    if (empty($fields)) wb_error('No fields to update', 422);

    $now      = wb_ms();
    $fields[] = 'updated_at = ?'; $params[] = $now;
    $params[] = $chapterId; $params[] = $bookId;

    wb_exec($bdb,
        'UPDATE chapters SET ' . implode(', ', $fields) . ' WHERE id = ? AND book_id = ?',
        $params
    );

    if ($contentChanged) {
        _save_revision($bdb, $chapterId, $content, $wc, $auth['uid'],
            wb_str($b['commit_message'] ?? ''));
        _sync_mdcache($bdb, $bookId, $row['file_id'], $content);
        _update_book_wordcount($bookId);
    }

    $row = wb_select_one($bdb, 'SELECT * FROM chapters WHERE id = ?', [$chapterId]);
    wb_json($row);
}

// ── DELETE /books/{bookId}/chapters/{chapterId} ────────
function ch_delete(string $bookId, string $chapterId): void
{
    $bdb = wb_init_book_db($bookId);
    $row = wb_select_one($bdb,
        'SELECT id FROM chapters WHERE id = ? AND book_id = ?',
        [$chapterId, $bookId]);
    if (!$row) wb_error('Chapter not found', 404);

    // Cascade: revisions deleted by FK constraint
    wb_exec($bdb, 'DELETE FROM chapters WHERE id = ?', [$chapterId]);
    _update_book_wordcount($bookId);

    http_response_code(204);
    exit;
}

// ── GET /books/{bookId}/chapters/{chapterId}/revisions ─
function ch_revisions(string $bookId, string $chapterId): void
{
    $bdb   = wb_init_book_db($bookId);
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
    $rows  = wb_select($bdb,
        'SELECT id, chapter_id, word_count, author_id, message, created_at
         FROM chapter_revisions WHERE chapter_id = ?
         ORDER BY created_at DESC LIMIT ?',
        [$chapterId, $limit]
    );
    // Optionally include content if requested
    if (($_GET['include_content'] ?? '0') === '1') {
        $rows = wb_select($bdb,
            'SELECT * FROM chapter_revisions WHERE chapter_id = ?
             ORDER BY created_at DESC LIMIT ?',
            [$chapterId, $limit]
        );
    }
    wb_json(['revisions' => $rows]);
}

// ── POST /books/{bookId}/chapters/reorder ──────────────
function ch_reorder(string $bookId): void
{
    $b   = wb_body();
    $bdb = wb_init_book_db($bookId);

    // Expect: { "order": [{"id":"…","sort_order":0}, …] }
    $order = $b['order'] ?? [];
    if (!is_array($order)) wb_error('order must be an array', 422);

    $now = wb_ms();
    $db  = $bdb;
    $db->beginTransaction();
    try {
        foreach ($order as $item) {
            if (empty($item['id'])) continue;
            wb_exec($db,
                'UPDATE chapters SET sort_order = ?, updated_at = ?
                 WHERE id = ? AND book_id = ?',
                [(int)($item['sort_order'] ?? 0), $now, $item['id'], $bookId]
            );
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    ch_list($bookId);
}

// ── Private helpers ────────────────────────────────────

function _save_revision(PDO $bdb, string $chapterId, string $content, int $wc, string $authorId, string $msg): void
{
    // Trim to most recent 50
    $count = (int)(wb_select_one($bdb,
        'SELECT COUNT(*) AS n FROM chapter_revisions WHERE chapter_id = ?',
        [$chapterId])['n'] ?? 0);
    if ($count >= 50) {
        $oldest = wb_select_one($bdb,
            'SELECT id FROM chapter_revisions WHERE chapter_id = ?
             ORDER BY created_at ASC LIMIT 1',
            [$chapterId]);
        if ($oldest) wb_exec($bdb,
            'DELETE FROM chapter_revisions WHERE id = ?', [$oldest['id']]);
    }

    wb_exec($bdb,
        'INSERT INTO chapter_revisions
            (id, chapter_id, content, word_count, author_id, message, created_at)
         VALUES (?,?,?,?,?,?,?)',
        [wb_uuid(), $chapterId, $content, $wc, $authorId, $msg, wb_ms()]
    );
}

/** Keep reader_mdcache in sync whenever a chapter's content changes. */
function _sync_mdcache(PDO $bdb, string $bookId, string $fileId, string $content): void
{
    $now = wb_ms();
    wb_exec($bdb,
        'INSERT INTO reader_mdcache (id, book_id, content, ts)
         VALUES (?,?,?,?)
         ON CONFLICT(id) DO UPDATE SET content = excluded.content, ts = excluded.ts',
        [$fileId, $bookId, $content, $now]
    );
}

/** Recalculate and persist total word count on the book registry. */
function _update_book_wordcount(string $bookId): void
{
    $bdb   = wb_init_book_db($bookId);
    $total = (int)(wb_select_one($bdb,
        'SELECT SUM(word_count) AS s FROM chapters WHERE book_id = ?',
        [$bookId])['s'] ?? 0);
    $db    = wb_init_main_db();
    wb_exec($db,
        'UPDATE books SET total_words = ?, updated_at = ? WHERE id = ?',
        [$total, wb_ms(), $bookId]
    );
}

function _slugify_id(string $str): string
{
    $s = mb_strtolower(trim($str));
    $s = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $s);
    $s = preg_replace('/[\s]+/u', '-', $s);
    $s = preg_replace('/-+/', '-', $s);
    return substr(trim($s, '-'), 0, 50);
}
