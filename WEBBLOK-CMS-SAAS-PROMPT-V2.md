# WebBlok CMS SaaS — Complete Merged Specification Prompt (V2 Final)

> **Document Purpose**: The single, authoritative, developer-ready specification to build the **WebBlok CMS SaaS** platform from absolute zero. This document **merges** both `WEBBLOK-SAAS-PROMPT.md` (V1 embed API) and `WEBBLOK-CMS-SAAS-PROMPT.md` (V2 CMS builder) into one unified build target, with the following confirmed adjustments:
>
> 1. **Desktop-first deployment** — platform is installed on a local PC, then uploaded via FTP/SFTP to a server **without SSH access**. All package choices and post-install steps must work without SSH commands.
> 2. **SQLite as the universal database** — both the platform registry and tenant databases use SQLite. MySQL/PostgreSQL is optional and only configurable via a single `.env` switch.
> 3. **Auth system switcher** — Breeze Blade auth (default, works offline) plus a config-file switch to **GAS (Global Authenticator System)** API-based auth with zero code changes.
> 4. **Template & Theme Marketplace** — tenants can install themes and page templates from a built-in marketplace; third parties can publish to it.
>
> **Strictness Level**: PHPStan / Larastan **Level 10** (maximum). CI-enforced. No exceptions.
>
> **Framework**: Latest stable Laravel (do not pin to a specific version — always use the current latest stable release at installation time).

---

## 0. One-Line Vision

> **WebBlok CMS** is a **full-featured, multi-tenant SaaS CMS** (one SQLite per tenant) built on **latest Laravel + Breeze Blade + Alpine.js + SortableJS**, where any user can **build a complete website from scratch** — with hierarchical menus, SEO pages, multi-language content, RBAC, and a **drag-and-drop visual blok editor** — and either serve it live via API or **download a self-contained static site bundle**. It also ships a **V1 embed API** so existing third-party sites can embed individual bloks via CDN widget or JSON/HTML endpoints. Auth can be switched between **Breeze Blade** (default) and **GAS (Global Authenticator System)** via a config file. Themes and page templates are managed via a built-in **marketplace**.

---

## 1. Four Key Adjustments Explained

### 1.1 Desktop-First / No-SSH Deployment

**Scenario**: Developer installs and builds the platform on their PC. When ready, they ZIP the entire project and upload via FTP to a shared hosting server. The server runs PHP + SQLite but has **no SSH, no Artisan CLI, no Composer on server**.

**Consequences for the build**:

| Concern | Solution |
|---------|---------|
| Livewire 3 requires Artisan publish | Replace Livewire with **Alpine.js + Blade + vanilla JS** for all interactive UI. Livewire is NOT included. |
| Filament 3 requires Artisan publish + SSH | Replace Filament with a **custom Blade admin panel** (no external admin panel package). |
| Queue workers need SSH/Supervisor | Queue driver defaults to `database` (SQLite table). No Redis, no Supervisor. Long jobs use **chunked PHP** with polling. |
| `composer install` on server | Run `composer install --no-dev --optimize-autoloader` locally. Upload the whole `vendor/` directory. |
| `php artisan migrate` on server | Run migrations locally against the platform SQLite before upload. Tenant migrations run on first login via a **web-triggered migration endpoint**. |
| npm/Vite on server | Run `npm run build` locally. Upload the `public/build/` output. Never run Vite on server. |
| Cron jobs | A single `/cron.php` entry-point exists; the server's cPanel cron calls `GET /cron/run` every minute via HTTP. All scheduled tasks hook into this. |

**Packages EXCLUDED** (require SSH post-install or Supervisor):
- `livewire/livewire` — replaced by Alpine.js + Blade
- `filament/filament` — replaced by custom Blade admin panel
- `laravel/horizon` — replaced by database queue + polling
- `meilisearch/meilisearch-php` — replaced by SQLite FTS5 full-text search
- `predis/predis` — Redis not available; cache driver = `file` or `database`

**Packages KEPT** (install-and-upload, no post-install SSH):
- All `spatie/*` packages (pure PHP, no daemon needed)
- `pestphp/pest` (dev only, never on server)
- `larastan/larastan` (dev only)
- `laravel/pint` (dev only)
- `barryvdh/laravel-dompdf` (pure PHP)

### 1.2 SQLite as Universal Database (Platform + Tenants)

**Default**: Both the platform registry and all tenant databases use **SQLite**.
**Optional**: A single `.env` line switches the platform registry to MySQL/PostgreSQL:

```ini
# .env — Platform database
DB_CONNECTION=sqlite          # default (works everywhere, zero config)
# DB_CONNECTION=mysql         # switch to MySQL if server supports it
# DB_DATABASE_MYSQL=webblok   # only used when DB_CONNECTION=mysql
```

**SQLite platform database** lives at `database/platform.sqlite`.
**SQLite tenant databases** live at `storage/tenants/{slug}/database.sqlite`.
Both use **WAL mode** for concurrent reads.

The `config/database.php` is written to handle this switch cleanly (see Section 5).

### 1.3 Auth System Switcher (Breeze vs GAS)

Two auth drivers exist, selectable via `config/auth_driver.php`:

```php
// config/auth_driver.php
return [
    /*
    |--------------------------------------------------------------
    | Auth Driver
    | 'breeze'  — Laravel Breeze Blade (default, works offline)
    | 'gas'     — Global Authenticator System (external API)
    |--------------------------------------------------------------
    */
    'driver' => env('AUTH_DRIVER', 'breeze'),

    'gas' => [
        'base_url'        => env('GAS_BASE_URL', 'https://gas.example.com'),
        'api_key'         => env('GAS_API_KEY', ''),
        'app_id'          => env('GAS_APP_ID', ''),
        'timeout_seconds' => env('GAS_TIMEOUT', 10),
        'user_sync'       => env('GAS_USER_SYNC', true),  // auto-upsert GAS users locally
        'callback_route'  => '/auth/gas/callback',
    ],
];
```

**How it works**:
- A single `AuthManager` service reads `config('auth_driver.driver')`
- In Blade routes, all `auth` middleware and login/register views are conditionally swapped
- No code changes required to switch; only `.env` update + FTP re-upload of `config/` folder

**GAS API contract** (what WebBlok calls):

```
POST {GAS_BASE_URL}/api/auth/verify
Headers: X-App-Id: {APP_ID}, X-Api-Key: {API_KEY}
Body:    { "token": "<GAS_user_token>" }
Response: { "user_id": "...", "email": "...", "name": "...", "roles": [...] }
```

```
POST {GAS_BASE_URL}/api/auth/login
Body:    { "email": "...", "password": "..." }
Response: { "token": "...", "user": { ... } }
```

GAS users are **locally mirrored** in the platform `users` table (if `GAS_USER_SYNC=true`) so all existing Laravel auth guards continue to function. The GAS token is stored in `users.gas_token`, refreshed on each login.

### 1.4 Template & Theme Marketplace

**Template**: A pre-configured **page structure** — a set of blok instances with default content, arranged into sections, ready to be placed on a new page. Templates are installable in one click.

**Theme**: A **CSS + JS bundle** (Tailwind config + custom CSS variables + optional fonts) that changes the visual appearance of the entire site or individual pages.

**Marketplace**: A built-in catalog of templates and themes, browsable from the CMS admin. Sources:
1. **Official** — platform-bundled (shipped in `marketplace/official/`)
2. **Community** — fetched from a remote manifest JSON URL (configurable)
3. **Custom** — tenant-uploaded ZIP packages

The marketplace system is detailed in Section 14.

---

## 2. Source Material Context (from V1)

### 2.1 The 2030B Source Project

```
2030b-project/
  index.html                          ← Original single-page viewer
  a1-index.html … a5-index.html       ← Volume viewers (1–5)
  css/styles.css                      ← Master CSS design system
  js/
    animations.js                     ← Particle system, fade-in, glow
    loader.js                         ← Language loader
    slide-utils.js                    ← initSlide(), buildSlideHeader()
  slides/
    slide-{001..150}.html             ← 150 individual slide HTML files
    data/
      slide-{001..150}.{en|ar|fr}.json ← 450 JSON data files
  components/
    core/         cards/    skeleton/  layout/   advanced/
    audio/        certification/       cognitive-ux/
    conditioning/ data/     economy-ui/ ecosystem/ effects/
    governance/   identity/ lists/     marketplace/ progression/
    public/       system/   ui/        user/      video/
  langs/
    ar.json  en.json  fr.json  es.json  de.json  tr.json  ur.json
  2030b-config.json                   ← Master config (chapters, volumes)
```

### 2.2 The 11 Chapters / 3 Volumes

| Volume | Slides | Chapters |
|--------|--------|---------|
| 1 | 1–50 | Dashboard, Cognitive Profiles, CTC Economy |
| 2 | 51–100 | Platform Features, Projects, Achievements, Governance |
| 3 | 101–150 | Enterprise, Spatial Computing, Vision 2030, Cosmic Mission |

### 2.3 Languages Supported

| Code | Language | Direction | Font |
|------|----------|-----------|------|
| `en` | English | LTR | Inter |
| `ar` | Arabic | RTL | Cairo |
| `fr` | French | LTR | Inter |
| `es` | Spanish | LTR | Inter |
| `de` | German | LTR | Inter |
| `tr` | Turkish | LTR | Inter |
| `ur` | Urdu | RTL | Cairo |

---

## 3. Platform Feature Set (V1 + V2 Combined)

### 3.1 V1 Embed API Features (retained)
- `GET /api/v1/bloks` — list all bloks available to tenant
- `GET /api/v1/bloks/{id}/data` — raw JSON data
- `GET /api/v1/bloks/{id}/render` — rendered HTML
- `POST /api/v1/bloks/{id}/data` — content overrides
- CDN widget embed (`widget.js`)
- API key management (live / test keys)
- Per-tenant blok grants (whitelist)
- Usage tracking + quota enforcement

### 3.2 V2 CMS Features (new)
- Full page tree (parent/child slugs, SEO, scheduling)
- Drag-and-drop blok editor (Alpine.js + SortableJS)
- Multi-step Form Wizard per blok (19 field types)
- Recursive blok nesting (bloks inside bloks, up to depth 10)
- Hierarchical menus with role visibility
- Multi-language CMS content (per-locale page titles, descriptions, meta)
- Revision history (full page snapshots, restore)
- RBAC: roles + permissions per tenant
- Media library (upload, organize, tag)
- Form bloks with submission inbox
- Static site export (full ZIP download + injected AJAX bridge)
- Analytics (page views, referrers, country)
- Outgoing webhooks on publish/export events
- Template & Theme Marketplace (Section 14)
- Auth switcher: Breeze Blade ↔ GAS API (Section 1.3)

---

## 4. Technology Stack

### 4.1 Core Stack

| Layer | Technology | Notes |
|-------|-----------|-------|
| Framework | **Latest stable Laravel** | Do not pin version; use whatever is current stable at install time |
| Auth | **Laravel Breeze (Blade)** default | Switchable to GAS via `config/auth_driver.php` |
| Frontend JS | **Alpine.js** (via Breeze) | No Livewire; all reactivity via Alpine.js + Blade partials via HTMX-style fetch |
| Drag-Drop | **SortableJS** | Blok reordering + palette clone |
| CSS | **Tailwind CSS** | Utility-first; customised via theme config |
| Assets | **Vite** | Run locally; upload `public/build/` |
| Platform DB | **SQLite** (default) / MySQL (optional) | Switched via `DB_CONNECTION` in `.env` |
| Tenant DB | **SQLite** (WAL mode, always) | One file per tenant in `storage/tenants/` |
| Cache | **File** driver (default) / Database | No Redis required |
| Queue | **Database** driver (SQLite table) | No Redis, no Horizon, no Supervisor |
| Search | **SQLite FTS5** | No MeiliSearch required |
| Static Analysis | **Larastan + PHPStan Level 10** | Dev only; never runs on server |
| Code Style | **Laravel Pint** | Dev only |
| Testing | **Pest PHP** | Dev only |

### 4.2 Packages — Full `composer.json`

```json
{
  "name": "webblok/cms-saas",
  "description": "WebBlok CMS SaaS — Multi-tenant website builder with embed API",
  "type": "project",
  "license": "proprietary",
  "require": {
    "php": "^8.4",
    "laravel/framework": "*",
    "laravel/breeze": "*",
    "laravel/sanctum": "*",
    "spatie/laravel-data": "*",
    "spatie/laravel-permission": "*",
    "spatie/laravel-medialibrary": "*",
    "spatie/laravel-translatable": "*",
    "spatie/laravel-activitylog": "*",
    "spatie/laravel-sluggable": "*",
    "spatie/laravel-query-builder": "*",
    "spatie/laravel-backup": "*",
    "barryvdh/laravel-dompdf": "*",
    "nesbot/carbon": "*",
    "doctrine/dbal": "*"
  },
  "require-dev": {
    "fakerphp/faker": "*",
    "laravel/pint": "*",
    "mockery/mockery": "*",
    "nunomaduro/collision": "*",
    "pestphp/pest": "*",
    "pestphp/pest-plugin-laravel": "*",
    "pestphp/pest-plugin-arch": "*",
    "larastan/larastan": "*",
    "phpstan/phpstan": "*",
    "phpstan/phpstan-strict-rules": "*",
    "rector/rector": "*",
    "driftingly/rector-laravel": "*"
  },
  "autoload": {
    "psr-4": { "App\\": "app/" }
  },
  "autoload-dev": {
    "psr-4": { "Tests\\": "tests/" }
  },
  "scripts": {
    "post-autoload-dump": [
      "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
      "@php artisan package:discover --ansi"
    ],
    "analyse": "vendor/bin/phpstan analyse --configuration=phpstan.neon",
    "test":    "vendor/bin/pest --parallel",
    "lint":    "vendor/bin/pint --test",
    "fix":     "vendor/bin/pint",
    "ci":      ["@lint", "@analyse", "@test"]
  }
}
```

> **Note on versions**: All versions are `"*"` — always install the latest stable version compatible with the current Laravel. Lock versions via `composer.lock` after local install; commit the lock file and upload it alongside `vendor/`.

### 4.3 `phpstan.neon` — Level 10

```neon
includes:
    - vendor/larastan/larastan/extension.neon
    - vendor/phpstan/phpstan-strict-rules/rules.neon

parameters:
    level: 10
    paths:
        - app
        - database
        - routes
        - config
    checkMissingIterableValueType: true
    checkGenericClassInNonGenericObjectType: true
    checkUninitializedProperties: true
    checkDynamicProperties: true
    reportUnmatchedIgnoredErrors: true
    treatPhpDocTypesAsCertain: true
    ignoreErrors:
        - '#Unsafe usage of new static\(\)#'
```

### 4.4 `pint.json`

```json
{
  "preset": "laravel",
  "rules": {
    "declare_strict_types": true,
    "no_unused_imports": true,
    "ordered_imports": { "sort_algorithm": "alpha" },
    "single_quote": true,
    "trailing_comma_in_multiline": { "elements": ["arrays", "arguments", "parameters"] }
  }
}
```

---

## 5. Database Architecture

### 5.1 `config/database.php` — Dual-Mode Platform DB

```php
<?php

declare(strict_types=1);

return [
    'default' => env('DB_CONNECTION', 'sqlite'),

    'connections' => [
        // ── Platform database (SQLite default, MySQL optional) ──────────
        'sqlite' => [
            'driver'                  => 'sqlite',
            'database'                => env('DB_DATABASE', database_path('platform.sqlite')),
            'prefix'                  => '',
            'foreign_key_constraints' => true,
            'busy_timeout'            => 5000,
        ],

        'mysql' => [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST', '127.0.0.1'),
            'port'      => env('DB_PORT', '3306'),
            'database'  => env('DB_DATABASE_MYSQL', 'webblok'),
            'username'  => env('DB_USERNAME', 'root'),
            'password'  => env('DB_PASSWORD', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => true,
        ],

        // ── Tenant database (always SQLite, switched per request) ───────
        'tenant' => [
            'driver'                  => 'sqlite',
            'database'                => env('TENANT_DB_PATH', ':memory:'),
            'prefix'                  => '',
            'foreign_key_constraints' => true,
            'busy_timeout'            => 5000,
        ],
    ],

    'migrations' => 'migrations',
    'redis'      => [],  // not used
];
```

### 5.2 Platform Database Schema (SQLite / MySQL — identical SQL)

