# WebBlok CMS SaaS — Complete Project Specification Prompt (V2)

> **Document Purpose**: The single, authoritative, developer-ready specification to build the **WebBlok CMS SaaS** platform from absolute zero. Hand this file to any AI coding assistant, senior engineer, or engineering team. Every architectural decision, every database table, every class signature, every package version, every PHPStan rule, and every deployment detail is covered here.
>
> **Prerequisite**: Read `WEBBLOK-SAAS-PROMPT.md` (V1) for the source-material context — 150-slide 2030B interactive dashboard, 450 JSON data files, 7 supported languages, and the existing Blade component inventory.
>
> **Strictness Level**: PHPStan / Larastan **Level 10** (maximum). No exceptions. This is non-negotiable.

---

## 0. One-Line Vision

> **WebBlok CMS** is a **full-featured, multi-tenant SaaS CMS** (one SQLite database per tenant) built on **Laravel 12.x + Filament 3 + Livewire 3 + Alpine.js + SortableJS**, where any non-technical user can **build a complete website from scratch** — with hierarchical menus, SEO-optimised pages, multi-language content, role-based access control, and a **drag-and-drop visual blok editor** that supports infinite recursive nesting — then serve their site live via API **or download the entire website** as a self-contained static bundle that uses AJAX to talk back to their tenant SQLite API.

---

## 1. V2 vs V1 Feature Matrix

| Dimension | V1 (`WEBBLOK-SAAS-PROMPT.md`) | V2 (This Document) |
|-----------|-------------------------------|---------------------|
| **Core Purpose** | Embed API for existing third-party sites | Build complete websites from scratch inside the platform |
| **Target User** | Developer consuming an API | Non-technical content creator / site builder |
| **Visual Editor** | None (API only) | Full drag-and-drop Livewire 3 page editor |
| **Page System** | No pages concept | Full page tree: slugs, parent/child, drafts, scheduling |
| **SEO** | No | Per-page SEO (title, meta description, OG tags, sitemap) |
| **Menus** | No | Nested menus with roles-aware visibility |
| **ACL** | API key only | Full RBAC: roles + permissions per tenant |
| **Multi-language** | Per-blok JSON overrides | Full CMS with locale switcher and i18n content |
| **Blok Nesting** | Single-level | Infinite recursive nesting with named slots |
| **Blok Config** | JSON API payload | Multi-step Form Wizard (Livewire UI) |
| **Static Export** | No | Full site ZIP download with embedded AJAX bridge |
| **Admin UI** | Blade + Breeze | Filament 3 two-panel system |
| **Tenant Provisioning** | Manual | Automated via `ProvisionTenantJob` |
| **Static Analysis** | Not specified | **Larastan / PHPStan Level 10 — enforced in CI** |
| **Queue System** | No | Redis-backed Laravel Queues |
| **Revision History** | No | Full page/blok revision tracking with diff |
| **Media Management** | No | `spatie/laravel-medialibrary` per-tenant |
| **Analytics** | No | Tenant page-view tracking + export metrics |
| **Webhooks** | No | Outgoing webhooks on publish/export events |

---

## 2. Core Concepts & Mental Model

### 2.1 The Four-Layer CMS Hierarchy

```
TENANT WEBSITE
│
├── Global Settings (name, logo, default locale, timezone, plan)
│
├── Menus (N menus per site)
│   └── MenuItems (recursive tree: label, URL/page ref, icon, ACL role)
│
├── Pages (tree structure, parent→child)
│   └── Page (slug, status, SEO, ACL, scheduled_at)
│         └── PageLocale (content per language: title, description, meta)
│               └── Page Sections (ordered regions: header, body, footer, sidebar…)
│                     └── Blok Instance (configured blok placed in this section)
│                           ├── config: { field: value } ← Form Wizard output
│                           ├── locale_config: { en: {…}, ar: {…} }
│                           └── children: BlokInstance[] (recursive, named slots)
│
└── Media Library (images, files, videos — per tenant)
```

### 2.2 Blok Definition vs Blok Instance

| Concept | Scope | Who Owns It | What It Contains |
|---------|-------|-------------|------------------|
| **Blok Definition** | Platform-level | Platform operator | Component class, Blade view, JSON schema, default config |
| **Blok Instance** | Tenant-level | Tenant user | Ref to definition, field values (Form Wizard output), child instances, slot assignment |

**Blok Definition** is immutable from the tenant's perspective. A Definition might be `slide-01` (a dashboard summary blok), `hero-section`, `stat-card`, `two-column-grid`, `faq-accordion`, etc.

**Blok Instance** is the tenant's configured copy: "I placed `hero-section` at the top of my home page, with headline = 'Welcome', background = '#1a1a2e', and these two child bloks in the `cta_slot`."

### 2.3 Blok Schema — Form Wizard Definition

Every Blok Definition has a JSON schema that drives the Form Wizard UI. The schema is resolved by `BlokSchemaResolver`, merged with tenant overrides, and rendered by `BlokInstanceSettings` (Livewire component).

**Full schema example** (`blok_definitions/hero-section.schema.json`):

```json
{
  "blok_id": "hero-section",
  "version": "1.2.0",
  "label": "Hero Section",
  "description": "Full-width hero banner with headline, sub-text, CTA button, and optional background media.",
  "icon": "heroicon-o-sparkles",
  "category": "layout",
  "accepts_children": true,
  "slots": [
    { "name": "cta_slot", "label": "CTA Area", "max_children": 3 },
    { "name": "media_slot", "label": "Media Area", "max_children": 1 }
  ],
  "schema": {
    "steps": [
      {
        "key": "content",
        "label": "Content",
        "icon": "heroicon-o-document-text",
        "fields": [
          {
            "key": "headline",
            "type": "text",
            "label": "Headline",
            "required": true,
            "i18n": true,
            "placeholder": "Welcome to our platform",
            "max_length": 120,
            "hint": "Displayed as the main H1 of the hero section."
          },
          {
            "key": "subtext",
            "type": "textarea",
            "label": "Sub-text",
            "required": false,
            "i18n": true,
            "rows": 3
          },
          {
            "key": "cta_label",
            "type": "text",
            "label": "CTA Button Label",
            "i18n": true,
            "default": "Get Started"
          },
          {
            "key": "cta_url",
            "type": "text",
            "label": "CTA Button URL",
            "placeholder": "/pricing or https://..."
          }
        ]
      },
      {
        "key": "appearance",
        "label": "Appearance",
        "icon": "heroicon-o-paint-brush",
        "fields": [
          {
            "key": "theme",
            "type": "select",
            "label": "Visual Theme",
            "options": [
              { "value": "dark", "label": "Dark" },
              { "value": "light", "label": "Light" },
              { "value": "ocean", "label": "Ocean" },
              { "value": "custom", "label": "Custom Color" }
            ],
            "default": "dark"
          },
          {
            "key": "bg_color",
            "type": "color",
            "label": "Background Color",
            "condition": { "field": "theme", "operator": "eq", "value": "custom" }
          },
          {
            "key": "bg_image",
            "type": "image",
            "label": "Background Image",
            "hint": "Recommended: 1920×1080px WebP"
          },
          {
            "key": "text_align",
            "type": "select",
            "label": "Text Alignment",
            "options": ["left", "center", "right"],
            "default": "center"
          },
          {
            "key": "min_height",
            "type": "range",
            "label": "Minimum Height (vh)",
            "min": 30,
            "max": 100,
            "step": 5,
            "default": 60
          }
        ]
      },
      {
        "key": "seo",
        "label": "SEO & Meta",
        "icon": "heroicon-o-magnifying-glass",
        "fields": [
          {
            "key": "aria_label",
            "type": "text",
            "label": "ARIA Label",
            "hint": "Accessibility label for this section"
          },
          {
            "key": "schema_type",
            "type": "select",
            "label": "Schema.org Type",
            "options": ["None", "WebPageElement", "SiteNavigationElement"]
          }
        ]
      }
    ]
  }
}
```

**All supported Form Wizard field types**:

| Type | Renders As | Notes |
|------|-----------|-------|
| `text` | `<input type="text">` | Optional `i18n: true` for per-locale content |
| `textarea` | `<textarea>` | Optional `rows` |
| `rich_text` | Trix WYSIWYG | Full HTML output |
| `number` | `<input type="number">` | Optional `min`, `max`, `step` |
| `toggle` | Switch | Boolean |
| `select` | `<select>` | `options` array of `{value, label}` |
| `multi_select` | Checkboxes / tags | Array output |
| `color` | Color picker | Hex string |
| `image` | Media library picker | Returns `media_id` |
| `file` | File upload picker | Returns `media_id` |
| `icon_picker` | Heroicons grid | Returns icon name |
| `locale` | Locale select | Returns locale code |
| `relation` | Searchable dropdown | Returns related record ID |
| `blok_picker` | Blok definition select | Returns `blok_id` |
| `code` | CodeMirror editor | Returns code string |
| `date` | Date picker | ISO 8601 string |
| `range` | Slider | Number |
| `repeater` | Dynamic rows | Array of field-groups |
| `group` | Grouped sub-fields | Nested object |
| `condition` | Show/hide logic | Field visibility rules |

### 2.4 Recursive Blok Nesting

Container bloks declare `accepts_children: true` and name their **slots**. Each child Blok Instance references its parent instance and declares which slot it occupies.

**Resolution algorithm** (`BlokInstanceResolver`):

```
resolveTree(pageId, locale):
  1. Load all BlokInstances for pageId ORDER BY section, sort_order
  2. Group by parent_instance_id (NULL = root)
  3. For each root instance:
     a. Load Blok Definition (schema, view)
     b. Resolve config (merge definition defaults → instance config → locale_config[locale])
     c. If accepts_children: recurse into children (up to MAX_DEPTH = 10)
     d. Render Blade component with resolved config + rendered children
  4. Return rendered HTML per section
```

**Depth protection**: A `$depth` counter is passed down; at depth 10 an `EmergencyBlok` placeholder renders.

---

## 3. Technology Stack

### 3.1 Core Stack Table

| Layer | Technology | Version | Rationale |
|-------|-----------|---------|-----------|
| Language | PHP | ^8.3 | Typed properties, fibers, readonly, native enums |
| Framework | Laravel | ^12.0 | LTS, first-party packages, typed routing |
| CMS Admin | Filament | ^3.2 | Two-panel system: `/super-admin` + `/cms` |
| Reactive UI | Livewire | ^3.4 | `PageEditor`, Form Wizard, Live Preview |
| Alpine.js | Alpine.js | ^3.x | Lightweight DOM interactivity in Blade |
| CSS | Tailwind CSS | ^3.x | Utility-first; Filament compatible |
| Assets | Vite | ^5.x | Laravel 12 default bundler |
| Drag-Drop | SortableJS | ^1.15 | Blok reordering + palette clone |
| Platform DB | MySQL | ^8.0 | Central tenant registry |
| Tenant DB | SQLite | ^3.42 | WAL mode; one `.sqlite` per tenant |
| Cache | Redis | ^7.0 | Render cache, sessions, Horizon queues |
| Queue Driver | Laravel Horizon | ^5.x | Redis queue monitoring + management |
| Search | Laravel Scout | ^10.x | CMS blok / page content search |
| Full-text | MeiliSearch | ^1.x | Scout driver; fast typo-tolerant search |
| Static Analysis | PHPStan + Larastan | ^1.11 / ^2.9 | **Level 10 — CI-enforced** |
| Code Style | Laravel Pint | ^1.x | PSR-12 + `declare_strict_types: true` |

### 3.2 Spatie Package Suite

| Package | Version | Role |
|---------|---------|------|
| `spatie/laravel-data` | ^4.x | Typed DTOs — all data transfer objects |
| `spatie/laravel-permission` | ^6.x | Tenant RBAC (roles/permissions in tenant SQLite) |
| `spatie/laravel-medialibrary` | ^11.x | Per-tenant media management |
| `spatie/laravel-translatable` | ^6.x | Multi-language Eloquent model content |
| `spatie/laravel-activitylog` | ^4.x | Audit log for tenant actions |
| `spatie/laravel-sluggable` | ^3.x | Auto-slugs for pages, menus |
| `spatie/laravel-query-builder` | ^5.x | API filtering, sorting, includes |
| `spatie/laravel-backup` | ^9.x | Automated tenant SQLite backup |
| `spatie/laravel-webhook-client` | ^3.x | Incoming webhook handling |
| `spatie/laravel-schedule-monitor` | ^3.x | Scheduled job health monitoring |

### 3.3 Full `composer.json`

```json
{
  "name": "webblok/cms-saas",
  "description": "WebBlok CMS SaaS — Multi-tenant drag-and-drop website builder",
  "type": "project",
  "license": "proprietary",
  "require": {
    "php": "^8.3",
    "laravel/framework": "^12.0",
    "laravel/sanctum": "^4.0",
    "laravel/fortify": "^1.21",
    "laravel/horizon": "^5.25",
    "laravel/scout": "^10.9",
    "filament/filament": "^3.2",
    "livewire/livewire": "^3.4",
    "spatie/laravel-data": "^4.3",
    "spatie/laravel-permission": "^6.3",
    "spatie/laravel-medialibrary": "^11.5",
    "spatie/laravel-translatable": "^6.7",
    "spatie/laravel-activitylog": "^4.7",
    "spatie/laravel-sluggable": "^3.5",
    "spatie/laravel-query-builder": "^5.8",
    "spatie/laravel-backup": "^9.1",
    "spatie/laravel-webhook-client": "^3.3",
    "spatie/laravel-schedule-monitor": "^3.7",
    "meilisearch/meilisearch-php": "^1.7",
    "predis/predis": "^2.2",
    "league/flysystem-aws-s3-v3": "^3.22",
    "barryvdh/laravel-dompdf": "^2.2",
    "doctrine/dbal": "^3.8",
    "nesbot/carbon": "^3.0"
  },
  "require-dev": {
    "fakerphp/faker": "^1.23",
    "laravel/pint": "^1.16",
    "mockery/mockery": "^1.6",
    "nunomaduro/collision": "^8.3",
    "pestphp/pest": "^2.34",
    "pestphp/pest-plugin-laravel": "^2.4",
    "pestphp/pest-plugin-arch": "^2.7",
    "pestphp/pest-plugin-livewire": "^2.1",
    "larastan/larastan": "^2.9",
    "phpstan/phpstan": "^1.11",
    "phpstan/phpstan-strict-rules": "^1.6",
    "rector/rector": "^1.1",
    "driftingly/rector-laravel": "^1.0",
    "brianium/paratest": "^7.4",
    "spatie/laravel-ray": "^1.37"
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
      "@php artisan package:discover --ansi",
      "@php artisan filament:upgrade"
    ],
    "analyse": "vendor/bin/phpstan analyse --configuration=phpstan.neon",
    "test": "vendor/bin/pest --parallel",
    "lint": "vendor/bin/pint --test",
    "fix": "vendor/bin/pint",
    "rector": "vendor/bin/rector process --dry-run",
    "ci": ["@lint", "@analyse", "@test"]
  },
  "minimum-stability": "stable",
  "prefer-stable": true
}
```

