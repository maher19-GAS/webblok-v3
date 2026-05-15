<?php
/**
 * WebBook SaaS — Reader IndexedDB Sync Routes
 *
 * Every reader (authenticated or anonymous) can push/pull their local IndexedDB
 * data to the server so it survives device changes and is available for author analytics.
 *
 *  POST   /books/{bookId}/sync            → full push  (all stores in one request)
 *  GET    /books/{bookId}/sync            → full pull  (server sends all stores back)
 *  PUT    /books/{bookId}/sync/kv         → upsert kv  pairs
 *  POST   /books/{bookId}/sync/anns       → upsert annotations
 *  POST   /books/{bookId}/sync/replies    → upsert annotation replies
 *  POST   /books/{bookId}/sync/activity   → push activity rows
 *  POST   /books/{bookId}/sync/scrollpos  → push scroll-position rows
 *  GET    /books/{bookId}/sync/mdcache    → pull all markdown content (for offline cache)
 *
 * Auth:
 *   • Authenticated users  → reader_id = user id
 *   • Anonymous readers    → pass X-WB-Reader header with a client-generated UUID
 *     (the server accepts any non-empty string as anonymous reader_id)
 *   • A valid book api_key (X-WB-Book header) bypasses bearer auth for public books.
 */

require_once __DIR__ . '/../lib/MainDB.php';
require_once __DIR__ . '/../lib/BookDB.php';

function route_sync(string $method, array $params): void
{
    $bookId = $params['bookId'] ?? '';
    $store  = $params['store']  ?? null;

    // Resolve reader identity (auth or anonymous)
    $readerId = _sync_reader_id($bookId);

    match (true) {
        $method === 'POST' && !$store       => sync_push_full($bookId, $readerId),
        $method === 'GET'  && !$store       => sync_pull_full($bookId, $readerId),
        $method === 'PUT'  && $store === 'kv'         => sync_kv($bookId, $readerId),
        $method === 'POST' && $store === 'anns'       => sync_anns($bookId, $readerId),
        $method === 'POST' && $store === 'replies'    => sync_replies($bookId, $readerId),
        $method === 'POST' && $store === 'activity'   => sync_activity($bookId, $readerId),
        $method === 'POST' && $store === 'scrollpos'  => sync_scrollpos($bookId, $readerId),
        $method === 'GET'  && $store === 'mdcache'    => sync_mdcache_pull($bookId),
        default => wb_error('Not found', 404),
    };
}

// ══════════════════════════════════════════════════════
//  FULL PUSH / PULL
// ══════════════════════════════════════════════════════

/** POST /books/{bookId}/sync — push all stores at once */
function sync_push_full(string $bookId, string $readerId): void
{
    $b = wb_body();

    $results = [];

    if (!empty($b['kv']))       $results['kv']        = _upsert_kv($bookId, $readerId, $b['kv']);
    if (!empty($b['anns']))     $results['anns']       = _upsert_anns($bookId, $readerId, $b['anns']);
    if (!empty($b['replies']))  $results['replies']    = _upsert_replies($bookId, $readerId, $b['replies']);
    if (!empty($b['activity'])) $results['activity']   = _insert_activity($bookId, $readerId, $b['activity']);
    if (!empty($b['scrollpos']))$results['scrollpos']  = _insert_scrollpos($bookId, $readerId, $b['scrollpos']);

    // Update reader_stats
    _update_reader_stats($bookId, $readerId, $b);

    wb_json(['ok' => true, 'results' => $results, 'reader_id' => $readerId]);
}

/** GET /books/{bookId}/sync — pull all reader data back to client */
function sync_pull_full(string $bookId, string $readerId): void
{
    $bdb = wb_init_book_db($bookId);

    $kv         = wb_select($bdb, 'SELECT k, v, updated_at FROM reader_kv
                                    WHERE book_id = ? AND reader_id = ?',
                                  [$bookId, $readerId]);
    $anns       = wb_select($bdb, 'SELECT * FROM reader_anns
                                    WHERE book_id = ? AND reader_id = ? AND deleted = 0',
                                  [$bookId, $readerId]);
    $replies    = wb_select($bdb, 'SELECT * FROM reader_replies
                                    WHERE book_id = ? AND reader_id = ? AND deleted = 0',
                                  [$bookId, $readerId]);
    $activity   = wb_select($bdb, 'SELECT * FROM reader_activity
                                    WHERE book_id = ? AND reader_id = ?
                                    ORDER BY ts DESC LIMIT 250',
                                  [$bookId, $readerId]);
    $scrollpos  = wb_select($bdb, 'SELECT * FROM reader_scrollpos
                                    WHERE book_id = ? AND reader_id = ?
                                    ORDER BY start_ts DESC LIMIT 500',
                                  [$bookId, $readerId]);

    wb_json([
        'reader_id' => $readerId,
        'kv'        => $kv,
        'anns'      => $anns,
        'replies'   => $replies,
        'activity'  => $activity,
        'scrollpos' => $scrollpos,
    ]);
}

