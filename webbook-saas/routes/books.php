<?php
/**
 * WebBook SaaS — Books CRUD Routes
 *
 *  GET    /books                     → list owner's books
 *  POST   /books                     → create book
 *  GET    /books/{bookId}            → get book
 *  PUT    /books/{bookId}            → update book metadata
 *  DELETE /books/{bookId}            → delete book (soft via status=archived or hard)
 *  GET    /books/{bookId}/stats      → reading stats for this book
 *  GET    /books/{bookId}/analytics  → aggregated daily analytics
 */

require_once __DIR__ . '/../lib/MainDB.php';
require_once __DIR__ . '/../lib/BookDB.php';

function route_books(string $method, array $params): void
{
    $bookId = $params['bookId'] ?? null;
    $action = $params['action'] ?? null;

    match (true) {
        !$bookId && $method === 'GET'                        => books_list(),
        !$bookId && $method === 'POST'                       => books_create(),
        $bookId  && $method === 'GET'  && !$action           => books_get($bookId),
        $bookId  && $method === 'PUT'  && !$action           => books_update($bookId),
        $bookId  && $method === 'PATCH' && !$action          => books_update($bookId),
        $bookId  && $method === 'DELETE' && !$action         => books_delete($bookId),
        $bookId  && $action === 'stats'                      => books_stats($bookId),
        $bookId  && $action === 'analytics'                  => books_analytics($bookId),
        default => wb_error('Not found', 404),
    };
}

// ── GET /books ─────────────────────────────────────────
function books_list(): void
{
    $auth   = wb_require_auth();
    $db     = wb_init_main_db();

    $page   = max(1, (int)($_GET['page']   ?? 1));
    $limit  = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $status = $_GET['status'] ?? '';
    $offset = ($page - 1) * $limit;

    $where  = 'WHERE owner_id = ?';
    $args   = [$auth['uid']];

    if ($status && in_array($status, ['draft','published','archived'])) {
        $where .= ' AND status = ?';
        $args[] = $status;
    }

    $total = (int)(wb_select_one($db,
        "SELECT COUNT(*) AS n FROM books $where", $args)['n'] ?? 0);
    $rows  = wb_select($db,
        "SELECT * FROM books $where ORDER BY updated_at DESC LIMIT ? OFFSET ?",
        array_merge($args, [$limit, $offset])
    );

    wb_json([
        'books'  => $rows,
        'total'  => $total,
        'page'   => $page,
        'limit'  => $limit,
        'pages'  => (int)ceil($total / $limit),
    ]);
}

// ── POST /books ────────────────────────────────────────
function books_create(): void
{
    $auth = wb_require_auth();
    $b    = wb_body();
    $db   = wb_init_main_db();

    // Enforce plan limits
    $bc = (int)(wb_select_one($db,
        'SELECT COUNT(*) AS n FROM books WHERE owner_id = ?',
        [$auth['uid']])['n'] ?? 0);
    if ($bc >= WB_MAX_BOOKS)
        wb_error('Book limit reached (' . WB_MAX_BOOKS . ')', 403);

    // Required fields
    $title = wb_str($b['title'] ?? '');
    if (!$title) wb_error('title is required', 422);

    $slug = _slugify($b['slug'] ?? $title);
    if (!$slug) wb_error('Invalid slug', 422);

    // Unique slug per owner
    if (wb_select_one($db,
        'SELECT id FROM books WHERE owner_id = ? AND slug = ?',
        [$auth['uid'], $slug]))
        wb_error("Slug '$slug' already used by another book", 409);

    $id       = wb_uuid();
    $now      = wb_ms();
    $apiKey   = bin2hex(random_bytes(20));
    $visibility = in_array($b['visibility'] ?? '', ['private','unlisted','public'])
                    ? $b['visibility'] : 'private';
    $lang       = wb_str($b['language'] ?? 'ar', 10);
    $dir        = in_array($b['direction'] ?? '', ['rtl','ltr']) ? $b['direction'] : 'rtl';
    $theme      = wb_str($b['theme'] ?? 'dark-night', 50);

    $readerUrl  = 'https://2030b.com/webbook/' . strtolower($auth['uid']) . '/' . $slug;

    wb_exec($db,
        'INSERT INTO books
            (id, owner_id, slug, title, description, language, direction,
             cover_url, theme, status, visibility, reader_url, api_key,
             total_slides, total_words, created_at, updated_at, published_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,0,0,?,?,NULL)',
        [
            $id, $auth['uid'], $slug, $title,
            wb_str($b['description'] ?? '', 500),
            $lang, $dir,
            wb_str($b['cover_url'] ?? '', 255),
            $theme, 'draft', $visibility,
            $readerUrl, $apiKey,
            $now, $now,
        ]
    );

    // Initialise the per-book SQLite database
    wb_init_book_db($id);

    $book = wb_select_one($db, 'SELECT * FROM books WHERE id = ?', [$id]);
    wb_json($book, 201);
}

// ── GET /books/{bookId} ────────────────────────────────
function books_get(string $bookId): void
{
    $auth = wb_require_auth();
    $db   = wb_init_main_db();
    $book = wb_select_one($db, 'SELECT * FROM books WHERE id = ?', [$bookId]);
    if (!$book) wb_error('Book not found', 404);

    if (!wb_can_access_book($bookId, $auth['uid']))
        wb_error('Access denied', 403);

    wb_json($book);
}

