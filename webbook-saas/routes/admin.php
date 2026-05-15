<?php
/**
 * WebBook SaaS — Admin Routes
 *
 *  GET    /admin/stats                  → platform-wide stats
 *  GET    /admin/users                  → list all users (paginated)
 *  GET    /admin/users/{id}             → get user
 *  PATCH  /admin/users/{id}             → update user (role, plan, is_active)
 *  DELETE /admin/users/{id}             → delete user
 *  GET    /admin/books                  → list all books
 *  DELETE /admin/books/{id}             → hard-delete a book
 *  POST   /admin/users/{id}/impersonate → get token as any user (super-admin)
 */

require_once __DIR__ . '/../lib/MainDB.php';
require_once __DIR__ . '/../lib/BookDB.php';

function route_admin(string $method, array $seg): void
{
    // All admin routes require admin role
    wb_require_admin();

    $resource = $seg[0] ?? '';
    $id       = $seg[1] ?? null;
    $action   = $seg[2] ?? null;

    match (true) {
        $method === 'GET'    && $resource === 'stats'                            => admin_stats(),
        $method === 'GET'    && $resource === 'users' && !$id                   => admin_users_list(),
        $method === 'GET'    && $resource === 'users' && $id                    => admin_user_get($id),
        $method === 'PATCH'  && $resource === 'users' && $id && !$action        => admin_user_update($id),
        $method === 'DELETE' && $resource === 'users' && $id                    => admin_user_delete($id),
        $method === 'GET'    && $resource === 'books' && !$id                   => admin_books_list(),
        $method === 'DELETE' && $resource === 'books' && $id                    => admin_book_delete($id),
        $method === 'POST'   && $resource === 'users' && $id && $action === 'impersonate' => admin_impersonate($id),
        default => wb_error('Not found', 404),
    };
}

// ── GET /admin/stats ───────────────────────────────────
function admin_stats(): void
{
    $db   = wb_init_main_db();
    $stats = wb_select($db, 'SELECT key, value FROM platform_stats');
    $statsMap = array_column($stats, 'value', 'key');

    // Recent registrations (last 7 days)
    $since = wb_ms() - (7 * 24 * 3600 * 1000);
    $recent = (int)(wb_select_one($db,
        'SELECT COUNT(*) AS n FROM users WHERE created_at > ?', [$since])['n'] ?? 0);

    // Plan breakdown
    $plans = wb_select($db,
        'SELECT plan, COUNT(*) AS n FROM users GROUP BY plan');

    // Role breakdown
    $roles = wb_select($db,
        'SELECT role, COUNT(*) AS n FROM users GROUP BY role');

    wb_json([
        'platform'      => $statsMap,
        'new_users_7d'  => $recent,
        'plans'         => array_column($plans, 'n', 'plan'),
        'roles'         => array_column($roles, 'n', 'role'),
        'version'       => WB_VERSION,
    ]);
}