### 3.4 `phpstan.neon` — Level 10 Configuration

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
    excludePaths:
        - app/Http/Middleware/TrustProxies.php
    checkMissingIterableValueType: true
    checkGenericClassInNonGenericObjectType: true
    checkUninitializedProperties: true
    checkDynamicProperties: true
    reportUnmatchedIgnoredErrors: true
    treatPhpDocTypesAsCertain: true
    ignoreErrors:
        - '#Unsafe usage of new static\(\)#'
    parallel:
        maximumNumberOfProcesses: 4
```

### 3.5 `pint.json` — Code Style

```json
{
  "preset": "laravel",
  "rules": {
    "declare_strict_types": true,
    "final_class": false,
    "php_unit_strict": true,
    "strict_param": true,
    "no_unused_imports": true,
    "ordered_imports": { "sort_algorithm": "alpha" },
    "single_quote": true,
    "trailing_comma_in_multiline": { "elements": ["arrays", "arguments", "parameters"] }
  }
}
```

---

## 4. Database Architecture

### 4.1 Tenancy Strategy

**Manual tenancy — no `stancl/tenancy` package.**

Reason: `stancl/tenancy` breaks PHPStan inference at Level 10 (dynamic proxy calls on `Tenant` models). Instead, a custom lightweight system is used:

```
TenantContext singleton (bound in AppServiceProvider)
  └── holds: ?Tenant $currentTenant
  └── method: switchTo(Tenant $t): void
        └── sets DB::connection('tenant') → $t->database_path
        └── sets Storage::disk('tenant') → $t->storage_path
```

Every CMS/API request goes through `SetTenantDatabaseConnection` middleware which resolves the tenant from domain/subdomain and calls `TenantContext::switchTo()`.

### 4.2 Platform Database (MySQL) — Full Schema

```sql
-- ═══════════════════════════════════════════════════════════
-- PLATFORM DATABASE (MySQL) — Central Registry
-- ═══════════════════════════════════════════════════════════

CREATE TABLE plans (
    id            CHAR(36)     PRIMARY KEY,
    name          VARCHAR(64)  NOT NULL,           -- 'free','starter','pro','enterprise'
    display_name  VARCHAR(128) NOT NULL,
    max_pages     SMALLINT     NOT NULL DEFAULT 5,
    max_bloks     SMALLINT     NOT NULL DEFAULT 50,
    max_locales   TINYINT      NOT NULL DEFAULT 1,
    max_media_mb  INT          NOT NULL DEFAULT 100,
    max_exports   SMALLINT     NOT NULL DEFAULT 1,
    api_rate_rpm  INT          NOT NULL DEFAULT 60,
    price_monthly DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    price_yearly  DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    is_active     BOOLEAN      NOT NULL DEFAULT TRUE,
    features      JSON         NULL,               -- extra feature flags
    created_at    TIMESTAMP    NULL,
    updated_at    TIMESTAMP    NULL
) ENGINE=InnoDB;

CREATE TABLE tenants (
    id              CHAR(36)      PRIMARY KEY,
    plan_id         CHAR(36)      NOT NULL REFERENCES plans(id),
    name            VARCHAR(128)  NOT NULL,
    slug            VARCHAR(64)   NOT NULL UNIQUE,
    domain          VARCHAR(253)  NULL UNIQUE,     -- custom domain (CNAME)
    subdomain       VARCHAR(64)   NOT NULL UNIQUE, -- {subdomain}.webblok.io
    database_path   VARCHAR(512)  NOT NULL,        -- absolute path to .sqlite file
    storage_path    VARCHAR(512)  NOT NULL,        -- absolute path to tenant media dir
    status          ENUM('provisioning','active','suspended','deleted') NOT NULL DEFAULT 'provisioning',
    trial_ends_at   TIMESTAMP     NULL,
    billing_email   VARCHAR(255)  NOT NULL,
    settings        JSON          NULL,            -- theme preferences, timezone, etc.
    metadata        JSON          NULL,            -- internal ops data
    created_at      TIMESTAMP     NULL,
    updated_at      TIMESTAMP     NULL,
    deleted_at      TIMESTAMP     NULL,
    INDEX idx_tenants_subdomain (subdomain),
    INDEX idx_tenants_domain (domain),
    INDEX idx_tenants_status (status)
) ENGINE=InnoDB;

CREATE TABLE platform_users (
    id              CHAR(36)     PRIMARY KEY,
    name            VARCHAR(128) NOT NULL,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL,
    is_super_admin  BOOLEAN      NOT NULL DEFAULT FALSE,
    two_factor_secret      TEXT NULL,
    two_factor_recovery_codes TEXT NULL,
    remember_token  VARCHAR(100) NULL,
    created_at      TIMESTAMP    NULL,
    updated_at      TIMESTAMP    NULL,
    deleted_at      TIMESTAMP    NULL
) ENGINE=InnoDB;

CREATE TABLE tenant_users (
    id          CHAR(36)     PRIMARY KEY,
    tenant_id   CHAR(36)     NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name        VARCHAR(128) NOT NULL,
    email       VARCHAR(255) NOT NULL,
    password    VARCHAR(255) NOT NULL,
    avatar_url  VARCHAR(512) NULL,
    last_login_at TIMESTAMP  NULL,
    remember_token VARCHAR(100) NULL,
    email_verified_at TIMESTAMP NULL,
    created_at  TIMESTAMP    NULL,
    updated_at  TIMESTAMP    NULL,
    deleted_at  TIMESTAMP    NULL,
    UNIQUE KEY uq_tenant_email (tenant_id, email),
    INDEX idx_tenant_users_tenant (tenant_id)
) ENGINE=InnoDB;

CREATE TABLE blok_definitions (
    id              CHAR(36)     PRIMARY KEY,
    blok_key        VARCHAR(128) NOT NULL UNIQUE, -- e.g. 'slide-01', 'hero-section'
    label           VARCHAR(128) NOT NULL,
    description     TEXT         NULL,
    category        VARCHAR(64)  NOT NULL DEFAULT 'content',
    icon            VARCHAR(128) NULL,
    accepts_children BOOLEAN     NOT NULL DEFAULT FALSE,
    slots           JSON         NULL,            -- [{name, label, max_children}]
    schema          JSON         NOT NULL,        -- Form Wizard schema
    default_config  JSON         NULL,            -- Default field values
    blade_component VARCHAR(255) NOT NULL,        -- Component class FQCN
    is_active       BOOLEAN      NOT NULL DEFAULT TRUE,
    sort_order      SMALLINT     NOT NULL DEFAULT 0,
    tags            JSON         NULL,
    preview_url     VARCHAR(512) NULL,
    thumbnail_url   VARCHAR(512) NULL,
    created_at      TIMESTAMP    NULL,
    updated_at      TIMESTAMP    NULL,
    INDEX idx_blok_defs_category (category),
    INDEX idx_blok_defs_active (is_active)
) ENGINE=InnoDB;

CREATE TABLE api_tokens (
    id            CHAR(36)     PRIMARY KEY,
    tenant_id     CHAR(36)     NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name          VARCHAR(128) NOT NULL,
    token_hash    VARCHAR(64)  NOT NULL UNIQUE,   -- SHA-256 of raw token
    token_prefix  CHAR(8)      NOT NULL,          -- first 8 chars for lookup
    abilities     JSON         NULL,              -- ['read:bloks','write:bloks']
    last_used_at  TIMESTAMP    NULL,
    expires_at    TIMESTAMP    NULL,
    revoked_at    TIMESTAMP    NULL,
    created_at    TIMESTAMP    NULL,
    INDEX idx_api_tokens_tenant (tenant_id),
    INDEX idx_api_tokens_prefix (token_prefix)
) ENGINE=InnoDB;

CREATE TABLE platform_settings (
    id          INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    `key`       VARCHAR(128)   NOT NULL UNIQUE,
    value       TEXT           NOT NULL,
    group       VARCHAR(64)    NULL,
    cast_type   VARCHAR(32)    NULL DEFAULT 'string',
    description TEXT           NULL,
    updated_at  TIMESTAMP      NULL
) ENGINE=InnoDB;

CREATE TABLE subscription_events (
    id          CHAR(36)     PRIMARY KEY,
    tenant_id   CHAR(36)     NOT NULL REFERENCES tenants(id),
    event_type  VARCHAR(64)  NOT NULL, -- 'plan_upgrade','trial_start','payment_failed'
    from_plan   VARCHAR(64)  NULL,
    to_plan     VARCHAR(64)  NULL,
    payload     JSON         NULL,
    occurred_at TIMESTAMP    NOT NULL,
    created_at  TIMESTAMP    NULL
) ENGINE=InnoDB;
```

### 4.3 Tenant Database (SQLite) — Full Schema

```sql
-- ═══════════════════════════════════════════════════════════
-- TENANT DATABASE (SQLite 3.x — WAL mode)
-- Each tenant has their own .sqlite file
-- ═══════════════════════════════════════════════════════════

PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;
PRAGMA foreign_keys = ON;
PRAGMA temp_store = MEMORY;
PRAGMA cache_size = -16000;   -- 16MB page cache
PRAGMA mmap_size = 134217728; -- 128MB memory-mapped I/O

-- ─────────────────────────────────────────────────────────
-- Site Global Settings
-- ─────────────────────────────────────────────────────────
CREATE TABLE site_settings (
    key         TEXT    NOT NULL PRIMARY KEY,
    value       TEXT    NULL,
    cast_type   TEXT    NOT NULL DEFAULT 'string',
    group       TEXT    NULL
);

INSERT INTO site_settings VALUES
    ('site_name',        'My Website',  'string',  'general'),
    ('site_tagline',     '',            'string',  'general'),
    ('default_locale',   'en',          'string',  'general'),
    ('supported_locales','["en"]',      'json',    'general'),
    ('logo_media_id',    NULL,          'string',  'branding'),
    ('favicon_media_id', NULL,          'string',  'branding'),
    ('primary_color',    '#6366f1',     'string',  'branding'),
    ('footer_text',      '',            'string',  'general'),
    ('google_analytics_id', NULL,       'string',  'analytics'),
    ('robots_txt',       'User-agent: *\nAllow: /', 'string', 'seo');

-- ─────────────────────────────────────────────────────────
-- Roles & Permissions (spatie/laravel-permission)
-- ─────────────────────────────────────────────────────────
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

-- ─────────────────────────────────────────────────────────
-- Menus
-- ─────────────────────────────────────────────────────────
CREATE TABLE menus (
    id          TEXT    NOT NULL PRIMARY KEY,
    name        TEXT    NOT NULL,
    handle      TEXT    NOT NULL UNIQUE,   -- 'main-nav', 'footer', 'sidebar'
    locale      TEXT    NOT NULL DEFAULT 'en',
    created_at  TEXT    NULL,
    updated_at  TEXT    NULL
);

CREATE TABLE menu_items (
    id              TEXT    NOT NULL PRIMARY KEY,
    menu_id         TEXT    NOT NULL REFERENCES menus(id) ON DELETE CASCADE,
    parent_id       TEXT    NULL REFERENCES menu_items(id) ON DELETE SET NULL,
    label           TEXT    NOT NULL,        -- JSON for multi-locale: {"en":"Home","ar":"الرئيسية"}
    type            TEXT    NOT NULL DEFAULT 'page',  -- 'page','url','anchor','divider'
    page_id         TEXT    NULL,
    url             TEXT    NULL,
    target          TEXT    NOT NULL DEFAULT '_self',
    icon            TEXT    NULL,
    required_role   TEXT    NULL,            -- hide for users without this role
    is_active       INTEGER NOT NULL DEFAULT 1,
    sort_order      INTEGER NOT NULL DEFAULT 0,
    created_at      TEXT    NULL,
    updated_at      TEXT    NULL
);

CREATE INDEX idx_menu_items_menu ON menu_items(menu_id);
CREATE INDEX idx_menu_items_parent ON menu_items(parent_id);

-- ─────────────────────────────────────────────────────────
-- Pages
-- ─────────────────────────────────────────────────────────
CREATE TABLE pages (
    id              TEXT    NOT NULL PRIMARY KEY,
    parent_id       TEXT    NULL REFERENCES pages(id) ON DELETE SET NULL,
    slug            TEXT    NOT NULL,        -- URL-safe slug (e.g. 'about-us')
    full_path       TEXT    NOT NULL,        -- computed: '/about-us' or '/services/seo'
    status          TEXT    NOT NULL DEFAULT 'draft', -- 'draft','published','scheduled','archived'
    template        TEXT    NOT NULL DEFAULT 'default', -- blade template to use
    is_homepage     INTEGER NOT NULL DEFAULT 0,
    requires_auth   INTEGER NOT NULL DEFAULT 0,
    required_role   TEXT    NULL,
    sort_order      INTEGER NOT NULL DEFAULT 0,
    scheduled_at    TEXT    NULL,            -- ISO 8601 for future publish
    published_at    TEXT    NULL,
    created_by      TEXT    NULL,
    updated_by      TEXT    NULL,
    created_at      TEXT    NULL,
    updated_at      TEXT    NULL,
    deleted_at      TEXT    NULL,
    UNIQUE (slug, parent_id)
);