// ── PUT /books/{bookId} ────────────────────────────────
function books_update(string $bookId): void
{
    $auth = wb_require_auth();
    $book = wb_require_book_owner($bookId, $auth['uid']);
    $b    = wb_body();
    $db   = wb_init_main_db();

    $fields = [];
    $params = [];

    $allowed = [
        'title'       => ['str', 255],
        'description' => ['str', 500],
        'language'    => ['str', 10],
        'direction'   => ['enum', ['rtl','ltr']],
        'cover_url'   => ['url', 255],
        'theme'       => ['str', 50],
        'status'      => ['enum', ['draft','published','archived']],
        'visibility'  => ['enum', ['private','unlisted','public']],
    ];

    foreach ($allowed as $key => [$type, $constraint]) {
        if (!array_key_exists($key, $b)) continue;
        $val = $b[$key];
        if ($type === 'str')  { $val = wb_str($val, $constraint); }
        if ($type === 'enum') { if (!in_array($val, $constraint)) wb_error("Invalid value for $key", 422); }
        if ($type === 'url')  {
            $val = wb_str($val, $constraint);
            if ($val && !filter_var($val, FILTER_VALIDATE_URL)) wb_error("Invalid URL for $key", 422);
        }
        // Slug change
        if ($key === 'title' && empty($b['slug'])) {
            $newSlug = _slugify($val);
            if ($newSlug !== $book['slug']) {
                $exists = wb_select_one($db,
                    'SELECT id FROM books WHERE owner_id = ? AND slug = ? AND id != ?',
                    [$auth['uid'], $newSlug, $bookId]);
                if ($exists) wb_error("Slug '$newSlug' already used", 409);
                $fields[] = 'slug = ?'; $params[] = $newSlug;
            }
        }
        $fields[] = "$key = ?";
        $params[] = $val;

        // Set published_at when publishing
        if ($key === 'status' && $val === 'published' && $book['status'] !== 'published') {
            $fields[] = 'published_at = ?';
            $params[] = wb_ms();
        }
    }

    if (empty($fields)) wb_error('No valid fields provided', 422);

    $fields[] = 'updated_at = ?';
    $params[] = wb_ms();
    $params[] = $bookId;

    wb_exec($db, 'UPDATE books SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);

    $book = wb_select_one($db, 'SELECT * FROM books WHERE id = ?', [$bookId]);
    wb_json($book);
}

// ── DELETE /books/{bookId} ─────────────────────────────
function books_delete(string $bookId): void
{
    $auth = wb_require_auth();
    wb_require_book_owner($bookId, $auth['uid']);

    $hard = ($_GET['hard'] ?? '0') === '1';
    $db   = wb_init_main_db();

    if ($hard) {
        // Remove SQLite file
        $safePath = WB_DB_BOOKS . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $bookId) . '.sqlite';
        if (is_file($safePath)) @unlink($safePath);
        wb_exec($db, 'DELETE FROM books WHERE id = ?', [$bookId]);
    } else {
        wb_exec($db,
            "UPDATE books SET status = 'archived', updated_at = ? WHERE id = ?",
            [wb_ms(), $bookId]);
    }

    http_response_code(204);
    exit;
}

// ── GET /books/{bookId}/stats ──────────────────────────
function books_stats(string $bookId): void
{
    $auth = wb_require_auth();
    if (!wb_can_access_book($bookId, $auth['uid']))
        wb_error('Access denied', 403);

    $bdb    = wb_init_book_db($bookId);
    $limit  = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = max(0, ((int)($_GET['page'] ?? 1) - 1) * $limit);

    $total  = (int)(wb_select_one($bdb,
        'SELECT COUNT(*) AS n FROM reader_stats WHERE book_id = ?',
        [$bookId])['n'] ?? 0);

    $rows   = wb_select($bdb,
        'SELECT * FROM reader_stats WHERE book_id = ? ORDER BY last_seen DESC LIMIT ? OFFSET ?',
        [$bookId, $limit, $offset]
    );

    $agg = wb_select_one($bdb,
        'SELECT COUNT(DISTINCT reader_id) AS unique_readers,
                SUM(sessions) AS total_sessions,
                SUM(slides_read) AS total_slides_read,
                SUM(total_min) AS total_minutes
         FROM reader_stats WHERE book_id = ?',
        [$bookId]
    );

    wb_json([
        'aggregate' => $agg,
        'readers'   => $rows,
        'total'     => $total,
    ]);
}

// ── GET /books/{bookId}/analytics ─────────────────────
function books_analytics(string $bookId): void
{
    $auth = wb_require_auth();
    if (!wb_can_access_book($bookId, $auth['uid']))
        wb_error('Access denied', 403);

    $bdb  = wb_init_book_db($bookId);
    $days = min(365, max(1, (int)($_GET['days'] ?? 30)));
    $from = date('Y-m-d', strtotime("-$days days"));

    $rows = wb_select($bdb,
        "SELECT * FROM book_analytics
         WHERE book_id = ? AND date >= ?
         ORDER BY date ASC",
        [$bookId, $from]
    );

    // Totals
    $totals = wb_select_one($bdb,
        "SELECT
            SUM(unique_readers) AS readers,
            SUM(total_sessions) AS sessions,
            SUM(total_slides)   AS slides,
            AVG(avg_duration_ms) AS avg_dur
         FROM book_analytics WHERE book_id = ? AND date >= ?",
        [$bookId, $from]
    );

    wb_json([
        'days'    => $days,
        'from'    => $from,
        'data'    => $rows,
        'totals'  => $totals,
    ]);
}

// ── Private helpers ────────────────────────────────────

/** Convert any string to a URL-safe slug. */
function _slugify(string $str): string
{
    $s = mb_strtolower(trim($str));
    $s = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $s);
    $s = preg_replace('/[\s]+/u', '-', $s);
    $s = preg_replace('/-+/', '-', $s);
    return trim($s, '-');
}
