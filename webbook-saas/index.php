<?php
/**
 * WebBook SaaS — API Router v2
 * Entry point for all requests to /webbook/api/
 *
 * Routes:
 *   POST   /auth/register
 *   POST   /auth/login
 *   POST   /auth/logout
 *   GET    /auth/me
 *   PUT    /auth/me
 *   GET    /auth/me/stats            ← NEW
 *   GET    /auth/me/stats/books      ← NEW
 *   GET    /auth/me/stats/readers    ← NEW
 *   GET    /auth/me/stats/activity   ← NEW
 *   GET    /auth/sessions
 *   DELETE /auth/sessions/{id}
 *   POST   /auth/verify/send         ← NEW
 *   GET    /auth/verify/{token}      ← NEW
 *   POST   /auth/reset/request       ← NEW
 *   POST   /auth/reset/{token}       ← NEW
 *
 *   GET    /books
 *   POST   /books
 *   GET    /books/{bookId}
 *   PUT    /books/{bookId}
 *   DELETE /books/{bookId}
 *   GET    /books/{bookId}/stats
 *   GET    /books/{bookId}/analytics
 *   GET    /books/{bookId}/chapters
 *   POST   /books/{bookId}/chapters
 *   GET    /books/{bookId}/chapters/{chapterId}
 *   PUT    /books/{bookId}/chapters/{chapterId}
 *   PATCH  /books/{bookId}/chapters/{chapterId}
 *   DELETE /books/{bookId}/chapters/{chapterId}
 *   GET    /books/{bookId}/chapters/{chapterId}/revisions
 *   POST   /books/{bookId}/chapters/reorder
 *   POST   /books/{bookId}/sync
 *   GET    /books/{bookId}/sync
 *   PUT    /books/{bookId}/sync/kv
 *   POST   /books/{bookId}/sync/anns
 *   POST   /books/{bookId}/sync/replies
 *   POST   /books/{bookId}/sync/activity
 *   POST   /books/{bookId}/sync/scrollpos
 *   GET    /books/{bookId}/sync/mdcache
 *
 *   POST   /upload/cover             ← NEW
 *   POST   /upload/avatar            ← NEW
 *   GET    /upload/list              ← NEW
 *   DELETE /upload/{filename}        ← NEW
 *
 *   GET    /webhooks                 ← NEW
 *   POST   /webhooks                 ← NEW
 *   GET    /webhooks/{id}            ← NEW
 *   PATCH  /webhooks/{id}            ← NEW
 *   DELETE /webhooks/{id}            ← NEW
 *   POST   /webhooks/{id}/test       ← NEW
 *   GET    /webhooks/{id}/logs       ← NEW
 *
 *   GET    /admin/stats              ← NEW
 *   GET    /admin/users              ← NEW
 *   GET    /admin/users/{id}         ← NEW
 *   PATCH  /admin/users/{id}         ← NEW
 *   DELETE /admin/users/{id}         ← NEW
 *   GET    /admin/books              ← NEW
 *   DELETE /admin/books/{id}         ← NEW
 *   POST   /admin/users/{id}/impersonate ← NEW
 *
 *   GET    /public/{bookId}/info
 *   GET    /public/{bookId}/chapters
 *   GET    /public/{bookId}/mdcache
 *
 *   GET    /                         health check
 */

require_once __DIR__ . '/bootstrap.php';

wb_rate_check();

// ── Parse path ─────────────────────────────────────────
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base   = '/webbook/api';
$path   = '/' . ltrim(substr($uri, strlen($base)), '/');
$method = strtoupper($_SERVER['REQUEST_METHOD']);
$path   = rtrim($path, '/') ?: '/';
$segments = array_values(array_filter(explode('/', $path)));

// ── Router ─────────────────────────────────────────────
try {

    // ── /auth/* ────────────────────────────────────────
    if (($segments[0] ?? '') === 'auth') {
        $authSeg = array_slice($segments, 1);

        // /auth/me/stats and sub-routes go to stats.php
        if (($authSeg[0] ?? '') === 'me' && ($authSeg[1] ?? '') === 'stats') {
            require_once __DIR__ . '/routes/stats.php';
            route_stats($method, array_slice($authSeg, 2));
            exit;
        }

        // /auth/verify/* and /auth/reset/*
        if (in_array($authSeg[0] ?? '', ['verify', 'reset'])) {
            require_once __DIR__ . '/routes/verify.php';
            route_verify($method, $authSeg);
            exit;
        }

        // All other auth routes
        require_once __DIR__ . '/routes/auth.php';
        route_auth($method, $authSeg);
        exit;
    }

    // ── /upload/* ──────────────────────────────────────
    if (($segments[0] ?? '') === 'upload') {
        require_once __DIR__ . '/routes/upload.php';
        route_upload($method, array_slice($segments, 1));
        exit;
    }

    // ── /webhooks/* ────────────────────────────────────
    if (($segments[0] ?? '') === 'webhooks') {
        require_once __DIR__ . '/routes/webhooks.php';
        route_webhooks($method, array_slice($segments, 1));
        exit;
    }

    // ── /admin/* ───────────────────────────────────────
    if (($segments[0] ?? '') === 'admin') {
        require_once __DIR__ . '/routes/admin.php';
        route_admin($method, array_slice($segments, 1));
        exit;
    }

    // ── /public/{bookId}/* ─────────────────────────────
    if (($segments[0] ?? '') === 'public') {
        require_once __DIR__ . '/routes/public.php';
        route_public($method, array_slice($segments, 1));
        exit;
    }

    // ── /books/* ───────────────────────────────────────
    if (($segments[0] ?? '') === 'books') {
        $bookId = $segments[1] ?? null;

        if (!$bookId) {
            require_once __DIR__ . '/routes/books.php';
            route_books($method, []);
            exit;
        }

        $sub = $segments[2] ?? null;

        if (!$sub) {
            require_once __DIR__ . '/routes/books.php';
            route_books($method, ['bookId' => $bookId]);
            exit;
        }

        if ($sub === 'stats' || $sub === 'analytics') {
            require_once __DIR__ . '/routes/books.php';
            route_books($method, ['bookId' => $bookId, 'action' => $sub]);
            exit;
        }

        if ($sub === 'chapters') {
            require_once __DIR__ . '/routes/chapters.php';
            $chapterId = $segments[3] ?? null;
            $chSub     = $segments[4] ?? null;
            route_chapters($method, [
                'bookId'    => $bookId,
                'chapterId' => $chapterId,
                'sub'       => $chSub,
            ]);
            exit;
        }

        if ($sub === 'sync') {
            require_once __DIR__ . '/routes/sync.php';
            $store = $segments[3] ?? null;
            route_sync($method, ['bookId' => $bookId, 'store' => $store]);
            exit;
        }

        wb_error('Not found', 404);
    }

    // ── / (health check) ──────────────────────────────
    if ($path === '/') {
        $db    = wb_init_main_db();
        $stats = wb_select($db, 'SELECT key, value FROM platform_stats');
        wb_json([
            'service'  => WB_APP_NAME,
            'version'  => WB_VERSION,
            'status'   => 'ok',
            'endpoint' => WB_API_BASE,
            'platform' => array_column($stats, 'value', 'key'),
            'ts'       => wb_ms(),
        ]);
    }

    wb_error('Not found', 404);

} catch (PDOException $e) {
    error_log('[WB-DB] ' . $e->getMessage());
    wb_error('Database error', 500);
} catch (Throwable $e) {
    error_log('[WB] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    wb_error('Internal server error', 500);
}