CREATE INDEX idx_pages_parent ON pages(parent_id);
CREATE INDEX idx_pages_status ON pages(status);
CREATE INDEX idx_pages_path ON pages(full_path);

CREATE TABLE page_locales (
    id              TEXT    NOT NULL PRIMARY KEY,
    page_id         TEXT    NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
    locale          TEXT    NOT NULL,        -- 'en','ar','fr','es','tr','id','ur'
    title           TEXT    NOT NULL DEFAULT '',
    description     TEXT    NULL,
    content         TEXT    NULL,            -- optional rich text intro
    meta_title      TEXT    NULL,
    meta_description TEXT   NULL,
    og_title        TEXT    NULL,
    og_description  TEXT    NULL,
    og_image_id     TEXT    NULL,
    canonical_url   TEXT    NULL,
    is_indexable    INTEGER NOT NULL DEFAULT 1,
    created_at      TEXT    NULL,
    updated_at      TEXT    NULL,
    UNIQUE (page_id, locale)
);

CREATE INDEX idx_page_locales_page ON page_locales(page_id);

-- ─────────────────────────────────────────────────────────
-- Blok Instances
-- ─────────────────────────────────────────────────────────
CREATE TABLE blok_instances (
    id              TEXT    NOT NULL PRIMARY KEY,
    page_id         TEXT    NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
    parent_id       TEXT    NULL REFERENCES blok_instances(id) ON DELETE CASCADE,
    slot_name       TEXT    NULL,            -- which slot in parent (if child)
    blok_key        TEXT    NOT NULL,        -- references blok_definitions.blok_key
    section         TEXT    NOT NULL DEFAULT 'body', -- 'header','body','footer','sidebar'
    config          TEXT    NOT NULL DEFAULT '{}',   -- JSON: Form Wizard field values
    locale_config   TEXT    NOT NULL DEFAULT '{}',   -- JSON: {en:{…}, ar:{…}} per-locale overrides
    is_visible      INTEGER NOT NULL DEFAULT 1,
    required_role   TEXT    NULL,            -- ACL: hide for users without this role
    sort_order      INTEGER NOT NULL DEFAULT 0,
    css_classes     TEXT    NULL,            -- custom CSS classes
    animation       TEXT    NULL,            -- 'fade-in','slide-up', etc.
    anchor_id       TEXT    NULL,            -- HTML id for deep-linking
    created_at      TEXT    NULL,
    updated_at      TEXT    NULL
);

CREATE INDEX idx_blok_inst_page ON blok_instances(page_id);
CREATE INDEX idx_blok_inst_parent ON blok_instances(parent_id);
CREATE INDEX idx_blok_inst_section ON blok_instances(section, sort_order);

-- ─────────────────────────────────────────────────────────
-- Page Revisions (full snapshot for undo)
-- ─────────────────────────────────────────────────────────
CREATE TABLE page_revisions (
    id              TEXT    NOT NULL PRIMARY KEY,
    page_id         TEXT    NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
    revision_number INTEGER NOT NULL,
    snapshot        TEXT    NOT NULL,        -- full JSON snapshot of page + all blok instances
    change_summary  TEXT    NULL,
    created_by      TEXT    NULL,
    created_at      TEXT    NULL,
    UNIQUE (page_id, revision_number)
);

CREATE INDEX idx_revisions_page ON page_revisions(page_id);

-- ─────────────────────────────────────────────────────────
-- Media Library
-- ─────────────────────────────────────────────────────────
CREATE TABLE media (
    id              TEXT    NOT NULL PRIMARY KEY,
    collection_name TEXT    NOT NULL DEFAULT 'default',
    name            TEXT    NOT NULL,
    file_name       TEXT    NOT NULL,
    mime_type       TEXT    NOT NULL,
    disk            TEXT    NOT NULL DEFAULT 'tenant',
    size            INTEGER NOT NULL DEFAULT 0,
    manipulations   TEXT    NOT NULL DEFAULT '{}',
    custom_properties TEXT  NOT NULL DEFAULT '{}',
    responsive_images TEXT  NOT NULL DEFAULT '{}',
    order_column    INTEGER NULL,
    created_at      TEXT    NULL,
    updated_at      TEXT    NULL
);

-- ─────────────────────────────────────────────────────────
-- Form Submissions (bloks with forms)
-- ─────────────────────────────────────────────────────────
CREATE TABLE form_submissions (
    id              TEXT    NOT NULL PRIMARY KEY,
    blok_instance_id TEXT   NOT NULL REFERENCES blok_instances(id) ON DELETE CASCADE,
    page_id         TEXT    NOT NULL,
    locale          TEXT    NOT NULL DEFAULT 'en',
    data            TEXT    NOT NULL,        -- JSON form data
    ip_address      TEXT    NULL,
    user_agent      TEXT    NULL,
    submitted_at    TEXT    NOT NULL,
    is_read         INTEGER NOT NULL DEFAULT 0,
    created_at      TEXT    NULL
);

-- ─────────────────────────────────────────────────────────
-- Analytics: Page Views
-- ─────────────────────────────────────────────────────────
CREATE TABLE page_views (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    page_id         TEXT    NOT NULL,
    locale          TEXT    NOT NULL DEFAULT 'en',
    referrer        TEXT    NULL,
    user_agent      TEXT    NULL,
    country_code    TEXT    NULL,
    viewed_at       TEXT    NOT NULL
);

CREATE INDEX idx_page_views_page ON page_views(page_id, viewed_at);

-- ─────────────────────────────────────────────────────────
-- Static Export Jobs
-- ─────────────────────────────────────────────────────────
CREATE TABLE static_exports (
    id              TEXT    NOT NULL PRIMARY KEY,
    status          TEXT    NOT NULL DEFAULT 'pending', -- 'pending','building','complete','failed'
    locale_set      TEXT    NOT NULL DEFAULT '["en"]',   -- JSON array of locales
    include_api_bridge INTEGER NOT NULL DEFAULT 1,
    zip_path        TEXT    NULL,
    zip_size        INTEGER NULL,
    error_message   TEXT    NULL,
    started_at      TEXT    NULL,
    completed_at    TEXT    NULL,
    expires_at      TEXT    NULL,                        -- auto-cleanup date
    created_by      TEXT    NULL,
    created_at      TEXT    NULL,
    updated_at      TEXT    NULL
);

-- ─────────────────────────────────────────────────────────
-- Webhooks (outgoing events)
-- ─────────────────────────────────────────────────────────
CREATE TABLE webhooks (
    id              TEXT    NOT NULL PRIMARY KEY,
    url             TEXT    NOT NULL,
    events          TEXT    NOT NULL DEFAULT '[]',  -- JSON: ['page.published','export.complete']
    secret          TEXT    NULL,
    is_active       INTEGER NOT NULL DEFAULT 1,
    last_triggered_at TEXT  NULL,
    last_status_code  INTEGER NULL,
    created_at      TEXT    NULL,
    updated_at      TEXT    NULL
);

CREATE TABLE webhook_deliveries (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    webhook_id      TEXT    NOT NULL REFERENCES webhooks(id) ON DELETE CASCADE,
    event           TEXT    NOT NULL,
    payload         TEXT    NOT NULL,
    status_code     INTEGER NULL,
    response_body   TEXT    NULL,
    attempts        INTEGER NOT NULL DEFAULT 0,
    delivered_at    TEXT    NULL
);
```

---

## 5. Laravel Project Structure

```
webblok-cms/
│
├── app/
│   ├── Actions/                         ← Single-purpose action classes (final)
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
│   │   │   ├── ProvisionTenant.php
│   │   │   ├── SuspendTenant.php
│   │   │   └── DeleteTenant.php
│   │   └── Exports/
│   │       ├── BuildStaticSite.php
│   │       └── CleanExpiredExports.php
│   │
│   ├── Bloks/                           ← Blok component classes (150+ bloks)
│   │   ├── BaseBlok.php                 ← Abstract base class
│   │   ├── Contracts/
│   │   │   ├── RendersBlok.php
│   │   │   └── HasFormWizard.php
│   │   ├── Layout/
│   │   │   ├── HeroSectionBlok.php
│   │   │   ├── TwoColumnGridBlok.php
│   │   │   ├── SectionWrapperBlok.php
│   │   │   └── AppShellBlok.php
│   │   ├── Content/
│   │   │   ├── StatCardBlok.php
│   │   │   ├── ChartBlok.php
│   │   │   ├── FaqAccordionBlok.php
│   │   │   └── RichTextBlok.php
│   │   ├── Dashboard/
│   │   │   ├── Slide01Blok.php          ← 2030B slide 01
│   │   │   ├── Slide02Blok.php
│   │   │   └── ...                      ← up to Slide150Blok.php
│   │   └── Forms/
│   │       ├── ContactFormBlok.php
│   │       └── NewsletterBlok.php
│   │
│   ├── Data/                            ← spatie/laravel-data DTOs
│   │   ├── BlokInstanceData.php
│   │   ├── BlokDefinitionData.php
│   │   ├── BlokSchemaData.php
│   │   ├── BlokSchemaStepData.php
│   │   ├── BlokSchemaFieldData.php
│   │   ├── PageData.php
│   │   ├── PageLocaleData.php
│   │   ├── MenuData.php
│   │   ├── MenuItemData.php
│   │   ├── StaticExportData.php
│   │   └── TenantData.php
│   │
│   ├── Enums/
│   │   ├── PageStatus.php               ← Backed enum: DRAFT, PUBLISHED, SCHEDULED, ARCHIVED
│   │   ├── BlokSection.php              ← Backed enum: HEADER, BODY, FOOTER, SIDEBAR
│   │   ├── TenantStatus.php
│   │   ├── ExportStatus.php
│   │   └── MenuItemType.php
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
│   │   └── InvalidBlokSchemaException.php
│   │
│   ├── Filament/
│   │   ├── SuperAdmin/                  ← /super-admin panel
│   │   │   ├── SuperAdminPanelProvider.php
│   │   │   ├── Resources/
│   │   │   │   ├── TenantResource.php
│   │   │   │   ├── BlokDefinitionResource.php
│   │   │   │   ├── PlanResource.php
│   │   │   │   └── ApiTokenResource.php
│   │   │   └── Widgets/
│   │   │       ├── TenantStatsWidget.php
│   │   │       └── BlokUsageWidget.php
│   │   └── Cms/                         ← /cms panel (per-tenant)
│   │       ├── CmsPanelProvider.php
│   │       ├── Pages/
│   │       │   ├── PageEditorPage.php   ← Wraps Livewire PageEditor
│   │       │   ├── MediaLibraryPage.php
│   │       │   ├── SettingsPage.php
│   │       │   └── StaticExportPage.php
│   │       ├── Resources/
│   │       │   ├── PageResource.php
│   │       │   ├── MenuResource.php
│   │       │   └── FormSubmissionsResource.php
│   │       └── Widgets/
│   │           ├── PageViewsWidget.php
│   │           └── ExportStatusWidget.php
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── BlokController.php
│   │   │   │   ├── PageController.php
│   │   │   │   ├── MenuController.php
│   │   │   │   ├── FormSubmissionController.php
│   │   │   │   └── AnalyticsController.php
│   │   │   └── Web/
│   │   │       ├── SiteController.php   ← Public site rendering
│   │   │       └── SitemapController.php
│   │   ├── Middleware/
│   │   │   ├── SetTenantDatabaseConnection.php
│   │   │   ├── AuthenticateApiKey.php
│   │   │   ├── EnforcePlanLimits.php
│   │   │   └── TrackPageView.php
│   │   └── Requests/
│   │       ├── CreateBlokInstanceRequest.php
│   │       ├── UpdateBlokInstanceRequest.php
│   │       └── CreatePageRequest.php
│   │
│   ├── Jobs/
│   │   ├── ProvisionTenantJob.php
│   │   ├── BuildStaticSiteJob.php
│   │   ├── WarmBlokCacheJob.php
│   │   ├── SendWebhookJob.php
│   │   ├── CleanExpiredExportsJob.php
│   │   └── BackupTenantDatabaseJob.php
│   │
│   ├── Listeners/
│   │   ├── OnPagePublished/
│   │   │   ├── InvalidatePageCache.php
│   │   │   ├── GenerateSitemap.php
│   │   │   └── FirePublishedWebhook.php
│   │   └── OnStaticExportCompleted/
│   │       ├── NotifyTenantUser.php
│   │       └── FireExportWebhook.php
│   │
│   ├── Livewire/
│   │   ├── PageEditor.php               ← Main drag-drop editor
│   │   ├── BlokPalette.php              ← Sidebar palette of available bloks
│   │   ├── BlokInstanceSettings.php     ← Form Wizard (step-based config)
│   │   ├── LivePreview.php              ← Iframe live preview panel
│   │   ├── RevisionHistory.php          ← Revision list + restore
│   │   ├── LocaleSwitcher.php           ← CMS language switcher
│   │   └── MediaLibrary.php             ← Media picker/uploader
│   │
│   ├── Models/
│   │   ├── Platform/                    ← MySQL models
│   │   │   ├── Tenant.php
│   │   │   ├── Plan.php
│   │   │   ├── PlatformUser.php
│   │   │   ├── TenantUser.php
│   │   │   ├── BlokDefinition.php
│   │   │   └── ApiToken.php
│   │   └── Tenant/                      ← SQLite models (use 'tenant' connection)
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
│   │       └── Webhook.php
│   │
│   ├── Services/
│   │   ├── BlokInstanceResolver.php     ← Recursive blok tree resolution
│   │   ├── BlokRenderer.php             ← Blade render + cache
│   │   ├── BlokSchemaResolver.php       ← Schema JSON loading + caching
│   │   ├── StaticSiteExporter.php       ← Full static site generation
│   │   ├── SitemapGenerator.php
│   │   ├── TenantContext.php            ← Current tenant singleton
│   │   ├── LocaleService.php            ← Active locale management
│   │   └── AclService.php              ← Role/permission checks
│   │
│   └── ValueObjects/
│       ├── BlokConfig.php
│       ├── BlokSchemaField.php
│       ├── BlokSchemaStep.php
│       └── TenantSettings.php
│
├── blok_definitions/                    ← JSON schema files (150+ bloks)
│   ├── slide-01.schema.json
│   ├── hero-section.schema.json
│   ├── stat-card.schema.json
│   └── ...
│
├── database/
│   ├── migrations/
│   │   ├── platform/
│   │   │   ├── 2024_01_01_000001_create_plans_table.php
│   │   │   ├── 2024_01_01_000002_create_tenants_table.php
│   │   │   ├── 2024_01_01_000003_create_platform_users_table.php
│   │   │   ├── 2024_01_01_000004_create_tenant_users_table.php
│   │   │   ├── 2024_01_01_000005_create_blok_definitions_table.php
│   │   │   ├── 2024_01_01_000006_create_api_tokens_table.php
│   │   │   └── 2024_01_01_000007_create_platform_settings_table.php
│   │   └── tenant/
│   │       └── (SQLite migrations run via TenantMigrator service)
│   ├── seeders/
│   │   ├── PlanSeeder.php
│   │   ├── BlokDefinitionSeeder.php     ← Seeds all 150 slide bloks + components
│   │   └── SuperAdminSeeder.php
│   └── factories/
│       ├── TenantFactory.php
│       ├── PageFactory.php
│       └── BlokInstanceFactory.php
│
├── resources/
│   ├── views/
│   │   ├── bloks/                       ← Blade views per blok type
│   │   │   ├── layout/
│   │   │   │   ├── hero-section.blade.php
│   │   │   │   ├── two-column-grid.blade.php
│   │   │   │   └── section-wrapper.blade.php
│   │   │   ├── dashboard/
│   │   │   │   ├── slide-01.blade.php
│   │   │   │   └── ...
│   │   │   └── content/
│   │   │       ├── stat-card.blade.php
│   │   │       └── faq-accordion.blade.php
│   │   ├── livewire/
│   │   │   ├── page-editor.blade.php
│   │   │   ├── blok-palette.blade.php
│   │   │   ├── blok-instance-settings.blade.php
│   │   │   ├── live-preview.blade.php
│   │   │   ├── revision-history.blade.php
│   │   │   └── media-library.blade.php
│   │   └── site/
│   │       ├── layouts/
│   │       │   ├── default.blade.php
│   │       │   └── blank.blade.php
│   │       └── page.blade.php           ← Public page renderer
│   ├── js/
│   │   ├── app.js
│   │   ├── drag-drop.js                 ← SortableJS integration
│   │   ├── live-preview.js
│   │   └── form-wizard.js
│   └── css/
│       └── app.css
│
├── routes/
│   ├── api.php                          ← V1 + V2 REST API
│   ├── web.php                          ← CMS panel + public site
│   └── console.php
│
├── storage/tenants/                     ← One subdirectory per tenant
│   └── {tenant-slug}/
│       ├── database.sqlite
│       ├── media/
│       └── exports/
│
├── config/
│   ├── webblok.php                      ← All platform config
│   ├── tenancy.php
│   └── bloks.php
│
├── tests/
│   ├── Unit/
│   │   ├── Services/
│   │   ├── ValueObjects/
│   │   └── Bloks/
│   ├── Feature/
│   │   ├── Api/
│   │   ├── Livewire/
│   │   └── Actions/
│   └── Architecture/
│       └── ArchTest.php
│
├── phpstan.neon
├── pint.json
├── rector.php
├── Makefile
└── .github/workflows/ci.yml
```

---

## 6. PHPStan Level 10 — Code Patterns

### 6.1 The Rules (Non-Negotiable)

1. `declare(strict_types=1);` on **every PHP file**
2. **All return types** must be declared (never omit)
3. **All property types** must be declared
4. **All parameter types** must be declared
5. Generic collection types required: `Collection<int, BlokInstance>`, `array<string, mixed>`
6. **Backed enums** for all finite value sets (never plain strings for statuses)
7. **Final action classes** — actions are not extended
8. **Value objects** are `readonly` classes
9. **No dynamic properties** (no `$obj->foo = 'bar'` on undefined properties)
10. **DTOs via `spatie/laravel-data`** — no `array` function arguments/return types
11. PHPDoc `@param`, `@return`, `@var`, `@property` must be PHPStan-compatible

### 6.2 `PageStatus.php` — Backed Enum

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
        return match($this) {
            self::DRAFT     => 'Draft',
            self::PUBLISHED => 'Published',
            self::SCHEDULED => 'Scheduled',
            self::ARCHIVED  => 'Archived',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::DRAFT     => 'gray',
            self::PUBLISHED => 'green',
            self::SCHEDULED => 'yellow',
            self::ARCHIVED  => 'red',
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
            array_map(fn(self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases()),
            'label',
            'value'
        );
    }
}
```

