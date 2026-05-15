<?php
/**
 * WebBook SaaS — Author Statistics Routes
 *
 *  GET  /auth/me/stats          → aggregate stats across all books
 *  GET  /auth/me/stats/books    → per-book breakdown
 *  GET  /auth/me/stats/readers  → total unique readers across all books
 *  GET  /auth/me/stats/activity → recent activity across all books
 */

require_once __DIR__ . '/../lib/MainDB.php';
require_once __DIR__ . '/../lib/BookDB.php';

function route_stats(string $method, array $seg): void
{
    $sub = $seg[0] ?? '';

    match (true) {
        $method === 'GET' && $sub === ''        => stats_overview(),
        $method === 'GET' && $sub === 'books'   => stats_books(),
        $method === 'GET' && $sub === 'readers' => stats_readers(),
        $method === 'GET' && $sub === 'activity'=> stats_activity(),
        default => wb_error('Not found', 404),
    };
}

// ── GET /auth/me/stats ─────────────────────────────────
function stats_overview(): void
{
    $auth   = wb_require_auth();
    $db     = wb_init_main_db();
    $userId = $auth['uid'];

    // Fetch all books for this user
    $books = wb_select($db,
        'SELECT id, title, slug, status, total_slides, total_words,
                created_at, published_at, updated_at
         FROM books WHERE owner_id = ? ORDER BY updated_at DESC',
        [$userId]
    );

    $overview = [
        'total_books'       => count($books),
        'published_books'   => 0,
        'draft_books'       => 0,
        'archived_books'    => 0,
        'total_slides'      => 0,
        'total_words'       => 0,
        'total_readers'     => 0,
        'total_sessions'    => 0,
        'total_slides_read' => 0,
        'total_minutes'     => 0,
        'plan'              => '',
        'role'              => '',
    ];

    // User plan + role
    $user = wb_select_one($db, 'SELECT plan, role FROM users WHERE id = ?', [$userId]);
    $overview['plan'] = $user['plan'] ?? 'free';
    $overview['role'] = $user['role'] ?? 'reader';
    $overview['plan_limits'] = WB_PLAN_LIMITS[$overview['plan']] ?? WB_PLAN_LIMITS['free'];
    $overview['books_used']  = count($books);
    $overview['books_limit'] = wb_plan_limit($userId, 'books');

    foreach ($books as $book) {
        match ($book['status']) {
            'published' => $overview['published_books']++,
            'draft'     => $overview['draft_books']++,
            'archived'  => $overview['archived_books']++,
            default     => null,
        };
        $overview['total_slides'] += (int)$book['total_slides'];
        $overview['total_words']  += (int)$book['total_words'];

        // Pull from per-book DB
        try {
            $bdb = wb_init_book_db($book['id']);
            $agg = wb_select_one($bdb,
                'SELECT COUNT(DISTINCT reader_id) AS readers,
                        SUM(sessions)    AS sessions,
                        SUM(slides_read) AS slides_read,
                        SUM(total_min)   AS total_min
                 FROM reader_stats WHERE book_id = ?',
                [$book['id']]
            );
            $overview['total_readers']     += (int)($agg['readers']     ?? 0);
            $overview['total_sessions']    += (int)($agg['sessions']    ?? 0);
            $overview['total_slides_read'] += (int)($agg['slides_read'] ?? 0);
            $overview['total_minutes']     += (int)($agg['total_min']   ?? 0);
        } catch (\Throwable) {}
    }

    // Recent 7-day trend (aggregate all book analytics)
    $trend = _aggregate_trend($books, 7);

    wb_json([
        'overview' => $overview,
        'trend'    => $trend,
    ]);
}