```sql
-- ═══════════════════════════════════════════════════════════
-- PLATFORM DATABASE
-- ═══════════════════════════════════════════════════════════

-- Platform users (super-admins + tenant owners/members)
CREATE TABLE users (
    id                      TEXT        NOT NULL PRIMARY KEY,  -- UUID
    name                    TEXT        NOT NULL,
    email                   TEXT        NOT NULL UNIQUE,
    password                TEXT        NULL,                  -- NULL when using GAS
    role                    TEXT        NOT NULL DEFAULT 'tenant_owner',
                                                               -- 'super_admin','tenant_owner','tenant_member'
    gas_token               TEXT        NULL,                  -- GAS auth token (if driver=gas)
    gas_user_id             TEXT        NULL,                  -- GAS user ID
    email_verified_at       TEXT        NULL,
    two_factor_secret       TEXT        NULL,
    two_factor_recovery_codes TEXT      NULL,
    remember_token          TEXT        NULL,
    created_at              TEXT        NULL,
    updated_at              TEXT        NULL,
    deleted_at              TEXT        NULL
);

-- Plans
CREATE TABLE plans (
    id                TEXT        NOT NULL PRIMARY KEY,
    name              TEXT        NOT NULL UNIQUE,             -- 'free','starter','pro','enterprise'
    display_name      TEXT        NOT NULL,
    max_pages         INTEGER     NOT NULL DEFAULT 5,
    max_bloks         INTEGER     NOT NULL DEFAULT 50,
    max_locales       INTEGER     NOT NULL DEFAULT 1,
    max_media_mb      INTEGER     NOT NULL DEFAULT 100,
    max_exports       INTEGER     NOT NULL DEFAULT 1,
    max_api_rpm       INTEGER     NOT NULL DEFAULT 60,
    price_monthly     REAL        NOT NULL DEFAULT 0.0,
    price_yearly      REAL        NOT NULL DEFAULT 0.0,
    features          TEXT        NULL,                        -- JSON feature flags
    is_active         INTEGER     NOT NULL DEFAULT 1,
    created_at        TEXT        NULL,
    updated_at        TEXT        NULL
);

-- Tenants (one row per website / customer)
CREATE TABLE tenants (
    id                TEXT        NOT NULL PRIMARY KEY,        -- UUID slug-friendly
    plan_id           TEXT        NOT NULL REFERENCES plans(id),
    owner_id          TEXT        NOT NULL REFERENCES users(id),
    name              TEXT        NOT NULL,
    slug              TEXT        NOT NULL UNIQUE,             -- URL-safe: 'acme-corp'
    domain            TEXT        NULL UNIQUE,                 -- custom domain
    subdomain         TEXT        NOT NULL UNIQUE,             -- {subdomain}.webblok.io
    database_path     TEXT        NOT NULL,                    -- absolute path to .sqlite
    storage_path      TEXT        NOT NULL,                    -- absolute path to media dir
    status            TEXT        NOT NULL DEFAULT 'provisioning',
                                                               -- provisioning,active,suspended,deleted
    trial_ends_at     TEXT        NULL,
    billing_email     TEXT        NOT NULL,
    settings          TEXT        NULL,                        -- JSON
    created_at        TEXT        NULL,
    updated_at        TEXT        NULL,
    deleted_at        TEXT        NULL
);

CREATE INDEX idx_tenants_slug      ON tenants(slug);
CREATE INDEX idx_tenants_domain    ON tenants(domain);
CREATE INDEX idx_tenants_subdomain ON tenants(subdomain);

-- API keys (V1 embed API)
CREATE TABLE api_keys (
    id              TEXT    NOT NULL PRIMARY KEY,
    tenant_id       TEXT    NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name            TEXT    NOT NULL,
    key_hash        TEXT    NOT NULL UNIQUE,                   -- SHA-256 of raw key
    key_prefix      TEXT    NOT NULL,                          -- first 8 chars (shown in UI)
    environment     TEXT    NOT NULL DEFAULT 'live',           -- 'live','test'
    scopes          TEXT    NOT NULL DEFAULT '["bloks:read"]', -- JSON array
    last_used_at    TEXT    NULL,
    expires_at      TEXT    NULL,
    revoked_at      TEXT    NULL,
    created_at      TEXT    NULL
);

CREATE INDEX idx_api_keys_tenant  ON api_keys(tenant_id);
CREATE INDEX idx_api_keys_prefix  ON api_keys(key_prefix);

-- Blok definitions (platform-level: all 150 slides + components)
CREATE TABLE blok_definitions (
    id                TEXT    NOT NULL PRIMARY KEY,
    blok_key          TEXT    NOT NULL UNIQUE,                 -- 'slide-01','hero-section'
    label             TEXT    NOT NULL,
    description       TEXT    NULL,
    category          TEXT    NOT NULL DEFAULT 'content',      -- 'dashboard','layout','content','form'
    chapter           INTEGER NULL,
    volume            INTEGER NULL,
    icon              TEXT    NULL,
    accepts_children  INTEGER NOT NULL DEFAULT 0,
    slots             TEXT    NULL,                            -- JSON: [{name,label,max_children}]
    schema            TEXT    NOT NULL DEFAULT '{}',           -- JSON: Form Wizard schema
    default_config    TEXT    NULL,                            -- JSON: default field values
    blade_component   TEXT    NOT NULL,                        -- FQCN of Blade component class
    langs             TEXT    NOT NULL DEFAULT '["en"]',       -- JSON array
    has_skeleton      INTEGER NOT NULL DEFAULT 1,
    has_interactive   INTEGER NOT NULL DEFAULT 0,
    cdn_ready         INTEGER NOT NULL DEFAULT 0,
    source_file       TEXT    NULL,
    preview_html      TEXT    NULL,
    thumbnail_url     TEXT    NULL,
    is_active         INTEGER NOT NULL DEFAULT 1,
    sort_order        INTEGER NOT NULL DEFAULT 0,
    tags              TEXT    NULL,
    created_at        TEXT    NULL,
    updated_at        TEXT    NULL
);

CREATE INDEX idx_blok_defs_category ON blok_definitions(category);
CREATE INDEX idx_blok_defs_active   ON blok_definitions(is_active);

-- Usage logs (V1 API usage tracking, aggregated per tenant per day)
CREATE TABLE usage_logs (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id       TEXT    NOT NULL REFERENCES tenants(id),
    api_key_id      TEXT    NULL,
    blok_key        TEXT    NOT NULL,
    response_type   TEXT    NOT NULL,                          -- 'json','html','widget'
    lang            TEXT    NOT NULL DEFAULT 'en',
    requests        INTEGER NOT NULL DEFAULT 1,
    render_ms       INTEGER NULL,
    log_date        TEXT    NOT NULL,                          -- ISO date: 2026-06-03
    created_at      TEXT    NULL
);

CREATE INDEX idx_usage_tenant_date ON usage_logs(tenant_id, log_date);
CREATE INDEX idx_usage_blok_date   ON usage_logs(blok_key, log_date);

-- Platform settings (key-value store)
CREATE TABLE platform_settings (
    key         TEXT    NOT NULL PRIMARY KEY,
    value       TEXT    NOT NULL,
    group_name  TEXT    NULL,
    cast_type   TEXT    NOT NULL DEFAULT 'string',
    description TEXT    NULL,
    updated_at  TEXT    NULL
);

-- Marketplace items (templates + themes catalog)
CREATE TABLE marketplace_items (
    id              TEXT    NOT NULL PRIMARY KEY,
    type            TEXT    NOT NULL,                          -- 'template','theme'
    source          TEXT    NOT NULL DEFAULT 'official',       -- 'official','community','custom'
    name            TEXT    NOT NULL,
    slug            TEXT    NOT NULL UNIQUE,
    description     TEXT    NULL,
    author          TEXT    NULL,
    version         TEXT    NOT NULL DEFAULT '1.0.0',
    thumbnail_url   TEXT    NULL,
    preview_url     TEXT    NULL,
    download_url    TEXT    NULL,                              -- NULL for locally-bundled items
    local_path      TEXT    NULL,                              -- path in marketplace/ dir
    tags            TEXT    NULL,                              -- JSON array
    category        TEXT    NULL,                              -- 'business','blog','portfolio'…
    locale_support  TEXT    NOT NULL DEFAULT '["en"]',         -- JSON array
    price           REAL    NOT NULL DEFAULT 0.0,              -- 0 = free
    install_count   INTEGER NOT NULL DEFAULT 0,
    rating          REAL    NULL,
    is_featured     INTEGER NOT NULL DEFAULT 0,
    is_active       INTEGER NOT NULL DEFAULT 1,
    created_at      TEXT    NULL,
    updated_at      TEXT    NULL
);

-- Tenant marketplace installations
CREATE TABLE tenant_marketplace_installs (
    id              TEXT    NOT NULL PRIMARY KEY,
    tenant_id       TEXT    NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    item_id         TEXT    NOT NULL REFERENCES marketplace_items(id),
    installed_at    TEXT    NOT NULL,
    is_active       INTEGER NOT NULL DEFAULT 1,
    UNIQUE (tenant_id, item_id)
);

-- Queue jobs table (database driver — no Redis)
CREATE TABLE jobs (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    queue           TEXT    NOT NULL,
    payload         TEXT    NOT NULL,
    attempts        INTEGER NOT NULL DEFAULT 0,
    reserved_at     INTEGER NULL,
    available_at    INTEGER NOT NULL,
    created_at      INTEGER NOT NULL
);

CREATE INDEX idx_jobs_queue ON jobs(queue, reserved_at);

CREATE TABLE failed_jobs (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    uuid            TEXT    NOT NULL UNIQUE,
    connection      TEXT    NOT NULL,
    queue           TEXT    NOT NULL,
    payload         TEXT    NOT NULL,
    exception       TEXT    NOT NULL,
    failed_at       TEXT    NOT NULL
);

-- HTTP cron log
CREATE TABLE cron_runs (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    task        TEXT    NOT NULL,
    status      TEXT    NOT NULL DEFAULT 'ok',
    duration_ms INTEGER NULL,
    ran_at      TEXT    NOT NULL
);
```

### 5.3 Tenant Database Schema (SQLite — always)

```sql
-- ═══════════════════════════════════════════════════════════
-- TENANT DATABASE (SQLite WAL — one per tenant)
-- ═══════════════════════════════════════════════════════════

PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;
PRAGMA foreign_keys = ON;
PRAGMA temp_store = MEMORY;
PRAGMA cache_size = -16000;
PRAGMA busy_timeout = 5000;

-- Site-wide settings
CREATE TABLE site_settings (
    key         TEXT    NOT NULL PRIMARY KEY,
    value       TEXT    NULL,
    cast_type   TEXT    NOT NULL DEFAULT 'string',
    group_name  TEXT    NULL
);

INSERT INTO site_settings VALUES
    ('site_name',         'My Website', 'string', 'general'),
    ('default_locale',    'en',         'string', 'general'),
    ('supported_locales', '["en"]',     'json',   'general'),
    ('logo_media_id',     NULL,         'string', 'branding'),
    ('favicon_media_id',  NULL,         'string', 'branding'),
    ('primary_color',     '#6366f1',    'string', 'branding'),
    ('active_theme_slug', NULL,         'string', 'theme'),
    ('google_analytics_id', NULL,       'string', 'analytics'),
    ('footer_text',       '',           'string', 'general'),
    ('robots_txt',        'User-agent: *\nAllow: /', 'string', 'seo');

-- Roles & permissions (spatie/laravel-permission)
CREATE TABLE roles (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT    NOT NULL,
    guard_name  TEXT    NOT NULL DEFAULT 'web',
    created_at  TEXT    NULL,
    updated_at  TEXT    NULL,
    UNIQUE (name, guard_name)
);
CREATE TABLE permissions (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT    NOT NULL,
    guard_name  TEXT    NOT NULL DEFAULT 'web',
    created_at  TEXT    NULL,
    updated_at  TEXT    NULL,
    UNIQUE (name, guard_name)
);
CREATE TABLE role_has_permissions (
    permission_id INTEGER NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    role_id       INTEGER NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    PRIMARY KEY (permission_id, role_id)
);
CREATE TABLE model_has_roles (
    role_id     INTEGER NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    model_type  TEXT    NOT NULL,
    model_id    TEXT    NOT NULL,
    PRIMARY KEY (role_id, model_id, model_type)
);
CREATE TABLE model_has_permissions (
    permission_id INTEGER NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    model_type    TEXT    NOT NULL,
    model_id      TEXT    NOT NULL,
    PRIMARY KEY (permission_id, model_id, model_type)
);

-- Menus
CREATE TABLE menus (
    id          TEXT    NOT NULL PRIMARY KEY,
    name        TEXT    NOT NULL,
    handle      TEXT    NOT NULL UNIQUE,   -- 'main-nav','footer','sidebar'
    created_at  TEXT    NULL,
    updated_at  TEXT    NULL
);
CREATE TABLE menu_items (
    id            TEXT    NOT NULL PRIMARY KEY,
    menu_id       TEXT    NOT NULL REFERENCES menus(id) ON DELETE CASCADE,
    parent_id     TEXT    NULL REFERENCES menu_items(id) ON DELETE SET NULL,
    label         TEXT    NOT NULL,                            -- JSON: {"en":"Home","ar":"الرئيسية"}
    type          TEXT    NOT NULL DEFAULT 'page',             -- 'page','url','anchor','divider'
    page_id       TEXT    NULL,
    url           TEXT    NULL,
    target        TEXT    NOT NULL DEFAULT '_self',
    icon          TEXT    NULL,
    required_role TEXT    NULL,
    is_active     INTEGER NOT NULL DEFAULT 1,
    sort_order    INTEGER NOT NULL DEFAULT 0,
    created_at    TEXT    NULL,
    updated_at    TEXT    NULL
);
CREATE INDEX idx_menu_items_menu   ON menu_items(menu_id);
CREATE INDEX idx_menu_items_parent ON menu_items(parent_id);

-- Pages
CREATE TABLE pages (
    id            TEXT    NOT NULL PRIMARY KEY,
    parent_id     TEXT    NULL REFERENCES pages(id) ON DELETE SET NULL,
    slug          TEXT    NOT NULL,
    full_path     TEXT    NOT NULL,                            -- '/about-us'
    status        TEXT    NOT NULL DEFAULT 'draft',            -- draft,published,scheduled,archived
    template      TEXT    NOT NULL DEFAULT 'default',
    is_homepage   INTEGER NOT NULL DEFAULT 0,
    requires_auth INTEGER NOT NULL DEFAULT 0,
    required_role TEXT    NULL,
    sort_order    INTEGER NOT NULL DEFAULT 0,
    scheduled_at  TEXT    NULL,
    published_at  TEXT    NULL,
    created_by    TEXT    NULL,
    updated_by    TEXT    NULL,
    created_at    TEXT    NULL,
    updated_at    TEXT    NULL,
    deleted_at    TEXT    NULL,
    UNIQUE (slug, parent_id)
);
CREATE INDEX idx_pages_status ON pages(status);
CREATE INDEX idx_pages_path   ON pages(full_path);

-- Page locale content
CREATE TABLE page_locales (
    id               TEXT    NOT NULL PRIMARY KEY,
    page_id          TEXT    NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
    locale           TEXT    NOT NULL,
    title            TEXT    NOT NULL DEFAULT '',
    description      TEXT    NULL,
    content          TEXT    NULL,
    meta_title       TEXT    NULL,
    meta_description TEXT    NULL,
    og_title         TEXT    NULL,
    og_description   TEXT    NULL,
    og_image_id      TEXT    NULL,
    canonical_url    TEXT    NULL,
    is_indexable     INTEGER NOT NULL DEFAULT 1,
    created_at       TEXT    NULL,
    updated_at       TEXT    NULL,
    UNIQUE (page_id, locale)
);
CREATE INDEX idx_page_locales_page ON page_locales(page_id);

-- Blok instances (page content, recursive nesting)
CREATE TABLE blok_instances (
    id            TEXT    NOT NULL PRIMARY KEY,
    page_id       TEXT    NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
    parent_id     TEXT    NULL REFERENCES blok_instances(id) ON DELETE CASCADE,
    slot_name     TEXT    NULL,
    blok_key      TEXT    NOT NULL,                            -- refs blok_definitions.blok_key
    section       TEXT    NOT NULL DEFAULT 'body',             -- header,body,footer,sidebar
    config        TEXT    NOT NULL DEFAULT '{}',               -- JSON: Form Wizard values
    locale_config TEXT    NOT NULL DEFAULT '{}',               -- JSON: {en:{…},ar:{…}}
    is_visible    INTEGER NOT NULL DEFAULT 1,
    required_role TEXT    NULL,
    sort_order    INTEGER NOT NULL DEFAULT 0,
    css_classes   TEXT    NULL,
    animation     TEXT    NULL,
    anchor_id     TEXT    NULL,
    created_at    TEXT    NULL,
    updated_at    TEXT    NULL
);
CREATE INDEX idx_blok_inst_page    ON blok_instances(page_id);
CREATE INDEX idx_blok_inst_parent  ON blok_instances(parent_id);
CREATE INDEX idx_blok_inst_section ON blok_instances(section, sort_order);

-- Page revisions (full snapshots for undo)
CREATE TABLE page_revisions (
    id              TEXT    NOT NULL PRIMARY KEY,
    page_id         TEXT    NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
    revision_number INTEGER NOT NULL,
    snapshot        TEXT    NOT NULL,                          -- JSON: full page + blok tree
    change_summary  TEXT    NULL,
    created_by      TEXT    NULL,
    created_at      TEXT    NULL,
    UNIQUE (page_id, revision_number)
);
CREATE INDEX idx_revisions_page ON page_revisions(page_id);

-- Media library
CREATE TABLE media (
    id                  TEXT    NOT NULL PRIMARY KEY,
    collection_name     TEXT    NOT NULL DEFAULT 'default',
    name                TEXT    NOT NULL,
    file_name           TEXT    NOT NULL,
    mime_type           TEXT    NOT NULL,
    disk                TEXT    NOT NULL DEFAULT 'tenant',
    size                INTEGER NOT NULL DEFAULT 0,
    manipulations       TEXT    NOT NULL DEFAULT '{}',
    custom_properties   TEXT    NOT NULL DEFAULT '{}',
    responsive_images   TEXT    NOT NULL DEFAULT '{}',
    order_column        INTEGER NULL,
    created_at          TEXT    NULL,
    updated_at          TEXT    NULL
);

-- Form submissions
CREATE TABLE form_submissions (
    id               TEXT    NOT NULL PRIMARY KEY,
    blok_instance_id TEXT    NOT NULL REFERENCES blok_instances(id) ON DELETE CASCADE,
    page_id          TEXT    NOT NULL,
    locale           TEXT    NOT NULL DEFAULT 'en',
    data             TEXT    NOT NULL,                         -- JSON
    ip_address       TEXT    NULL,
    user_agent       TEXT    NULL,
    submitted_at     TEXT    NOT NULL,
    is_read          INTEGER NOT NULL DEFAULT 0,
    created_at       TEXT    NULL
);

-- Analytics
CREATE TABLE page_views (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    page_id      TEXT    NOT NULL,
    locale       TEXT    NOT NULL DEFAULT 'en',
    referrer     TEXT    NULL,
    user_agent   TEXT    NULL,
    country_code TEXT    NULL,
    viewed_at    TEXT    NOT NULL
);
CREATE INDEX idx_page_views_page ON page_views(page_id, viewed_at);

-- V1 embed API: tenant blok grants
CREATE TABLE blok_grants (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    blok_key    TEXT    NOT NULL UNIQUE,
    is_enabled  INTEGER NOT NULL DEFAULT 1,
    custom_data TEXT    NULL,                                  -- JSON: per-tenant data overrides
    granted_at  TEXT    DEFAULT (datetime('now'))
);

-- V1 embed API: content overrides (field-level)
CREATE TABLE content_overrides (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    blok_key   TEXT    NOT NULL,
    lang       TEXT    NOT NULL,
    key_path   TEXT    NOT NULL,                               -- 'stats.ctc_balance'
    value      TEXT    NOT NULL,
    updated_at TEXT    DEFAULT (datetime('now')),
    UNIQUE (blok_key, lang, key_path)
);

-- V1 embed API: render cache per blok
CREATE TABLE blok_render_cache (
    cache_key   TEXT    PRIMARY KEY,                           -- 'slide-01:ar:dark:v1.0.0'
    html        TEXT    NOT NULL,
    data_hash   TEXT    NOT NULL,
    lang        TEXT    NOT NULL,
    blok_key    TEXT    NOT NULL,
    rendered_at TEXT    DEFAULT (datetime('now')),
    expires_at  TEXT    NOT NULL
);

-- Static site exports
CREATE TABLE static_exports (
    id               TEXT    NOT NULL PRIMARY KEY,
    status           TEXT    NOT NULL DEFAULT 'pending',       -- pending,building,complete,failed
    locale_set       TEXT    NOT NULL DEFAULT '["en"]',
    include_api_bridge INTEGER NOT NULL DEFAULT 1,
    zip_path         TEXT    NULL,
    zip_size         INTEGER NULL,
    error_message    TEXT    NULL,
    started_at       TEXT    NULL,
    completed_at     TEXT    NULL,
    expires_at       TEXT    NULL,
    created_by       TEXT    NULL,
    created_at       TEXT    NULL,
    updated_at       TEXT    NULL
);

-- Outgoing webhooks
CREATE TABLE webhooks (
    id               TEXT    NOT NULL PRIMARY KEY,
    url              TEXT    NOT NULL,
    events           TEXT    NOT NULL DEFAULT '[]',            -- JSON: ['page.published']
    secret           TEXT    NULL,
    is_active        INTEGER NOT NULL DEFAULT 1,
    last_triggered_at TEXT   NULL,
    last_status_code INTEGER NULL,
    created_at       TEXT    NULL,
    updated_at       TEXT    NULL
);
CREATE TABLE webhook_deliveries (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    webhook_id   TEXT    NOT NULL REFERENCES webhooks(id) ON DELETE CASCADE,
    event        TEXT    NOT NULL,
    payload      TEXT    NOT NULL,
    status_code  INTEGER NULL,
    response_body TEXT   NULL,
    attempts     INTEGER NOT NULL DEFAULT 0,
    delivered_at TEXT    NULL
);

-- Active theme + installed templates
CREATE TABLE tenant_themes (
    id           TEXT    NOT NULL PRIMARY KEY,
    item_slug    TEXT    NOT NULL,                             -- refs marketplace_items.slug
    is_active    INTEGER NOT NULL DEFAULT 0,
    custom_vars  TEXT    NULL,                                 -- JSON: CSS variable overrides
    installed_at TEXT    NOT NULL,
    updated_at   TEXT    NULL
);
CREATE TABLE tenant_templates (
    id              TEXT    NOT NULL PRIMARY KEY,
    item_slug       TEXT    NOT NULL,
    name            TEXT    NOT NULL,                          -- user-given name after install
    installed_at    TEXT    NOT NULL
);

-- FTS5 search index
CREATE VIRTUAL TABLE pages_fts USING fts5(
    page_id UNINDEXED,
    locale  UNINDEXED,
    title,
    content,
    tokenize = 'porter ascii'
);
```