### 6.3 `BlokSection.php` — Backed Enum

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum BlokSection: string
{
    case HEADER  = 'header';
    case BODY    = 'body';
    case FOOTER  = 'footer';
    case SIDEBAR = 'sidebar';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

### 6.4 `Page.php` — Tenant Model (fully typed, PHPStan Level 10)

```php
<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\PageStatus;
use App\Models\Tenant\PageLocale;
use App\Models\Tenant\BlokInstance;
use App\Models\Tenant\PageRevision;
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
 * @property string|null  $created_by
 * @property string|null  $updated_by
 * @property Carbon|null  $created_at
 * @property Carbon|null  $updated_at
 * @property Carbon|null  $deleted_at
 * @property-read Page|null $parent
 * @property-read Collection<int, Page>          $children
 * @property-read Collection<int, PageLocale>    $locales
 * @property-read Collection<int, BlokInstance>  $blokInstances
 * @property-read Collection<int, PageRevision>  $revisions
 */
final class Page extends Model
{
    use HasSlug;
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'pages';

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
            ->generateSlugsFrom('title')
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
        return $this->hasMany(PageRevision::class, 'page_id')
            ->orderByDesc('revision_number');
    }

    public function isPublished(): bool
    {
        return $this->status === PageStatus::PUBLISHED;
    }

    public function locale(string $locale): ?PageLocale
    {
        return $this->locales->firstWhere('locale', $locale);
    }
}
```

### 6.5 `BlokSchemaField.php` — Readonly Value Object

```php
<?php

declare(strict_types=1);

namespace App\ValueObjects;

/**
 * @phpstan-type FieldOption array{value: string, label: string}
 * @phpstan-type FieldCondition array{field: string, operator: string, value: mixed}
 */
final readonly class BlokSchemaField
{
    /** @param list<FieldOption>|null    $options
     *  @param FieldCondition|null       $condition
     *  @param array<string, mixed>      $extra     */
    public function __construct(
        public string  $key,
        public string  $type,
        public string  $label,
        public bool    $required   = false,
        public bool    $i18n       = false,
        public mixed   $default    = null,
        public ?string $placeholder = null,
        public ?string $hint        = null,
        public ?int    $maxLength   = null,
        public ?array  $options     = null,
        public ?array  $condition   = null,
        public array   $extra       = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            key:         (string) ($data['key']         ?? ''),
            type:        (string) ($data['type']        ?? 'text'),
            label:       (string) ($data['label']       ?? ''),
            required:    (bool)   ($data['required']    ?? false),
            i18n:        (bool)   ($data['i18n']        ?? false),
            default:     $data['default']               ?? null,
            placeholder: isset($data['placeholder'])    ? (string) $data['placeholder'] : null,
            hint:        isset($data['hint'])            ? (string) $data['hint']        : null,
            maxLength:   isset($data['max_length'])      ? (int)   $data['max_length']  : null,
            options:     isset($data['options'])         ? (array) $data['options']     : null,
            condition:   isset($data['condition'])       ? (array) $data['condition']   : null,
            extra:       array_diff_key($data, array_flip([
                'key','type','label','required','i18n','default',
                'placeholder','hint','max_length','options','condition',
            ])),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'key'         => $this->key,
            'type'        => $this->type,
            'label'       => $this->label,
            'required'    => $this->required,
            'i18n'        => $this->i18n,
            'default'     => $this->default,
            'placeholder' => $this->placeholder,
            'hint'        => $this->hint,
            'max_length'  => $this->maxLength,
            'options'     => $this->options,
            'condition'   => $this->condition,
            ...$this->extra,
        ], fn(mixed $v) => $v !== null);
    }
}
```

### 6.6 `BlokSchemaStep.php` — Readonly Value Object

```php
<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Illuminate\Support\Collection;

final readonly class BlokSchemaStep
{
    /** @param Collection<int, BlokSchemaField> $fields */
    public function __construct(
        public string     $key,
        public string     $label,
        public ?string    $icon   = null,
        public Collection $fields = new Collection(),
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var list<array<string, mixed>> $rawFields */
        $rawFields = $data['fields'] ?? [];

        return new self(
            key:    (string) ($data['key']   ?? ''),
            label:  (string) ($data['label'] ?? ''),
            icon:   isset($data['icon']) ? (string) $data['icon'] : null,
            fields: collect($rawFields)->map(fn(array $f): BlokSchemaField => BlokSchemaField::fromArray($f)),
        );
    }

    /** @return Collection<string, BlokSchemaField> */
    public function fieldsByKey(): Collection
    {
        return $this->fields->keyBy('key');
    }
}
```

### 6.7 `CreateBlokInstance.php` — Final Action Class

```php
<?php

declare(strict_types=1);

namespace App\Actions\Bloks;

use App\Data\BlokInstanceData;
use App\Enums\BlokSection;
use App\Exceptions\BlokNotFoundException;
use App\Exceptions\PlanLimitExceededException;
use App\Models\Platform\BlokDefinition;
use App\Models\Tenant\BlokInstance;
use App\Services\TenantContext;
use Illuminate\Support\Str;

final class CreateBlokInstance
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /** @throws BlokNotFoundException
     *  @throws PlanLimitExceededException */
    public function execute(BlokInstanceData $data): BlokInstance
    {
        $definition = BlokDefinition::where('blok_key', $data->blokKey)
            ->where('is_active', true)
            ->first();

        if ($definition === null) {
            throw new BlokNotFoundException($data->blokKey);
        }

        $tenant = $this->tenantContext->require();

        $currentCount = BlokInstance::where('page_id', $data->pageId)->count();
        $maxBloks     = $tenant->plan->max_bloks;

        if ($currentCount >= $maxBloks) {
            throw new PlanLimitExceededException(
                "Blok limit ({$maxBloks}) reached for tenant {$tenant->slug}"
            );
        }

        /** @var array<string, mixed> $config */
        $config = $data->config ?? [];

        /** @var array<string, array<string, mixed>> $localeConfig */
        $localeConfig = $data->localeConfig ?? [];

        return BlokInstance::create([
            'id'            => Str::uuid()->toString(),
            'page_id'       => $data->pageId,
            'parent_id'     => $data->parentId,
            'slot_name'     => $data->slotName,
            'blok_key'      => $data->blokKey,
            'section'       => $data->section?->value ?? BlokSection::BODY->value,
            'config'        => json_encode($config, JSON_THROW_ON_ERROR),
            'locale_config' => json_encode($localeConfig, JSON_THROW_ON_ERROR),
            'sort_order'    => $data->sortOrder ?? $this->nextSortOrder($data->pageId, $data->parentId),
        ]);
    }

    private function nextSortOrder(string $pageId, ?string $parentId): int
    {
        return BlokInstance::where('page_id', $pageId)
            ->where('parent_id', $parentId)
            ->max('sort_order') + 1;
    }
}
```

### 6.8 `TenantContext.php` — Singleton Service

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\TenantNotFoundException;
use App\Models\Platform\Tenant;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Config;

final class TenantContext
{
    private ?Tenant $currentTenant = null;

    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function switchTo(Tenant $tenant): void
    {
        $this->currentTenant = $tenant;

        Config::set('database.connections.tenant', [
            'driver'                  => 'sqlite',
            'database'                => $tenant->database_path,
            'prefix'                  => '',
            'foreign_key_constraints' => true,
        ]);

        $this->db->purge('tenant');
        $this->db->reconnect('tenant');
    }

    public function current(): ?Tenant
    {
        return $this->currentTenant;
    }

    /** @throws TenantNotFoundException */
    public function require(): Tenant
    {
        if ($this->currentTenant === null) {
            throw new TenantNotFoundException('No tenant context is active.');
        }
        return $this->currentTenant;
    }

    public function clear(): void
    {
        $this->currentTenant = null;
    }
}
```

---

## 7. Key Service Classes

### 7.1 `BlokInstanceResolver.php` — Recursive Tree Resolution

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BlokNestingDepthExceeded;
use App\Models\Platform\BlokDefinition;
use App\Models\Tenant\BlokInstance;
use Illuminate\Support\Collection;

final class BlokInstanceResolver
{
    private const MAX_DEPTH = 10;

    public function __construct(
        private readonly BlokSchemaResolver $schemaResolver,
        private readonly BlokRenderer       $renderer,
    ) {}

    /**
     * @param  string $pageId
     * @param  string $locale
     * @return array<string, string>  section => rendered HTML
     */
    public function resolvePageSections(string $pageId, string $locale): array
    {
        /** @var Collection<int, BlokInstance> $allInstances */
        $allInstances = BlokInstance::where('page_id', $pageId)
            ->orderBy('section')
            ->orderBy('sort_order')
            ->get();

        /** @var Collection<int, BlokInstance> $roots */
        $roots = $allInstances->whereNull('parent_id');

        /** @var array<string, string> $sections */
        $sections = [];

        foreach ($roots as $instance) {
            $section  = $instance->section;
            $rendered = $this->resolveInstance($instance, $allInstances, $locale, depth: 0);
            $sections[$section] = ($sections[$section] ?? '') . $rendered;
        }

        return $sections;
    }

    /**
     * @param  Collection<int, BlokInstance> $allInstances
     */
    private function resolveInstance(
        BlokInstance $instance,
        Collection   $allInstances,
        string       $locale,
        int          $depth
    ): string {
        if ($depth > self::MAX_DEPTH) {
            throw new BlokNestingDepthExceeded(
                "Max blok nesting depth (" . self::MAX_DEPTH . ") exceeded at instance {$instance->id}"
            );
        }

        $definition = BlokDefinition::where('blok_key', $instance->blok_key)->firstOrFail();

        /** @var array<string, mixed> $config */
        $config = json_decode($instance->config, true, 512, JSON_THROW_ON_ERROR);

        /** @var array<string, array<string, mixed>> $localeConfig */
        $localeConfig = json_decode($instance->locale_config, true, 512, JSON_THROW_ON_ERROR);

        // Merge: definition defaults → instance config → locale overrides
        /** @var array<string, mixed> $defaultConfig */
        $defaultConfig = $definition->default_config ?? [];

        /** @var array<string, mixed> $merged */
        $merged = array_merge($defaultConfig, $config, $localeConfig[$locale] ?? []);

        // Resolve children per slot
        /** @var array<string, list<string>> $slots */
        $slots = [];

        if ($definition->accepts_children) {
            /** @var Collection<int, BlokInstance> $children */
            $children = $allInstances
                ->where('parent_id', $instance->id)
                ->sortBy('sort_order');

            foreach ($children as $child) {
                $slotName          = $child->slot_name ?? 'default';
                $slots[$slotName][] = $this->resolveInstance($child, $allInstances, $locale, $depth + 1);
            }
        }

        return $this->renderer->render($definition, $merged, $slots, $locale);
    }
}
```

### 7.2 `BlokRenderer.php` — Render with Cache

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Platform\BlokDefinition;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\Cache;

final class BlokRenderer
{
    public function __construct(
        private readonly ViewFactory  $viewFactory,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>        $config
     * @param  array<string, list<string>> $slots
     */
    public function render(
        BlokDefinition $definition,
        array          $config,
        array          $slots,
        string         $locale
    ): string {
        $tenant   = $this->tenantContext->require();
        $cacheKey = "blok:{$tenant->id}:{$definition->blok_key}:" . md5(serialize([$config, $slots, $locale]));

        return Cache::tags(["tenant:{$tenant->id}", "blok:{$definition->blok_key}"])
            ->remember($cacheKey, now()->addHours(1), function () use ($definition, $config, $slots, $locale): string {
                $viewName = "bloks.{$definition->category}.{$definition->blok_key}";

                return $this->viewFactory->make($viewName, [
                    'config' => $config,
                    'slots'  => $slots,
                    'locale' => $locale,
                    'blok'   => $definition,
                ])->render();
            });
    }

    public function flushCache(string $tenantId, ?string $blokKey = null): void
    {
        $tags = $blokKey !== null
            ? ["tenant:{$tenantId}", "blok:{$blokKey}"]
            : ["tenant:{$tenantId}"];

        Cache::tags($tags)->flush();
    }
}
```

### 7.3 `BlokSchemaResolver.php` — Schema Loading with Cache

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidBlokSchemaException;
use App\ValueObjects\BlokSchemaField;
use App\ValueObjects\BlokSchemaStep;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class BlokSchemaResolver
{
    private const SCHEMA_TTL_SECONDS = 3600;

    /** @var array<string, Collection<int, BlokSchemaStep>> */
    private array $inMemoryCache = [];

    /** @return Collection<int, BlokSchemaStep> */
    public function resolve(string $blokKey): Collection
    {
        if (isset($this->inMemoryCache[$blokKey])) {
            return $this->inMemoryCache[$blokKey];
        }

        /** @var Collection<int, BlokSchemaStep> $steps */
        $steps = Cache::remember(
            "blok_schema:{$blokKey}",
            self::SCHEMA_TTL_SECONDS,
            fn(): Collection => $this->loadFromDisk($blokKey)
        );

        $this->inMemoryCache[$blokKey] = $steps;
        return $steps;
    }

    /** @return Collection<int, BlokSchemaStep> */
    private function loadFromDisk(string $blokKey): Collection
    {
        $path = base_path("blok_definitions/{$blokKey}.schema.json");

        if (! file_exists($path)) {
            throw new InvalidBlokSchemaException("Schema file not found: {$path}");
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new InvalidBlokSchemaException("Cannot read schema file: {$path}");
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new InvalidBlokSchemaException("Invalid JSON in schema file: {$path}");
        }

        /** @var list<array<string, mixed>> $rawSteps */
        $rawSteps = $decoded['schema']['steps'] ?? [];

        return collect($rawSteps)->map(
            fn(array $s): BlokSchemaStep => BlokSchemaStep::fromArray($s)
        );
    }

    public function flushCache(string $blokKey): void
    {
        Cache::forget("blok_schema:{$blokKey}");
        unset($this->inMemoryCache[$blokKey]);
    }
}
```

### 7.4 `StaticSiteExporter.php` — Full Static Export Service

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant\Page;
use App\Models\Tenant\StaticExport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

final class StaticSiteExporter
{
    public function __construct(
        private readonly BlokInstanceResolver $resolver,
        private readonly SitemapGenerator     $sitemapGenerator,
        private readonly TenantContext        $tenantContext,
    ) {}

    public function export(StaticExport $exportRecord): void
    {
        $tenant = $this->tenantContext->require();

        /** @var list<string> $locales */
        $locales = json_decode($exportRecord->locale_set, true, 512, JSON_THROW_ON_ERROR);

        $exportDir = sys_get_temp_dir() . '/webblok_export_' . Str::uuid();
        mkdir($exportDir, 0755, true);

        try {
            // 1. Export all published pages per locale
            /** @var Collection<int, Page> $pages */
            $pages = Page::where('status', 'published')->get();

            foreach ($locales as $locale) {
                foreach ($pages as $page) {
                    $this->exportPage($page, $locale, $exportDir);
                }
            }

            // 2. Copy static assets
            $this->copyAssets($exportDir, $tenant->storage_path);

            // 3. Inject api-client.js with tenant credentials
            if ($exportRecord->include_api_bridge === 1) {
                $this->injectApiClient($exportDir, $tenant);
            }

            // 4. Generate sitemap.xml
            $sitemap = $this->sitemapGenerator->generate($pages, $locales);
            file_put_contents("{$exportDir}/sitemap.xml", $sitemap);

            // 5. Generate robots.txt
            file_put_contents("{$exportDir}/robots.txt", $this->buildRobotsTxt());

            // 6. ZIP everything
            $zipPath   = storage_path("tenants/{$tenant->slug}/exports/{$exportRecord->id}.zip");
            $this->zipDirectory($exportDir, $zipPath);

            // 7. Update export record
            $exportRecord->update([
                'status'       => 'complete',
                'zip_path'     => $zipPath,
                'zip_size'     => filesize($zipPath),
                'completed_at' => now()->toISOString(),
                'expires_at'   => now()->addDays(7)->toISOString(),
            ]);

        } finally {
            // Cleanup temp dir
            $this->removeDirectory($exportDir);
        }
    }

    private function exportPage(Page $page, string $locale, string $exportDir): void
    {
        $sections = $this->resolver->resolvePageSections($page->id, $locale);
        $localeData = $page->locale($locale);

        $title       = $localeData?->title ?? $page->slug;
        $metaDesc    = $localeData?->meta_description ?? '';
        $bodyContent = $sections['body'] ?? '';
        $header      = $sections['header'] ?? '';
        $footer      = $sections['footer'] ?? '';

        $html = $this->buildHtml($title, $metaDesc, $header, $bodyContent, $footer, $locale);

        // Determine output path
        $relPath = $locale !== 'en' ? "{$locale}/{$page->full_path}" : $page->full_path;
        $relPath = ltrim($relPath, '/');
        $filePath = $page->is_homepage ? "{$exportDir}/index.html" : "{$exportDir}/{$relPath}/index.html";

        $dir = dirname($filePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($filePath, $html);
    }

    /** @param array<string, mixed> $tenant */
    private function injectApiClient(string $exportDir, \App\Models\Platform\Tenant $tenant): void
    {
        $template = file_get_contents(public_path('js/api-client.js'));

        if ($template === false) {
            return;
        }

        // Generate a read-only export API token
        $rawToken   = 'wbk_export_' . Str::random(40);
        $tokenHash  = hash('sha256', $rawToken);

        \App\Models\Platform\ApiToken::create([
            'id'           => Str::uuid()->toString(),
            'tenant_id'    => $tenant->id,
            'name'         => 'Static Export ' . now()->format('Y-m-d'),
            'token_hash'   => $tokenHash,
            'token_prefix' => substr($rawToken, 0, 8),
            'abilities'    => json_encode(['read:pages', 'read:bloks', 'write:analytics']),
            'expires_at'   => now()->addYear()->toDateTimeString(),
        ]);

        $apiBase  = "https://{$tenant->subdomain}.webblok.io/api/v2";
        $injected = str_replace(
            ['__WB_API_BASE__', '__WB_API_KEY__'],
            [$apiBase, $rawToken],
            $template
        );

        $jsDir = "{$exportDir}/assets/js";
        if (! is_dir($jsDir)) {
            mkdir($jsDir, 0755, true);
        }
        file_put_contents("{$jsDir}/api-client.js", $injected);
    }

    private function zipDirectory(string $sourceDir, string $zipPath): void
    {
        $zipDir = dirname($zipPath);
        if (! is_dir($zipDir)) {
            mkdir($zipDir, 0755, true);
        }

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Cannot create ZIP archive at {$zipPath}");
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            $localPath = substr($file->getRealPath(), strlen($sourceDir) + 1);
            $zip->addFile($file->getRealPath(), $localPath);
        }

        $zip->close();
    }

    private function buildHtml(
        string $title,
        string $metaDesc,
        string $header,
        string $body,
        string $footer,
        string $locale,
    ): string {
        $dir = in_array($locale, ['ar', 'ur', 'he', 'fa'], true) ? 'rtl' : 'ltr';
        return <<<HTML
        <!DOCTYPE html>
        <html lang="{$locale}" dir="{$dir}">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$title}</title>
            <meta name="description" content="{$metaDesc}">
            <link rel="stylesheet" href="/assets/css/app.css">
        </head>
        <body>
            {$header}
            <main>{$body}</main>
            {$footer}
            <script src="/assets/js/api-client.js"></script>
        </body>
        </html>
        HTML;
    }

    private function buildRobotsTxt(): string
    {
        return "User-agent: *\nAllow: /\nSitemap: /sitemap.xml\n";
    }

    private function copyAssets(string $exportDir, string $storagePath): void
    {
        // Copy compiled CSS/JS from public
        $assetsDir = "{$exportDir}/assets";
        if (! is_dir($assetsDir)) {
            mkdir($assetsDir, 0755, true);
        }

        if (is_dir(public_path('build'))) {
            $this->copyDirectoryContents(public_path('build'), $assetsDir);
        }

        // Copy tenant media
        $mediaDir = "{$exportDir}/media";
        if (is_dir("{$storagePath}/media")) {
            $this->copyDirectoryContents("{$storagePath}/media", $mediaDir);
        }
    }

    private function copyDirectoryContents(string $src, string $dst): void
    {
        if (! is_dir($dst)) {
            mkdir($dst, 0755, true);
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            $target = $dst . '/' . substr($file->getPathname(), strlen($src) + 1);
            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }
            copy($file->getPathname(), $target);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($dir);
    }
}
```

### 7.5 `BaseBlok.php` — Abstract Component Base

```php
<?php

declare(strict_types=1);

namespace App\Bloks;

use App\Bloks\Contracts\RendersBlok;
use App\ValueObjects\BlokSchemaStep;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

abstract class BaseBlok extends Component implements RendersBlok
{
    /** @param array<string, mixed>        $config
     *  @param array<string, list<string>> $slots */
    public function __construct(
        protected readonly array  $config = [],
        protected readonly array  $slots  = [],
        protected readonly string $locale = 'en',
    ) {}

    /** @return array<string, mixed> */
    public function config(): array
    {
        return $this->config;
    }

    /** @param string $key
     *  @param mixed  $default
     *  @return mixed */
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    /** @return list<string> */
    public function slot(string $name): array
    {
        return $this->slots[$name] ?? [];
    }

    /** @return Collection<int, BlokSchemaStep> */
    abstract public function schema(): Collection;

    /** @return array<string, mixed> */
    public function data(): array
    {
        return [
            'config' => $this->config,
            'slots'  => $this->slots,
            'locale' => $this->locale,
        ];
    }
}
```

---

## 8. Livewire Components

### 8.1 `PageEditor.php` — Main Drag-Drop Editor

```php
<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Bloks\CreateBlokInstance;
use App\Actions\Bloks\DeleteBlokInstance;
use App\Actions\Bloks\ReorderBlokInstances;
use App\Data\BlokInstanceData;
use App\Enums\BlokSection;
use App\Models\Tenant\BlokInstance;
use App\Models\Tenant\Page;
use App\Services\TenantContext;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

final class PageEditor extends Component
{
    public string $pageId;
    public string $activeSection  = 'body';
    public string $activeLocale   = 'en';
    public ?string $editingInstanceId = null;

    /** @var list<string> $supportedLocales */
    public array $supportedLocales = ['en'];

    public function mount(string $pageId): void
    {
        $this->pageId = $pageId;
        $this->supportedLocales = $this->loadSupportedLocales();
    }

    /** @return Collection<int, BlokInstance> */
    #[Computed]
    public function blokInstances(): Collection
    {
        return BlokInstance::where('page_id', $this->pageId)
            ->where('section', $this->activeSection)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();
    }

    /** @return Page */
    #[Computed]
    public function page(): Page
    {
        return Page::findOrFail($this->pageId);
    }

    #[On('blok-dropped')]
    public function onBlokDropped(
        string  $blokKey,
        string  $section,
        int     $sortOrder,
        ?string $parentId = null,
        ?string $slotName = null,
    ): void {
        $section = BlokSection::from($section);

        app(CreateBlokInstance::class)->execute(new BlokInstanceData(
            pageId:    $this->pageId,
            blokKey:   $blokKey,
            section:   $section,
            sortOrder: $sortOrder,
            parentId:  $parentId,
            slotName:  $slotName,
        ));

        $this->unsetComputed('blokInstances');
    }

    #[On('blok-reordered')]
    /** @param list<array{id: string, sort_order: int}> $order */
    public function onBlokReordered(array $order): void
    {
        app(ReorderBlokInstances::class)->execute($order);
        $this->unsetComputed('blokInstances');
    }

    public function deleteBlok(string $instanceId): void
    {
        app(DeleteBlokInstance::class)->execute($instanceId);
        $this->unsetComputed('blokInstances');

        if ($this->editingInstanceId === $instanceId) {
            $this->editingInstanceId = null;
        }
    }

    public function editBlok(string $instanceId): void
    {
        $this->editingInstanceId = $instanceId;
        $this->dispatch('open-blok-settings', instanceId: $instanceId);
    }

    public function switchSection(string $section): void
    {
        $this->activeSection = BlokSection::from($section)->value;
        $this->unsetComputed('blokInstances');
    }

    public function switchLocale(string $locale): void
    {
        $this->activeLocale = $locale;
        $this->dispatch('locale-changed', locale: $locale);
    }

    /** @return list<string> */
    private function loadSupportedLocales(): array
    {
        // Loaded from tenant site_settings
        $raw = \Illuminate\Support\Facades\DB::connection('tenant')
            ->table('site_settings')
            ->where('key', 'supported_locales')
            ->value('value');

        if ($raw === null) {
            return ['en'];
        }

        /** @var list<string>|null $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : ['en'];
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.page-editor');
    }
}
```

### 8.2 `BlokInstanceSettings.php` — Form Wizard Livewire Component

```php
<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Bloks\UpdateBlokInstance;
use App\Data\BlokInstanceData;
use App\Models\Tenant\BlokInstance;
use App\Services\BlokSchemaResolver;
use App\ValueObjects\BlokSchemaStep;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

final class BlokInstanceSettings extends Component
{
    public ?string $instanceId  = null;
    public int     $currentStep = 0;
    public string  $locale      = 'en';

    /** @var array<string, mixed> $formData */
    public array $formData = [];

    /** @var array<string, mixed> $localeFormData */
    public array $localeFormData = [];

    /** @var Collection<int, BlokSchemaStep>|null $steps */
    private ?Collection $steps = null;

    public function mount(string $locale = 'en'): void
    {
        $this->locale = $locale;
    }

    #[On('open-blok-settings')]
    public function openForInstance(string $instanceId): void
    {
        $this->instanceId  = $instanceId;
        $this->currentStep = 0;

        $instance = BlokInstance::findOrFail($instanceId);

        /** @var array<string, mixed> $config */
        $config = json_decode($instance->config, true, 512, JSON_THROW_ON_ERROR);

        /** @var array<string, array<string, mixed>> $localeConfig */
        $localeConfig = json_decode($instance->locale_config, true, 512, JSON_THROW_ON_ERROR);

        $this->formData       = $config;
        $this->localeFormData = $localeConfig[$this->locale] ?? [];
        $this->steps          = app(BlokSchemaResolver::class)->resolve($instance->blok_key);
    }

    public function nextStep(): void
    {
        if ($this->currentStep < ($this->stepCount() - 1)) {
            ++$this->currentStep;
        }
    }

    public function prevStep(): void
    {
        if ($this->currentStep > 0) {
            --$this->currentStep;
        }
    }

    public function save(): void
    {
        if ($this->instanceId === null) {
            return;
        }

        app(UpdateBlokInstance::class)->execute($this->instanceId, new BlokInstanceData(
            config:        $this->formData,
            localeConfig:  [$this->locale => $this->localeFormData],
        ));

        $this->dispatch('blok-settings-saved', instanceId: $this->instanceId);
        $this->instanceId = null;
    }

    public function close(): void
    {
        $this->instanceId  = null;
        $this->formData    = [];
        $this->steps       = null;
        $this->currentStep = 0;
    }

    public function stepCount(): int
    {
        return $this->steps?->count() ?? 0;
    }

    /** @return BlokSchemaStep|null */
    public function currentStepObject(): ?BlokSchemaStep
    {
        return $this->steps?->get($this->currentStep);
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.blok-instance-settings', [
            'currentStepObj' => $this->currentStepObject(),
            'stepCount'      => $this->stepCount(),
        ]);
    }
}
```

---

## 9. Middleware

### 9.1 `SetTenantDatabaseConnection.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\TenantNotFoundException;
use App\Models\Platform\Tenant;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetTenantDatabaseConnection
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        // Try by custom domain first, then subdomain
        $tenant = Tenant::where('domain', $host)->first()
            ?? Tenant::where('subdomain', $this->extractSubdomain($host))->first();

        if ($tenant === null) {
            abort(404, "Tenant not found for host: {$host}");
        }

        if ($tenant->status !== 'active') {
            abort(503, "Tenant account is not active.");
        }

        $this->tenantContext->switchTo($tenant);

        return $next($request);
    }

    private function extractSubdomain(string $host): string
    {
        $parts = explode('.', $host);
        return $parts[0] ?? '';
    }
}
```

### 9.2 `AuthenticateApiKey.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Platform\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $rawToken = $request->bearerToken();

        if ($rawToken === null) {
            return response()->json(['error' => 'API token required'], 401);
        }

        $prefix    = substr($rawToken, 0, 8);
        $tokenHash = hash('sha256', $rawToken);

        $apiToken = ApiToken::where('token_prefix', $prefix)
            ->where('token_hash', $tokenHash)
            ->whereNull('revoked_at')
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();

        if ($apiToken === null) {
            return response()->json(['error' => 'Invalid or expired API token'], 401);
        }

        $apiToken->update(['last_used_at' => now()]);
        $request->attributes->set('api_token', $apiToken);

        return $next($request);
    }
}
```

---

## 10. REST API V2 Endpoints

### 10.1 Route Definitions (`routes/api.php`)

```php
<?php