// ── GET /auth/me/stats/books ───────────────────────────
function stats_books(): void
{
    $auth   = wb_require_auth();
    $db     = wb_init_main_db();
    $books  = wb_select($db,
        'SELECT id, title, slug, status, cover_url, total_slides, total_words,
                created_at, published_at, updated_at
         FROM books WHERE owner_id = ? ORDER BY updated_at DESC',
        [$auth['uid']]
    );

    $result = [];
    foreach ($books as $book) {
        $row = $book;
        try {
            $bdb = wb_init_book_db($book['id']);
            $agg = wb_select_one($bdb,
                'SELECT COUNT(DISTINCT reader_id) AS readers,
                        SUM(sessions)    AS sessions,
                        SUM(slides_read) AS slides,
                        SUM(total_min)   AS minutes,
                        MAX(last_seen)   AS last_reader
                 FROM reader_stats WHERE book_id = ?',
                [$book['id']]
            );
            $row['stats'] = [
                'unique_readers' => (int)($agg['readers'] ?? 0),
                'total_sessions' => (int)($agg['sessions'] ?? 0),
                'slides_read'    => (int)($agg['slides']   ?? 0),
                'total_minutes'  => (int)($agg['minutes']  ?? 0),
                'last_reader_at' => $agg['last_reader'] ?? null,
            ];
            // Latest 7-day analytics
            $row['trend'] = _aggregate_trend([$book], 7);
            // Chapters count
            $cc = wb_select_one($bdb,
                'SELECT COUNT(*) AS n FROM chapters WHERE book_id = ?', [$book['id']]);
            $row['chapter_count'] = (int)($cc['n'] ?? 0);
        } catch (\Throwable) {
            $row['stats'] = null;
            $row['trend'] = [];
            $row['chapter_count'] = 0;
        }
        $result[] = $row;
    }

    wb_json(['books' => $result, 'total' => count($result)]);
}

// ── GET /auth/me/stats/readers ─────────────────────────
function stats_readers(): void
{
    $auth  = wb_require_auth();
    $db    = wb_init_main_db();
    $books = wb_select($db,
        'SELECT id, title FROM books WHERE owner_id = ?', [$auth['uid']]);

    $allReaders = [];
    foreach ($books as $book) {
        try {
            $bdb  = wb_init_book_db($book['id']);
            $rows = wb_select($bdb,
                'SELECT reader_id, sessions, slides_read, total_min,
                        last_slide, last_seen, first_seen
                 FROM reader_stats WHERE book_id = ?
                 ORDER BY last_seen DESC LIMIT 50',
                [$book['id']]
            );
            foreach ($rows as $r) {
                $r['book_id']    = $book['id'];
                $r['book_title'] = $book['title'];
                $allReaders[]    = $r;
            }
        } catch (\Throwable) {}
    }

    // Sort by last_seen desc
    usort($allReaders, fn($a, $b) => ($b['last_seen'] ?? 0) <=> ($a['last_seen'] ?? 0));

    wb_json([
        'readers' => array_slice($allReaders, 0, 100),
        'total'   => count($allReaders),
    ]);
}

// ── GET /auth/me/stats/activity ────────────────────────
function stats_activity(): void
{
    $auth  = wb_require_auth();
    $db    = wb_init_main_db();
    $books = wb_select($db,
        'SELECT id, title FROM books WHERE owner_id = ?', [$auth['uid']]);

    $days = min(30, max(1, (int)($_GET['days'] ?? 7)));
    $from = date('Y-m-d', strtotime("-{$days} days"));

    $result = [];
    foreach ($books as $book) {
        try {
            $bdb  = wb_init_book_db($book['id']);
            $rows = wb_select($bdb,
                "SELECT * FROM book_analytics
                 WHERE book_id = ? AND date >= ?
                 ORDER BY date ASC",
                [$book['id'], $from]
            );
            if ($rows) {
                $result[] = [
                    'book_id'   => $book['id'],
                    'title'     => $book['title'],
                    'analytics' => $rows,
                ];
            }
        } catch (\Throwable) {}
    }

    wb_json([
        'days'   => $days,
        'from'   => $from,
        'books'  => $result,
    ]);
}

// ── Private helpers ────────────────────────────────────

function _aggregate_trend(array $books, int $days): array
{
    $from    = date('Y-m-d', strtotime("-{$days} days"));
    $totals  = [];

    foreach ($books as $book) {
        try {
            $bdb  = wb_init_book_db($book['id']);
            $rows = wb_select($bdb,
                "SELECT date, unique_readers, total_sessions, total_slides
                 FROM book_analytics WHERE book_id = ? AND date >= ?",
                [$book['id'], $from]
            );
            foreach ($rows as $r) {
                $d = $r['date'];
                if (!isset($totals[$d])) {
                    $totals[$d] = ['date' => $d, 'readers' => 0, 'sessions' => 0, 'slides' => 0];
                }
                $totals[$d]['readers']  += (int)$r['unique_readers'];
                $totals[$d]['sessions'] += (int)$r['total_sessions'];
                $totals[$d]['slides']   += (int)$r['total_slides'];
            }
        } catch (\Throwable) {}
    }

    ksort($totals);
    return array_values($totals);
}