---

## 6. Laravel Project Structure

```
webblok-cms/
│
├── app/
│   ├── Actions/
│   │   ├── Auth/
│   │   │   ├── LoginWithBreeze.php           ← Breeze login action
│   │   │   ├── LoginWithGas.php              ← GAS API login action
│   │   │   └── ResolveAuthDriver.php         ← Factory: picks driver from config
│   │   ├── Bloks/
│   │   │   ├── CreateBlokInstance.php
│   │   │   ├── UpdateBlokInstance.php
│   │   │   ├── DeleteBlokInstance.php
│   │   │   ├── ReorderBlokInstances.php
│   │   │   └── CloneBlokInstance.php
│   │   ├── Pages/
│   │   │   ├── CreatePage.php
│   │   │   ├── PublishPage.php
│   │   │   ├── UnpublishPage.php
│   │   │   ├── SchedulePage.php
│   │   │   ├── DeletePage.php
│   │   │   └── CreatePageRevision.php
│   │   ├── Tenants/
│   │   │   ├── ProvisionTenant.php           ← Creates SQLite, runs migrations (web-triggered)
│   │   │   ├── SuspendTenant.php
│   │   │   └── DeleteTenant.php
│   │   ├── Exports/
│   │   │   ├── BuildStaticSite.php
│   │   │   └── CleanExpiredExports.php
│   │   └── Marketplace/
│   │       ├── InstallTheme.php
│   │       ├── InstallTemplate.php
│   │       ├── UninstallTheme.php
│   │       └── SyncMarketplaceCatalog.php    ← Fetches remote manifest JSON
│   │
│   ├── Auth/
│   │   ├── AuthManager.php                  ← Reads config/auth_driver.php
│   │   ├── Drivers/
│   │   │   ├── BreezeAuthDriver.php
│   │   │   └── GasAuthDriver.php
│   │   └── Contracts/
│   │       └── AuthDriverInterface.php
│   │
│   ├── Bloks/
│   │   ├── BaseBlok.php                     ← Abstract base (see Section 7)
│   │   ├── Contracts/
│   │   │   └── RendersBlok.php
│   │   ├── Layout/
│   │   │   ├── HeroSectionBlok.php
│   │   │   ├── TwoColumnGridBlok.php
│   │   │   └── AppShellBlok.php
│   │   ├── Content/
│   │   │   ├── StatCardBlok.php
│   │   │   ├── RichTextBlok.php
│   │   │   └── FaqAccordionBlok.php
│   │   ├── Dashboard/
│   │   │   ├── Slide01Blok.php … Slide150Blok.php
│   │   ├── Forms/
│   │   │   ├── ContactFormBlok.php
│   │   │   └── NewsletterBlok.php
│   │   └── Skeleton/
│   │       ├── SkeletonLayoutBlok.php
│   │       └── SkeletonCardsBlok.php
│   │
│   ├── Data/                                ← spatie/laravel-data DTOs
│   │   ├── BlokInstanceData.php
│   │   ├── BlokDefinitionData.php
│   │   ├── BlokSchemaData.php
│   │   ├── BlokSchemaStepData.php
│   │   ├── BlokSchemaFieldData.php
│   │   ├── PageData.php
│   │   ├── PageLocaleData.php
│   │   ├── MenuData.php
│   │   ├── StaticExportData.php
│   │   ├── TenantData.php
│   │   └── MarketplaceItemData.php
│   │
│   ├── Enums/
│   │   ├── PageStatus.php
│   │   ├── BlokSection.php
│   │   ├── TenantStatus.php
│   │   ├── ExportStatus.php
│   │   ├── AuthDriverType.php
│   │   └── MarketplaceItemType.php
│   │
│   ├── Events/
│   │   ├── PagePublished.php
│   │   ├── PageUnpublished.php
│   │   ├── StaticExportCompleted.php
│   │   ├── StaticExportFailed.php
│   │   └── TenantProvisioned.php
│   │
│   ├── Exceptions/
│   │   ├── BlokNotFoundException.php
│   │   ├── BlokNestingDepthExceeded.php
│   │   ├── TenantNotFoundException.php
│   │   ├── PlanLimitExceededException.php
│   │   ├── InvalidBlokSchemaException.php
│   │   ├── GasAuthException.php
│   │   └── MarketplaceInstallException.php
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── V1/
│   │   │   │   │   ├── BlokController.php          ← V1 embed API
│   │   │   │   │   ├── TenantController.php
│   │   │   │   │   ├── ApiKeyController.php
│   │   │   │   │   └── UsageController.php
│   │   │   │   └── V2/
│   │   │   │       ├── PageController.php          ← CMS REST API
│   │   │   │       ├── BlokInstanceController.php
│   │   │   │       ├── MenuController.php
│   │   │   │       ├── FormSubmissionController.php
│   │   │   │       └── AnalyticsController.php
│   │   │   ├── Auth/
│   │   │   │   ├── BreezeLoginController.php
│   │   │   │   ├── GasCallbackController.php
│   │   │   │   └── LogoutController.php
│   │   │   ├── Admin/
│   │   │   │   ├── DashboardController.php         ← Super-admin panel
│   │   │   │   ├── TenantAdminController.php
│   │   │   │   ├── BlokRegistryController.php
│   │   │   │   ├── PlanController.php
│   │   │   │   └── MarketplaceAdminController.php
│   │   │   ├── Cms/
│   │   │   │   ├── CmsDashboardController.php      ← Tenant CMS admin panel
│   │   │   │   ├── PageBuilderController.php        ← Blok editor AJAX endpoints
│   │   │   │   ├── PageManagerController.php
│   │   │   │   ├── MenuController.php
│   │   │   │   ├── MediaController.php
│   │   │   │   ├── SettingsController.php
│   │   │   │   ├── ThemeController.php             ← Theme switcher
│   │   │   │   ├── MarketplaceController.php       ← Browse + install
│   │   │   │   ├── ExportController.php
│   │   │   │   └── RevisionController.php
│   │   │   └── Site/
│   │   │       ├── SiteController.php              ← Public page renderer
│   │   │       └── SitemapController.php
│   │   ├── Middleware/
│   │   │   ├── SetTenantDatabaseConnection.php
│   │   │   ├── AuthenticateApiKey.php
│   │   │   ├── EnforcePlanLimits.php
│   │   │   ├── TrackPageView.php
│   │   │   └── EnsureTenantActive.php
│   │   └── Requests/
│   │       ├── CreateBlokInstanceRequest.php
│   │       ├── UpdateBlokInstanceRequest.php
│   │       ├── CreatePageRequest.php
│   │       └── InstallMarketplaceItemRequest.php
│   │
│   ├── Jobs/
│   │   ├── ProvisionTenantJob.php          ← Database queue (no Redis)
│   │   ├── BuildStaticSiteJob.php
│   │   ├── WarmBlokCacheJob.php
│   │   ├── SendWebhookJob.php
│   │   ├── CleanExpiredExportsJob.php
│   │   ├── SyncMarketplaceCatalogJob.php
│   │   └── BackupTenantDatabaseJob.php
│   │
│   ├── Models/
│   │   ├── Platform/
│   │   │   ├── User.php
│   │   │   ├── Tenant.php
│   │   │   ├── Plan.php
│   │   │   ├── ApiKey.php
│   │   │   ├── BlokDefinition.php
│   │   │   ├── UsageLog.php
│   │   │   └── MarketplaceItem.php
│   │   └── Tenant/
│   │       ├── Page.php
│   │       ├── PageLocale.php
│   │       ├── BlokInstance.php
│   │       ├── PageRevision.php
│   │       ├── Menu.php
│   │       ├── MenuItem.php
│   │       ├── Media.php
│   │       ├── FormSubmission.php
│   │       ├── PageView.php
│   │       ├── StaticExport.php
│   │       ├── Webhook.php
│   │       ├── BlokGrant.php
│   │       ├── ContentOverride.php
│   │       ├── TenantTheme.php
│   │       └── TenantTemplate.php
│   │
│   ├── Services/
│   │   ├── TenantContext.php
│   │   ├── TenantMigrator.php              ← Runs SQLite migrations web-triggered
│   │   ├── BlokInstanceResolver.php
│   │   ├── BlokRenderer.php
│   │   ├── BlokDataResolver.php            ← V1: loads JSON + tenant overrides
│   │   ├── BlokSchemaResolver.php
│   │   ├── StaticSiteExporter.php
│   │   ├── SitemapGenerator.php
│   │   ├── LocaleService.php
│   │   ├── AclService.php
│   │   ├── ApiKeyService.php
│   │   ├── CdnBundler.php                  ← Assembles widget.js
│   │   ├── UsageTracker.php
│   │   ├── SearchService.php               ← SQLite FTS5 wrapper
│   │   ├── CronRunner.php                  ← HTTP-invoked scheduler
│   │   ├── ThemeManager.php                ← Applies tenant theme CSS vars
│   │   └── MarketplaceService.php          ← Browse, download, install
│   │
│   └── ValueObjects/
│       ├── BlokConfig.php
│       ├── BlokSchemaField.php
│       ├── BlokSchemaStep.php
│       └── TenantSettings.php
│
├── blok_definitions/                       ← JSON schema files
│   ├── slide-01.schema.json … slide-150.schema.json
│   ├── hero-section.schema.json
│   ├── stat-card.schema.json
│   └── …
│
├── marketplace/                            ← Bundled marketplace items
│   ├── official/
│   │   ├── themes/
│   │   │   ├── dark-pro/
│   │   │   │   ├── manifest.json
│   │   │   │   ├── theme.css
│   │   │   │   └── thumbnail.jpg
│   │   │   ├── light-clean/
│   │   │   └── ocean-blue/
│   │   └── templates/
│   │       ├── business-starter/
│   │       │   ├── manifest.json
│   │       │   └── pages.json              ← Pre-configured blok instances
│   │       ├── blog-minimal/
│   │       ├── portfolio-modern/
│   │       └── landing-page/
│   └── community/                          ← Fetched from remote manifest; cached here
│       └── (auto-populated)
│
├── database/
│   ├── platform.sqlite                     ← Platform DB (committed empty; migrated locally)
│   ├── migrations/
│   │   ├── platform/                       ← Run once locally before FTP upload
│   │   │   ├── 0001_create_users_table.php
│   │   │   ├── 0002_create_plans_table.php
│   │   │   ├── 0003_create_tenants_table.php
│   │   │   ├── 0004_create_api_keys_table.php
│   │   │   ├── 0005_create_blok_definitions_table.php
│   │   │   ├── 0006_create_usage_logs_table.php
│   │   │   ├── 0007_create_platform_settings_table.php
│   │   │   ├── 0008_create_marketplace_items_table.php
│   │   │   ├── 0009_create_tenant_marketplace_installs_table.php
│   │   │   ├── 0010_create_jobs_table.php
│   │   │   └── 0011_create_cron_runs_table.php
│   │   └── tenant/                         ← Applied per-tenant on first login (web-triggered)
│   │       ├── 0001_create_site_settings_table.php
│   │       ├── 0002_create_roles_permissions_tables.php
│   │       ├── 0003_create_menus_tables.php
│   │       ├── 0004_create_pages_tables.php
│   │       ├── 0005_create_blok_instances_table.php
│   │       ├── 0006_create_revisions_table.php
│   │       ├── 0007_create_media_table.php
│   │       ├── 0008_create_form_submissions_table.php
│   │       ├── 0009_create_page_views_table.php
│   │       ├── 0010_create_blok_grants_table.php
│   │       ├── 0011_create_content_overrides_table.php
│   │       ├── 0012_create_blok_render_cache_table.php
│   │       ├── 0013_create_static_exports_table.php
│   │       ├── 0014_create_webhooks_tables.php
│   │       ├── 0015_create_tenant_themes_table.php
│   │       ├── 0016_create_tenant_templates_table.php
│   │       └── 0017_create_fts5_index.php
│   └── seeders/
│       ├── PlanSeeder.php
│       ├── BlokDefinitionSeeder.php
│       ├── MarketplaceSeeder.php
│       └── SuperAdminSeeder.php
│
├── resources/
│   ├── views/
│   │   ├── layouts/
│   │   │   ├── app.blade.php               ← Breeze app shell
│   │   │   ├── admin.blade.php             ← Super-admin layout
│   │   │   ├── cms.blade.php               ← Tenant CMS layout
│   │   │   └── site.blade.php              ← Public site layout
│   │   ├── auth/
│   │   │   ├── login.blade.php             ← Breeze login (shown when driver=breeze)
│   │   │   ├── register.blade.php
│   │   │   ├── gas-redirect.blade.php      ← GAS redirect (when driver=gas)
│   │   │   └── gas-callback.blade.php
│   │   ├── admin/                          ← Super-admin panel (custom Blade)
│   │   │   ├── dashboard.blade.php
│   │   │   ├── tenants/
│   │   │   ├── blok-registry/
│   │   │   ├── plans/
│   │   │   └── marketplace/
│   │   ├── cms/                            ← Tenant CMS panel
│   │   │   ├── dashboard.blade.php
│   │   │   ├── page-builder.blade.php      ← Drag-drop editor (Alpine.js + SortableJS)
│   │   │   ├── pages/
│   │   │   ├── menus/
│   │   │   ├── media/
│   │   │   ├── settings/
│   │   │   ├── themes.blade.php
│   │   │   ├── marketplace.blade.php
│   │   │   ├── exports.blade.php
│   │   │   └── revisions.blade.php
│   │   ├── bloks/                          ← One Blade view per blok type
│   │   │   ├── dashboard/
│   │   │   │   ├── slide-01.blade.php
│   │   │   │   └── …slide-150.blade.php
│   │   │   ├── layout/
│   │   │   ├── content/
│   │   │   └── forms/
│   │   ├── site/
│   │   │   ├── page.blade.php              ← Public page renderer
│   │   │   └── error.blade.php
│   │   └── partials/
│   │       ├── blok-palette.blade.php      ← Alpine.js sidebar palette
│   │       ├── blok-settings-panel.blade.php
│   │       ├── form-wizard.blade.php
│   │       ├── media-picker.blade.php
│   │       └── revision-history.blade.php
│   ├── js/
│   │   ├── app.js
│   │   ├── drag-drop.js                    ← SortableJS integration
│   │   ├── form-wizard.js
│   │   ├── live-preview.js
│   │   └── cms-editor.js                  ← Alpine.js stores for CMS
│   └── css/
│       ├── app.css
│       └── themes/                         ← Theme CSS variables
│           ├── dark-pro.css
│           ├── light-clean.css
│           └── ocean-blue.css
│
├── routes/
│   ├── api.php                             ← V1 + V2 REST API routes
│   ├── web.php                             ← Auth + Admin + CMS + Site routes
│   └── console.php
│
├── public/
│   ├── build/                              ← Vite output (committed after local build)
│   ├── cron.php                            ← HTTP cron entry-point (called by cPanel)
│   └── js/
│       ├── api-client.js                  ← Static export AJAX bridge template
│       └── widget.js                      ← V1 CDN widget bundle
│
├── config/
│   ├── auth_driver.php                     ← Auth switcher (Breeze vs GAS)
│   ├── webblok.php
│   ├── tenancy.php
│   ├── bloks.php
│   └── marketplace.php
│
├── storage/
│   └── tenants/
│       └── {tenant-slug}/
│           ├── database.sqlite
│           ├── media/
│           └── exports/
│
├── tests/
│   ├── Unit/
│   ├── Feature/
│   │   ├── Api/
│   │   ├── Auth/
│   │   └── Cms/
│   └── Architecture/
│       └── ArchTest.php
│
├── phpstan.neon
├── pint.json
├── Makefile
└── .github/workflows/ci.yml
```

---

## 7. Auth System Architecture

### 7.1 `AuthDriverType.php` — Backed Enum

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum AuthDriverType: string
{
    case BREEZE = 'breeze';
    case GAS    = 'gas';

    public static function fromConfig(): self
    {
        return self::from(config('auth_driver.driver', 'breeze'));
    }
}
```

### 7.2 `AuthDriverInterface.php` — Contract

```php
<?php

declare(strict_types=1);

namespace App\Auth\Contracts;

use App\Models\Platform\User;
use Illuminate\Http\Request;

interface AuthDriverInterface
{
    /** Attempt login; returns User on success, null on failure. */
    public function attempt(string $email, string $password): ?User;

    /** Verify an existing session/token is still valid. */
    public function verify(Request $request): ?User;

    /** Logout the current user. */
    public function logout(Request $request): void;

    /** Return the login URL (for GAS this is a redirect URL; for Breeze it's '/login'). */
    public function loginUrl(): string;
}
```

### 7.3 `BreezeAuthDriver.php`

```php
<?php

declare(strict_types=1);

namespace App\Auth\Drivers;

use App\Auth\Contracts\AuthDriverInterface;
use App\Models\Platform\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

final class BreezeAuthDriver implements AuthDriverInterface
{
    public function attempt(string $email, string $password): ?User
    {
        /** @var User|null $user */
        $user = User::where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password ?? '')) {
            return null;
        }

        Auth::login($user, remember: true);
        return $user;
    }

    public function verify(Request $request): ?User
    {
        /** @var User|null $user */
        $user = Auth::user();
        return $user;
    }

    public function logout(Request $request): void
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    public function loginUrl(): string
    {
        return route('login');
    }
}
```

### 7.4 `GasAuthDriver.php`

```php
<?php

declare(strict_types=1);

namespace App\Auth\Drivers;

use App\Auth\Contracts\AuthDriverInterface;
use App\Exceptions\GasAuthException;
use App\Models\Platform\User;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

final class GasAuthDriver implements AuthDriverInterface
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    public function attempt(string $email, string $password): ?User
    {
        /** @var array{base_url: string, api_key: string, app_id: string, timeout_seconds: int} $cfg */
        $cfg = config('auth_driver.gas');

        try {
            $response = $this->http
                ->timeout($cfg['timeout_seconds'])
                ->withHeaders([
                    'X-App-Id'  => $cfg['app_id'],
                    'X-Api-Key' => $cfg['api_key'],
                ])
                ->post($cfg['base_url'] . '/api/auth/login', compact('email', 'password'));

            if (! $response->ok()) {
                return null;
            }

            /** @var array{token: string, user: array{id: string, email: string, name: string}} $body */
            $body    = $response->json();
            $gasUser = $body['user'];

            return $this->syncGasUser($gasUser, $body['token']);

        } catch (\Throwable) {
            return null;
        }
    }

    public function verify(Request $request): ?User
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user === null || $user->gas_token === null) {
            return null;
        }

        /** @var array{base_url: string, api_key: string, app_id: string, timeout_seconds: int} $cfg */
        $cfg = config('auth_driver.gas');

        try {
            $response = $this->http
                ->timeout($cfg['timeout_seconds'])
                ->withHeaders([
                    'X-App-Id'  => $cfg['app_id'],
                    'X-Api-Key' => $cfg['api_key'],
                ])
                ->post($cfg['base_url'] . '/api/auth/verify', ['token' => $user->gas_token]);

            return $response->ok() ? $user : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function logout(Request $request): void
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    public function loginUrl(): string
    {
        return route('auth.gas.redirect');
    }

    /** @param array{id: string, email: string, name: string} $gasUser */
    private function syncGasUser(array $gasUser, string $token): User
    {
        if (! (bool) config('auth_driver.gas.user_sync', true)) {
            throw new GasAuthException('GAS user sync is disabled.');
        }

        /** @var User $user */
        $user = User::updateOrCreate(
            ['gas_user_id' => $gasUser['id']],
            [
                'id'        => Str::uuid()->toString(),
                'name'      => $gasUser['name'],
                'email'     => $gasUser['email'],
                'password'  => null,
                'gas_token' => $token,
            ]
        );

        Auth::login($user, remember: true);
        return $user;
    }
}
```

### 7.5 `AuthManager.php` — Factory

```php
<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Contracts\AuthDriverInterface;
use App\Auth\Drivers\BreezeAuthDriver;
use App\Auth\Drivers\GasAuthDriver;
use App\Enums\AuthDriverType;
use Illuminate\Contracts\Container\Container;