declare(strict_types=1);

use App\Http\Controllers\Api\BlokController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\FormSubmissionController;
use App\Http\Controllers\Api\AnalyticsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v2')->middleware([
    \App\Http\Middleware\SetTenantDatabaseConnection::class,
    \App\Http\Middleware\AuthenticateApiKey::class,
])->group(function (): void {

    // ── Pages ───────────────────────────────────────────
    Route::get('/pages',              [PageController::class, 'index']);
    Route::get('/pages/{slug}',       [PageController::class, 'show']);
    Route::post('/pages',             [PageController::class, 'store']);
    Route::put('/pages/{id}',         [PageController::class, 'update']);
    Route::delete('/pages/{id}',      [PageController::class, 'destroy']);
    Route::post('/pages/{id}/publish',[PageController::class, 'publish']);

    // ── Blok Instances ───────────────────────────────────
    Route::get('/pages/{pageId}/bloks',          [BlokController::class, 'index']);
    Route::post('/pages/{pageId}/bloks',         [BlokController::class, 'store']);
    Route::put('/blok-instances/{id}',           [BlokController::class, 'update']);
    Route::delete('/blok-instances/{id}',        [BlokController::class, 'destroy']);
    Route::post('/blok-instances/reorder',       [BlokController::class, 'reorder']);
    Route::get('/pages/{pageId}/render',         [BlokController::class, 'renderPage']);

    // ── Menus ─────────────────────────────────────────────
    Route::get('/menus',              [MenuController::class, 'index']);
    Route::get('/menus/{handle}',     [MenuController::class, 'show']);

    // ── Form Submissions ─────────────────────────────────
    Route::post('/forms/{blokInstanceId}/submit', [FormSubmissionController::class, 'store']);
    Route::get('/forms/submissions',              [FormSubmissionController::class, 'index']);

    // ── Analytics ─────────────────────────────────────────
    Route::post('/analytics/pageview',    [AnalyticsController::class, 'track']);
    Route::get('/analytics/summary',      [AnalyticsController::class, 'summary']);
});
```

### 10.2 Full API Response Examples

**GET `/api/v2/pages/home?locale=en`**

```json
{
  "data": {
    "id": "01J5ABC123",
    "slug": "home",
    "full_path": "/",
    "status": "published",
    "is_homepage": true,
    "template": "default",
    "locale": {
      "locale": "en",
      "title": "Welcome to My Website",
      "description": "The best place on the internet.",
      "meta_title": "My Website — Home",
      "meta_description": "Welcome to My Website — discover our services.",
      "og_title": "My Website",
      "og_description": "Discover what we do.",
      "is_indexable": true
    },
    "sections": {
      "header": "<header class=\"site-header\">...</header>",
      "body":   "<main class=\"page-body\">...</main>",
      "footer": "<footer class=\"site-footer\">...</footer>"
    },
    "blok_count": 7,
    "published_at": "2025-06-01T10:00:00Z"
  },
  "meta": {
    "locale": "en",
    "rendered_at": "2025-06-03T14:22:10Z",
    "cache_hit": true
  }
}
```

**POST `/api/v2/pages/{pageId}/bloks`**

Request:
```json
{
  "blok_key": "hero-section",
  "section":  "header",
  "sort_order": 0,
  "config": {
    "headline":  "Welcome to our platform",
    "cta_label": "Get Started",
    "cta_url":   "/pricing",
    "theme":     "dark",
    "min_height": 70
  },
  "locale_config": {
    "ar": {
      "headline":  "مرحباً بكم في منصتنا",
      "cta_label": "ابدأ الآن"
    }
  }
}
```

Response (201):
```json
{
  "data": {
    "id": "inst_01J5XYZ789",
    "page_id": "01J5ABC123",
    "blok_key": "hero-section",
    "section": "header",
    "sort_order": 0,
    "config": { "headline": "Welcome...", "theme": "dark", "min_height": 70 },
    "locale_config": { "ar": { "headline": "مرحباً بكم في منصتنا" } },
    "is_visible": true,
    "created_at": "2025-06-03T14:30:00Z"
  }
}
```

**GET `/api/v2/pages/{pageId}/render?locale=ar&section=body`**

Response:
```json
{
  "data": {
    "section": "body",
    "locale": "ar",
    "html": "<div class=\"blok hero-section\" dir=\"rtl\">...</div>",
    "blok_count": 3,
    "rendered_at": "2025-06-03T14:31:00Z"
  }
}
```

---

## 11. `api-client.js` — Static Export AJAX Bridge

```javascript
/*!
 * WebBlok API Client — Static Site Bridge
 * Auto-injected by StaticSiteExporter — DO NOT edit manually
 * Tokens replaced at export build time
 */
