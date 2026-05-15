<?php
/**
 * WebBook SaaS — Global Configuration v2
 * API Base: 2030b.com/webbook/api
 */

define('WB_VERSION',   '2.0.0');
define('WB_API_BASE',  'https://2030b.com/webbook/api');
define('WB_APP_NAME',  'WebBook SaaS');
define('WB_APP_URL',   'https://2030b.com');

// ── Paths ──────────────────────────────────────────────
define('WB_ROOT',      __DIR__);
define('WB_DATA',      WB_ROOT . '/data');
define('WB_DB_MAIN',   WB_DATA . '/main.sqlite');
define('WB_DB_BOOKS',  WB_DATA . '/books');
define('WB_MD_ROOT',   WB_DATA . '/markdown');
define('WB_UPLOADS',   WB_DATA . '/uploads');    // media uploads

// ── Security ───────────────────────────────────────────
define('WB_TOKEN_SECRET',       getenv('WB_TOKEN_SECRET') ?: 'CHANGE_THIS_SECRET_IN_ENV_wb2030b');
define('WB_TOKEN_TTL',          60 * 60 * 24 * 30);   // 30 days
define('WB_VERIFY_TOKEN_TTL',   60 * 60 * 24 * 3);    // 3 days  (email verify)
define('WB_RESET_TOKEN_TTL',    60 * 60 * 2);          // 2 hours (password reset)
define('WB_WEBHOOK_SECRET',     getenv('WB_WEBHOOK_SECRET') ?: 'CHANGE_WEBHOOK_SECRET_wb2030b');

// ── Plan limits ────────────────────────────────────────
// reader role: cannot create books, only read
define('WB_PLAN_LIMITS', [
    'free'       => ['books' => 3,   'chapters' => 30,  'upload_mb' => 5],
    'pro'        => ['books' => 50,  'chapters' => 500, 'upload_mb' => 100],
    'enterprise' => ['books' => 999, 'chapters' => 9999,'upload_mb' => 1000],
]);
define('WB_MAX_BOOKS',     50);   // absolute cap
define('WB_MAX_MD_SIZE',   512 * 1024);
define('WB_MAX_SYNC_ROWS', 5000);

// ── Upload settings ────────────────────────────────────
define('WB_UPLOAD_MAX_BYTES',   10 * 1024 * 1024);   // 10 MB per file
define('WB_UPLOAD_ALLOWED',     ['image/jpeg','image/png','image/webp','image/gif','image/svg+xml']);
define('WB_UPLOAD_URL_BASE',    'https://2030b.com/webbook/uploads');

// ── CORS ───────────────────────────────────────────────
define('WB_ALLOWED_ORIGINS', [
    'https://2030b.com',
    'http://localhost',
    'http://localhost:3000',
    'http://127.0.0.1',
    '*',   // remove in production
]);

// ── Rate limiting ──────────────────────────────────────
define('WB_RATE_DIR',    WB_DATA . '/rate');
define('WB_RATE_LIMIT',  120);
define('WB_RATE_WINDOW', 60);

// ── Webhooks ───────────────────────────────────────────
define('WB_WEBHOOK_EVENTS', [
    'book.published', 'book.archived', 'book.created', 'book.deleted',
    'reader.joined',  'reader.synced',
]);
define('WB_WEBHOOK_TIMEOUT', 10);   // seconds

// ── Email (simple mail() or SMTP stub) ────────────────
define('WB_MAIL_FROM',    getenv('WB_MAIL_FROM')    ?: 'noreply@2030b.com');
define('WB_MAIL_ENABLED', (bool)(getenv('WB_MAIL_ENABLED') ?: true));