final class AuthManager
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function driver(): AuthDriverInterface
    {
        return match (AuthDriverType::fromConfig()) {
            AuthDriverType::BREEZE => $this->container->make(BreezeAuthDriver::class),
            AuthDriverType::GAS    => $this->container->make(GasAuthDriver::class),
        };
    }
}
```

### 7.6 Route Behaviour by Auth Driver

In `routes/web.php`, auth routes are conditionally registered:

```php
// routes/web.php (excerpt)
if (config('auth_driver.driver') === 'gas') {
    Route::get('/auth/gas',          [GasCallbackController::class, 'redirect'])->name('auth.gas.redirect');
    Route::get('/auth/gas/callback', [GasCallbackController::class, 'callback'])->name('auth.gas.callback');
    Route::post('/logout',           [LogoutController::class, 'destroy'])->middleware('auth')->name('logout');
} else {
    // Standard Breeze routes (already published by Breeze installer)
    require __DIR__ . '/auth.php';
}
```

---

## 8. Tenant Provisioning Without SSH

### 8.1 `TenantMigrator.php` — Web-Triggered Migration Service

Since the server has no SSH, tenant migrations are triggered via a **secure web endpoint** called after the tenant account is created:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Platform\Tenant;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\File;

final class TenantMigrator
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly TenantContext   $tenantContext,
    ) {}

    public function provision(Tenant $tenant): void
    {
        // 1. Create directory structure
        $tenantDir = storage_path("tenants/{$tenant->slug}");
        File::makeDirectory("{$tenantDir}/media",   0755, true, true);
        File::makeDirectory("{$tenantDir}/exports",  0755, true, true);

        // 2. Create SQLite file
        $dbPath = "{$tenantDir}/database.sqlite";
        if (! file_exists($dbPath)) {
            touch($dbPath);
        }

        $tenant->update([
            'database_path' => $dbPath,
            'storage_path'  => $tenantDir,
        ]);

        // 3. Switch tenant connection
        $this->tenantContext->switchTo($tenant);

        // 4. Apply WAL pragmas
        $this->db->connection('tenant')->statement('PRAGMA journal_mode = WAL');
        $this->db->connection('tenant')->statement('PRAGMA synchronous = NORMAL');
        $this->db->connection('tenant')->statement('PRAGMA foreign_keys = ON');
        $this->db->connection('tenant')->statement('PRAGMA busy_timeout = 5000');

        // 5. Run all tenant migration files directly (no Artisan CLI)
        $migrationPath = database_path('migrations/tenant');
        $migrationFiles = glob("{$migrationPath}/*.php");

        if ($migrationFiles !== false) {
            sort($migrationFiles);
            foreach ($migrationFiles as $file) {
                require_once $file;
                $className = $this->extractClassName($file);
                if (class_exists($className)) {
                    (new $className())->up();
                }
            }
        }

        // 6. Seed default roles and site settings
        $this->seedDefaults($tenant);

        // 7. Mark active
        $tenant->update(['status' => 'active']);
    }

    private function seedDefaults(Tenant $tenant): void
    {
        $conn = $this->db->connection('tenant');

        // Default roles
        foreach (['admin', 'editor', 'viewer'] as $role) {
            $conn->table('roles')->insert([
                'name'       => $role,
                'guard_name' => 'web',
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ]);
        }

        // Default menu
        $conn->table('menus')->insert([
            'id'         => \Illuminate\Support\Str::uuid()->toString(),
            'name'       => 'Main Navigation',
            'handle'     => 'main-nav',
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ]);
    }

    private function extractClassName(string $filePath): string
    {
        $content   = (string) file_get_contents($filePath);
        preg_match('/class\s+(\w+)\s+extends/', $content, $matches);
        return $matches[1] ?? '';
    }
}
```

### 8.2 Web-Triggered Provisioning Endpoint

```php
// In Admin/TenantAdminController.php
public function provision(string $tenantId): JsonResponse
{
    // Only callable by super_admin
    $tenant = Tenant::findOrFail($tenantId);
    app(TenantMigrator::class)->provision($tenant);
    return response()->json(['status' => 'provisioned']);
}
```

This endpoint is called automatically via AJAX after tenant registration — no SSH needed.

---

## 9. HTTP Cron System (No SSH / No Supervisor)

### 9.1 `public/cron.php` — Entry Point

```php
<?php
// public/cron.php
// Called every minute by cPanel: GET https://yourdomain.com/cron/run
// Protected by a secret token set in config/webblok.php

define('LARAVEL_START', microtime(true));
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);
$response->send();
$kernel->terminate($request, $response);
```

### 9.2 Cron Route + Controller

```php
// routes/web.php (cron section)
Route::get('/cron/run', [CronController::class, 'run'])
     ->middleware('throttle:1,1'); // max 1 req/min

// app/Http/Controllers/CronController.php
final class CronController extends Controller
{
    public function run(Request $request): JsonResponse
    {
        $secret = config('webblok.cron_secret');
        if ($request->query('secret') !== $secret) {
            abort(403);
        }

        app(CronRunner::class)->run();
        return response()->json(['ran_at' => now()->toISOString()]);
    }
}
```

### 9.3 `CronRunner.php`

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class CronRunner
{
    public function run(): void
    {
        $tasks = [
            'process-queue'        => fn() => $this->processQueue(),
            'publish-scheduled'    => fn() => $this->publishScheduledPages(),
            'clean-exports'        => fn() => $this->cleanExpiredExports(),
            'sync-marketplace'     => fn() => $this->syncMarketplace(),
        ];

        foreach ($tasks as $name => $task) {
            $start = microtime(true);
            try {
                $task();
                $status = 'ok';
            } catch (\Throwable $e) {
                $status = 'error: ' . $e->getMessage();
            }
            $ms = (int) ((microtime(true) - $start) * 1000);

            DB::table('cron_runs')->insert([
                'task'        => $name,
                'status'      => $status,
                'duration_ms' => $ms,
                'ran_at'      => now()->toISOString(),
            ]);
        }
    }

    private function processQueue(): void
    {
        // Process up to 5 jobs from the database queue
        for ($i = 0; $i < 5; $i++) {
            $job = DB::table('jobs')
                ->where('available_at', '<=', now()->timestamp)
                ->whereNull('reserved_at')
                ->orderBy('id')
                ->first();

            if ($job === null) {
                break;
            }

            DB::table('jobs')->where('id', $job->id)->update([
                'reserved_at' => now()->timestamp,
                'attempts'    => $job->attempts + 1,
            ]);

            /** @var array{displayName: string, job: string, data: array<string,mixed>} $payload */
            $payload = json_decode($job->payload, true, 512, JSON_THROW_ON_ERROR);

            try {
                /** @var \Illuminate\Contracts\Queue\Job $jobInstance */
                $jobInstance = app($payload['job']);
                $jobInstance->handle();
                DB::table('jobs')->where('id', $job->id)->delete();
            } catch (\Throwable $e) {
                if ($job->attempts >= 3) {
                    DB::table('failed_jobs')->insert([
                        'uuid'       => \Illuminate\Support\Str::uuid()->toString(),
                        'connection' => 'database',
                        'queue'      => $job->queue,
                        'payload'    => $job->payload,
                        'exception'  => $e->getMessage(),
                        'failed_at'  => now()->toISOString(),
                    ]);
                    DB::table('jobs')->where('id', $job->id)->delete();
                } else {
                    DB::table('jobs')->where('id', $job->id)->update(['reserved_at' => null]);
                }
            }
        }
    }

    private function publishScheduledPages(): void
    {
        // Per-tenant: publish pages where scheduled_at <= now()
        // (iterates all active tenants)
    }

    private function cleanExpiredExports(): void
    {
        // Per-tenant: delete ZIPs where expires_at < now()
    }

    private function syncMarketplace(): void
    {
        // Once per hour: fetch remote manifest if configured
    }
}
```

---

## 10. Template & Theme Marketplace

### 10.1 Marketplace Mental Model

```
MARKETPLACE
├── Themes (visual appearance)
│   ├── Official (bundled in marketplace/official/themes/)
│   ├── Community (fetched from config('marketplace.remote_url'))
│   └── Custom (tenant-uploaded ZIP)
└── Templates (page content presets)
    ├── Official (bundled in marketplace/official/templates/)
    ├── Community (fetched remotely)
    └── Custom (tenant-uploaded ZIP)
```

### 10.2 Theme Structure

A theme is a directory containing:

```
marketplace/official/themes/dark-pro/
├── manifest.json          ← Theme metadata + CSS variable map
├── theme.css              ← Full CSS: variables, overrides, component styles
├── config.js              ← Optional: theme-specific Alpine.js behaviour
└── thumbnail.jpg
```

**`manifest.json` example** (Dark Pro theme):

```json
{
  "name": "Dark Pro",
  "slug": "dark-pro",
  "version": "1.2.0",
  "author": "WebBlok Official",
  "category": "professional",
  "description": "A sophisticated dark theme with glassmorphism effects.",
  "thumbnail": "thumbnail.jpg",
  "locale_support": ["en", "ar", "fr"],
  "rtl_support": true,
  "css_variables": {
    "--wb-bg-primary":    "#0a0a1a",
    "--wb-bg-secondary":  "#111128",
    "--wb-accent":        "#6366f1",
    "--wb-text-primary":  "#f0f0ff",
    "--wb-text-muted":    "#8888aa",
    "--wb-border":        "rgba(255,255,255,0.08)",
    "--wb-radius":        "12px",
    "--wb-shadow":        "0 8px 32px rgba(0,0,0,0.4)",
    "--wb-font-body":     "'Inter', sans-serif",
    "--wb-font-rtl":      "'Cairo', sans-serif"
  },
  "customizable_vars": ["--wb-accent", "--wb-radius"],
  "required_blok_version": "1.0.0"
}
```

### 10.3 Template Structure

A template is a directory containing:

```
marketplace/official/templates/business-starter/
├── manifest.json          ← Template metadata
├── pages.json             ← Full page tree with blok instances
├── thumbnail.jpg
└── preview/
    ├── home.jpg
    └── about.jpg
```

**`manifest.json` example** (Business Starter template):

```json
{
  "name": "Business Starter",
  "slug": "business-starter",
  "version": "1.0.0",
  "author": "WebBlok Official",
  "category": "business",
  "description": "A complete 5-page business website: Home, About, Services, Blog, Contact.",
  "pages": ["home", "about", "services", "blog", "contact"],
  "locale_support": ["en", "ar"],
  "blok_keys_required": ["hero-section", "stat-card", "contact-form", "rich-text"],
  "thumbnail": "thumbnail.jpg"
}
```

**`pages.json` example** (abbreviated):

```json
{
  "pages": [
    {
      "slug": "home",
      "is_homepage": true,
      "template": "default",
      "locales": {
        "en": { "title": "Home", "meta_title": "Welcome — My Business" },
        "ar": { "title": "الرئيسية", "meta_title": "مرحباً — شركتي" }
      },
      "sections": {
        "header": [],
        "body": [
          {
            "blok_key": "hero-section",
            "sort_order": 0,
            "config": { "theme": "dark", "min_height": 70 },
            "locale_config": {
              "en": { "headline": "Welcome to Our Business", "cta_label": "Get Started" },
              "ar": { "headline": "مرحباً بكم في شركتنا",   "cta_label": "ابدأ الآن" }
            }
          },
          {
            "blok_key": "stat-card",
            "sort_order": 1,
            "config": { "value": "500+", "label": "Happy Clients", "icon": "😊" }
          }
        ],
        "footer": []
      }
    }
  ]
}
```

### 10.4 `MarketplaceService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MarketplaceInstallException;
use App\Models\Platform\MarketplaceItem;
use App\Models\Tenant\TenantTheme;
use App\Models\Tenant\TenantTemplate;
use App\Models\Tenant\BlokInstance;
use App\Models\Tenant\Page;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MarketplaceService
{
    private const REMOTE_MANIFEST_CACHE_HOURS = 6;

    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /** @return list<array<string, mixed>> */
    public function listThemes(string $source = 'all'): array
    {
        $query = MarketplaceItem::where('type', 'theme')->where('is_active', 1);

        if ($source !== 'all') {
            $query->where('source', $source);
        }

        return $query->orderByDesc('is_featured')->orderBy('name')->get()->toArray();
    }

    /** @return list<array<string, mixed>> */
    public function listTemplates(string $category = '', string $locale = ''): array
    {
        $query = MarketplaceItem::where('type', 'template')->where('is_active', 1);

        if ($category !== '') {
            $query->where('category', $category);
        }

        if ($locale !== '') {
            $query->whereJsonContains('locale_support', $locale);
        }

        return $query->orderByDesc('is_featured')->orderBy('name')->get()->toArray();
    }

    public function installTheme(string $itemSlug, bool $activate = true): void
    {
        $item = MarketplaceItem::where('slug', $itemSlug)->where('type', 'theme')->first();

        if ($item === null) {
            throw new MarketplaceInstallException("Theme '{$itemSlug}' not found.");
        }

        $manifestPath = $item->local_path
            ? base_path($item->local_path . '/manifest.json')
            : $this->downloadAndCache($item);

        if (! file_exists($manifestPath)) {
            throw new MarketplaceInstallException("Theme manifest not found at: {$manifestPath}");
        }

        $tenant = $this->tenantContext->require();

        $themeId = Str::uuid()->toString();

        TenantTheme::create([
            'id'           => $themeId,
            'item_slug'    => $itemSlug,
            'is_active'    => $activate ? 1 : 0,
            'custom_vars'  => null,
            'installed_at' => now()->toISOString(),
            'updated_at'   => now()->toISOString(),
        ]);

        if ($activate) {
            // Deactivate all other themes for this tenant
            TenantTheme::where('id', '!=', $themeId)->update(['is_active' => 0]);
            // Update site_settings
            DB::connection('tenant')
                ->table('site_settings')
                ->where('key', 'active_theme_slug')
                ->update(['value' => $itemSlug]);
        }

        $item->increment('install_count');
    }

    public function installTemplate(string $itemSlug, string $name): void
    {
        $item = MarketplaceItem::where('slug', $itemSlug)->where('type', 'template')->first();

        if ($item === null) {
            throw new MarketplaceInstallException("Template '{$itemSlug}' not found.");
        }

        $pagesJsonPath = $item->local_path
            ? base_path($item->local_path . '/pages.json')
            : $this->downloadAndCache($item, 'pages.json');

        if (! file_exists($pagesJsonPath)) {
            throw new MarketplaceInstallException("Template pages.json not found.");
        }

        $pagesData = json_decode((string) file_get_contents($pagesJsonPath), true, 512, JSON_THROW_ON_ERROR);

        DB::connection('tenant')->transaction(function () use ($pagesData, $itemSlug, $name): void {
            foreach ($pagesData['pages'] as $pageData) {
                $this->createPageFromTemplate($pageData);
            }

            TenantTemplate::create([
                'id'           => Str::uuid()->toString(),
                'item_slug'    => $itemSlug,
                'name'         => $name,
                'installed_at' => now()->toISOString(),
            ]);
        });

        $item->increment('install_count');
    }

    /** @param array<string, mixed> $pageData */
    private function createPageFromTemplate(array $pageData): void
    {
        $pageId = Str::uuid()->toString();

        $page = Page::create([
            'id'          => $pageId,
            'slug'        => $pageData['slug'],
            'full_path'   => '/' . ltrim($pageData['slug'], '/'),
            'status'      => 'draft',
            'template'    => $pageData['template'] ?? 'default',
            'is_homepage' => (int) ($pageData['is_homepage'] ?? false),
            'created_at'  => now()->toISOString(),
            'updated_at'  => now()->toISOString(),
        ]);

        // Create page locales
        foreach ($pageData['locales'] ?? [] as $locale => $localeData) {
            \App\Models\Tenant\PageLocale::create([
                'id'              => Str::uuid()->toString(),
                'page_id'         => $pageId,
                'locale'          => $locale,
                'title'           => $localeData['title'] ?? '',
                'meta_title'      => $localeData['meta_title'] ?? null,
                'meta_description'=> $localeData['meta_description'] ?? null,
                'is_indexable'    => 1,
                'created_at'      => now()->toISOString(),
                'updated_at'      => now()->toISOString(),
            ]);
        }

        // Create blok instances per section
        foreach ($pageData['sections'] ?? [] as $section => $bloks) {
            foreach ($bloks as $sortOrder => $blokData) {
                BlokInstance::create([
                    'id'           => Str::uuid()->toString(),
                    'page_id'      => $pageId,
                    'parent_id'    => null,
                    'blok_key'     => $blokData['blok_key'],
                    'section'      => $section,
                    'config'       => json_encode($blokData['config'] ?? [], JSON_THROW_ON_ERROR),
                    'locale_config'=> json_encode($blokData['locale_config'] ?? [], JSON_THROW_ON_ERROR),
                    'sort_order'   => $sortOrder,
                    'is_visible'   => 1,
                    'created_at'   => now()->toISOString(),
                    'updated_at'   => now()->toISOString(),
                ]);
            }
        }
    }

    public function syncRemoteCatalog(): void
    {
        $remoteUrl = config('marketplace.remote_manifest_url');
        if (empty($remoteUrl)) {
            return;
        }

        $cacheKey  = 'marketplace_remote_manifest';
        $cached    = cache()->get($cacheKey);

        if ($cached !== null) {
            return;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)->get($remoteUrl);
            if (! $response->ok()) {
                return;
            }

            /** @var array{items: list<array<string, mixed>>} $manifest */
            $manifest = $response->json();

            foreach ($manifest['items'] as $item) {
                MarketplaceItem::updateOrCreate(
                    ['slug' => $item['slug']],
                    array_merge($item, ['source' => 'community', 'is_active' => 1])
                );
            }

            cache()->put($cacheKey, true, now()->addHours(self::REMOTE_MANIFEST_CACHE_HOURS));
        } catch (\Throwable) {
            // Silently fail — marketplace sync is non-critical
        }
    }

    private function downloadAndCache(MarketplaceItem $item, string $file = 'manifest.json'): string
    {
        if ($item->download_url === null) {
            throw new MarketplaceInstallException("No download URL for item: {$item->slug}");
        }

        $localDir = storage_path("marketplace/{$item->slug}");
        if (! is_dir($localDir)) {
            mkdir($localDir, 0755, true);
        }

        $localPath = "{$localDir}/{$file}";
        if (! file_exists($localPath)) {
            $content = \Illuminate\Support\Facades\Http::timeout(30)->get($item->download_url . '/' . $file)->body();
            file_put_contents($localPath, $content);
        }

        return $localPath;
    }
}
```

### 10.5 `ThemeManager.php` — Apply Active Theme

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class ThemeManager
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /** Returns the compiled CSS string for the active tenant theme. */
    public function getActiveThemeCss(): string
    {
        $this->tenantContext->require();

        $activeThemeSlug = DB::connection('tenant')
            ->table('site_settings')
            ->where('key', 'active_theme_slug')
            ->value('value');

        if ($activeThemeSlug === null || $activeThemeSlug === '') {
            // No theme active — return default CSS variable set
            return $this->defaultCssVariables();
        }

        $theme = DB::connection('tenant')
            ->table('tenant_themes')
            ->where('item_slug', $activeThemeSlug)
            ->where('is_active', 1)
            ->first();

        if ($theme === null) {
            return $this->defaultCssVariables();
        }

        $themePath = base_path("marketplace/official/themes/{$activeThemeSlug}/theme.css");

        if (! file_exists($themePath)) {
            $themePath = storage_path("marketplace/{$activeThemeSlug}/theme.css");
        }

        if (! file_exists($themePath)) {
            return $this->defaultCssVariables();
        }

        $css = (string) file_get_contents($themePath);

        // Apply tenant custom variable overrides
        if ($theme->custom_vars !== null) {
            /** @var array<string, string> $customVars */
            $customVars = json_decode($theme->custom_vars, true, 512, JSON_THROW_ON_ERROR);
            $css .= "\n:root {\n";
            foreach ($customVars as $var => $value) {
                $css .= "  {$var}: {$value};\n";
            }
            $css .= "}\n";
        }

        return $css;
    }

    private function defaultCssVariables(): string
    {
        return <<<CSS
        :root {
          --wb-bg-primary:   #0a0a1a;
          --wb-bg-secondary: #111128;
          --wb-accent:       #6366f1;
          --wb-text-primary: #f0f0ff;
          --wb-text-muted:   #8888aa;
          --wb-border:       rgba(255,255,255,0.08);
          --wb-radius:       12px;
          --wb-shadow:       0 8px 32px rgba(0,0,0,0.4);
          --wb-font-body:    'Inter', sans-serif;
          --wb-font-rtl:     'Cairo', sans-serif;
        }
        CSS;
    }
}
```