(function (window) {
  'use strict';

  const API_BASE = '__WB_API_BASE__';  // replaced: e.g. https://mysite.webblok.io/api/v2
  const API_KEY  = '__WB_API_KEY__';   // replaced: wbk_export_XXXXXXXX

  const headers = {
    'Authorization': 'Bearer ' + API_KEY,
    'Content-Type':  'application/json',
    'Accept':        'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  };

  /**
   * Fetch a fully-rendered page from the API.
   * @param {string} slug    - Page slug (e.g. 'about-us')
   * @param {string} locale  - Locale code (e.g. 'en', 'ar')
   * @returns {Promise<{html: string, title: string, meta: object}>}
   */
  async function fetchPage(slug, locale = 'en') {
    const url = API_BASE + '/pages/' + encodeURIComponent(slug) + '?locale=' + encodeURIComponent(locale);
    const res = await fetch(url, { method: 'GET', headers });
    if (!res.ok) throw new Error('[WebBlok] fetchPage failed: ' + res.status);
    const json = await res.json();
    return {
      html:   json.data?.sections?.body ?? '',
      title:  json.data?.locale?.title ?? '',
      meta:   json.meta ?? {},
    };
  }

  /**
   * Fetch a rendered section of a page.
   * @param {string} pageId
   * @param {string} section - 'header'|'body'|'footer'|'sidebar'
   * @param {string} locale
   * @returns {Promise<string>} Rendered HTML
   */
  async function fetchSection(pageId, section = 'body', locale = 'en') {
    const url = API_BASE + '/pages/' + pageId + '/render?locale=' + locale + '&section=' + section;
    const res = await fetch(url, { method: 'GET', headers });
    if (!res.ok) throw new Error('[WebBlok] fetchSection failed: ' + res.status);
    const json = await res.json();
    return json.data?.html ?? '';
  }

  /**
   * Submit a form (contact, newsletter, etc.)
   * @param {string} blokInstanceId
   * @param {object} formData
   * @param {string} locale
   * @returns {Promise<{success: boolean, message: string}>}
   */
  async function submitForm(blokInstanceId, formData, locale = 'en') {
    const url = API_BASE + '/forms/' + blokInstanceId + '/submit';
    const res = await fetch(url, {
      method:  'POST',
      headers,
      body:    JSON.stringify({ data: formData, locale }),
    });
    const json = await res.json();
    return { success: res.ok, message: json.message ?? (res.ok ? 'Submitted' : 'Error') };
  }

  /**
   * Track a page view (analytics).
   * Uses sendBeacon for reliability; falls back to fetch.
   * @param {string} pageId
   * @param {string} locale
   */
  function trackView(pageId, locale = 'en') {
    const url = API_BASE + '/analytics/pageview';
    const payload = JSON.stringify({
      page_id:    pageId,
      locale,
      referrer:   document.referrer,
      user_agent: navigator.userAgent,
    });

    if (navigator.sendBeacon) {
      const blob = new Blob([payload], { type: 'application/json' });
      navigator.sendBeacon(url, blob);
    } else {
      fetch(url, { method: 'POST', headers, body: payload, keepalive: true }).catch(() => {});
    }
  }

  /**
   * Fetch menu by handle.
   * @param {string} handle - e.g. 'main-nav', 'footer'
   * @returns {Promise<Array>}
   */
  async function fetchMenu(handle, locale = 'en') {
    const url = API_BASE + '/menus/' + encodeURIComponent(handle) + '?locale=' + locale;
    const res = await fetch(url, { method: 'GET', headers });
    if (!res.ok) throw new Error('[WebBlok] fetchMenu failed: ' + res.status);
    const json = await res.json();
    return json.data?.items ?? [];
  }

  // Auto-track on load
  document.addEventListener('DOMContentLoaded', function () {
    const pageId = document.body.dataset.pageId;
    const locale = document.documentElement.lang || 'en';
    if (pageId) trackView(pageId, locale);
  });

  // Expose public API
  window.WebBlok = {
    fetchPage,
    fetchSection,
    submitForm,
    trackView,
    fetchMenu,
    apiBase: API_BASE,
  };

}(window));
```

---

## 12. Drag-Drop JavaScript (`resources/js/drag-drop.js`)

```javascript
/**
 * WebBlok CMS — Drag & Drop
 * SortableJS-powered blok reordering and palette cloning
 */
import Sortable from 'sortablejs';

/** @type {Map<string, Sortable>} */
const sortableInstances = new Map();

/**
 * Initialise drag-drop on a page section container.
 * @param {string} sectionEl - CSS selector for the section container
 * @param {object} livewireComponent - Livewire component instance
 */
export function initSectionSortable(sectionEl, livewireComponent) {
  const el = document.querySelector(sectionEl);
  if (!el) return;

  const key = el.dataset.section || sectionEl;

  if (sortableInstances.has(key)) {
    sortableInstances.get(key).destroy();
  }

  const sortable = Sortable.create(el, {
    group: {
      name:  'blok-canvas',
      put:   ['blok-palette'],     // Accept from palette
      pull:  true,
    },
    animation:   200,
    ghostClass:  'blok-ghost',
    chosenClass: 'blok-chosen',
    dragClass:   'blok-dragging',
    handle:      '.blok-drag-handle',

    onEnd(evt) {
      const items     = /** @type {NodeListOf<HTMLElement>} */ (el.querySelectorAll('[data-instance-id]'));
      const newOrder  = Array.from(items).map((item, idx) => ({
        id:         item.dataset.instanceId,
        sort_order: idx,
      }));
      livewireComponent.dispatch('blok-reordered', { order: newOrder });
    },

    onAdd(evt) {
      const blokKey   = evt.item.dataset.blokKey;
      const section   = el.dataset.section || 'body';
      const sortOrder = evt.newIndex ?? 0;
      const parentId  = el.dataset.parentInstanceId || null;
      const slotName  = el.dataset.slotName || null;

      // Remove the clone that Sortable added (Livewire will re-render)
      evt.item.remove();

      livewireComponent.dispatch('blok-dropped', {
        blokKey, section, sortOrder, parentId, slotName,
      });
    },
  });

  sortableInstances.set(key, sortable);
}

/**
 * Initialise the blok palette (sidebar list of available bloks).
 */
export function initPaletteSortable() {
  const palette = document.querySelector('[data-blok-palette]');
  if (!palette) return;

  Sortable.create(palette, {
    group: {
      name:  'blok-palette',
      pull:  'clone',             // Clone items instead of moving
      put:   false,               // Cannot drop onto palette
    },
    sort:      false,             // Palette itself is not sortable
    animation: 150,
    ghostClass: 'palette-ghost',
  });
}

/**
 * Destroy all sortable instances (called before Livewire re-renders).
 */
export function destroyAll() {
  sortableInstances.forEach((sortable) => sortable.destroy());
  sortableInstances.clear();
}
```

---

## 13. Filament 3 — CMS Panel Provider

```php
<?php

declare(strict_types=1);

namespace App\Filament\Cms;

use App\Filament\Cms\Pages\PageEditorPage;
use App\Filament\Cms\Pages\MediaLibraryPage;
use App\Filament\Cms\Pages\SettingsPage;
use App\Filament\Cms\Pages\StaticExportPage;
use App\Filament\Cms\Resources\PageResource;
use App\Filament\Cms\Resources\MenuResource;
use App\Filament\Cms\Resources\FormSubmissionsResource;
use App\Http\Middleware\SetTenantDatabaseConnection;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

final class CmsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('cms')
            ->path('cms')
            ->login()
            ->colors([
                'primary' => Color::Violet,
            ])
            ->font('Inter')
            ->brandName('WebBlok CMS')
            ->favicon(asset('favicon.ico'))
            ->darkMode(defaultIsEnabled: true)
            ->navigationGroups([
                NavigationGroup::make('Content')->icon('heroicon-o-document-text'),
                NavigationGroup::make('Structure')->icon('heroicon-o-view-columns'),
                NavigationGroup::make('Media')->icon('heroicon-o-photo'),
                NavigationGroup::make('Settings')->icon('heroicon-o-cog-6-tooth'),
            ])
            ->resources([
                PageResource::class,
                MenuResource::class,
                FormSubmissionsResource::class,
            ])
            ->pages([
                PageEditorPage::class,
                MediaLibraryPage::class,
                SettingsPage::class,
                StaticExportPage::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetTenantDatabaseConnection::class,   // ← Tenant context
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
```

---

## 14. Jobs

### 14.1 `BuildStaticSiteJob.php`

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\StaticExportCompleted;
use App\Events\StaticExportFailed;
use App\Models\Platform\Tenant;
use App\Models\Tenant\StaticExport;
use App\Services\StaticSiteExporter;
use App\Services\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class BuildStaticSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int   $tries   = 3;
    public int   $timeout = 600; // 10 minutes
    public int   $backoff = 60;

    public function __construct(
        private readonly string $tenantId,
        private readonly string $exportId,
    ) {}

    public function handle(
        StaticSiteExporter $exporter,
        TenantContext      $tenantContext
    ): void {
        $tenant = Tenant::findOrFail($this->tenantId);
        $tenantContext->switchTo($tenant);

        $export = StaticExport::findOrFail($this->exportId);
        $export->update(['status' => 'building', 'started_at' => now()->toISOString()]);

        try {
            $exporter->export($export);
            event(new StaticExportCompleted($this->tenantId, $this->exportId));
        } catch (\Throwable $e) {
            $export->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            event(new StaticExportFailed($this->tenantId, $this->exportId, $e->getMessage()));
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        \Illuminate\Support\Facades\Log::error('[BuildStaticSiteJob] Failed', [
            'tenant_id' => $this->tenantId,
            'export_id' => $this->exportId,
            'error'     => $e->getMessage(),
        ]);
    }
}
```

### 14.2 `ProvisionTenantJob.php`

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Tenants\ProvisionTenant;
use App\Events\TenantProvisioned;
use App\Models\Platform\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProvisionTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 5;
    public int $timeout = 120;
    public int $backoff = 30;

    public function __construct(
        private readonly string $tenantId,
    ) {}

    public function handle(ProvisionTenant $provisionTenant): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);
        $provisionTenant->execute($tenant);
        event(new TenantProvisioned($tenant));
    }
}
```

---

## 15. `ProvisionTenant.php` — Action

```php
<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Models\Platform\Tenant;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

