<?php
/**
 * WebBook SaaS — Auth Routes
 *
 *  POST   /auth/register   → create account
 *  POST   /auth/login      → get token
 *  POST   /auth/logout     → revoke token
 *  GET    /auth/me         → current user profile
 *  PUT    /auth/me         → update profile
 *  GET    /auth/sessions   → list active sessions
 *  DELETE /auth/sessions/{id} → revoke specific session
 */

require_once __DIR__ . '/../lib/MainDB.php';

function route_auth(string $method, array $seg): void
{
    $action = $seg[0] ?? '';

    match (true) {
        $method === 'POST' && $action === 'register'          => auth_register(),
        $method === 'POST' && $action === 'login'             => auth_login(),
        $method === 'POST' && $action === 'logout'            => auth_logout(),
        $method === 'GET'  && $action === 'me'                => auth_me(),
        $method === 'PUT'  && $action === 'me'                => auth_me_update(),
        $method === 'GET'  && $action === 'sessions'          => auth_sessions_list(),
        $method === 'DELETE' && $action === 'sessions'        => auth_session_revoke($seg[1] ?? ''),
        default => wb_error('Not found', 404),
    };
}

// ── POST /auth/register ────────────────────────────────
function auth_register(): void
{
    $b = wb_body();

    $email    = wb_str($b['email'] ?? '');
    $username = wb_str($b['username'] ?? '');
    $password = $b['password'] ?? '';
    $display  = wb_str($b['display_name'] ?? $username, 80);

    // Validate
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        wb_error('Invalid email address', 422);
    if (!preg_match('/^[a-zA-Z0-9_-]{3,32}$/', $username))
        wb_error('Username must be 3-32 alphanumeric characters (letters, digits, _ -)', 422);
    if (strlen($password) < 8)
        wb_error('Password must be at least 8 characters', 422);

    $db  = wb_init_main_db();
    $now = wb_ms();

    // Uniqueness checks
    if (wb_select_one($db, 'SELECT id FROM users WHERE email = ?', [$email]))
        wb_error('Email already registered', 409);
    if (wb_select_one($db, 'SELECT id FROM users WHERE username = ?', [strtolower($username)]))
        wb_error('Username already taken', 409);

    $id   = wb_uuid();
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    // role: 'reader' by default; pass role=author to self-register as author
    $wantRole = wb_str($b['role'] ?? 'reader');
    $role     = in_array($wantRole, ['reader', 'author']) ? $wantRole : 'reader';

    wb_exec($db,
        'INSERT INTO users
            (id, email, username, display_name, password, role, plan,
             avatar_url, bio, website, created_at, updated_at, is_active, is_verified)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,0)',
        [$id, $email, strtolower($username), $display, $hash,
         $role, 'free', '', '', '', $now, $now]
    );

    // Create token + session
    $token   = _create_session($db, $id);
    $user    = wb_select_one($db, 'SELECT * FROM users WHERE id = ?', [$id]);

    wb_json([
        'token' => $token,
        'user'  => _safe_user($user),
    ], 201);
}

// ── POST /auth/login ───────────────────────────────────
function auth_login(): void
{
    $b        = wb_body();
    $login    = wb_str($b['email'] ?? $b['username'] ?? '');
    $password = $b['password'] ?? '';

    if (!$login || !$password) wb_error('Email/username and password required', 422);

    $db   = wb_init_main_db();
    $field = str_contains($login, '@') ? 'email' : 'username';
    $user  = wb_select_one($db,
        "SELECT * FROM users WHERE $field = ? AND is_active = 1",
        [strtolower($login)]
    );

    if (!$user || !password_verify($password, $user['password']))
        wb_error('Invalid credentials', 401);

    // Update last login
    $now = wb_ms();
    wb_exec($db, 'UPDATE users SET last_login = ?, updated_at = ? WHERE id = ?',
        [$now, $now, $user['id']]);

    $token = _create_session($db, $user['id']);
    $user  = wb_select_one($db, 'SELECT * FROM users WHERE id = ?', [$user['id']]);

    wb_json(['token' => $token, 'user' => _safe_user($user)]);
}

// ── POST /auth/logout ──────────────────────────────────
function auth_logout(): void
{
    $tok = wb_bearer();
    if (!$tok) { wb_json(['ok' => true]); }

    $hash = hash('sha256', $tok);
    $db   = wb_init_main_db();
    wb_exec($db, 'DELETE FROM sessions WHERE token_hash = ?', [$hash]);
    wb_json(['ok' => true]);
}