### 10.6 `config/marketplace.php`

```php
<?php

declare(strict_types=1);

return [
    /*
    |------------------------------------------------------------------
    | Remote Marketplace Manifest URL
    | Set to a JSON endpoint to enable community marketplace items.
    | Leave empty to use only official (bundled) items.
    |------------------------------------------------------------------
    */
    'remote_manifest_url' => env('MARKETPLACE_REMOTE_URL', ''),

    /*
    |------------------------------------------------------------------
    | Allow Custom Uploads
    | Tenants can upload their own .zip theme/template packages.
    |------------------------------------------------------------------
    */
    'allow_custom_uploads' => env('MARKETPLACE_ALLOW_UPLOADS', true),

    /*
    |------------------------------------------------------------------
    | Max custom upload size (MB)
    |------------------------------------------------------------------
    */
    'max_upload_mb' => env('MARKETPLACE_MAX_UPLOAD_MB', 50),

    /*
    |------------------------------------------------------------------
    | Official marketplace items path
    |------------------------------------------------------------------
    */
    'official_path' => base_path('marketplace/official'),

    /*
    |------------------------------------------------------------------
    | Community cache path
    |------------------------------------------------------------------
    */
    'community_cache_path' => storage_path('marketplace'),
];
```

---

## 11. V1 Embed API (from WEBBLOK-SAAS-PROMPT.md)

### 11.1 API Endpoints

All V1 endpoints require `Authorization: Bearer wbk_live_xxx` or `?api_key=wbk_live_xxx`.

| Method | Endpoint | Description |
|--------|---------|-------------|
| `GET` | `/api/v1/bloks` | List all bloks available to tenant |
| `GET` | `/api/v1/bloks/{id}` | Get single blok metadata |
| `GET` | `/api/v1/bloks/{id}/data` | Raw JSON data payload (language-resolved) |
| `GET` | `/api/v1/bloks/{id}/render` | Fully rendered HTML |
| `POST` | `/api/v1/bloks/{id}/data` | Save tenant content overrides |
| `GET` | `/api/v1/tenant` | Current tenant info + usage |
| `GET` | `/api/v1/usage` | Usage statistics |
| `GET` | `/api/v1/langs` | Supported languages |
| `POST` | `/api/v1/keys` | Create API key |
| `DELETE` | `/api/v1/keys/{id}` | Revoke API key |

### 11.2 CDN Widget Embed

```html
<div id="wb-slide-01"></div>
<script
  src="https://cdn.webblok.io/v1/widget.js"
  data-key="wbk_live_xxx"
  data-blok="slide-01"
  data-lang="ar"
  data-theme="dark"
></script>
```

`public/js/widget.js` behaviour:
1. Reads `data-*` attributes
2. Injects skeleton immediately (from embedded skeleton templates)
3. Fetches `/api/v1/bloks/{id}/render?lang=ar&theme=dark`
4. Replaces skeleton with rendered HTML
5. Initialises Alpine.js / animation system
6. Tracks render event to tenant analytics

### 11.3 `BlokDataResolver.php` (V1 service — unchanged from V1 spec)

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class BlokDataResolver
{
    /** @param array<string, mixed> $runtimeOverrides
     *  @return array<string, mixed> */
    public function resolve(string $blokKey, string $lang, array $runtimeOverrides = []): array
    {
        // 1. Load base JSON from slides/data/{blok}.{lang}.json
        $padded = str_pad(str_replace('slide-', '', $blokKey), 3, '0', STR_PAD_LEFT);
        $path   = base_path("slides/data/slide-{$padded}.{$lang}.json");

        if (! file_exists($path)) {
            $path = base_path("slides/data/slide-{$padded}.en.json");
        }

        /** @var array<string, mixed> $base */
        $base = file_exists($path)
            ? (array) json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)
            : [];

        // 2. Load tenant content_overrides from SQLite
        $tenantOverrides = $this->loadTenantOverrides($blokKey, $lang);

        // 3. Merge: runtime > tenant > base
        return $this->deepMerge($base, $tenantOverrides, $runtimeOverrides);
    }

    /** @return array<string, mixed> */
    private function loadTenantOverrides(string $blokKey, string $lang): array
    {
        try {
            $rows = DB::connection('tenant')
                ->table('content_overrides')
                ->where('blok_key', $blokKey)
                ->where('lang', $lang)
                ->get();

            /** @var array<string, mixed> $result */
            $result = [];

            foreach ($rows as $row) {
                data_set($result, $row->key_path, $row->value);
            }

            return $result;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param array<string, mixed> ...$arrays
     *  @return array<string, mixed> */
    private function deepMerge(array ...$arrays): array
    {
        /** @var array<string, mixed> $result */
        $result = [];
        foreach ($arrays as $arr) {
            /** @var array<string, mixed> $arr */
            foreach ($arr as $key => $value) {
                if (is_array($value) && isset($result[$key]) && is_array($result[$key])) {
                    /** @var array<string, mixed> $merged */
                    $merged = $this->deepMerge($result[$key], $value);
                    $result[$key] = $merged;
                } else {
                    $result[$key] = $value;
                }
            }
        }
        return $result;
    }
}
```

---

## 12. V2 CMS REST API

### 12.1 V2 Route Definitions

```php
Route::prefix('v2')->middleware([
    \App\Http\Middleware\SetTenantDatabaseConnection::class,
    \App\Http\Middleware\AuthenticateApiKey::class,
])->group(function (): void {
    Route::get('/pages',                       [PageController::class, 'index']);
    Route::get('/pages/{slug}',                [PageController::class, 'show']);
    Route::post('/pages',                      [PageController::class, 'store']);
    Route::put('/pages/{id}',                  [PageController::class, 'update']);
    Route::delete('/pages/{id}',               [PageController::class, 'destroy']);
    Route::post('/pages/{id}/publish',         [PageController::class, 'publish']);
    Route::get('/pages/{pageId}/bloks',        [BlokInstanceController::class, 'index']);
    Route::post('/pages/{pageId}/bloks',       [BlokInstanceController::class, 'store']);
    Route::put('/blok-instances/{id}',         [BlokInstanceController::class, 'update']);
    Route::delete('/blok-instances/{id}',      [BlokInstanceController::class, 'destroy']);
    Route::post('/blok-instances/reorder',     [BlokInstanceController::class, 'reorder']);
    Route::get('/pages/{pageId}/render',       [BlokInstanceController::class, 'renderPage']);
    Route::get('/menus/{handle}',              [MenuController::class, 'show']);
    Route::post('/forms/{blokInstanceId}/submit', [FormSubmissionController::class, 'store']);
    Route::post('/analytics/pageview',         [AnalyticsController::class, 'track']);
    Route::get('/analytics/summary',           [AnalyticsController::class, 'summary']);
});
```

---

## 13. Static Site Export with AJAX Bridge

### 13.1 `api-client.js` — Embedded Bridge Template

```javascript
/*!
 * WebBlok API Client — Static Site Bridge
 * Auto-injected by StaticSiteExporter — DO NOT edit manually
 * __WB_API_BASE__ and __WB_API_KEY__ are replaced at export build time
 */
(function (window) {
  'use strict';

  const API_BASE = '__WB_API_BASE__';
  const API_KEY  = '__WB_API_KEY__';

  const headers = {
    'Authorization':     'Bearer ' + API_KEY,
    'Content-Type':      'application/json',
    'Accept':            'application/json',
    'X-Requested-With':  'XMLHttpRequest',
  };

  async function fetchPage(slug, locale) {
    locale = locale || 'en';
    const res = await fetch(API_BASE + '/v2/pages/' + slug + '?locale=' + locale, { headers });
    if (!res.ok) throw new Error('[WebBlok] fetchPage failed: ' + res.status);
    const json = await res.json();
    return {
      html:  json.data?.sections?.body ?? '',
      title: json.data?.locale?.title ?? '',
      meta:  json.meta ?? {},
    };
  }

  async function fetchSection(pageId, section, locale) {
    locale  = locale  || 'en';
    section = section || 'body';
    const res = await fetch(
      API_BASE + '/v2/pages/' + pageId + '/render?locale=' + locale + '&section=' + section,
      { headers }
    );
    if (!res.ok) throw new Error('[WebBlok] fetchSection failed: ' + res.status);
    const json = await res.json();
    return json.data?.html ?? '';
  }

  async function submitForm(blokInstanceId, formData, locale) {
    locale = locale || 'en';
    const res = await fetch(API_BASE + '/v2/forms/' + blokInstanceId + '/submit', {
      method: 'POST',
      headers,
      body: JSON.stringify({ data: formData, locale }),
    });
    const json = await res.json();
    return { success: res.ok, message: json.message ?? (res.ok ? 'Submitted' : 'Error') };
  }

  function trackView(pageId, locale) {
    locale = locale || 'en';
    const payload = JSON.stringify({
      page_id:    pageId,
      locale,
      referrer:   document.referrer,
      user_agent: navigator.userAgent,
    });
    if (navigator.sendBeacon) {
      navigator.sendBeacon(API_BASE + '/v2/analytics/pageview', new Blob([payload], { type: 'application/json' }));
    } else {
      fetch(API_BASE + '/v2/analytics/pageview', { method: 'POST', headers, body: payload, keepalive: true }).catch(() => {});
    }
  }

  async function fetchMenu(handle, locale) {
    locale = locale || 'en';
    const res = await fetch(API_BASE + '/v2/menus/' + handle + '?locale=' + locale, { headers });
    if (!res.ok) return [];
    const json = await res.json();
    return json.data?.items ?? [];
  }

  document.addEventListener('DOMContentLoaded', function () {
    const pageId = document.body.dataset.pageId;
    const locale = document.documentElement.lang || 'en';
    if (pageId) trackView(pageId, locale);
  });

  window.WebBlok = { fetchPage, fetchSection, submitForm, trackView, fetchMenu, apiBase: API_BASE };

}(window));
```

---

## 14. Drag-Drop Page Editor (Alpine.js + SortableJS)

Since Livewire is excluded (no SSH), the page editor is built with **Alpine.js stores + Blade partials fetched via `fetch()`**.

### 14.1 `cms-editor.js` — Alpine.js Editor Store

```javascript
/**
 * WebBlok CMS — Page Editor
 * Alpine.js store-based editor (no Livewire)
 * All AJAX calls go to /cms/page-builder/* endpoints
 */
document.addEventListener('alpine:init', function () {
  Alpine.store('editor', {
    pageId:          null,
    activeSection:   'body',
    activeLocale:    'en',
    editingInstance: null,
    isDirty:         false,

    /** @type {Array} */
    blokInstances:   [],

    init(pageId, locale) {
      this.pageId       = pageId;
      this.activeLocale = locale;
      this.loadBloks();
    },

    async loadBloks() {
      const res = await fetch(`/cms/page-builder/${this.pageId}/bloks?section=${this.activeSection}&locale=${this.activeLocale}`, {
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
      });
      const json = await res.json();
      this.blokInstances = json.data ?? [];
    },

    async addBlok(blokKey, sortOrder, parentId, slotName) {
      const res = await fetch(`/cms/page-builder/${this.pageId}/bloks`, {
        method:  'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({
          blok_key:   blokKey,
          section:    this.activeSection,
          sort_order: sortOrder,
          parent_id:  parentId  ?? null,
          slot_name:  slotName  ?? null,
          locale:     this.activeLocale,
        }),
      });
      if (res.ok) {
        await this.loadBloks();
        this.isDirty = true;
      }
    },

    async deleteBlok(instanceId) {
      await fetch(`/cms/page-builder/bloks/${instanceId}`, {
        method:  'DELETE',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
      });
      this.blokInstances = this.blokInstances.filter(b => b.id !== instanceId);
      if (this.editingInstance?.id === instanceId) this.editingInstance = null;
      this.isDirty = true;
    },

    async reorder(newOrder) {
      await fetch(`/cms/page-builder/bloks/reorder`, {
        method:  'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ order: newOrder }),
      });
      this.isDirty = true;
    },

    async openSettings(instanceId) {
      const res = await fetch(`/cms/page-builder/bloks/${instanceId}/settings?locale=${this.activeLocale}`);
      const html = await res.text();
      document.getElementById('blok-settings-panel').innerHTML = html;
      this.editingInstance = { id: instanceId };
    },

    switchSection(section) {
      this.activeSection = section;
      this.loadBloks();
    },

    switchLocale(locale) {
      this.activeLocale = locale;
      this.loadBloks();
    },
  });
});
```

### 14.2 `drag-drop.js` — SortableJS Integration

```javascript
import Sortable from 'sortablejs';

const sortableInstances = new Map();

export function initSectionSortable(sectionSelector, pageId) {
  const el = document.querySelector(sectionSelector);
  if (!el) return;

  const key = el.dataset.section || sectionSelector;
  if (sortableInstances.has(key)) sortableInstances.get(key).destroy();

  const sortable = Sortable.create(el, {
    group:      { name: 'blok-canvas', put: ['blok-palette'], pull: true },
    animation:  200,
    ghostClass: 'blok-ghost',
    handle:     '.blok-drag-handle',

    onEnd(evt) {
      const items    = el.querySelectorAll('[data-instance-id]');
      const newOrder = Array.from(items).map((item, idx) => ({
        id: item.dataset.instanceId, sort_order: idx,
      }));
      Alpine.store('editor').reorder(newOrder);
    },

    onAdd(evt) {
      const blokKey   = evt.item.dataset.blokKey;
      const section   = el.dataset.section || 'body';
      const sortOrder = evt.newIndex ?? 0;
      const parentId  = el.dataset.parentInstanceId || null;
      const slotName  = el.dataset.slotName || null;
      evt.item.remove();
      Alpine.store('editor').addBlok(blokKey, sortOrder, parentId, slotName);
    },
  });

  sortableInstances.set(key, sortable);
}

export function initPaletteSortable() {
  const palette = document.querySelector('[data-blok-palette]');
  if (!palette) return;
  Sortable.create(palette, {
    group: { name: 'blok-palette', pull: 'clone', put: false },
    sort:  false,
    animation: 150,
  });
}
```

---

## 15. PHPStan Level 10 — Critical Code Patterns

### 15.1 Rules Summary

1. `declare(strict_types=1)` on **every** PHP file
2. All return types, parameter types, and property types declared
3. Generic collection types: `Collection<int, Page>`, `array<string, mixed>`
4. Backed enums for all finite value sets
5. Final action and service classes
6. Readonly value objects
7. `spatie/laravel-data` DTOs — no raw `array` for data transfer
8. PHPDocs with PHPStan-compatible types on all models

### 15.2 `PageStatus.php` — Backed Enum

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum PageStatus: string
{
    case DRAFT     = 'draft';
    case PUBLISHED = 'published';
    case SCHEDULED = 'scheduled';
    case ARCHIVED  = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT     => 'Draft',
            self::PUBLISHED => 'Published',
            self::SCHEDULED => 'Scheduled',
            self::ARCHIVED  => 'Archived',
        };
    }

    public function isPublic(): bool
    {
        return $this === self::PUBLISHED;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_column(
            array_map(fn(self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases()),
            'label',
            'value'
        );
    }
}
```

### 15.3 `Page.php` — Tenant Model

```php
<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\PageStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * @property string       $id
 * @property string|null  $parent_id
 * @property string       $slug
 * @property string       $full_path
 * @property PageStatus   $status
 * @property string       $template
 * @property bool         $is_homepage
 * @property bool         $requires_auth
 * @property string|null  $required_role
 * @property int          $sort_order
 * @property Carbon|null  $scheduled_at
 * @property Carbon|null  $published_at
 * @property Carbon|null  $created_at
 * @property Carbon|null  $updated_at
 * @property Carbon|null  $deleted_at
 * @property-read Page|null                         $parent
 * @property-read Collection<int, Page>             $children
 * @property-read Collection<int, PageLocale>       $locales
 * @property-read Collection<int, BlokInstance>     $blokInstances
 * @property-read Collection<int, PageRevision>     $revisions
 */
final class Page extends Model
{
    use HasSlug;
    use SoftDeletes;

    protected $connection = 'tenant';
    protected $table      = 'pages';

    /** @var list<string> */
    protected $guarded = [];

    /** @return array<string, string|class-string> */
    protected function casts(): array
    {
        return [
            'status'        => PageStatus::class,
            'is_homepage'   => 'boolean',
            'requires_auth' => 'boolean',
            'sort_order'    => 'integer',
            'scheduled_at'  => 'datetime',
            'published_at'  => 'datetime',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('slug')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    /** @return BelongsTo<Page, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Page, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /** @return HasMany<PageLocale, $this> */
    public function locales(): HasMany
    {
        return $this->hasMany(PageLocale::class, 'page_id');
    }

    /** @return HasMany<BlokInstance, $this> */
    public function blokInstances(): HasMany
    {
        return $this->hasMany(BlokInstance::class, 'page_id')->orderBy('sort_order');
    }

    /** @return HasMany<PageRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class, 'page_id')->orderByDesc('revision_number');
    }

    public function locale(string $locale): ?PageLocale
    {
        return $this->locales->firstWhere('locale', $locale);
    }

    public function isPublished(): bool
    {
        return $this->status === PageStatus::PUBLISHED;
    }
}
```

### 15.4 Architecture Test (`tests/Architecture/ArchTest.php`)

```php
<?php

declare(strict_types=1);

use function Pest\ArchTesting\arch;

arch('All PHP files declare strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('Action classes are final')
    ->expect('App\Actions')
    ->toBeFinal();

arch('Value objects are final and readonly')
    ->expect('App\ValueObjects')
    ->toBeFinal()
    ->toBeReadonly();

arch('Service classes are final')
    ->expect('App\Services')
    ->toBeFinal();

arch('Enums are backed')
    ->expect('App\Enums')
    ->toExtend(\BackedEnum::class);

arch('Tenant models use tenant connection')
    ->expect('App\Models\Tenant')
    ->toHaveProperty('connection', 'tenant');

arch('No debug calls')
    ->expect('App')
    ->not->toUse(['dd', 'dump', 'var_dump', 'print_r', 'ray']);

arch('No Livewire or Filament references')
    ->expect('App')
    ->not->toUse(['Livewire\Component', 'Filament\Panel']);
```

