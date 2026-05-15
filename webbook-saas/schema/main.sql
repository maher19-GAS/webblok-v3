-- ══════════════════════════════════════════════════════
--  WebBook SaaS — MAIN DATABASE  (main.sqlite)
--  One file for the whole platform.
--  Users, sessions, and the books registry live here.
-- ══════════════════════════════════════════════════════

PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;

-- ── Users ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id           TEXT PRIMARY KEY,          -- UUID v4
    email        TEXT UNIQUE NOT NULL,
    username     TEXT UNIQUE NOT NULL,      -- slug-safe, 3-32 chars
    display_name TEXT NOT NULL DEFAULT '',
    password     TEXT NOT NULL,             -- bcrypt hash
    role         TEXT NOT NULL DEFAULT 'author',  -- author | admin
    plan         TEXT NOT NULL DEFAULT 'free',    -- free | pro | enterprise
    avatar_url   TEXT NOT NULL DEFAULT '',
    bio          TEXT NOT NULL DEFAULT '',
    website      TEXT NOT NULL DEFAULT '',
    created_at   INTEGER NOT NULL,          -- Unix ms
    updated_at   INTEGER NOT NULL,
    last_login   INTEGER,
    is_active    INTEGER NOT NULL DEFAULT 1,
    is_verified  INTEGER NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_users_email    ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);

-- ── Sessions / API tokens ──────────────────────────────
CREATE TABLE IF NOT EXISTS sessions (
    id           TEXT PRIMARY KEY,          -- UUID v4
    user_id      TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash   TEXT UNIQUE NOT NULL,      -- sha256 of the raw token
    device_info  TEXT NOT NULL DEFAULT '',
    ip           TEXT NOT NULL DEFAULT '',
    created_at   INTEGER NOT NULL,
    expires_at   INTEGER NOT NULL,
    last_used    INTEGER
);

CREATE INDEX IF NOT EXISTS idx_sessions_user  ON sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_sessions_token ON sessions(token_hash);

-- ── Books registry ─────────────────────────────────────
-- Metadata only. Content lives in per-book SQLite files.
CREATE TABLE IF NOT EXISTS books (
    id           TEXT PRIMARY KEY,          -- UUID v4
    owner_id     TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    slug         TEXT NOT NULL,             -- unique per owner: owner/slug
    title        TEXT NOT NULL,
    description  TEXT NOT NULL DEFAULT '',
    language     TEXT NOT NULL DEFAULT 'ar',
    direction    TEXT NOT NULL DEFAULT 'rtl',
    cover_url    TEXT NOT NULL DEFAULT '',
    theme        TEXT NOT NULL DEFAULT 'dark-night',
    status       TEXT NOT NULL DEFAULT 'draft',  -- draft | published | archived
    visibility   TEXT NOT NULL DEFAULT 'private',-- private | unlisted | public
    reader_url   TEXT NOT NULL DEFAULT '',       -- public reader URL
    api_key      TEXT NOT NULL DEFAULT '',       -- per-book read token for public readers
    total_slides INTEGER NOT NULL DEFAULT 0,
    total_words  INTEGER NOT NULL DEFAULT 0,
    created_at   INTEGER NOT NULL,
    updated_at   INTEGER NOT NULL,
    published_at INTEGER
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_books_owner_slug ON books(owner_id, slug);
CREATE INDEX IF NOT EXISTS idx_books_owner             ON books(owner_id);
CREATE INDEX IF NOT EXISTS idx_books_status            ON books(status);

-- ── Book collaborators ─────────────────────────────────
CREATE TABLE IF NOT EXISTS book_collaborators (
    id         TEXT PRIMARY KEY,
    book_id    TEXT NOT NULL REFERENCES books(id) ON DELETE CASCADE,
    user_id    TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role       TEXT NOT NULL DEFAULT 'viewer',  -- viewer | editor | co-author
    added_at   INTEGER NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_collab_book_user ON book_collaborators(book_id, user_id);

-- ── Platform statistics (aggregate, updated by triggers) ─
CREATE TABLE IF NOT EXISTS platform_stats (
    key   TEXT PRIMARY KEY,
    value INTEGER NOT NULL DEFAULT 0
);

INSERT OR IGNORE INTO platform_stats(key, value) VALUES
    ('total_users', 0),
    ('total_books', 0),
    ('total_readers', 0);

-- ── Triggers: keep counters updated ───────────────────
CREATE TRIGGER IF NOT EXISTS trg_user_insert
AFTER INSERT ON users BEGIN
    UPDATE platform_stats SET value = value + 1 WHERE key = 'total_users';
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