final class ProvisionTenant
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function execute(Tenant $tenant): void
    {
        // 1. Create directory structure
        $tenantDir = storage_path("tenants/{$tenant->slug}");
        File::makeDirectory("{$tenantDir}/media",   0755, true, true);
        File::makeDirectory("{$tenantDir}/exports",  0755, true, true);

        // 2. Create SQLite database file
        $dbPath = "{$tenantDir}/database.sqlite";
        if (! file_exists($dbPath)) {
            touch($dbPath);
        }

        // 3. Update tenant record with paths
        $tenant->update([
            'database_path' => $dbPath,
            'storage_path'  => $tenantDir,
        ]);

        // 4. Switch to tenant context
        $this->tenantContext->switchTo($tenant);

        // 5. Run tenant SQLite migrations
        Artisan::call('migrate', [
            '--path'     => 'database/migrations/tenant',
            '--database' => 'tenant',
            '--force'    => true,
        ]);

        // 6. Apply WAL mode pragmas
        \Illuminate\Support\Facades\DB::connection('tenant')->statement('PRAGMA journal_mode = WAL');
        \Illuminate\Support\Facades\DB::connection('tenant')->statement('PRAGMA synchronous = NORMAL');
        \Illuminate\Support\Facades\DB::connection('tenant')->statement('PRAGMA foreign_keys = ON');

        // 7. Seed default roles, permissions, and site settings
        Artisan::call('db:seed', [
            '--class'    => 'TenantDefaultSeeder',
            '--database' => 'tenant',
        ]);

        // 8. Mark tenant as active
        $tenant->update(['status' => 'active']);
    }
}
```

---

## 16. Testing Strategy

### 16.1 Architecture Test (`tests/Architecture/ArchTest.php`)

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

arch('Enums are backed')
    ->expect('App\Enums')
    ->toExtend(\BackedEnum::class);

arch('Models use correct database connections')
    ->expect('App\Models\Tenant')
    ->toHaveProperty('connection', 'tenant');

arch('No debug calls in production code')
    ->expect('App')
    ->not->toUse(['dd', 'dump', 'var_dump', 'print_r', 'ray', 'die']);

arch('Controllers are not invokable')
    ->expect('App\Http\Controllers')
    ->not->toHaveMethod('__invoke');

arch('Livewire components are final')
    ->expect('App\Livewire')
    ->toBeFinal();
```

### 16.2 Feature Tests

```php
<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Models\Platform\Tenant;
use App\Models\Tenant\Page;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['status' => 'active']);
    app(TenantContext::class)->switchTo($this->tenant);
    $this->token  = 'wbk_live_' . str_repeat('a', 40);
    // ... setup token in DB
});

test('can fetch published page via API', function (): void {
    $page = Page::factory()->create([
        'status'    => PageStatus::PUBLISHED,
        'slug'      => 'home',
        'full_path' => '/',
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->token}",
    ])->getJson("/api/v2/pages/home?locale=en");

    $response
        ->assertOk()
        ->assertJsonPath('data.slug', 'home')
        ->assertJsonPath('data.status', 'published')
        ->assertJsonStructure([
            'data' => ['id', 'slug', 'status', 'sections', 'locale'],
            'meta' => ['locale', 'rendered_at'],
        ]);
});

test('cannot fetch draft page via public API', function (): void {
    Page::factory()->create([
        'status' => PageStatus::DRAFT,
        'slug'   => 'draft-page',
    ]);

    $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
        ->getJson('/api/v2/pages/draft-page')
        ->assertNotFound();
});

test('creating a blok instance enforces plan limits', function (): void {
    $this->tenant->plan->update(['max_bloks' => 2]);
    $page = Page::factory()->create(['status' => PageStatus::PUBLISHED]);
    \App\Models\Tenant\BlokInstance::factory()->count(2)->create(['page_id' => $page->id]);

    $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
        ->postJson("/api/v2/pages/{$page->id}/bloks", [
            'blok_key' => 'hero-section',
            'section'  => 'body',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error', fn(string $msg) => str_contains($msg, 'limit'));
});
```

### 16.3 Livewire Tests

```php
<?php

declare(strict_types=1);

use App\Livewire\PageEditor;
use App\Models\Tenant\Page;
use Livewire\Livewire;

test('page editor mounts with correct pageId', function (): void {
    $page = Page::factory()->create();

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->assertSet('pageId', $page->id)
        ->assertSet('activeSection', 'body');
});

test('dropping a blok dispatches correct event', function (): void {
    $page = Page::factory()->create();

    Livewire::test(PageEditor::class, ['pageId' => $page->id])
        ->dispatch('blok-dropped', [
            'blokKey'   => 'hero-section',
            'section'   => 'body',
            'sortOrder' => 0,
        ])
        ->assertDispatched('blok-settings-saved');
});
```

---

## 17. Artisan Commands

| Command | Description |
|---------|-------------|
| `webblok:provision-tenant {tenant}` | Provision SQLite + run migrations for a tenant |
| `webblok:seed-bloks` | Seed all 150 blok definitions from `blok_definitions/` |
| `webblok:build-static {tenant} {--locales=en}` | Dispatch `BuildStaticSiteJob` for a tenant |
| `webblok:warm-cache {tenant}` | Pre-render all published pages to Redis cache |
| `webblok:flush-cache {tenant}` | Flush render cache for a tenant |
| `webblok:backup-tenant {tenant}` | Backup tenant SQLite to S3 |
| `webblok:clean-exports` | Delete exports older than their `expires_at` |
| `webblok:publish-scheduled` | Publish all pages with `scheduled_at <= now()` |
| `webblok:validate-schemas` | Validate all `.schema.json` files in `blok_definitions/` |
| `webblok:migrate-tenant {tenant}` | Run pending tenant migrations on a specific SQLite |
| `webblok:migrate-all-tenants` | Run pending migrations on all active tenant SQLites |
| `webblok:stats` | Output platform stats (tenant count, blok count, export count) |

---

## 18. Event / Listener / Job Flow

```
User clicks "Publish Page" in CMS
  → PublishPage action executes
  → Page::status = PUBLISHED, published_at = now()
  → PagePublished event fired
      ├── InvalidatePageCache listener     → Cache::tags(["tenant:{id}"]).flush()
      ├── GenerateSitemap listener         → SitemapGenerator::generate()
      └── FirePublishedWebhook listener    → SendWebhookJob::dispatch()

User clicks "Export Static Site"
  → BuildStaticSiteJob::dispatch(tenantId, exportId)
      → StaticSiteExporter::export()
          → BlokInstanceResolver::resolvePageSections() per page per locale
          → StaticSiteExporter::injectApiClient() [injects tokens]
          → ZipArchive
      → StaticExportCompleted event fired
          ├── NotifyTenantUser listener    → Email notification
          └── FireExportWebhook listener   → SendWebhookJob::dispatch()

Scheduled every minute via Laravel Scheduler:
  → webblok:publish-scheduled → Publishes pages where scheduled_at <= now()
  → webblok:clean-exports     → Deletes expired ZIP files
```

---

## 19. Performance Architecture

### 19.1 Caching Layers

| Layer | Technology | TTL | Scope | Invalidated By |
|-------|-----------|-----|-------|----------------|
| **Blok schema** | Redis string | 1 hour | Platform-wide | `webblok:seed-bloks`, schema file change |
| **Rendered blok HTML** | Redis tagged | 1 hour | Per-tenant, per-blok | Page publish, blok config change |
| **Full page HTML** | Redis tagged | 30 min | Per-tenant, per-page | Page publish, any blok change on page |
| **Tenant lookup** | Request-scoped | Lifetime of request | Per-request | N/A |
| **Menu tree** | Redis tagged | 2 hours | Per-tenant, per-menu | Menu save |
| **Sitemap** | Filesystem | Invalidated on publish | Per-tenant | Page publish/unpublish |

### 19.2 SQLite WAL PRAGMAs (applied at provisioning)