// ── GET /admin/users ───────────────────────────────────
function admin_users_list(): void
{
    $db     = wb_init_main_db();
    $page   = max(1, (int)($_GET['page']   ?? 1));
    $limit  = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $search = $_GET['search'] ?? '';
    $role   = $_GET['role']   ?? '';
    $plan   = $_GET['plan']   ?? '';
    $offset = ($page - 1) * $limit;

    $where  = '1=1';
    $params = [];

    if ($search) {
        $where   .= ' AND (email LIKE ? OR username LIKE ? OR display_name LIKE ?)';
        $like     = '%' . $search . '%';
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
    if ($role && in_array($role, ['reader','author','admin'])) {
        $where .= ' AND role = ?'; $params[] = $role;
    }
    if ($plan && in_array($plan, ['free','pro','enterprise'])) {
        $where .= ' AND plan = ?'; $params[] = $plan;
    }

    $total = (int)(wb_select_one($db,
        "SELECT COUNT(*) AS n FROM users WHERE $where", $params)['n'] ?? 0);

    $rows = wb_select($db,
        "SELECT id, email, username, display_name, role, plan,
                avatar_url, created_at, last_login, is_active, is_verified
         FROM users WHERE $where
         ORDER BY created_at DESC LIMIT ? OFFSET ?",
        array_merge($params, [$limit, $offset])
    );

    // Attach book count per user
    foreach ($rows as &$row) {
        $bc = wb_select_one($db,
            'SELECT COUNT(*) AS n FROM books WHERE owner_id = ?', [$row['id']]);
        $row['book_count'] = (int)($bc['n'] ?? 0);
    }
    unset($row);

    wb_json([
        'users' => $rows,
        'total' => $total,
        'page'  => $page,
        'limit' => $limit,
        'pages' => (int)ceil($total / $limit),
    ]);
}

// ── GET /admin/users/{id} ──────────────────────────────
function admin_user_get(string $id): void
{
    $db   = wb_init_main_db();
    $user = wb_select_one($db,
        'SELECT id, email, username, display_name, role, plan,
                avatar_url, bio, website, created_at, updated_at,
                last_login, is_active, is_verified
         FROM users WHERE id = ?', [$id]);
    if (!$user) wb_error('User not found', 404);

    $books = wb_select($db,
        'SELECT id, title, slug, status, total_slides, total_words, created_at
         FROM books WHERE owner_id = ? ORDER BY updated_at DESC', [$id]);

    $user['books'] = $books;
    wb_json($user);
}

// ── PATCH /admin/users/{id} ────────────────────────────
function admin_user_update(string $id): void
{
    $db   = wb_init_main_db();
    $user = wb_select_one($db, 'SELECT id FROM users WHERE id = ?', [$id]);
    if (!$user) wb_error('User not found', 404);

    $b      = wb_body();
    $fields = [];
    $params = [];

    if (isset($b['role']) && in_array($b['role'], ['reader','author','admin'])) {
        $fields[] = 'role = ?'; $params[] = $b['role'];
    }
    if (isset($b['plan']) && in_array($b['plan'], ['free','pro','enterprise'])) {
        $fields[] = 'plan = ?'; $params[] = $b['plan'];
    }
    if (isset($b['is_active'])) {
        $fields[] = 'is_active = ?'; $params[] = $b['is_active'] ? 1 : 0;
    }
    if (isset($b['is_verified'])) {
        $fields[] = 'is_verified = ?'; $params[] = $b['is_verified'] ? 1 : 0;
    }
    if (empty($fields)) wb_error('No valid fields provided', 422);

    $fields[] = 'updated_at = ?'; $params[] = wb_ms();
    $params[] = $id;

    wb_exec($db, 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);

    $user = wb_select_one($db,
        'SELECT id, email, username, display_name, role, plan,
                is_active, is_verified, updated_at FROM users WHERE id = ?', [$id]);
    wb_json($user);
}

// ── DELETE /admin/users/{id} ───────────────────────────
function admin_user_delete(string $id): void
{
    $db   = wb_init_main_db();
    $user = wb_select_one($db, 'SELECT id FROM users WHERE id = ?', [$id]);
    if (!$user) wb_error('User not found', 404);

    // Hard-delete all books and their SQLite files
    $books = wb_select($db, 'SELECT id FROM books WHERE owner_id = ?', [$id]);
    foreach ($books as $book) {
        $safePath = WB_DB_BOOKS . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $book['id']) . '.sqlite';
        if (is_file($safePath)) @unlink($safePath);
    }

    wb_exec($db, 'DELETE FROM users WHERE id = ?', [$id]);
    http_response_code(204);
    exit;
}

// ── GET /admin/books ───────────────────────────────────
function admin_books_list(): void
{
    $db     = wb_init_main_db();
    $page   = max(1, (int)($_GET['page']   ?? 1));
    $limit  = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $status = $_GET['status'] ?? '';
    $offset = ($page - 1) * $limit;

    $where  = '1=1';
    $params = [];
    if ($status && in_array($status, ['draft','published','archived'])) {
        $where .= ' AND b.status = ?'; $params[] = $status;
    }

    $total = (int)(wb_select_one($db,
        "SELECT COUNT(*) AS n FROM books b WHERE $where", $params)['n'] ?? 0);

    $rows = wb_select($db,
        "SELECT b.id, b.title, b.slug, b.status, b.visibility,
                b.total_slides, b.total_words, b.created_at, b.published_at,
                u.username AS owner_username, u.email AS owner_email
         FROM books b
         JOIN users u ON u.id = b.owner_id
         WHERE $where
         ORDER BY b.updated_at DESC LIMIT ? OFFSET ?",
        array_merge($params, [$limit, $offset])
    );

    wb_json([
        'books' => $rows,
        'total' => $total,
        'page'  => $page,
        'limit' => $limit,
    ]);
}

// ── DELETE /admin/books/{id} ───────────────────────────
function admin_book_delete(string $id): void
{
    $db   = wb_init_main_db();
    $book = wb_select_one($db, 'SELECT id FROM books WHERE id = ?', [$id]);
    if (!$book) wb_error('Book not found', 404);

    $safePath = WB_DB_BOOKS . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $id) . '.sqlite';
    if (is_file($safePath)) @unlink($safePath);

    wb_exec($db, 'DELETE FROM books WHERE id = ?', [$id]);
    http_response_code(204);
    exit;
}

// ── POST /admin/users/{id}/impersonate ────────────────
function admin_impersonate(string $id): void
{
    $db   = wb_init_main_db();
    $user = wb_select_one($db, 'SELECT id, is_active FROM users WHERE id = ?', [$id]);
    if (!$user)           wb_error('User not found', 404);
    if (!$user['is_active']) wb_error('Cannot impersonate inactive user', 403);

    // Short-lived token (1 hour) with impersonation flag
    $payload = ['uid' => $user['id'], 'impersonated' => true, 'exp' => time() + 3600];
    $token   = wb_token_create($payload);

    wb_json([
        'token'         => $token,
        'user_id'       => $user['id'],
        'expires_in'    => 3600,
        'impersonated'  => true,
    ]);
}