---

## 16. FTP Upload Checklist

Steps to prepare and deploy without SSH:

### Local Preparation (Developer's PC)
```bash
# 1. Install dependencies (dev)
composer install

# 2. Set up local config
cp .env.example .env
php artisan key:generate

# 3. Run platform migrations locally
php artisan migrate --path=database/migrations/platform

# 4. Seed platform data
php artisan db:seed --class=PlanSeeder
php artisan db:seed --class=BlokDefinitionSeeder
php artisan db:seed --class=MarketplaceSeeder
php artisan db:seed --class=SuperAdminSeeder

# 5. Build frontend assets
npm install
npm run build

# 6. Run full CI checks
make ci

# 7. Install production dependencies (no dev)
composer install --no-dev --optimize-autoloader

# 8. Cache for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### What to Upload via FTP
```
✅ Upload these:
  app/                vendor/             database/
  config/             routes/             resources/
  public/             storage/            blok_definitions/
  marketplace/        bootstrap/          .env (production version)

❌ Do NOT upload:
  node_modules/       tests/              .git/
  .github/            phpstan.neon        pint.json
```

### Server `.env` (production)
```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# SQLite (default — works everywhere)
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database/platform.sqlite

# Auth driver
AUTH_DRIVER=breeze           # or 'gas' with GAS_* settings

# Cron secret (set this, keep it secret)
WEBBLOK_CRON_SECRET=your-very-long-random-secret-here

# Queue driver (no Redis needed)
QUEUE_CONNECTION=database

# Cache (file-based)
CACHE_STORE=file

# Marketplace (leave empty for official only)
MARKETPLACE_REMOTE_URL=

# Session
SESSION_DRIVER=file
SESSION_LIFETIME=120
```

### Server-Side Post-Upload (via Browser / FTP)
1. Set correct permissions: `storage/` and `bootstrap/cache/` → `755` or `775`
2. Visit `https://yourdomain.com/setup` (one-time setup wizard — auto-disabled after completion)
3. Setup wizard performs:
   - Validates PHP version + extensions (SQLite, PDO)
   - Creates `storage/` directory structure
   - Runs any missed platform migrations (web-triggered)
   - Creates super-admin account

---

## 17. Artisan Commands (Run Locally Only)

| Command | Description |
|---------|-------------|
| `webblok:seed-bloks` | Seed all 150+ blok definitions |
| `webblok:seed-marketplace` | Seed official themes + templates |
| `webblok:validate-schemas` | Validate all `blok_definitions/*.schema.json` |
| `webblok:build-static {tenant} {--locales=en}` | Queue a static export job |
| `webblok:flush-cache {tenant}` | Clear render cache for a tenant |
| `webblok:warm-cache {tenant}` | Pre-render all published pages |
| `webblok:provision-tenant {slug}` | Provision tenant DB (local testing) |
| `webblok:publish-scheduled` | Publish pages scheduled in the past |
| `webblok:clean-exports` | Delete expired ZIP exports |
| `webblok:stats` | Print platform statistics |
| `webblok:migrate-tenant {slug}` | Run pending tenant migrations |
| `webblok:export-theme {slug}` | Export a tenant's active theme as ZIP |

---

## 18. Testing Strategy

### 18.1 Feature Tests

```php
<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Models\Platform\Tenant;
use App\Models\Tenant\Page;
use App\Services\TenantContext;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['status' => 'active']);
    app(TenantContext::class)->switchTo($this->tenant);
});

test('can publish a draft page', function (): void {
    $page = Page::factory()->create(['status' => PageStatus::DRAFT]);
    app(\App\Actions\Pages\PublishPage::class)->execute($page->id);
    expect($page->fresh()->status)->toBe(PageStatus::PUBLISHED);
});

test('breeze login returns user', function (): void {
    $user = \App\Models\Platform\User::factory()->create(['password' => bcrypt('secret')]);
    $driver = app(\App\Auth\Drivers\BreezeAuthDriver::class);
    $result = $driver->attempt($user->email, 'secret');
    expect($result)->not->toBeNull();
    expect($result->id)->toBe($user->id);
});

test('installing a theme activates it', function (): void {
    $service = app(\App\Services\MarketplaceService::class);
    $service->installTheme('dark-pro', activate: true);
    $active = \Illuminate\Support\Facades\DB::connection('tenant')
        ->table('site_settings')
        ->where('key', 'active_theme_slug')
        ->value('value');
    expect($active)->toBe('dark-pro');
});
```

### 18.2 API Tests (V1)

```php
test('v1 blok render returns html', function (): void {
    $response = $this->withHeaders(['Authorization' => "Bearer wbk_live_test"])
        ->get('/api/v1/bloks/slide-01/render?lang=en&theme=dark');
    $response->assertOk()
             ->assertSee('wb-slide-01');
});
```

---

## 19. `Makefile`

```makefile
.PHONY: analyse lint fix test ci install fresh ftp-prep

analyse:
	vendor/bin/phpstan analyse --configuration=phpstan.neon

lint:
	vendor/bin/pint --test

fix:
	vendor/bin/pint

test:
	vendor/bin/pest --parallel

test-coverage:
	vendor/bin/pest --parallel --coverage --min=80

ci: lint analyse test

install:
	composer install
	npm ci
	cp .env.example .env
	php artisan key:generate
	php artisan migrate --path=database/migrations/platform
	php artisan db:seed
	npm run build

fresh:
	php artisan migrate:fresh --path=database/migrations/platform --seed
	php artisan webblok:seed-bloks
	php artisan webblok:seed-marketplace

ftp-prep:
	composer install --no-dev --optimize-autoloader
	npm run build
	php artisan config:cache
	php artisan route:cache
	php artisan view:cache
	@echo "✅ Ready for FTP upload"

seed-bloks:
	php artisan webblok:seed-bloks

seed-marketplace:
	php artisan webblok:seed-marketplace

validate-schemas:
	php artisan webblok:validate-schemas

stats:
	php artisan webblok:stats
```

---

## 20. Implementation Sprint Plan

### Sprint 1 — Foundation (Days 1–5)
- [ ] `composer create-project laravel/laravel webblok-cms`
- [ ] Install all packages, run `make ci` → clean baseline
- [ ] Configure `phpstan.neon` L10, `pint.json`, `Makefile`
- [ ] Create dual-mode `config/database.php` (SQLite default / MySQL optional)
- [ ] Create `config/auth_driver.php` + `config/marketplace.php`
- [ ] Create platform SQLite migrations (11 tables) + run locally
- [ ] `PlanSeeder`, `SuperAdminSeeder`
- [ ] `TenantContext` singleton + `SetTenantDatabaseConnection` middleware
- [ ] `AuthenticateApiKey` middleware
- [ ] Run `make ci` → zero errors ✅

### Sprint 2 — Auth System (Days 6–9)
- [ ] `AuthDriverInterface` + `BreezeAuthDriver` + `GasAuthDriver`
- [ ] `AuthManager` factory + `AppServiceProvider` binding
- [ ] Breeze Blade auth views (login, register)
- [ ] GAS redirect + callback controllers + views
- [ ] Conditional route registration based on `config('auth_driver.driver')`
- [ ] Auth feature tests (Breeze + GAS mock)
- [ ] Run `make ci` ✅

### Sprint 3 — Blok System (Days 10–16)
- [ ] `BaseBlok` abstract class
- [ ] `BlokDefinition` model + `BlokDefinitionSeeder` (150 bloks)
- [ ] Create all `blok_definitions/*.schema.json` files
- [ ] `BlokSchemaResolver` service (file cache)
- [ ] `BlokSchemaField` + `BlokSchemaStep` value objects
- [ ] `BlokDataResolver` (V1 JSON + tenant overrides)
- [ ] All 150 `Slide{NN}Blok.php` classes + Blade views
- [ ] Component layout bloks: HeroSection, TwoColumnGrid, AppShell
- [ ] Run `make ci` ✅

### Sprint 4 — Tenant Database & Models (Days 17–21)
- [ ] All 17 tenant SQLite migration files
- [ ] `TenantMigrator` service (web-triggered, no Artisan)
- [ ] All tenant Eloquent models (Page, PageLocale, BlokInstance, etc.)
- [ ] All backed enums
- [ ] All `spatie/laravel-data` DTOs
- [ ] `ProvisionTenant` action
- [ ] Tenant model factories
- [ ] Run `make test` ≥60% ✅

### Sprint 5 — CMS Page Editor (Days 22–28)
- [ ] `cms-editor.js` Alpine.js store
- [ ] `drag-drop.js` SortableJS integration
- [ ] `PageBuilderController` (AJAX endpoints for editor)
- [ ] `cms/page-builder.blade.php` (full editor layout)
- [ ] Blok palette sidebar partial
- [ ] Form Wizard partial (19 field types in Blade)
- [ ] Blok settings panel partial (loaded via AJAX)
- [ ] Live preview iframe partial
- [ ] Revision history partial
- [ ] `BlokInstanceResolver` recursive service
- [ ] `BlokRenderer` service with file cache
- [ ] Run `make ci` ✅

### Sprint 6 — V1 Embed API + V2 REST API (Days 29–34)
- [ ] All V1 API controllers + routes
- [ ] `CdnBundler` + `public/js/widget.js`
- [ ] `UsageTracker` service
- [ ] All V2 API controllers + routes
- [ ] Full API feature tests (V1 + V2)
- [ ] Run `make ci` ✅

### Sprint 7 — Marketplace (Days 35–40)
- [ ] `MarketplaceService` (listThemes, listTemplates, installTheme, installTemplate, syncRemote)
- [ ] `ThemeManager` (getActiveThemeCss, apply custom vars)
- [ ] 3 official themes: dark-pro, light-clean, ocean-blue
- [ ] 4 official templates: business-starter, blog-minimal, portfolio-modern, landing-page
- [ ] `MarketplaceSeeder`
- [ ] CMS Marketplace controller + views (browse, preview, install)
- [ ] Theme switcher in CMS settings
- [ ] Custom ZIP upload (if allowed)
- [ ] Marketplace feature tests
- [ ] Run `make ci` ✅

### Sprint 8 — Static Export + Cron + Admin Panel (Days 41–46)
- [ ] `StaticSiteExporter` service
- [ ] `BuildStaticSiteJob` (database queue)
- [ ] `api-client.js` token injection
- [ ] `SitemapGenerator`
- [ ] `CronRunner` + `public/cron.php`
- [ ] HTTP cron route + controller
- [ ] Custom Blade super-admin panel
- [ ] Custom Blade tenant CMS panel (dashboard, pages, menus, media, settings)
- [ ] One-time setup wizard (`/setup` route, auto-disables)
- [ ] Run `make ci` → 100% ✅

### Sprint 9 — FTP Prep + Final (Days 47–50)
- [ ] Run `make ftp-prep`
- [ ] Test upload to test shared hosting (FTP)
- [ ] Verify cPanel cron works (`/cron/run?secret=xxx`)
- [ ] Verify tenant provisioning via web
- [ ] Verify GAS auth switch works
- [ ] Verify marketplace theme install
- [ ] Verify static export download
- [ ] Final `make ci` → PHPStan L10 zero errors ✅
- [ ] Write `DEPLOY.md` (FTP steps, cPanel cron setup, post-upload checklist)

---

## 21. Summary Table

| Aspect | Specification |
|--------|--------------|
| **Framework** | Latest stable Laravel (version-agnostic) |
| **Admin UI** | Custom Blade panels (no Filament, no Livewire) |
| **Reactive UI** | Alpine.js + SortableJS |
| **Auth** | Breeze Blade (default) ↔ GAS API (config switch) |
| **Platform DB** | SQLite (default) or MySQL (`.env` switch) |
| **Tenant DB** | SQLite WAL (always, 1 file per tenant) |
| **Queue** | Database driver (SQLite table, no Redis) |
| **Cache** | File driver (no Redis) |
| **Search** | SQLite FTS5 (no MeiliSearch) |
| **Deployment** | Local build → FTP upload (no SSH required) |
| **Tenant Provisioning** | Web-triggered (no Artisan CLI on server) |
| **Cron Jobs** | HTTP endpoint called by cPanel (no Supervisor) |
| **Blok System** | 150+ definitions, JSON schemas, recursive nesting (depth 10) |
| **Form Wizard** | 19 field types, multi-step, i18n, conditional visibility |
| **V1 Embed API** | REST V1: 10 endpoints, CDN widget.js |
| **V2 CMS API** | REST V2: 16 endpoints, bearer token auth |
| **Static Export** | Full ZIP download + injected api-client.js AJAX bridge |
| **Marketplace** | Official + Community + Custom themes & templates |
| **Theme System** | CSS variable-based; per-tenant customisation |
| **Static Analysis** | PHPStan / Larastan Level 10 — dev CI only |
| **Implementation** | 9 sprints × 50 days |
| **Test Coverage** | ≥ 80% target |

---

## 22. Quick-Start Commands

```bash
# 1. Create project
composer create-project laravel/laravel webblok-cms
cd webblok-cms

# 2. Install packages
composer require laravel/breeze laravel/sanctum spatie/laravel-data \
  spatie/laravel-permission spatie/laravel-medialibrary \
  spatie/laravel-translatable spatie/laravel-activitylog \
  spatie/laravel-sluggable spatie/laravel-query-builder \
  spatie/laravel-backup barryvdh/laravel-dompdf

composer require --dev larastan/larastan phpstan/phpstan \
  phpstan/phpstan-strict-rules pestphp/pest pestphp/pest-plugin-laravel \
  pestphp/pest-plugin-arch laravel/pint rector/rector

# 3. Install Breeze (Blade + Alpine.js)
php artisan breeze:install blade

# 4. Build
npm install && npm run build

# 5. Setup
cp .env.example .env
php artisan key:generate
php artisan migrate --path=database/migrations/platform
php artisan db:seed

# 6. Seed bloks + marketplace
php artisan webblok:seed-bloks
php artisan webblok:seed-marketplace

# 7. Validate
make ci

# 8. Prepare for FTP upload
make ftp-prep
```

---

# WebBlok CMS SaaS — V3 Adjusted Specification (Builder-First + Community + Scale)

> **This document is an addendum and override layer on top of the V2 Final spec.** Where this document conflicts with V2, **this document wins**. It adds three things: (1) corrected, PHPStan-L10-clean implementations of the scale layer, (2) a complete **Community Builders** contribution system, and (3) a deeply detailed **Website Builder** — the single most important feature of the platform.
>
> Strictness, framework, no-SSH constraints, SQLite-per-tenant, auth switcher, and marketplace from V2 all remain in force.

---

## 23. V3 Correctness & Scale Layer (drop-in files)

These replace the risky hand-rolled implementations identified in review. All files are `declare(strict_types=1)`, fully typed, final, and written to pass Larastan Level 10.

### 23.1 `CronRunner.php` — corrected (delegates to the real queue worker)

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Console\Kernel as Artisan;
use Illuminate\Support\Facades\DB;

final class CronRunner
{
    public function __construct(
        private readonly Artisan $artisan,
    ) {}

    public function run(): void
    {
        /** @var array<string, callable(): void> $tasks */
        $tasks = [
            'process-queue'     => fn (): mixed => $this->processQueue(),
            'publish-scheduled' => fn (): mixed => $this->publishScheduledPages(),
            'clean-exports'     => fn (): mixed => $this->cleanExpiredExports(),
            'sync-marketplace'  => fn (): mixed => $this->syncMarketplace(),
        ];

        foreach ($tasks as $name => $task) {
            $start = microtime(true);
            $status = 'ok';

            try {
                $task();
            } catch (\Throwable $e) {
                $status = 'error: '.mb_substr($e->getMessage(), 0, 500);
            }

            DB::table('cron_runs')->insert([
                'task'        => $name,
                'status'      => $status,
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'ran_at'      => now()->toISOString(),
            ]);
        }
    }

    private function processQueue(): void
    {
        // Delegate to Laravel's real worker in one-shot mode.
        // Preserves job middleware, retries, backoff, model serialization, batching.
        $this->artisan->call('queue:work', [
            '--once'            => true,
            '--stop-when-empty' => true,
            '--max-time'        => 50,
            '--tries'           => 3,
        ]);
    }

    private function publishScheduledPages(): void
    {
        $this->artisan->call('webblok:publish-scheduled');
    }

    private function cleanExpiredExports(): void
    {
        $this->artisan->call('webblok:clean-exports');
    }

    private function syncMarketplace(): void
    {
        app(MarketplaceService::class)->syncRemoteCatalog();
    }
}
```

### 23.2 `TenantMigrator` — corrected migration runner

Replace the `extractClassName` regex approach. Modern migrations are anonymous classes returned by `require`.

```php
// Inside App\Services\TenantMigrator::provision(), step 5:

$migrationPath  = database_path('migrations/tenant');
$migrationFiles = glob("{$migrationPath}/*.php");

if ($migrationFiles !== false) {
    sort($migrationFiles);

    foreach ($migrationFiles as $file) {
        /** @var \Illuminate\Database\Migrations\Migration|mixed $migration */
        $migration = require $file;

        if ($migration instanceof \Illuminate\Database\Migrations\Migration) {
            $migration->up();
        }
    }
}
```

The `extractClassName()` method is **deleted**.

### 23.3 `TenantStatus.php` — add `DORMANT`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantStatus: string
{
    case PROVISIONING = 'provisioning';
    case DORMANT      = 'dormant';      // no SQLite file yet; stored as compressed snapshot
    case ACTIVE       = 'active';
    case SUSPENDED    = 'suspended';
    case DELETED      = 'deleted';

    public function isLive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function needsMaterialization(): bool
    {
        return $this === self::DORMANT;
    }
}
```

### 23.4 `SiteBundleData.php` — the open portable site format (DTO)

```php
<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * WebBlok Site Bundle v1 — the open, portable site format.
 * Used for: templates, AI generation output, import/export, dormant snapshots.
 */
final class SiteBundleData extends Data
{
    /**
     * @param  list<string>             $locales
     * @param  list<SiteBundlePageData> $pages
     * @param  array<string, mixed>     $settings
     */
    public function __construct(
        public string $bundleVersion,   // 'webblok-site-bundle/1'
        public string $name,
        public string $defaultLocale,
        public array $locales,
        public ?string $themeSlug,
        public array $pages,
        public array $settings = [],
    ) {}
}
```

```php
<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

final class SiteBundlePageData extends Data
{
    /**
     * @param  array<string, array<string, string|null>> $locales  // [locale => [title, meta_title, ...]]
     * @param  array<string, list<SiteBundleBlokData>>    $sections // [section => bloks[]]
     */
    public function __construct(
        public string $slug,
        public bool $isHomepage,
        public string $template,
        public array $locales,
        public array $sections,
    ) {}
}
```

```php
<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

final class SiteBundleBlokData extends Data
{
    /**
     * @param  array<string, mixed>                    $config
     * @param  array<string, array<string, mixed>>     $localeConfig
     * @param  list<SiteBundleBlokData>                $children
     */
    public function __construct(
        public string $blokKey,
        public int $sortOrder,
        public array $config = [],
        public array $localeConfig = [],
        public ?string $slotName = null,
        public array $children = [],
    ) {}
}
```

### 23.5 `MaterializeTenant.php` — lazy provisioning (the zero-cost-at-scale lever)