```sql
PRAGMA journal_mode = WAL;        -- Write-Ahead Logging: concurrent reads + writes
PRAGMA synchronous = NORMAL;      -- Balance durability vs speed
PRAGMA foreign_keys = ON;         -- Referential integrity
PRAGMA temp_store = MEMORY;       -- Temp tables in RAM
PRAGMA cache_size = -16000;       -- 16MB in-process page cache
PRAGMA mmap_size = 134217728;     -- 128MB memory-mapped I/O
PRAGMA busy_timeout = 5000;       -- Wait 5s before SQLITE_BUSY error
```

### 19.3 Horizon Queue Config (`config/horizon.php`)

```php
'environments' => [
    'production' => [
        'supervisor-default' => [
            'connection' => 'redis',
            'queue'      => ['default', 'exports', 'webhooks', 'notifications'],
            'balance'    => 'auto',
            'minProcesses' => 2,
            'maxProcesses' => 10,
            'tries'      => 3,
            'timeout'    => 600,
        ],
        'supervisor-exports' => [
            'connection' => 'redis',
            'queue'      => ['exports'],
            'balance'    => 'simple',
            'processes'  => 3,
            'timeout'    => 900,
        ],
    ],
],
```

---

## 20. Environment Variables

```ini
# ═══════ App ═══════════════════════════════════════════════
APP_NAME="WebBlok CMS"
APP_ENV=production
APP_KEY=base64:YOUR_KEY_HERE
APP_DEBUG=false
APP_URL=https://app.webblok.io

# ═══════ Platform Database (MySQL) ═════════════════════════
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=webblok_platform
DB_USERNAME=webblok
DB_PASSWORD=secret

# ═══════ Tenant SQLite Base Path ═══════════════════════════
WEBBLOK_TENANTS_BASE_PATH=/var/www/webblok/storage/tenants

# ═══════ Cache / Queue (Redis) ═════════════════════════════
CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
QUEUE_CONNECTION=redis

# ═══════ Mail ══════════════════════════════════════════════
MAIL_MAILER=smtp
MAIL_HOST=smtp.postmarkapp.com
MAIL_PORT=587
MAIL_USERNAME=your-postmark-token
MAIL_PASSWORD=your-postmark-token
MAIL_FROM_ADDRESS=noreply@webblok.io
MAIL_FROM_NAME="${APP_NAME}"

# ═══════ Storage ═══════════════════════════════════════════
FILESYSTEM_DISK=local
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=webblok-tenant-media
AWS_ENDPOINT=https://s3.amazonaws.com

# ═══════ Search ════════════════════════════════════════════
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://localhost:7700
MEILISEARCH_KEY=

# ═══════ Platform Config ═══════════════════════════════════
WEBBLOK_PLATFORM_DOMAIN=webblok.io
WEBBLOK_SUPER_ADMIN_GUARD=web
WEBBLOK_RENDER_CACHE_TTL=3600
WEBBLOK_EXPORT_EXPIRY_DAYS=7
WEBBLOK_MAX_NESTING_DEPTH=10
WEBBLOK_BLOK_DEFINITIONS_PATH=blok_definitions/

# ═══════ Horizon ═══════════════════════════════════════════
HORIZON_PREFIX=webblok_horizon:
HORIZON_MEMORY_LIMIT=256
```

---

## 21. Deployment Stack

### 21.1 Nginx Configuration

```nginx
# WebBlok CMS — Multi-tenant Nginx config
# Wildcard subdomain + custom domain support

server {
    listen 80;
    listen [::]:80;
    server_name *.webblok.io webblok.io;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name *.webblok.io webblok.io;

    ssl_certificate     /etc/letsencrypt/live/webblok.io/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/webblok.io/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256;

    root /var/www/webblok/public;
    index index.php;

    client_max_body_size 64M;

    # Gzip
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml;
    gzip_min_length 1024;

    # Static assets — long cache
    location ~* \.(css|js|png|jpg|jpeg|gif|webp|woff2|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass   unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param  SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include        fastcgi_params;
        fastcgi_read_timeout 600;
    }

    # Block hidden files
    location ~ /\. {
        deny all;
    }

    # Block tenant SQLite files from web access
    location ~ \.sqlite$ {
        deny all;
    }
}
```

### 21.2 Supervisor Configuration

```ini
[program:webblok-horizon]
command=php /var/www/webblok/artisan horizon
directory=/var/www/webblok
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/horizon.log
stopwaitsecs=3600
user=www-data

[program:webblok-scheduler]
command=php /var/www/webblok/artisan schedule:work
directory=/var/www/webblok
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/scheduler.log
user=www-data
```

### 21.3 GitHub Actions CI/CD

```yaml
name: WebBlok CMS CI/CD

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  analyse:
    name: PHPStan Level 10
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: sqlite3, pdo_sqlite
      - run: composer install --no-interaction --prefer-dist
      - run: vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress

  lint:
    name: Laravel Pint
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install --no-interaction
      - run: vendor/bin/pint --test

  test:
    name: Pest Tests
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_DATABASE: webblok_test
          MYSQL_ROOT_PASSWORD: secret
        ports: ['3306:3306']
        options: --health-cmd="mysqladmin ping" --health-interval=10s
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: sqlite3, pdo_sqlite, pdo_mysql
      - run: composer install --no-interaction
      - run: cp .env.testing .env
      - run: php artisan key:generate
      - run: vendor/bin/pest --parallel --coverage --min=80

  deploy:
    name: Deploy to Production
    runs-on: ubuntu-latest
    needs: [analyse, lint, test]
    if: github.ref == 'refs/heads/main'
    steps:
      - uses: actions/checkout@v4
      - name: Deploy via SSH
        uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.PROD_HOST }}
          username: ${{ secrets.PROD_USER }}
          key: ${{ secrets.PROD_SSH_KEY }}
          script: |
            cd /var/www/webblok
            git pull origin main
            composer install --no-dev --optimize-autoloader
            php artisan migrate --force
            php artisan webblok:migrate-all-tenants
            php artisan config:cache
            php artisan route:cache
            php artisan view:cache
            php artisan filament:cache-components
            php artisan horizon:terminate
            sudo supervisorctl restart webblok-horizon
            npm ci && npm run build
            echo "✅ Deployed successfully"
```

---

## 22. `Makefile`

```makefile
.PHONY: analyse lint fix test rector ci install fresh dev

# ── Static Analysis ──────────────────────────────────────
analyse:
	vendor/bin/phpstan analyse --configuration=phpstan.neon

analyse-baseline:
	vendor/bin/phpstan analyse --generate-baseline phpstan-baseline.neon

# ── Code Style ───────────────────────────────────────────
lint:
	vendor/bin/pint --test

fix:
	vendor/bin/pint

# ── Refactoring ──────────────────────────────────────────
rector:
	vendor/bin/rector process --dry-run

rector-fix:
	vendor/bin/rector process

# ── Tests ────────────────────────────────────────────────
test:
	vendor/bin/pest --parallel

test-coverage:
	vendor/bin/pest --parallel --coverage --min=80

test-arch:
	vendor/bin/pest --filter=arch

# ── Full CI (lint + analyse + test) ──────────────────────
ci: lint analyse test

# ── Setup ────────────────────────────────────────────────
install:
	composer install
	npm ci
	cp .env.example .env
	php artisan key:generate
	php artisan migrate
	php artisan db:seed
	npm run build

fresh:
	php artisan migrate:fresh --seed
	php artisan webblok:seed-bloks

dev:
	npm run dev &
	php artisan serve

# ── WebBlok Platform Commands ────────────────────────────
seed-bloks:
	php artisan webblok:seed-bloks

warm-cache:
	php artisan webblok:warm-cache $(tenant)

flush-cache:
	php artisan webblok:flush-cache $(tenant)

build-static:
	php artisan webblok:build-static $(tenant) --locales=$(locales)

validate-schemas:
	php artisan webblok:validate-schemas
```

---

## 23. Implementation Sprint Checklist

### Sprint 1 — Foundation (Days 1–6)
- [ ] `composer create-project laravel/laravel webblok-cms`
- [ ] Install all packages from `composer.json`
- [ ] Configure `phpstan.neon` level 10 + `pint.json`
- [ ] Create `phpstan-baseline.neon` (empty — start clean)
- [ ] Set up MySQL platform database + all 7 platform migrations
- [ ] Create `TenantContext` singleton + `AppServiceProvider` binding
- [ ] Create `SetTenantDatabaseConnection` middleware
- [ ] Create `AuthenticateApiKey` middleware
- [ ] Implement `ProvisionTenant` action + `ProvisionTenantJob`
- [ ] Create `PlanSeeder` + `SuperAdminSeeder`
- [ ] Run `make analyse` → zero errors ✅

### Sprint 2 — Blok System (Days 7–13)
- [ ] Create `BaseBlok` abstract class
- [ ] Create `BlokDefinition` model + seeder
- [ ] Create all 150 `Slide{NN}Blok.php` classes
- [ ] Create `blok_definitions/*.schema.json` files for all 150 bloks
- [ ] Create `BlokSchemaResolver` service with Redis cache
- [ ] Create `BlokSchemaField` + `BlokSchemaStep` value objects
- [ ] Run `make analyse` → zero errors ✅

### Sprint 3 — Tenant Data Model (Days 14–20)
- [ ] Create tenant SQLite migration files (all 15 tables)
- [ ] Create `TenantMigrator` service (runs migrations on arbitrary SQLite)
- [ ] Create all tenant Eloquent models (Page, PageLocale, BlokInstance, etc.)
- [ ] Create all backed enums (PageStatus, BlokSection, TenantStatus, ExportStatus)
- [ ] Create all `spatie/laravel-data` DTOs
- [ ] Create tenant model factories for testing
- [ ] Run `make test` → ≥60% passing ✅

### Sprint 4 — Page Editor (Days 21–27)
- [ ] Build `PageEditor` Livewire component
- [ ] Build `BlokPalette` Livewire component
- [ ] Build `BlokInstanceSettings` (Form Wizard) Livewire component
- [ ] Build `LivePreview` Livewire component (iframe)
- [ ] Build `RevisionHistory` Livewire component
- [ ] Integrate SortableJS (`drag-drop.js`)
- [ ] Build all Form Wizard field renderers in Blade
- [ ] Run `make test` + `make analyse` → all pass ✅

### Sprint 5 — REST API (Days 28–33)
- [ ] Implement `PageController` (all endpoints)
- [ ] Implement `BlokController` (all endpoints + render)
- [ ] Implement `MenuController`
- [ ] Implement `FormSubmissionController`
- [ ] Implement `AnalyticsController`
- [ ] Write full API feature tests
- [ ] Run `make ci` → all pass ✅

### Sprint 6 — Filament Panels (Days 34–39)
- [ ] Implement `CmsPanelProvider` (/cms)
- [ ] Implement `SuperAdminPanelProvider` (/super-admin)
- [ ] Implement all Filament Resources (Page, Menu, FormSubmissions)
- [ ] Implement all Filament Widgets
- [ ] Build `StaticExportPage` Filament page + UI
- [ ] Build `MediaLibraryPage`
- [ ] Run `make ci` ✅

### Sprint 7 — Static Export + Webhooks + Final (Days 40–45)
- [ ] Implement `StaticSiteExporter` service
- [ ] Implement `BuildStaticSiteJob`
- [ ] Implement `api-client.js` static bridge
- [ ] Implement `SitemapGenerator`
- [ ] Implement webhook outgoing system (`SendWebhookJob`)
- [ ] Implement `BackupTenantDatabaseJob`
- [ ] Configure Laravel Horizon
- [ ] Configure Supervisor
- [ ] Set up GitHub Actions CI/CD
- [ ] Run full `make ci` → 100% pass
- [ ] Run `phpstan analyse` → Level 10 zero errors ✅
- [ ] Deploy to staging → smoke test all endpoints ✅

---

## 24. V2 vs V1 Final Decision Table

| You Need This | Use V1 | Use V2 |
|--------------|--------|--------|
| Embed 2030B bloks in an existing site | ✅ | — |
| Give users a visual website builder | — | ✅ |
| Multi-language content management | — | ✅ |
| Drag-and-drop editor | — | ✅ |
| Role-based access control | — | ✅ |
| Download full static website | — | ✅ |
| Simple API integration | ✅ | — |
| Complete CMS platform | — | ✅ |
| Fastest implementation | ✅ (6 weeks) | — |
| Maximum features | — | ✅ (10 weeks) |
| PHPStan Level 10 | Optional | **Required** |
| Filament admin panel | — | ✅ |
| Revision history | — | ✅ |
| Media library | — | ✅ |
| Analytics tracking | — | ✅ |
| Outgoing webhooks | — | ✅ |

---

## 25. Quick-Start Commands

```bash
# 1. Clone and install
git clone git@github.com:your-org/webblok-cms.git
cd webblok-cms
make install

# 2. Validate everything
make ci

# 3. Seed blok definitions
make seed-bloks

# 4. Provision a test tenant
php artisan webblok:provision-tenant acme-corp

# 5. Run with live reload
make dev

# 6. Build static export for tenant
make build-static tenant=acme-corp locales=en,ar

# 7. Validate all blok schemas
make validate-schemas
```

---

## 26. Summary

| Aspect | Specification |
|--------|--------------|
| **Framework** | Laravel 12.x |
| **Admin Panel** | Filament 3.2 (two panels) |
| **Reactive UI** | Livewire 3.4 + Alpine.js 3 |
| **Static Analysis** | PHPStan Level 10 via Larastan 2.9 |
| **Tenancy** | Manual (TenantContext singleton) — no stancl/tenancy |
| **Platform DB** | MySQL 8+ (7 tables) |
| **Tenant DB** | SQLite WAL (15 tables per tenant) |
| **Blok System** | 150+ definitions, JSON schemas, recursive nesting (depth 10) |
| **Form Wizard** | 19 field types, multi-step, i18n, conditional visibility |
| **Static Export** | Full ZIP download + injected api-client.js AJAX bridge |
| **API** | REST V2 (14 endpoints, bearer token auth) |
| **Queue** | Redis + Laravel Horizon |
| **Cache** | Redis tagged cache (3 layers) |
| **Search** | MeiliSearch via Laravel Scout |
| **Implementation** | 7 sprints × 45 days |
| **Test Coverage Target** | ≥ 80% |
| **CI/CD** | GitHub Actions (lint + analyse L10 + test + deploy) |