// ── GET /auth/me ───────────────────────────────────────
function auth_me(): void
{
    $auth = wb_require_auth();
    $db   = wb_init_main_db();
    $user = wb_select_one($db, 'SELECT * FROM users WHERE id = ? AND is_active = 1',
        [$auth['uid']]);
    if (!$user) wb_error('User not found', 404);

    // Attach book count
    $bc = wb_select_one($db,
        'SELECT COUNT(*) AS n FROM books WHERE owner_id = ?', [$auth['uid']]);
    $user['book_count'] = (int)($bc['n'] ?? 0);

    wb_json(_safe_user($user));
}

// ── PUT /auth/me ───────────────────────────────────────
function auth_me_update(): void
{
    $auth = wb_require_auth();
    $b    = wb_body();
    $db   = wb_init_main_db();

    $user = wb_select_one($db, 'SELECT * FROM users WHERE id = ?', [$auth['uid']]);
    if (!$user) wb_error('User not found', 404);

    $fields = [];
    $params = [];

    if (isset($b['display_name'])) {
        $v = wb_str($b['display_name'], 80);
        if (!$v) wb_error('display_name cannot be empty', 422);
        $fields[] = 'display_name = ?'; $params[] = $v;
    }
    if (isset($b['bio'])) {
        $fields[] = 'bio = ?'; $params[] = wb_str($b['bio'], 500);
    }
    if (isset($b['website'])) {
        $url = wb_str($b['website'], 255);
        if ($url && !filter_var($url, FILTER_VALIDATE_URL))
            wb_error('Invalid website URL', 422);
        $fields[] = 'website = ?'; $params[] = $url;
    }
    if (isset($b['avatar_url'])) {
        $url = wb_str($b['avatar_url'], 255);
        if ($url && !filter_var($url, FILTER_VALIDATE_URL))
            wb_error('Invalid avatar URL', 422);
        $fields[] = 'avatar_url = ?'; $params[] = $url;
    }

    // Password change
    if (!empty($b['new_password'])) {
        if (empty($b['current_password']))
            wb_error('current_password required to change password', 422);
        if (!password_verify($b['current_password'], $user['password']))
            wb_error('Current password is incorrect', 401);
        if (strlen($b['new_password']) < 8)
            wb_error('New password must be at least 8 characters', 422);
        $fields[] = 'password = ?';
        $params[] = password_hash($b['new_password'], PASSWORD_BCRYPT, ['cost' => 12]);
    }

    if (empty($fields)) wb_error('No fields to update', 422);

    $now      = wb_ms();
    $fields[] = 'updated_at = ?';
    $params[] = $now;
    $params[] = $auth['uid'];

    wb_exec($db, 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);

    $user = wb_select_one($db, 'SELECT * FROM users WHERE id = ?', [$auth['uid']]);
    wb_json(_safe_user($user));
}

// ── GET /auth/sessions ─────────────────────────────────
function auth_sessions_list(): void
{
    $auth = wb_require_auth();
    $db   = wb_init_main_db();
    $rows = wb_select($db,
        'SELECT id, device_info, ip, created_at, expires_at, last_used
         FROM sessions WHERE user_id = ? ORDER BY created_at DESC',
        [$auth['uid']]
    );
    wb_json(['sessions' => $rows]);
}

// ── DELETE /auth/sessions/{id} ─────────────────────────
function auth_session_revoke(string $sessId): void
{
    if (!$sessId) wb_error('Session ID required', 422);
    $auth = wb_require_auth();
    $db   = wb_init_main_db();
    $n    = wb_exec($db,
        'DELETE FROM sessions WHERE id = ? AND user_id = ?',
        [$sessId, $auth['uid']]
    );
    if (!$n) wb_error('Session not found', 404);
    wb_json(['ok' => true]);
}

// ── Private helpers ────────────────────────────────────

/** Create a DB session and return the raw token string. */
function _create_session(PDO $db, string $userId): string
{
    $payload = ['uid' => $userId];
    $token   = wb_token_create($payload);
    $hash    = hash('sha256', $token);
    $now     = wb_ms();
    $exp     = (int)(time() + WB_TOKEN_TTL) * 1000;

    $device  = wb_str($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 200);
    $ip      = wb_str($_SERVER['REMOTE_ADDR'] ?? '', 45);

    wb_exec($db,
        'INSERT OR REPLACE INTO sessions
            (id, user_id, token_hash, device_info, ip, created_at, expires_at, last_used)
         VALUES (?,?,?,?,?,?,?,?)',
        [wb_uuid(), $userId, $hash, $device, $ip, $now, $exp, $now]
    );
    return $token;
}

/** Strip sensitive fields from user row. */
function _safe_user(array $u): array
{
    unset($u['password'], $u['is_active']);
    return $u;
}