// ══════════════════════════════════════════════════════
//  INDIVIDUAL STORE ENDPOINTS
// ══════════════════════════════════════════════════════

/** PUT /books/{bookId}/sync/kv */
function sync_kv(string $bookId, string $readerId): void
{
    $b  = wb_body();
    $kv = $b['kv'] ?? $b;    // accept both {kv:[…]} and direct array
    if (!is_array($kv)) wb_error('kv must be an array', 422);
    $n = _upsert_kv($bookId, $readerId, $kv);
    wb_json(['ok' => true, 'upserted' => $n]);
}

/** POST /books/{bookId}/sync/anns */
function sync_anns(string $bookId, string $readerId): void
{
    $b    = wb_body();
    $rows = $b['anns'] ?? $b;
    if (!is_array($rows)) wb_error('anns must be an array', 422);
    $n = _upsert_anns($bookId, $readerId, $rows);
    wb_json(['ok' => true, 'upserted' => $n]);
}

/** POST /books/{bookId}/sync/replies */
function sync_replies(string $bookId, string $readerId): void
{
    $b    = wb_body();
    $rows = $b['replies'] ?? $b;
    if (!is_array($rows)) wb_error('replies must be an array', 422);
    $n = _upsert_replies($bookId, $readerId, $rows);
    wb_json(['ok' => true, 'upserted' => $n]);
}

/** POST /books/{bookId}/sync/activity */
function sync_activity(string $bookId, string $readerId): void
{
    $b    = wb_body();
    $rows = $b['activity'] ?? $b;
    if (!is_array($rows)) wb_error('activity must be an array', 422);
    $n = _insert_activity($bookId, $readerId, $rows);
    wb_json(['ok' => true, 'inserted' => $n]);
}

/** POST /books/{bookId}/sync/scrollpos */
function sync_scrollpos(string $bookId, string $readerId): void
{
    $b    = wb_body();
    $rows = $b['scrollpos'] ?? $b;
    if (!is_array($rows)) wb_error('scrollpos must be an array', 422);
    $n = _insert_scrollpos($bookId, $readerId, $rows);
    wb_json(['ok' => true, 'inserted' => $n]);
}

/** GET /books/{bookId}/sync/mdcache — public-accessible with valid api_key */
function sync_mdcache_pull(string $bookId): void
{
    $bdb  = wb_init_book_db($bookId);
    $rows = wb_select($bdb,
        'SELECT id, content, ts FROM reader_mdcache WHERE book_id = ?',
        [$bookId]);
    wb_json(['book_id' => $bookId, 'mdcache' => $rows, 'total' => count($rows)]);
}

// ══════════════════════════════════════════════════════
//  PRIVATE UPSERT HELPERS
// ══════════════════════════════════════════════════════

function _upsert_kv(string $bookId, string $readerId, array $rows): int
{
    $bdb = wb_init_book_db($bookId);
    $n   = 0;
    $now = wb_ms();
    foreach (array_slice($rows, 0, WB_MAX_SYNC_ROWS) as $item) {
        $k = wb_str($item['k'] ?? $item['key'] ?? '');
        $v = $item['v'] ?? $item['value'] ?? null;
        if (!$k) continue;
        $encoded = is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE);
        $ts      = (int)($item['updated_at'] ?? $now);

        wb_exec($bdb,
            'INSERT INTO reader_kv (id, book_id, reader_id, k, v, updated_at)
             VALUES (?,?,?,?,?,?)
             ON CONFLICT(book_id, reader_id, k)
             DO UPDATE SET v = excluded.v, updated_at = excluded.updated_at
                       WHERE excluded.updated_at >= reader_kv.updated_at',
            [wb_uuid(), $bookId, $readerId, $k, $encoded, $ts]
        );
        $n++;
    }
    return $n;
}