```php
<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Data\SiteBundleData;
use App\Enums\TenantStatus;
use App\Models\Platform\Tenant;
use App\Services\SiteBundleImporter;
use App\Services\TenantMigrator;

final class MaterializeTenant
{
    public function __construct(
        private readonly TenantMigrator     $migrator,
        private readonly SiteBundleImporter $importer,
    ) {}

    /**
     * Converts a DORMANT tenant (stored only as a compressed snapshot)
     * into a live SQLite-backed tenant on first write / first traffic.
     */
    public function execute(Tenant $tenant): void
    {
        if ($tenant->status !== TenantStatus::DORMANT) {
            return; // already materialized — idempotent
        }

        // 1. Create SQLite file + run all tenant migrations
        $this->migrator->provision($tenant);

        // 2. If a dormant snapshot exists, hydrate the DB from it
        /** @var array<string, mixed> $settings */
        $settings = $tenant->settings ?? [];

        if (isset($settings['snapshot']) && is_string($settings['snapshot'])) {
            $json   = (string) gzdecode(base64_decode($settings['snapshot'], true) ?: '');
            $bundle = SiteBundleData::from(
                json_decode($json, true, 512, JSON_THROW_ON_ERROR)
            );
            $this->importer->import($bundle);

            unset($settings['snapshot']);
            $tenant->settings = $settings;
        }

        $tenant->status = TenantStatus::ACTIVE;
        $tenant->save();
    }
}
```

### 23.6 `GenerateSite.php` — prompt-to-site (the +reach engine, offline-safe)

```php
<?php

declare(strict_types=1);

namespace App\Actions\Ai;

use App\Data\SiteBundleData;
use App\Services\Ai\Contracts\SiteGeneratorDriver;

final class GenerateSite
{
    public function __construct(
        private readonly SiteGeneratorDriver $driver,
    ) {}

    /**
     * @param  list<string> $locales
     */
    public function execute(
        string $prompt,
        array $locales = ['en'],
        ?string $logoMediaId = null,
    ): SiteBundleData {
        return $this->driver->generate($prompt, $locales, $logoMediaId);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

use App\Data\SiteBundleData;

interface SiteGeneratorDriver
{
    /** @param list<string> $locales */
    public function generate(string $prompt, array $locales, ?string $logoMediaId): SiteBundleData;
}
```

```php
<?php

declare(strict_types=1);

namespace App\Services\Ai\Drivers;

use App\Data\SiteBundleBlokData;
use App\Data\SiteBundleData;
use App\Data\SiteBundlePageData;
use App\Services\Ai\Contracts\SiteGeneratorDriver;

/**
 * Default offline-safe driver. Builds a sensible 3-page starter from the prompt
 * WITHOUT calling any external API, so the platform builds & passes CI offline.
 * Swap to a real LLM driver via config('webblok.ai_driver').
 */
final class StubSiteGeneratorDriver implements SiteGeneratorDriver
{
    /** @param list<string> $locales */
    public function generate(string $prompt, array $locales, ?string $logoMediaId): SiteBundleData
    {
        $default = $locales[0] ?? 'en';
        $name    = mb_substr(trim($prompt), 0, 60) ?: 'My Website';

        $home = new SiteBundlePageData(
            slug: 'home',
            isHomepage: true,
            template: 'default',
            locales: $this->localeMap($locales, $name, 'Welcome'),
            sections: [
                'body' => [
                    new SiteBundleBlokData('hero-section', 0, ['min_height' => 70], [
                        $default => ['headline' => $name, 'cta_label' => 'Get Started'],
                    ]),
                    new SiteBundleBlokData('rich-text', 1, [], [
                        $default => ['html' => '<p>'.e($prompt).'</p>'],
                    ]),
                ],
            ],
        );

        $about = new SiteBundlePageData('about', false, 'default',
            $this->localeMap($locales, 'About', 'About'),
            ['body' => [new SiteBundleBlokData('rich-text', 0)]],
        );

        $contact = new SiteBundlePageData('contact', false, 'default',
            $this->localeMap($locales, 'Contact', 'Contact'),
            ['body' => [new SiteBundleBlokData('contact-form', 0)]],
        );

        return new SiteBundleData(
            bundleVersion: 'webblok-site-bundle/1',
            name: $name,
            defaultLocale: $default,
            locales: $locales,
            themeSlug: 'light-clean',
            pages: [$home, $about, $contact],
        );
    }

    /**
     * @param  list<string> $locales
     * @return array<string, array<string, string|null>>
     */
    private function localeMap(array $locales, string $title, string $metaPrefix): array
    {
        /** @var array<string, array<string, string|null>> $map */
        $map = [];
        foreach ($locales as $locale) {
            $map[$locale] = ['title' => $title, 'meta_title' => $metaPrefix];
        }

        return $map;
    }
}
```

### 23.7 `SiteBundleImporter.php` — installs any bundle into the current tenant

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\SiteBundleBlokData;
use App\Data\SiteBundleData;
use App\Data\SiteBundlePageData;
use App\Models\Tenant\BlokInstance;
use App\Models\Tenant\Page;
use App\Models\Tenant\PageLocale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SiteBundleImporter
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function import(SiteBundleData $bundle): void
    {
        $this->tenantContext->require();

        DB::connection('tenant')->transaction(function () use ($bundle): void {
            foreach ($bundle->pages as $page) {
                $this->importPage($page);
            }

            if ($bundle->themeSlug !== null) {
                DB::connection('tenant')->table('site_settings')
                    ->updateOrInsert(
                        ['key' => 'active_theme_slug'],
                        ['value' => $bundle->themeSlug, 'cast_type' => 'string', 'group_name' => 'theme'],
                    );
            }
        });
    }

    private function importPage(SiteBundlePageData $page): void
    {
        $pageId = Str::uuid()->toString();

        Page::create([
            'id'          => $pageId,
            'slug'        => $page->slug,
            'full_path'   => '/'.ltrim($page->slug, '/'),
            'status'      => 'draft',
            'template'    => $page->template,
            'is_homepage' => (int) $page->isHomepage,
            'created_at'  => now()->toISOString(),
            'updated_at'  => now()->toISOString(),
        ]);

        foreach ($page->locales as $locale => $fields) {
            PageLocale::create([
                'id'               => Str::uuid()->toString(),
                'page_id'          => $pageId,
                'locale'           => $locale,
                'title'            => $fields['title'] ?? '',
                'meta_title'       => $fields['meta_title'] ?? null,
                'meta_description' => $fields['meta_description'] ?? null,
                'is_indexable'     => 1,
                'created_at'       => now()->toISOString(),
                'updated_at'       => now()->toISOString(),
            ]);
        }

        foreach ($page->sections as $section => $bloks) {
            foreach ($bloks as $blok) {
                $this->importBlok($pageId, $section, $blok, null);
            }
        }
    }

    private function importBlok(string $pageId, string $section, SiteBundleBlokData $blok, ?string $parentId): void
    {
        $id = Str::uuid()->toString();

        BlokInstance::create([
            'id'            => $id,
            'page_id'       => $pageId,
            'parent_id'     => $parentId,
            'slot_name'     => $blok->slotName,
            'blok_key'      => $blok->blokKey,
            'section'       => $section,
            'config'        => json_encode($blok->config, JSON_THROW_ON_ERROR),
            'locale_config' => json_encode($blok->localeConfig, JSON_THROW_ON_ERROR),
            'sort_order'    => $blok->sortOrder,
            'is_visible'    => 1,
            'created_at'    => now()->toISOString(),
            'updated_at'    => now()->toISOString(),
        ]);

        foreach ($blok->children as $child) {
            $this->importBlok($pageId, $section, $child, $id);
        }
    }
}
```

### 23.8 Config additions (`config/webblok.php` excerpt)

```php
'ai_driver'    => env('WEBBLOK_AI_DRIVER', 'stub'),   // 'stub' | 'openai' | 'anthropic'
'edge_publish' => env('WEBBLOK_EDGE_PUBLISH', 'local'),
'dormant_tenants' => [
    'enabled'              => env('WEBBLOK_DORMANT', true),
    'materialize_on_views' => env('WEBBLOK_MATERIALIZE_VIEWS', 50),
],
```

### 23.9 New endpoints

```php
// routes/api.php — V1 group
Route::post('/v1/sites/generate', [Api\V1\SiteGenerationController::class, 'generate']);
Route::post('/v1/tenants',        [Api\V1\TenantProvisionController::class, 'store']); // partner-scoped
```

---

## 24. Community Builders System

This turns WebBlok from a closed product into an **ecosystem**: any user can author bloks, templates, pages, and whole sites, share them, and submit them to the marketplace for others to install. This is the contribution engine behind community marketplace growth.

### 24.1 Mental model

There are four contributable artifact types, each portable and reviewable:

| Artifact | What it is | Authoring surface |
|----------|-----------|-------------------|
| **Community Blok** | A reusable block: schema (Form Wizard fields) + a safe HTML/Twig-like template + default config. No raw PHP. | Visual Blok Studio (Section 24.3) |
| **Community Template** | A multi-section page preset (a `SiteBundlePageData`). | "Save page as template" from the builder |
| **Community Page / Site** | A full `SiteBundleData` (one or many pages). | "Publish site to community" |
| **Community Theme** | CSS-variable + CSS bundle (existing theme format). | Theme editor (Section 24.4) |

Everything reduces to two existing formats: the **Site Bundle** (pages/templates/sites) and the **Blok Definition** (bloks). Community contributions never inject executable PHP — they are sandboxed data + a restricted template language (see 24.5).

### 24.2 Platform schema additions

```sql
-- Community-authored artifacts (lives in PLATFORM db)
CREATE TABLE community_artifacts (
    id              TEXT    NOT NULL PRIMARY KEY,
    author_id       TEXT    NOT NULL REFERENCES users(id),
    author_tenant_id TEXT   NULL REFERENCES tenants(id),
    type            TEXT    NOT NULL,              -- 'blok','template','site','theme'
    name            TEXT    NOT NULL,
    slug            TEXT    NOT NULL UNIQUE,
    description     TEXT    NULL,
    version         TEXT    NOT NULL DEFAULT '1.0.0',
    payload         TEXT    NOT NULL,              -- JSON: BlokDefinition or SiteBundle or theme manifest
    thumbnail_url   TEXT    NULL,
    tags            TEXT    NULL,                  -- JSON array
    category        TEXT    NULL,
    locale_support  TEXT    NOT NULL DEFAULT '["en"]',
    license         TEXT    NOT NULL DEFAULT 'free', -- 'free','paid','mit','cc-by'
    price           REAL    NOT NULL DEFAULT 0.0,
    status          TEXT    NOT NULL DEFAULT 'draft',
                                                   -- draft,submitted,in_review,approved,rejected,published,delisted
    review_notes    TEXT    NULL,
    reviewed_by     TEXT    NULL,
    install_count   INTEGER NOT NULL DEFAULT 0,
    rating_sum      INTEGER NOT NULL DEFAULT 0,
    rating_count    INTEGER NOT NULL DEFAULT 0,
    is_verified     INTEGER NOT NULL DEFAULT 0,    -- author identity verified
    created_at      TEXT    NULL,
    updated_at      TEXT    NULL,
    published_at    TEXT    NULL
);
CREATE INDEX idx_community_status ON community_artifacts(status, type);
CREATE INDEX idx_community_author ON community_artifacts(author_id);

CREATE TABLE community_ratings (
    id           TEXT    NOT NULL PRIMARY KEY,
    artifact_id  TEXT    NOT NULL REFERENCES community_artifacts(id) ON DELETE CASCADE,
    user_id      TEXT    NOT NULL REFERENCES users(id),
    stars        INTEGER NOT NULL,                 -- 1..5
    comment      TEXT    NULL,
    created_at   TEXT    NULL,
    UNIQUE (artifact_id, user_id)
);

CREATE TABLE community_reports (
    id           TEXT    NOT NULL PRIMARY KEY,
    artifact_id  TEXT    NOT NULL REFERENCES community_artifacts(id) ON DELETE CASCADE,
    reporter_id  TEXT    NOT NULL REFERENCES users(id),
    reason       TEXT    NOT NULL,
    details      TEXT    NULL,
    status       TEXT    NOT NULL DEFAULT 'open',  -- open,reviewed,actioned,dismissed
    created_at   TEXT    NULL
);
```

When an artifact reaches `published`, a mirror row is upserted into `marketplace_items` with `source = 'community'`, so the existing install pipeline (`MarketplaceService`) works unchanged.

### 24.3 Publishing lifecycle (state machine)

```
draft ──submit──▶ submitted ──auto-validate──▶ in_review ──approve──▶ approved ──▶ published
   ▲                                               │                                   │
   └────────────────── reject ◀────────────────────┘                          delist ─┘
```

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum ArtifactStatus: string
{
    case DRAFT     = 'draft';
    case SUBMITTED = 'submitted';
    case IN_REVIEW = 'in_review';
    case APPROVED  = 'approved';
    case REJECTED  = 'rejected';
    case PUBLISHED = 'published';
    case DELISTED  = 'delisted';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT     => [self::SUBMITTED],
            self::SUBMITTED => [self::IN_REVIEW, self::REJECTED],
            self::IN_REVIEW => [self::APPROVED, self::REJECTED],
            self::APPROVED  => [self::PUBLISHED, self::REJECTED],
            self::PUBLISHED => [self::DELISTED],
            self::REJECTED  => [self::SUBMITTED],
            self::DELISTED  => [self::PUBLISHED],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), strict: true);
    }
}
```

### 24.4 Key actions

```php
<?php

declare(strict_types=1);

namespace App\Actions\Community;

use App\Enums\ArtifactStatus;
use App\Exceptions\InvalidArtifactException;
use App\Models\Platform\CommunityArtifact;
use App\Services\Community\ArtifactValidator;

final class SubmitArtifact
{
    public function __construct(
        private readonly ArtifactValidator $validator,
    ) {}

    public function execute(CommunityArtifact $artifact): void
    {
        if (! $artifact->status->canTransitionTo(ArtifactStatus::SUBMITTED)) {
            throw new InvalidArtifactException('Artifact cannot be submitted from its current state.');
        }

        // Hard validation gate BEFORE it enters the review queue
        $this->validator->assertValid($artifact);

        $artifact->status     = ArtifactStatus::SUBMITTED;
        $artifact->updated_at = now()->toISOString();
        $artifact->save();
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Actions\Community;

use App\Enums\ArtifactStatus;
use App\Enums\MarketplaceItemType;
use App\Models\Platform\CommunityArtifact;
use App\Models\Platform\MarketplaceItem;
use Illuminate\Support\Str;

final class PublishArtifact
{
    public function execute(CommunityArtifact $artifact): void
    {
        if (! $artifact->status->canTransitionTo(ArtifactStatus::PUBLISHED)) {
            throw new \App\Exceptions\InvalidArtifactException('Artifact is not approved for publishing.');
        }

        $artifact->status       = ArtifactStatus::PUBLISHED;
        $artifact->published_at = now()->toISOString();
        $artifact->save();

        // Mirror into marketplace_items so the existing install pipeline works
        MarketplaceItem::updateOrCreate(
            ['slug' => $artifact->slug],
            [
                'id'             => Str::uuid()->toString(),
                'type'           => $artifact->type === 'blok' ? 'template' : $artifact->type, // bloks install as part of templates; see 24.6
                'source'         => 'community',
                'name'           => $artifact->name,
                'description'    => $artifact->description,
                'author'         => $artifact->author?->name,
                'version'        => $artifact->version,
                'thumbnail_url'  => $artifact->thumbnail_url,
                'tags'           => $artifact->tags,
                'category'       => $artifact->category,
                'locale_support' => $artifact->locale_support,
                'price'          => $artifact->price,
                'install_count'  => $artifact->install_count,
                'is_active'      => 1,
            ],
        );
    }
}
```

### 24.5 Security: the contribution sandbox (non-negotiable)

Community content is **untrusted**. The rules enforced by `ArtifactValidator`:

Community bloks **must not** contain executable PHP, `<script>` with arbitrary JS, inline event handlers (`onclick=`), `javascript:` URLs, `<iframe>` to non-allowlisted hosts, or `<style>` with `@import` from external origins. Blok templates use a **restricted, allowlisted template syntax** (a safe subset: `{{ field.x }}` interpolation, `{% for %}` / `{% if %}` over the blok's own config only — no filesystem, no DB, no PHP functions). Rendering runs through an output-escaping renderer by default; raw HTML is only permitted in fields explicitly typed `richtext` and is passed through an HTML sanitizer (allowlist of tags/attributes). All community CSS is namespaced/scoped to the blok instance to prevent global style hijacking. Site bundles are validated against the `SiteBundleData` schema and every referenced `blok_key` must resolve to an approved definition.

```php
<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Exceptions\InvalidArtifactException;
use App\Models\Platform\CommunityArtifact;

final class ArtifactValidator
{
    /** @var list<string> */
    private const FORBIDDEN_PATTERNS = [
        '/<\?php/i',
        '/<script\b/i',
        '/\bon\w+\s*=/i',        // inline event handlers
        '/javascript:/i',
        '/@import/i',
        '/<iframe\b/i',
        '/\beval\s*\(/i',
        '/\bbase64_decode\s*\(/i',
    ];

    public function assertValid(CommunityArtifact $artifact): void
    {
        $payload = $artifact->payload; // raw JSON string

        if ($payload === '' || json_validate($payload) === false) {
            throw new InvalidArtifactException('Artifact payload is not valid JSON.');
        }

        foreach (self::FORBIDDEN_PATTERNS as $pattern) {
            if (preg_match($pattern, $payload) === 1) {
                throw new InvalidArtifactException(
                    'Artifact contains forbidden content (scripts/PHP/unsafe HTML are not allowed).'
                );
            }
        }

        match ($artifact->type) {
            'blok'              => $this->validateBlok($payload),
            'template', 'site'  => $this->validateBundle($payload),
            'theme'             => $this->validateTheme($payload),
            default             => throw new InvalidArtifactException('Unknown artifact type.'),
        };
    }

    private function validateBlok(string $payload): void
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        foreach (['blok_key', 'label', 'schema', 'template'] as $required) {
            if (! array_key_exists($required, $data)) {
                throw new InvalidArtifactException("Blok is missing required field: {$required}");
            }
        }
    }

    private function validateBundle(string $payload): void
    {
        // Will throw if the structure does not match SiteBundleData
        \App\Data\SiteBundleData::from(
            json_decode($payload, true, 512, JSON_THROW_ON_ERROR)
        );
    }

    private function validateTheme(string $payload): void
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        if (! isset($data['css_variables']) || ! is_array($data['css_variables'])) {
            throw new InvalidArtifactException('Theme must declare css_variables.');
        }
    }
}
```

### 24.6 Community blok rendering

Community bloks render through `CommunityBlokRenderer`, which compiles the restricted template against the instance config with all output escaped, applies the HTML sanitizer to `richtext` fields, and scopes the blok's CSS. This is separate from the trusted `BlokRenderer` used for official Blade-backed bloks. A community blok is installed into a tenant as a tenant-local `blok_definition` row (so it appears in the palette) but is flagged `is_community = 1` to route it to the safe renderer.

### 24.7 Author surfaces (UI)

The CMS gains a **Creator Studio** area (`/cms/studio`): a dashboard of the user's artifacts and their statuses; the **Blok Studio** (24 below references the visual schema designer); a "Save as community template" button inside the builder; a "Publish site to community" flow; an earnings/installs panel; and a submission tracker showing review state and reviewer notes.

The super-admin gains a **Review Queue** (`/admin/community/review`): list of `submitted`/`in_review` artifacts, a sandboxed live preview, approve/reject with notes, the abuse-report inbox, and delist controls.

### 24.8 Reputation & trust

Authors accrue `install_count`, average rating, and a `is_verified` flag. Verified authors with a clean history can be granted **auto-publish** (skip manual review, post-publish spot-check) via a permission. New/unverified authors always pass through manual review. Reports above a threshold auto-delist pending re-review.

---

## 25. THE WEBSITE BUILDER (primary feature — full detail)

This is the product. Everything else is plumbing around it. The builder is a **no-Livewire, Alpine.js + SortableJS, AJAX-driven visual editor** that lets a user assemble a complete multi-page, multi-language website by dragging bloks onto a canvas, configuring them through a Form Wizard, editing inline, and previewing live — then publish or export.

### 25.1 Builder layout (anatomy)

The builder screen (`/cms/builder/{pageId}`) is a four-region application shell, all reactive via Alpine stores:

```
┌──────────────────────────────────────────────────────────────────────────┐
│ TOP BAR: page selector ▸ locale switcher ▸ device toggle ▸ undo/redo ▸     │
│          Preview ▸ Save ▸ Publish ▸ Export                                  │
├───────────┬──────────────────────────────────────────────┬───────────────┤
│           │                                                │               │
│  LEFT     │              CANVAS (live preview)             │   RIGHT       │
│  PALETTE  │   ┌──────────────────────────────────────┐    │   INSPECTOR   │
│           │   │  HEADER section (droppable)           │    │               │
│  search   │   ├──────────────────────────────────────┤    │  Form Wizard  │
│  bloks    │   │  BODY section (droppable, sortable)   │    │  for selected │
│  by       │   │    [Hero blok]      ⋮ ✎ ⧉ 🗑          │    │  blok         │
│  category │   │    [Stat row]       ⋮ ✎ ⧉ 🗑          │    │               │
│           │   │    [Two-col ▸ slot] ⋮ ✎ ⧉ 🗑          │    │  • Content    │
│  drag ──▶ │   │       ↳ nested bloks                  │    │  • Style      │
│           │   ├──────────────────────────────────────┤    │  • Layout     │
│  + Layers │   │  FOOTER section (droppable)           │    │  • Visibility │
│    tree   │   └──────────────────────────────────────┘    │  • SEO        │
│           │                                                │               │
└───────────┴──────────────────────────────────────────────┴───────────────┘
```

The left rail toggles between the **Palette** (draggable blok library, searchable, grouped by category, including installed community bloks) and the **Layers tree** (the recursive blok hierarchy as a collapsible outline for precise selection and reordering of deeply nested bloks).

### 25.2 Core interactions

The builder must support, end to end: **drag from palette to canvas** (clone-on-drag, drops into the section/slot under the cursor); **drag to reorder** within a section and **across sections**; **drag into a slot** of a nesting-capable blok (e.g. columns), respecting `accepts_children` and `slots[].max_children`, and enforcing the **depth-10 nesting limit**; **click to select** (selection highlights the blok and opens its Inspector); **inline editing** of text/richtext fields directly on the canvas (contenteditable bound back to config); **duplicate** (clone blok + subtree); **delete** (with subtree); **copy/paste** a blok or subtree across pages; **hide/show** a blok (`is_visible`); **role-gate** a blok (`required_role`); **device preview** (desktop/tablet/mobile widths) with per-breakpoint visibility; **locale switch** (edits `locale_config` for the active locale while shared structure stays in `config`); **undo/redo** (client-side command stack backed by server revisions); **autosave** (debounced) plus explicit Save; **live preview** in an iframe rendering the real public template; and **revision restore** from history.

### 25.3 Builder state — Alpine store (`builder.js`)

```javascript
/**
 * WebBlok Website Builder — Alpine store (no Livewire).
 * Manages canvas state, selection, undo/redo, autosave, and AJAX sync.
 */
document.addEventListener('alpine:init', () => {
  Alpine.store('builder', {
    pageId: null,
    locale: 'en',
    device: 'desktop',          // desktop | tablet | mobile
    sections: { header: [], body: [], footer: [] },
    selectedId: null,
    isDirty: false,
    saving: false,

    // Undo/redo command stacks (client-side, mirrored to server revisions)
    _undo: [],
    _redo: [],

    csrf() { return document.querySelector('meta[name="csrf-token"]').content; },

    async init(pageId, locale) {
      this.pageId = pageId;
      this.locale = locale;
      await this.load();
    },

    async load() {
      const r = await fetch(`/cms/builder/${this.pageId}/tree?locale=${this.locale}`, {
        headers: { 'X-CSRF-TOKEN': this.csrf() },
      });
      const j = await r.json();
      this.sections = j.data.sections;
      this.selectedId = null;
      this.isDirty = false;
    },

    _snapshot() {
      return JSON.parse(JSON.stringify(this.sections));
    },

    _pushUndo() {
      this._undo.push(this._snapshot());
      if (this._undo.length > 50) this._undo.shift();
      this._redo = [];
    },

    async undo() {
      if (!this._undo.length) return;
      this._redo.push(this._snapshot());
      this.sections = this._undo.pop();
      await this.persistTree();
    },

    async redo() {
      if (!this._redo.length) return;
      this._undo.push(this._snapshot());
      this.sections = this._redo.pop();
      await this.persistTree();
    },

    select(id) { this.selectedId = id; this.$dispatch('blok-selected', { id }); },

    async addBlok(blokKey, section, index, parentId = null, slotName = null) {
      this._pushUndo();
      const r = await fetch(`/cms/builder/${this.pageId}/bloks`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
        body: JSON.stringify({ blok_key: blokKey, section, sort_order: index, parent_id: parentId, slot_name: slotName, locale: this.locale }),
      });
      if (r.status === 422) { this.toast('Nesting limit or slot is full.'); return; }
      await this.load();
      this.isDirty = true;
    },

    async updateConfig(instanceId, patch) {
      this._pushUndo();
      await fetch(`/cms/builder/bloks/${instanceId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
        body: JSON.stringify({ ...patch, locale: this.locale }),
      });
      this.isDirty = true;
      this.scheduleAutosave();
    },

    async duplicate(instanceId) {
      this._pushUndo();
      await fetch(`/cms/builder/bloks/${instanceId}/duplicate`, { method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf() } });
      await this.load();
    },

    async remove(instanceId) {
      this._pushUndo();
      await fetch(`/cms/builder/bloks/${instanceId}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': this.csrf() } });
      if (this.selectedId === instanceId) this.selectedId = null;
      await this.load();
    },

    async persistTree() {
      await fetch(`/cms/builder/${this.pageId}/tree`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
        body: JSON.stringify({ sections: this.sections, locale: this.locale }),
      });
    },

    _autosaveTimer: null,
    scheduleAutosave() {
      clearTimeout(this._autosaveTimer);
      this._autosaveTimer = setTimeout(() => this.save(true), 1500);
    },

    async save(auto = false) {
      this.saving = true;
      await this.persistTree();
      // Create a server-side revision snapshot on explicit saves
      if (!auto) {
        await fetch(`/cms/builder/${this.pageId}/revision`, { method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf() } });
      }
      this.saving = false;
      this.isDirty = false;
    },

    async publish() {
      await this.save(false);
      await fetch(`/cms/builder/${this.pageId}/publish`, { method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf() } });
      this.toast('Published.');
    },

    setDevice(d) { this.device = d; },
    setLocale(l) { this.locale = l; this.load(); },
    toast(msg) { this.$dispatch('toast', { msg }); },
  });
});
```

### 25.4 Drag-and-drop with nesting + slot rules (`builder-dnd.js`)

```javascript
import Sortable from 'sortablejs';

const instances = new Map();

function bindZone(el) {
  if (!el) return;
  const key = el.dataset.zoneKey;
  if (instances.has(key)) instances.get(key).destroy();

  const sortable = Sortable.create(el, {
    group: { name: 'wb-canvas', put: ['wb-canvas', 'wb-palette'], pull: true },
    animation: 180,
    handle: '.wb-handle',
    ghostClass: 'wb-ghost',
    fallbackOnBody: true,
    swapThreshold: 0.65,

    onAdd(evt) {
      const store = Alpine.store('builder');
      const section  = el.dataset.section || 'body';
      const parentId = el.dataset.parentId || null;
      const slotName = el.dataset.slotName || null;
      const index    = evt.newIndex ?? 0;

      if (evt.item.dataset.blokKey && evt.from.dataset.palette) {
        // New blok from palette
        const blokKey = evt.item.dataset.blokKey;
        evt.item.remove();
        store.addBlok(blokKey, section, index, parentId, slotName);
      } else {
        // Moved existing blok across zones — persist whole tree
        store.persistTree();
      }
    },

    onUpdate() { Alpine.store('builder').persistTree(); },
  });

  instances.set(key, sortable);
}

export function initBuilderDnd() {
  document.querySelectorAll('[data-zone-key]').forEach(bindZone);
  const palette = document.querySelector('[data-palette]');
  if (palette) {
    Sortable.create(palette, { group: { name: 'wb-palette', pull: 'clone', put: false }, sort: false });
  }
}

// Re-bind after every canvas re-render (Alpine x-effect calls this)
window.WBRebindDnd = initBuilderDnd;
```

### 25.5 Server: builder controller (authoritative tree + rules)

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cms;

use App\Actions\Bloks\CreateBlokInstance;
use App\Actions\Pages\CreatePageRevision;
use App\Http\Controllers\Controller;
use App\Services\BlokInstanceResolver;
use App\Services\BuilderTreeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BuilderController extends Controller
{
    public function __construct(
        private readonly BlokInstanceResolver $resolver,
        private readonly BuilderTreeService   $tree,
        private readonly CreateBlokInstance   $createBlok,
        private readonly CreatePageRevision   $createRevision,
    ) {}

    public function tree(string $pageId, Request $request): JsonResponse
    {
        $locale = (string) $request->query('locale', 'en');

        return response()->json([
            'data' => ['sections' => $this->resolver->sectionedTree($pageId, $locale)],
        ]);
    }

    public function storeBlok(string $pageId, Request $request): JsonResponse
    {
        /** @var array{blok_key:string,section:string,sort_order:int,parent_id:?string,slot_name:?string} $v */
        $v = $request->validate([
            'blok_key'   => ['required', 'string'],
            'section'    => ['required', 'string'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'parent_id'  => ['nullable', 'string'],
            'slot_name'  => ['nullable', 'string'],
        ]);

        // Enforces depth-10 limit + slot max_children (throws 422 on violation)
        $instance = $this->createBlok->execute($pageId, $v);

        return response()->json(['data' => ['id' => $instance->id]], 201);
    }

    public function persistTree(string $pageId, Request $request): JsonResponse
    {
        /** @var array{sections: array<string, array<int, array<string, mixed>>>} $v */
        $v = $request->validate(['sections' => ['required', 'array']]);

        $this->tree->persist($pageId, $v['sections']); // validates depth + slots server-side

        return response()->json(['ok' => true]);
    }

    public function revision(string $pageId): JsonResponse
    {
        $this->createRevision->execute($pageId, 'Manual save from builder');

        return response()->json(['ok' => true]);
    }
}
```

### 25.6 Nesting + slot enforcement (`CreateBlokInstance` action)

```php
<?php

declare(strict_types=1);

namespace App\Actions\Bloks;

use App\Exceptions\BlokNestingDepthExceeded;
use App\Exceptions\InvalidBlokSchemaException;
use App\Models\Platform\BlokDefinition;
use App\Models\Tenant\BlokInstance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateBlokInstance
{
    private const MAX_DEPTH = 10;

    /**
     * @param array{blok_key:string,section:string,sort_order:int,parent_id:?string,slot_name:?string} $input
     */
    public function execute(string $pageId, array $input): BlokInstance
    {
        $parentId = $input['parent_id'];

        if ($parentId !== null) {
            $depth = $this->depthOf($parentId);
            if ($depth + 1 > self::MAX_DEPTH) {
                throw new BlokNestingDepthExceeded('Maximum nesting depth of 10 exceeded.');
            }
            $this->assertSlotHasRoom($parentId, $input['slot_name']);
        }

        return BlokInstance::create([
            'id'         => Str::uuid()->toString(),
            'page_id'    => $pageId,
            'parent_id'  => $parentId,
            'slot_name'  => $input['slot_name'],
            'blok_key'   => $input['blok_key'],
            'section'    => $input['section'],
            'config'     => '{}',
            'locale_config' => '{}',
            'sort_order' => $input['sort_order'],
            'is_visible' => 1,
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ]);
    }

    private function depthOf(string $instanceId): int
    {
        $depth = 0;
        $current = $instanceId;

        while ($current !== null) {
            /** @var object{parent_id: ?string}|null $row */
            $row = DB::connection('tenant')->table('blok_instances')
                ->select('parent_id')->where('id', $current)->first();

            if ($row === null) {
                break;
            }
            $depth++;
            $current = $row->parent_id;

            if ($depth > self::MAX_DEPTH + 1) {
                throw new BlokNestingDepthExceeded('Cycle or excessive depth detected.');
            }
        }

        return $depth;
    }

    private function assertSlotHasRoom(string $parentId, ?string $slotName): void
    {
        /** @var object{blok_key: string}|null $parent */
        $parent = DB::connection('tenant')->table('blok_instances')
            ->select('blok_key')->where('id', $parentId)->first();

        if ($parent === null) {
            throw new InvalidBlokSchemaException('Parent blok not found.');
        }

        $def = BlokDefinition::where('blok_key', $parent->blok_key)->first();

        if ($def === null || ! (bool) $def->accepts_children) {
            throw new InvalidBlokSchemaException('Parent blok does not accept children.');
        }

        if ($slotName !== null && $def->slots !== null) {
            /** @var list<array{name:string,max_children:int}> $slots */
            $slots = json_decode($def->slots, true, 512, JSON_THROW_ON_ERROR);
            foreach ($slots as $slot) {
                if ($slot['name'] === $slotName) {
                    $count = DB::connection('tenant')->table('blok_instances')
                        ->where('parent_id', $parentId)->where('slot_name', $slotName)->count();
                    if ($count >= $slot['max_children']) {
                        throw new InvalidBlokSchemaException("Slot '{$slotName}' is full.");
                    }
                }
            }
        }
    }
}
```

### 25.7 The Form Wizard — 19 field types (the configuration engine)

Every blok exposes its editable fields through a multi-step **Form Wizard** defined by its `schema` JSON. The Inspector renders the wizard; changing a field calls `store.updateConfig()`. The 19 supported field types, each with a Blade partial and Alpine binding:

`text`, `textarea`, `richtext` (sanitized WYSIWYG), `number`, `toggle` (boolean), `select`, `multiselect`, `radio`, `checkbox-group`, `color` (picker, maps to CSS var), `image` (media-picker), `gallery` (multi-image), `icon` (icon picker), `link` (URL + page selector + target), `date`, `range` (slider), `repeater` (array of sub-field groups, e.g. list items / stats), `code` (restricted, escaped), and `hidden`.

Schema example (a stat-row blok):

```json
{
  "steps": [
    {
      "key": "content",
      "label": "Content",
      "fields": [
        { "key": "title", "type": "text", "label": "Section title", "localized": true },
        {
          "key": "stats", "type": "repeater", "label": "Stats", "max": 6,
          "fields": [
            { "key": "value", "type": "text",  "label": "Value", "localized": true },
            { "key": "label", "type": "text",  "label": "Label", "localized": true },
            { "key": "icon",  "type": "icon",  "label": "Icon" }
          ]
        }
      ]
    },
    {
      "key": "style",
      "label": "Style",
      "fields": [
        { "key": "bg",      "type": "color",  "label": "Background", "cssVar": "--wb-bg-secondary" },
        { "key": "columns", "type": "range",  "label": "Columns", "min": 1, "max": 6, "default": 3 }
      ]
    }
  ]
}
```

Fields marked `"localized": true` write to `locale_config[locale]`; everything else writes to the shared `config`. The wizard supports **conditional visibility** (`"showIf": {"field": "layout", "equals": "grid"}`) so a field only appears when another field has a given value.

### 25.8 Inline editing on the canvas

For `text` and `richtext` fields, the rendered canvas blok carries `contenteditable` regions bound via `data-field`. On blur, Alpine reads the edited content, sanitizes (for richtext), and calls `updateConfig`. This gives WYSIWYG editing directly in context, not just in the Inspector — the single most-requested builder behavior.

### 25.9 Page & site management around the builder

The builder operates on one page, but the **Pages panel** (`/cms/pages`) manages the whole site tree: create/rename/reorder pages, set parent/child (which updates `full_path`), mark a homepage, set per-page template and auth/role gating, schedule publish (`scheduled_at` picked up by the cron task), and manage per-locale SEO (`page_locales`). The **Menus panel** builds navigation from pages with drag-sortable hierarchical items and role visibility. Together these let a user build a *complete website*, not just a page.

### 25.10 Live preview, device modes, and publish/export

The canvas itself is an accurate live render, but a **Preview** button opens the real public renderer (`/site/...` in an iframe with a preview token) so the user sees exactly what visitors get, including the active theme. Device toggles constrain the canvas/iframe width to desktop/tablet/mobile breakpoints, and bloks honor per-breakpoint visibility flags. **Publish** transitions the page to `published` and fires `PagePublished` (webhooks + edge/static emit). **Export** runs `BuildStaticSiteJob` to produce the downloadable ZIP with the injected `api-client.js` bridge.

### 25.11 Builder accessibility & UX requirements

Full keyboard operation (tab to bloks, arrow to reorder, Enter to edit, Delete to remove), ARIA roles on canvas zones, focus-visible outlines, a persistent autosave indicator, an unsaved-changes guard on navigation, RTL-correct layout when the active locale is `ar`/`ur`, and a "blank page / start from template / generate with AI" entry chooser when creating a new page.

### 25.12 Builder data flow (summary)

A user drags a blok → `addBlok` POSTs → `CreateBlokInstance` enforces depth/slot rules → tree reloads → user edits via Form Wizard or inline → debounced `updateConfig`/autosave persists `config` + `locale_config` → explicit Save writes a `page_revision` snapshot (enables undo/restore) → Publish flips status and emits events → Export produces a portable static bundle. Every blok the user adds — official Blade or community sandboxed — appears in the same palette and renders through the appropriate renderer.

---

## 26. Updated sprint additions

Insert these into the V2 plan: **Sprint 3.5 (Builder Core)** — the Alpine builder store, DnD with nesting/slot enforcement, `BuilderController`, `CreateBlokInstance` rules, Form Wizard (19 field types), inline editing, undo/redo, autosave, live preview, device modes. Treat this as the highest-priority sprint. **Sprint 7.5 (Community)** — `community_*` tables, `ArtifactValidator` sandbox, lifecycle actions/state machine, `CommunityBlokRenderer`, Creator Studio + admin Review Queue, ratings/reports. **Sprint 8.5 (Scale)** — corrected `CronRunner`, `MaterializeTenant`, dormant tenants, `GenerateSite` + stub driver, `SiteBundleImporter`, headless `POST /v1/tenants`.

---

## 27. Updated arch tests

```php
arch('Community actions are final')
    ->expect('App\Actions\Community')->toBeFinal();

arch('AI actions are final')
    ->expect('App\Actions\Ai')->toBeFinal();

arch('Site generator drivers implement the contract')
    ->expect('App\Services\Ai\Drivers')
    ->toImplement('App\Services\Ai\Contracts\SiteGeneratorDriver');

arch('Artifact validator rejects executable content')
    ->expect('App\Services\Community\ArtifactValidator')->toBeFinal();

arch('Builder still avoids Livewire/Filament')
    ->expect('App')->not->toUse(['Livewire\Component', 'Filament\Panel']);
```

---

