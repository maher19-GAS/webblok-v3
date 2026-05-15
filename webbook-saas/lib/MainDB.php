<?php
/**
 * WebBook SaaS — Main Database initialiser v2
 * Adds: reader role, verify_tokens, reset_tokens, webhooks tables
 */

function wb_init_main_db(): PDO
{
    $db = wb_main_db();
    $db->exec("
        PRAGMA journal_mode = WAL;
        PRAGMA foreign_keys = ON;
        PRAGMA synchronous  = NORMAL;

        -- ── Users (roles: author | reader | admin) ──────────
        CREATE TABLE IF NOT EXISTS users (
            id           TEXT    PRIMARY KEY,
            email        TEXT    UNIQUE NOT NULL,
            username     TEXT    UNIQUE NOT NULL,
            display_name TEXT    NOT NULL DEFAULT '',
            password     TEXT    NOT NULL,
            role         TEXT    NOT NULL DEFAULT 'reader',   -- reader | author | admin
            plan         TEXT    NOT NULL DEFAULT 'free',     -- free | pro | enterprise
            avatar_url   TEXT    NOT NULL DEFAULT '',
            bio          TEXT    NOT NULL DEFAULT '',
            website      TEXT    NOT NULL DEFAULT '',
            created_at   INTEGER NOT NULL,
            updated_at   INTEGER NOT NULL,
            last_login   INTEGER,
            is_active    INTEGER NOT NULL DEFAULT 1,
            is_verified  INTEGER NOT NULL DEFAULT 0
        );
        CREATE INDEX IF NOT EXISTS idx_users_email    ON users(email);
        CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
        CREATE INDEX IF NOT EXISTS idx_users_role     ON users(role);

        -- ── Sessions / API tokens ────────────────────────────
        CREATE TABLE IF NOT EXISTS sessions (
            id           TEXT    PRIMARY KEY,
            user_id      TEXT    NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            token_hash   TEXT    UNIQUE NOT NULL,
            device_info  TEXT    NOT NULL DEFAULT '',
            ip           TEXT    NOT NULL DEFAULT '',
            created_at   INTEGER NOT NULL,
            expires_at   INTEGER NOT NULL,
            last_used    INTEGER
        );
        CREATE INDEX IF NOT EXISTS idx_sessions_user  ON sessions(user_id);
        CREATE INDEX IF NOT EXISTS idx_sessions_token ON sessions(token_hash);

        -- ── Email verification tokens ────────────────────────
        CREATE TABLE IF NOT EXISTS verify_tokens (
            id         TEXT    PRIMARY KEY,
            user_id    TEXT    NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            token_hash TEXT    UNIQUE NOT NULL,
            created_at INTEGER NOT NULL,
            expires_at INTEGER NOT NULL,
            used       INTEGER NOT NULL DEFAULT 0
        );
        CREATE INDEX IF NOT EXISTS idx_vt_user ON verify_tokens(user_id);

        -- ── Password reset tokens ────────────────────────────
        CREATE TABLE IF NOT EXISTS reset_tokens (
            id         TEXT    PRIMARY KEY,
            user_id    TEXT    NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            token_hash TEXT    UNIQUE NOT NULL,
            created_at INTEGER NOT NULL,
            expires_at INTEGER NOT NULL,
            used       INTEGER NOT NULL DEFAULT 0
        );
        CREATE INDEX IF NOT EXISTS idx_rt_user ON reset_tokens(user_id);

        -- ── Books registry ───────────────────────────────────
        CREATE TABLE IF NOT EXISTS books (
            id           TEXT    PRIMARY KEY,
            owner_id     TEXT    NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            slug         TEXT    NOT NULL,
            title        TEXT    NOT NULL,
            description  TEXT    NOT NULL DEFAULT '',
            language     TEXT    NOT NULL DEFAULT 'ar',
            direction    TEXT    NOT NULL DEFAULT 'rtl',
            cover_url    TEXT    NOT NULL DEFAULT '',
            theme        TEXT    NOT NULL DEFAULT 'dark-night',
            status       TEXT    NOT NULL DEFAULT 'draft',
            visibility   TEXT    NOT NULL DEFAULT 'private',
            reader_url   TEXT    NOT NULL DEFAULT '',
            api_key      TEXT    NOT NULL DEFAULT '',
            total_slides INTEGER NOT NULL DEFAULT 0,
            total_words  INTEGER NOT NULL DEFAULT 0,
            created_at   INTEGER NOT NULL,
            updated_at   INTEGER NOT NULL,
            published_at INTEGER
        );
        CREATE UNIQUE INDEX IF NOT EXISTS idx_books_owner_slug ON books(owner_id, slug);
        CREATE INDEX        IF NOT EXISTS idx_books_owner      ON books(owner_id);
        CREATE INDEX        IF NOT EXISTS idx_books_status     ON books(status);

        -- ── Book collaborators ───────────────────────────────
        CREATE TABLE IF NOT EXISTS book_collaborators (
            id       TEXT    PRIMARY KEY,
            book_id  TEXT    NOT NULL REFERENCES books(id) ON DELETE CASCADE,
            user_id  TEXT    NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            role     TEXT    NOT NULL DEFAULT 'viewer',
            added_at INTEGER NOT NULL
        );
        CREATE UNIQUE INDEX IF NOT EXISTS idx_collab_book_user
            ON book_collaborators(book_id, user_id);

        -- ── Webhooks ─────────────────────────────────────────
        CREATE TABLE IF NOT EXISTS webhooks (
            id         TEXT    PRIMARY KEY,
            user_id    TEXT    NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            book_id    TEXT,   -- NULL = all books for this user
            url        TEXT    NOT NULL,
            events     TEXT    NOT NULL DEFAULT '[]',   -- JSON array of event names
            secret     TEXT    NOT NULL DEFAULT '',
            is_active  INTEGER NOT NULL DEFAULT 1,
            created_at INTEGER NOT NULL,
            last_fired INTEGER,
            fail_count INTEGER NOT NULL DEFAULT 0
        );
        CREATE INDEX IF NOT EXISTS idx_wh_user ON webhooks(user_id);
        CREATE INDEX IF NOT EXISTS idx_wh_book ON webhooks(book_id);

        -- ── Webhook delivery log ─────────────────────────────
        CREATE TABLE IF NOT EXISTS webhook_deliveries (
            id          TEXT    PRIMARY KEY,
            webhook_id  TEXT    NOT NULL REFERENCES webhooks(id) ON DELETE CASCADE,
            event       TEXT    NOT NULL,
            payload     TEXT    NOT NULL DEFAULT '{}',
            status_code INTEGER,
            response    TEXT    NOT NULL DEFAULT '',
            fired_at    INTEGER NOT NULL,
            success     INTEGER NOT NULL DEFAULT 0
        );
        CREATE INDEX IF NOT EXISTS idx_wh_del_hook ON webhook_deliveries(webhook_id, fired_at DESC);

        -- ── Platform stats ───────────────────────────────────
        CREATE TABLE IF NOT EXISTS platform_stats (
            key   TEXT PRIMARY KEY,
            value INTEGER NOT NULL DEFAULT 0
        );
        INSERT OR IGNORE INTO platform_stats(key,value) VALUES
            ('total_users',0),('total_authors',0),('total_readers',0),
            ('total_books',0),('total_published',0);

        -- ── Triggers ────────────────────────────────────────
        CREATE TRIGGER IF NOT EXISTS trg_user_insert
        AFTER INSERT ON users BEGIN
            UPDATE platform_stats SET value = value + 1 WHERE key = 'total_users';
            UPDATE platform_stats SET value = value + 1
                WHERE key = CASE WHEN NEW.role = 'author' OR NEW.role = 'admin'
                                 THEN 'total_authors' ELSE 'total_readers' END;
        END;

        CREATE TRIGGER IF NOT EXISTS trg_user_delete
        AFTER DELETE ON users BEGIN
            UPDATE platform_stats SET value = value - 1 WHERE key = 'total_users';
        END;

        CREATE TRIGGER IF NOT EXISTS trg_book_insert
        AFTER INSERT ON books BEGIN
            UPDATE platform_stats SET value = value + 1 WHERE key = 'total_books';
        END;

        CREATE TRIGGER IF NOT EXISTS trg_book_delete
        AFTER DELETE ON books BEGIN
            UPDATE platform_stats SET value = value - 1 WHERE key = 'total_books';
        END;

        CREATE TRIGGER IF NOT EXISTS trg_book_publish
        AFTER UPDATE ON books
        WHEN NEW.status = 'published' AND OLD.status != 'published' BEGIN
            UPDATE platform_stats SET value = value + 1 WHERE key = 'total_published';
        END;
    ");
    return $db;
}

/** Get plan limit for a user. */
function wb_plan_limit(string $userId, string $key): int
{
    $db   = wb_main_db();
    $user = wb_select_one($db, 'SELECT plan FROM users WHERE id = ?', [$userId]);
    $plan = $user['plan'] ?? 'free';
    $limits = WB_PLAN_LIMITS[$plan] ?? WB_PLAN_LIMITS['free'];
    return (int)($limits[$key] ?? 0);
}

/** Check if user is at/over their book limit. */
function wb_check_book_limit(string $userId): void
{
    $db    = wb_main_db();
    $count = (int)(wb_select_one($db,
        'SELECT COUNT(*) AS n FROM books WHERE owner_id = ?',
        [$userId])['n'] ?? 0);
    $limit = wb_plan_limit($userId, 'books');
    if ($count >= $limit) {
        wb_error("Book limit reached ($limit for your plan). Upgrade to create more.", 403);
    }
}

/** Require user has author or admin role. */
function wb_require_author(): array
{
    $auth = wb_require_auth();
    $db   = wb_main_db();
    $user = wb_select_one($db, 'SELECT role FROM users WHERE id = ?', [$auth['uid']]);
    if (!$user || !in_array($user['role'], ['author', 'admin'])) {
        wb_error('Author account required to manage books', 403);
    }
    return $auth;
}

/** Require admin role. */
function wb_require_admin(): array
{
    $auth = wb_require_auth();
    $db   = wb_main_db();
    $user = wb_select_one($db, 'SELECT role FROM users WHERE id = ?', [$auth['uid']]);
    if (!$user || $user['role'] !== 'admin') {
        wb_error('Admin access required', 403);
    }
    return $auth;
}