function _upsert_anns(string $bookId, string $readerId, array $rows): int
{
    $bdb = wb_init_book_db($bookId);
    $n   = 0;
    $now = wb_ms();
    foreach (array_slice($rows, 0, WB_MAX_SYNC_ROWS) as $item) {
        $id = wb_str($item['id'] ?? '');
        if (!$id) continue;
        wb_exec($bdb,
            'INSERT INTO reader_anns
                (id, book_id, reader_id, type, sel_text, note_text,
                 slide_idx, hl_id, created_at, updated_at, deleted)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)
             ON CONFLICT(id) DO UPDATE SET
                type      = excluded.type,
                sel_text  = excluded.sel_text,
                note_text = excluded.note_text,
                slide_idx = excluded.slide_idx,
                hl_id     = excluded.hl_id,
                updated_at= excluded.updated_at,
                deleted   = excluded.deleted
             WHERE excluded.updated_at >= reader_anns.updated_at',
            [
                $id, $bookId, $readerId,
                wb_str($item['type'] ?? 'note'),
                wb_str($item['sel_text'] ?? '', 1000),
                wb_str($item['note_text'] ?? '', 2000),
                (int)($item['slide_idx'] ?? 0),
                wb_str($item['hl_id'] ?? ''),
                (int)($item['created_at'] ?? $now),
                (int)($item['updated_at'] ?? $now),
                (int)($item['deleted'] ?? 0),
            ]
        );
        $n++;
    }
    return $n;
}

function _upsert_replies(string $bookId, string $readerId, array $rows): int
{
    $bdb = wb_init_book_db($bookId);
    $n   = 0;
    $now = wb_ms();
    foreach (array_slice($rows, 0, WB_MAX_SYNC_ROWS) as $item) {
        $id = wb_str($item['id'] ?? '');
        if (!$id) continue;
        wb_exec($bdb,
            'INSERT INTO reader_replies
                (id, book_id, reader_id, ann_id, text, author, ts, deleted)
             VALUES (?,?,?,?,?,?,?,?)
             ON CONFLICT(id) DO UPDATE SET
                text    = excluded.text,
                deleted = excluded.deleted',
            [
                $id, $bookId, $readerId,
                wb_str($item['ann_id'] ?? ''),
                wb_str($item['text'] ?? '', 2000),
                wb_str($item['author'] ?? 'أنا', 80),
                (int)($item['ts'] ?? $now),
                (int)($item['deleted'] ?? 0),
            ]
        );
        $n++;
    }
    return $n;
}

