<?php
/**
 * WebBook SaaS — Per-Book Database initialiser
 * Called every time a book DB is opened to ensure schema is current.
 */

function wb_init_book_db(string $bookId): PDO
{
    $db = wb_book_db($bookId);

    $db->exec("
        PRAGMA journal_mode = WAL;
        PRAGMA foreign_keys = ON;
        PRAGMA synchronous  = NORMAL;

        -- ─── AUTHOR / CONTENT ───────────────────────────────
        CREATE TABLE IF NOT EXISTS chapters (
            id           TEXT    PRIMARY KEY,
            book_id      TEXT    NOT NULL,
            file_id      TEXT    NOT NULL,
            file_path    TEXT    NOT NULL,
            part         TEXT    NOT NULL DEFAULT '',
            title        TEXT    NOT NULL DEFAULT '',
            sort_order   INTEGER NOT NULL DEFAULT 0,
            content      TEXT    NOT NULL DEFAULT '',
            word_count   INTEGER NOT NULL DEFAULT 0,
            is_published INTEGER NOT NULL DEFAULT 0,
            created_at   INTEGER NOT NULL,
            updated_at   INTEGER NOT NULL
        );
        CREATE UNIQUE INDEX IF NOT EXISTS idx_chap_file  ON chapters(book_id, file_id);
        CREATE INDEX        IF NOT EXISTS idx_chap_order ON chapters(book_id, sort_order);

        CREATE TABLE IF NOT EXISTS chapter_revisions (
            id         TEXT    PRIMARY KEY,
            chapter_id TEXT    NOT NULL REFERENCES chapters(id) ON DELETE CASCADE,
            content    TEXT    NOT NULL,
            word_count INTEGER NOT NULL DEFAULT 0,
            author_id  TEXT    NOT NULL DEFAULT '',
            message    TEXT    NOT NULL DEFAULT '',
            created_at INTEGER NOT NULL
        );
        CREATE INDEX IF NOT EXISTS idx_rev_chapter
            ON chapter_revisions(chapter_id, created_at DESC);

        -- ─── READER IndexedDB MIRROR ────────────────────────
        CREATE TABLE IF NOT EXISTS reader_kv (
            id         TEXT    PRIMARY KEY,
            book_id    TEXT    NOT NULL,
            reader_id  TEXT    NOT NULL,
            k          TEXT    NOT NULL,
            v          TEXT    NOT NULL,
            updated_at INTEGER NOT NULL
        );
        CREATE UNIQUE INDEX IF NOT EXISTS idx_rkv_reader_key
            ON reader_kv(book_id, reader_id, k);
        CREATE INDEX IF NOT EXISTS idx_rkv_reader
            ON reader_kv(book_id, reader_id);

        CREATE TABLE IF NOT EXISTS reader_anns (
            id         TEXT    PRIMARY KEY,
            book_id    TEXT    NOT NULL,
            reader_id  TEXT    NOT NULL,
            type       TEXT    NOT NULL,
            sel_text   TEXT    NOT NULL DEFAULT '',
            note_text  TEXT    NOT NULL DEFAULT '',
            slide_idx  INTEGER NOT NULL DEFAULT 0,
            hl_id      TEXT    NOT NULL DEFAULT '',
            created_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL,
            deleted    INTEGER NOT NULL DEFAULT 0
        );
        CREATE INDEX IF NOT EXISTS idx_anns_reader
            ON reader_anns(book_id, reader_id);
        CREATE INDEX IF NOT EXISTS idx_anns_type
            ON reader_anns(book_id, reader_id, type);
        CREATE INDEX IF NOT EXISTS idx_anns_slide
            ON reader_anns(book_id, reader_id, slide_idx);

        CREATE TABLE IF NOT EXISTS reader_replies (
            id        TEXT    PRIMARY KEY,
            book_id   TEXT    NOT NULL,
            reader_id TEXT    NOT NULL,
            ann_id    TEXT    NOT NULL,
            text      TEXT    NOT NULL,
            author    TEXT    NOT NULL DEFAULT 'أنا',
            ts        INTEGER NOT NULL,
            deleted   INTEGER NOT NULL DEFAULT 0
        );
        CREATE INDEX IF NOT EXISTS idx_replies_ann
            ON reader_replies(book_id, reader_id, ann_id);

        CREATE TABLE IF NOT EXISTS reader_activity (
            id        TEXT    PRIMARY KEY,
            book_id   TEXT    NOT NULL,
            reader_id TEXT    NOT NULL,
            type      TEXT    NOT NULL,
            label     TEXT    NOT NULL DEFAULT '',
            meta      TEXT    NOT NULL DEFAULT '{}',
            ts        INTEGER NOT NULL
        );
        CREATE INDEX IF NOT EXISTS idx_act_reader
            ON reader_activity(book_id, reader_id, ts DESC);

        CREATE TABLE IF NOT EXISTS reader_scrollpos (
            id          TEXT    PRIMARY KEY,
            book_id     TEXT    NOT NULL,
            reader_id   TEXT    NOT NULL,
            slide_index INTEGER NOT NULL,
            slide_title TEXT    NOT NULL DEFAULT '',
            start_ts    INTEGER NOT NULL,
            end_ts      INTEGER,
            duration_ms INTEGER
        );
        CREATE INDEX IF NOT EXISTS idx_scroll_reader
            ON reader_scrollpos(book_id, reader_id, start_ts DESC);

        CREATE TABLE IF NOT EXISTS reader_mdcache (
            id      TEXT    PRIMARY KEY,
            book_id TEXT    NOT NULL,
            content TEXT    NOT NULL,
            ts      INTEGER NOT NULL
        );
        CREATE UNIQUE INDEX IF NOT EXISTS idx_mdc_file
            ON reader_mdcache(book_id, id);

        -- ─── ANALYTICS ──────────────────────────────────────
        CREATE TABLE IF NOT EXISTS book_analytics (
            id              TEXT    PRIMARY KEY,
            book_id         TEXT    NOT NULL,
            date            TEXT    NOT NULL,
            unique_readers  INTEGER NOT NULL DEFAULT 0,
            total_sessions  INTEGER NOT NULL DEFAULT 0,
            total_slides    INTEGER NOT NULL DEFAULT 0,
            avg_duration_ms INTEGER NOT NULL DEFAULT 0
        );
        CREATE UNIQUE INDEX IF NOT EXISTS idx_analytics_date
            ON book_analytics(book_id, date);

        CREATE TABLE IF NOT EXISTS reader_stats (
            id          TEXT    PRIMARY KEY,
            book_id     TEXT    NOT NULL,
            reader_id   TEXT    NOT NULL,
            sessions    INTEGER NOT NULL DEFAULT 0,
            slides_read INTEGER NOT NULL DEFAULT 0,
            total_min   INTEGER NOT NULL DEFAULT 0,
            last_slide  INTEGER NOT NULL DEFAULT 0,
            last_seen   INTEGER NOT NULL,
            first_seen  INTEGER NOT NULL
        );
        CREATE UNIQUE INDEX IF NOT EXISTS idx_rstats_reader
            ON reader_stats(book_id, reader_id);
    ");

    return $db;
}

/**
 * Validate that a book belongs to a user (checks main DB registry).
 * Returns the book row or calls wb_error(403/404).
 */
function wb_require_book_owner(string $bookId, string $userId): array
{
    $db   = wb_main_db();
    $book = wb_select_one($db,
        'SELECT * FROM books WHERE id = ? AND owner_id = ?',
        [$bookId, $userId]
    );
    if (!$book) wb_error('Book not found or access denied', 404);
    return $book;
}

/**
 * Check if a user can access a book (owner or collaborator with min role).
 */
function wb_can_access_book(string $bookId, string $userId, string $minRole = 'viewer'): bool
{
    $db = wb_main_db();
    // Owner always has access
    $book = wb_select_one($db, 'SELECT id FROM books WHERE id = ? AND owner_id = ?', [$bookId, $userId]);
    if ($book) return true;
    // Collaborator?
    $roles = ['viewer' => 0, 'editor' => 1, 'co-author' => 2];
    $collab = wb_select_one($db,
        'SELECT role FROM book_collaborators WHERE book_id = ? AND user_id = ?',
        [$bookId, $userId]
    );
    if (!$collab) return false;
    return ($roles[$collab['role']] ?? -1) >= ($roles[$minRole] ?? 0);
}

/** Count words in a markdown string. */
function wb_word_count(string $md): int
{
    $stripped = strip_tags($md);
    $words    = preg_split('/\s+/u', trim($stripped), -1, PREG_SPLIT_NO_EMPTY);
    return count($words ?: []);
}