function _insert_activity(string $bookId, string $readerId, array $rows): int
{
    $bdb = wb_init_book_db($bookId);
    $n   = 0;
    $now = wb_ms();

    // Trim server activity to 250 rows per reader
    $count = (int)(wb_select_one($bdb,
        'SELECT COUNT(*) AS c FROM reader_activity WHERE book_id = ? AND reader_id = ?',
        [$bookId, $readerId])['c'] ?? 0);

    if ($count > 250) {
        $bdb->exec("DELETE FROM reader_activity WHERE book_id = '$bookId'
                    AND reader_id = '$readerId'
                    AND id NOT IN (
                        SELECT id FROM reader_activity
                        WHERE book_id = '$bookId' AND reader_id = '$readerId'
                        ORDER BY ts DESC LIMIT 250)");
    }

    foreach (array_slice($rows, 0, 100) as $item) {
        $id = wb_str($item['id'] ?? '');
        if (!$id) $id = wb_uuid();
        wb_exec($bdb,
            'INSERT OR IGNORE INTO reader_activity
                (id, book_id, reader_id, type, label, meta, ts)
             VALUES (?,?,?,?,?,?,?)',
            [
                $id, $bookId, $readerId,
                wb_str($item['type'] ?? 'event'),
                wb_str($item['label'] ?? '', 200),
                is_string($item['meta'] ?? null)
                    ? $item['meta']
                    : json_encode($item['meta'] ?? [], JSON_UNESCAPED_UNICODE),
                (int)($item['ts'] ?? $now),
            ]
        );
        $n++;
    }
    return $n;
}

function _insert_scrollpos(string $bookId, string $readerId, array $rows): int
{
    $bdb = wb_init_book_db($bookId);
    $n   = 0;
    $now = wb_ms();

    // Trim to 500 per reader
    $bdb->exec("DELETE FROM reader_scrollpos
                WHERE book_id = '$bookId' AND reader_id = '$readerId'
                  AND id NOT IN (
                      SELECT id FROM reader_scrollpos
                      WHERE book_id = '$bookId' AND reader_id = '$readerId'
                      ORDER BY start_ts DESC LIMIT 500)");

    foreach (array_slice($rows, 0, 100) as $item) {
        $id = wb_str($item['id'] ?? '');
        if (!$id) $id = wb_uuid();
        wb_exec($bdb,
            'INSERT OR IGNORE INTO reader_scrollpos
                (id, book_id, reader_id, slide_index, slide_title, start_ts, end_ts, duration_ms)
             VALUES (?,?,?,?,?,?,?,?)',
            [
                $id, $bookId, $readerId,
                (int)($item['slide_index'] ?? 0),
                wb_str($item['slide_title'] ?? '', 200),
                (int)($item['start_ts'] ?? $now),
                isset($item['end_ts'])      ? (int)$item['end_ts']      : null,
                isset($item['duration_ms']) ? (int)$item['duration_ms'] : null,
            ]
        );
        $n++;
    }
    return $n;
}

/** Update or create the reader_stats summary row. */
function _update_reader_stats(string $bookId, string $readerId, array $b): void
{
    $bdb = wb_init_book_db($bookId);
    $now = wb_ms();

    // Extract hints from kv payload
    $kvMap = [];
    foreach ($b['kv'] ?? [] as $item) {
        $kvMap[$item['k'] ?? ''] = $item['v'] ?? null;
    }
    $sessions   = 0;
    $slidesRead = 0;
    $totalMin   = 0;
    $lastSlide  = 0;

    foreach ($kvMap as $k => $v) {
        $decoded = is_string($v) ? json_decode($v, true) : $v;
        if ($k === 'sessions')   $sessions   = (int)($decoded ?? 0);
        if ($k === 'slidesRead') $slidesRead = (int)($decoded ?? 0);
        if ($k === 'totalMin')   $totalMin   = (int)($decoded ?? 0);
        if ($k === 'lastSlide')  $lastSlide  = (int)($decoded ?? 0);
    }

    $exists = wb_select_one($bdb,
        'SELECT id FROM reader_stats WHERE book_id = ? AND reader_id = ?',
        [$bookId, $readerId]);

    if ($exists) {
        wb_exec($bdb,
            'UPDATE reader_stats SET
                sessions    = MAX(sessions, ?),
                slides_read = MAX(slides_read, ?),
                total_min   = MAX(total_min, ?),
                last_slide  = ?,
                last_seen   = ?
             WHERE book_id = ? AND reader_id = ?',
            [$sessions, $slidesRead, $totalMin, $lastSlide, $now, $bookId, $readerId]
        );
    } else {
        wb_exec($bdb,
            'INSERT INTO reader_stats
                (id, book_id, reader_id, sessions, slides_read, total_min, last_slide, last_seen, first_seen)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [wb_uuid(), $bookId, $readerId, $sessions, $slidesRead, $totalMin, $lastSlide, $now, $now]
        );
    }

    // Update daily analytics
    $date = date('Y-m-d');
    $dayId = hash('sha256', $bookId . $date);
    $existing = wb_select_one($bdb,
        'SELECT id FROM book_analytics WHERE book_id = ? AND date = ?',
        [$bookId, $date]);
    if ($existing) {
        wb_exec($bdb,
            'UPDATE book_analytics SET
                unique_readers = unique_readers + 1,
                total_sessions = total_sessions + 1,
                total_slides   = total_slides + ?
             WHERE book_id = ? AND date = ?',
            [$slidesRead, $bookId, $date]
        );
    } else {
        wb_exec($bdb,
            'INSERT INTO book_analytics
                (id, book_id, date, unique_readers, total_sessions, total_slides, avg_duration_ms)
             VALUES (?,?,?,1,1,?,0)',
            [$dayId, $bookId, $date, $slidesRead]
        );
    }
}

// ══════════════════════════════════════════════════════
//  READER IDENTITY
// ══════════════════════════════════════════════════════

/**
 * Resolve reader_id:
 *   1. Valid Bearer token → user UUID
 *   2. Valid book api_key (X-WB-Book header) → accept anonymous reader
 *   3. X-WB-Reader header (client UUID) → anonymous OK
 *   4. Fallback → 401
 */
function _sync_reader_id(string $bookId): string
{
    // 1. Authenticated user
    $tok = wb_bearer();
    if ($tok) {
        $p = wb_token_verify($tok);
        if ($p && !empty($p['uid'])) return $p['uid'];
    }

    // 2. Book api_key
    $apiKey = $_SERVER['HTTP_X_WB_BOOK'] ?? $_GET['api_key'] ?? '';
    if ($apiKey) {
        $db   = wb_init_main_db();
        $book = wb_select_one($db,
            'SELECT id FROM books WHERE id = ? AND api_key = ?',
            [$bookId, $apiKey]);
        if ($book) {
            $anon = wb_str($_SERVER['HTTP_X_WB_READER'] ?? $_GET['reader_id'] ?? '');
            if ($anon) return 'anon:' . $anon;
            return 'anon:' . ($tok ?? 'guest');
        }
    }

    // 3. Anonymous with reader header (for public books)
    $db   = wb_init_main_db();
    $book = wb_select_one($db,
        "SELECT visibility FROM books WHERE id = ?", [$bookId]);
    if ($book && in_array($book['visibility'], ['public','unlisted'])) {
        $anon = wb_str($_SERVER['HTTP_X_WB_READER'] ?? $_GET['reader_id'] ?? '');
        if ($anon) return 'anon:' . $anon;
    }

    wb_error('Authentication required', 401);
}
