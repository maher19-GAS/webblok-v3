# WebBlok SaaS — Full Project Specification Prompt

> **Purpose**: This document is the complete, developer-ready prompt for building the **WebBlok SaaS** platform. Hand this file to any AI coding assistant, senior developer, or project team to begin implementation from scratch with full context.

---

## 0. One-Line Vision

> **WebBlok** is a multi-tenant SaaS platform (one SQLite per external client website) built on **Laravel 12.x + Blade + Breeze**, that turns the existing 150-slide interactive dashboard system (from the 2030B project) into embeddable, API-served **Laravel Blade components** — delivered as JSON, rendered HTML, or CDN-hosted widget bundles — to any external website via a per-tenant API key.

---

## 1. Project Context & Source Material

### 1.1 Source Repository

The GitHub repository provided by the owner contains all source material:

```
2030b-project/                        ← root
  index.html                          ← Original single-page viewer (base version)
  a1-index.html                       ← Volume 1 viewer  (slides 1–50,   EN/AR)
  a2-index.html                       ← Volume 2 viewer  (slides 51–100, EN/AR)
  a3-index.html                       ← Volume 3 viewer  (slides 101–150, EN/AR)
  a4-index.html                       ← Extended viewer  (full 150 slides, EN/AR/FR)
  a5-index.html                       ← Inline viewer    (no iframes, full 150, AR/EN/FR)
  css/styles.css                      ← Master CSS design system
  js/
    animations.js                     ← Particle system, fade-in, glow effects
    loader.js                         ← Language loader, Loader.initLanguage()
    slide-utils.js                    ← initSlide(), buildSlideHeader(), footer nav
  slides/
    slide-{001..150}.html             ← 150 individual slide HTML files
    data/
      slide-{001..150}.{en|ar|fr}.json ← 450 JSON data files (150 × 3 langs)
  components/
    core/
      slide-header.blade.php
      slide-container.blade.php
      slide-footer-nav.blade.php
    cards/
      stat-card.blade.php
      glass-card.blade.php
      progress-card.blade.php
      project-card.blade.php
    skeleton/
      skeleton-layout.html
      skeleton-block.html
    layout/
      app-shell.html
      grid-container.html
      grid-system.html
      slide-container-full.html
      slide-container-card.html
      slide-container-split.html
    advanced/  cards/  audio/  certification/  cognitive-ux/
    conditioning/  data/  economy-ui/  ecosystem/  effects/
    governance/  identity/  lists/  marketplace/  progression/
    public/  system/  ui/  user/  video/
  langs/
    ar.json  en.json  fr.json  es.json  de.json  tr.json  ur.json
  slides/data/
    slide-{001-150}.{en|ar|fr}.json   ← 450 localized JSON payloads
  2030b-config.json                   ← Master config (chapters, volumes, schema)
```

### 1.2 What the Source Does

Each slide is a **self-contained interactive widget** containing:
- A **skeleton loading state** (CSS animation placeholder shown during fetch)
- A **real content div** (hidden until data loads, then revealed)
- A **footer navigation** (prev/next slide, volume links)
- A **`renderSlide(data, lang)`** JavaScript function consuming JSON data
- Support for **3 languages** (AR, EN, FR) via per-slide JSON files
- Design tokens: dark theme, gradient backgrounds, glass morphism cards, RTL/LTR

The slides cover 11 chapters across 3 volumes:
- **Chapter 1–3**: Dashboard, cognitive profiles, CTC economy (slides 1–50)
- **Chapter 4–7**: Platform features, projects, achievements, governance (51–100)
- **Chapter 8–11**: Enterprise, spatial computing, vision 2030, cosmic mission (101–150)

### 1.3 Languages Already Supported

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

## 2. What WebBlok SaaS Is

### 2.1 Core Concept

WebBlok converts each slide (and component group) into a **named "blok"** — a versioned, API-accessible UI widget. External websites embed bloks in three ways:

1. **JSON API** → `GET /api/v1/bloks/{blok_id}?lang=ar` → returns structured JSON
2. **HTML API** → `GET /api/v1/bloks/{blok_id}/render?lang=ar&theme=dark` → returns full rendered HTML with inline CSS and CDN script tags
3. **CDN Widget** → `<script src="https://cdn.webblok.io/widget.js" data-blok="slide-01" data-lang="ar"></script>` → self-contained JS widget that mounts into any `<div id="wb-{blok_id}">` element

### 2.2 Multi-Tenancy Model

- **One SQLite database per tenant website** (physical isolation)
- Tenants are "external websites" that register their domain(s)
- Each tenant gets:
  - A unique `tenant_id` (UUID)
  - One or more **API keys** (`wbk_live_xxx` / `wbk_test_xxx`)
  - A dedicated SQLite file at `storage/tenants/{tenant_id}/database.sqlite`
  - Configurable blok access (whitelist which bloks they can use)
  - Usage quota (requests/month, render calls/day)
  - Custom branding overrides (logo, accent color, font)

### 2.3 "Blok" Definition

A **blok** is the WebBlok SaaS atomic unit. It maps 1-to-1 with either:
- A single slide: `slide-01` → `slide-150`
- A component group: `stat-card`, `glass-card`, `progress-card`, `project-card`
- A layout shell: `app-shell`, `grid-2col`, `grid-3col`
- A skeleton: `skeleton-default`, `skeleton-cards`, `skeleton-list`

Each blok has:
```json
{
  "blok_id":   "slide-01",
  "version":   "1.0.0",
  "category":  "dashboard",
  "chapter":   1,
  "volume":    1,
  "langs":     ["en", "ar", "fr"],
  "has_skeleton": true,
  "has_interactive": true,
  "cdn_ready":  true,
  "component_class": "App\\View\\Components\\Bloks\\Slide01"
}
```

---

## 3. Technology Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| **Backend Framework** | Laravel | 12.x |
| **Starter Kit** | Breeze (Blade + Alpine.js) | Latest |
| **Database — Platform** | MySQL / PostgreSQL | 8.0+ / 15+ |
| **Database — Tenants** | SQLite (one file per tenant) | 3.x (WAL mode) |
| **Template Engine** | Blade | Laravel 12 |
| **Frontend JS** | Alpine.js (via Breeze) | 3.x |
| **CSS Framework** | Tailwind CSS | 3.x |
| **Queue** | Laravel Queue (Redis or DB driver) | — |
| **Cache** | Redis / File | — |
| **API Format** | REST + JSON (v1) | — |
| **Auth** | Laravel Sanctum (API tokens) | — |
| **File Storage** | Local (tenant SQLite files) | — |
| **CDN (future)** | Cloudflare / BunnyCDN | — |
| **Testing** | Pest PHP | 2.x |

---

## 4. Database Architecture

### 4.1 Platform Database (MySQL/PostgreSQL)

This is the **central registry** — no user content lives here.

```sql
-- Platform users (SaaS operators and tenant admins)
CREATE TABLE users (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(255) NOT NULL,
  email         VARCHAR(255) UNIQUE NOT NULL,
  password      VARCHAR(255) NOT NULL,
  role          ENUM('super_admin','tenant_owner','tenant_member') DEFAULT 'tenant_owner',
  email_verified_at TIMESTAMP NULL,
  remember_token VARCHAR(100),
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tenant registrations (one row per external website)
CREATE TABLE tenants (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid          CHAR(36) UNIQUE NOT NULL,              -- used as DB filename
  name          VARCHAR(255) NOT NULL,                  -- "Acme Corp Blog"
  domain        VARCHAR(255) NOT NULL,                  -- "blog.acme.com"
  extra_domains JSON NULL,                              -- ["acme.com","www.acme.com"]
  owner_id      BIGINT UNSIGNED NOT NULL REFERENCES users(id),
  plan          ENUM('free','starter','pro','enterprise') DEFAULT 'free',
  status        ENUM('active','suspended','pending') DEFAULT 'pending',
  db_path       VARCHAR(500) NOT NULL,                  -- absolute path to .sqlite
  settings      JSON NULL,                              -- branding, quota overrides
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- API keys per tenant
CREATE TABLE api_keys (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id     BIGINT UNSIGNED NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  name          VARCHAR(255) NOT NULL,                  -- "Production Key"
  key_hash      VARCHAR(255) UNIQUE NOT NULL,           -- SHA-256 of actual key
  key_prefix    VARCHAR(20)  NOT NULL,                  -- "wbk_live_" prefix (shown in UI)
  environment   ENUM('live','test') DEFAULT 'live',
  scopes        JSON NOT NULL DEFAULT ('["bloks:read"]'),
  last_used_at  TIMESTAMP NULL,
  expires_at    TIMESTAMP NULL,
  revoked_at    TIMESTAMP NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Blok registry (platform-level, all available bloks)
CREATE TABLE bloks (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  blok_id       VARCHAR(100) UNIQUE NOT NULL,           -- "slide-01", "stat-card"
  version       VARCHAR(20) NOT NULL DEFAULT '1.0.0',
  category      VARCHAR(100) NOT NULL,                  -- "dashboard","card","layout"
  chapter       TINYINT UNSIGNED NULL,
  volume        TINYINT UNSIGNED NULL,
  title_en      VARCHAR(255) NOT NULL,
  title_ar      VARCHAR(255) NULL,
  title_fr      VARCHAR(255) NULL,
  description   TEXT NULL,
  langs         JSON NOT NULL DEFAULT ('["en"]'),
  has_skeleton  BOOLEAN DEFAULT TRUE,
  has_interactive BOOLEAN DEFAULT FALSE,
  cdn_ready     BOOLEAN DEFAULT FALSE,
  component_class VARCHAR(255) NOT NULL,
  source_file   VARCHAR(500) NULL,                      -- path in source repo
  preview_html  LONGTEXT NULL,                          -- cached render preview
  is_published  BOOLEAN DEFAULT FALSE,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Usage tracking (aggregated per tenant per day)
CREATE TABLE usage_logs (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id     BIGINT UNSIGNED NOT NULL REFERENCES tenants(id),
  api_key_id    BIGINT UNSIGNED NULL REFERENCES api_keys(id),
  blok_id       VARCHAR(100) NOT NULL,
  response_type ENUM('json','html','widget') NOT NULL,
  lang          VARCHAR(10) NOT NULL,
  requests      INT UNSIGNED DEFAULT 1,
  render_ms     SMALLINT UNSIGNED NULL,
  date          DATE NOT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant_date (tenant_id, date),
  INDEX idx_blok_date   (blok_id, date)
);
```

### 4.2 Tenant SQLite Database Schema

Each tenant gets `storage/tenants/{uuid}/database.sqlite`. Migrations run per-tenant on first connection.

```sql
-- Tenant-specific blok access grants
CREATE TABLE blok_grants (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  blok_id       TEXT NOT NULL,
  is_enabled    INTEGER NOT NULL DEFAULT 1,
  custom_data   TEXT NULL,             -- JSON: tenant-specific data overrides per blok
  granted_at    TEXT DEFAULT (datetime('now')),
  UNIQUE(blok_id)
);

-- Tenant branding/config
CREATE TABLE tenant_config (
  key           TEXT PRIMARY KEY,
  value         TEXT NOT NULL,
  updated_at    TEXT DEFAULT (datetime('now'))
);

-- Per-tenant blok render cache
CREATE TABLE blok_cache (
  cache_key     TEXT PRIMARY KEY,      -- "slide-01:ar:dark:v1.0.0"
  html          TEXT NOT NULL,
  data_hash     TEXT NOT NULL,
  lang          TEXT NOT NULL,
  blok_id       TEXT NOT NULL,
  rendered_at   TEXT DEFAULT (datetime('now')),
  expires_at    TEXT NOT NULL
);

-- Tenant-side analytics (lightweight)
CREATE TABLE render_events (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  blok_id       TEXT NOT NULL,
  lang          TEXT NOT NULL,
  response_type TEXT NOT NULL,
  visitor_hash  TEXT NULL,             -- anonymized IP hash
  referrer      TEXT NULL,
  ts            INTEGER NOT NULL       -- unix timestamp
);

-- Tenant custom translations / content overrides
CREATE TABLE content_overrides (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  blok_id       TEXT NOT NULL,
  lang          TEXT NOT NULL,
  key_path      TEXT NOT NULL,         -- "stats.ctc_balance"
  value         TEXT NOT NULL,
  updated_at    TEXT DEFAULT (datetime('now')),
  UNIQUE(blok_id, lang, key_path)
);
```

---

## 5. Laravel Project Structure

```
webblok/
  app/
    Http/
      Controllers/
        Api/
          V1/
            BlokController.php          ← GET /bloks, /bloks/{id}, /bloks/{id}/render
            TenantController.php        ← Tenant CRUD (super_admin)
            ApiKeyController.php        ← Key management
            UsageController.php         ← Usage stats
        Web/
          DashboardController.php       ← Tenant dashboard (Blade)
          BlokManagerController.php     ← Manage blok grants
          SettingsController.php        ← Branding, domains, quota
          ApiKeyWebController.php       ← Web key management
      Middleware/
        AuthenticateApiKey.php          ← Validates wbk_live_xxx keys
        ResolveTenant.php               ← Sets tenant context from API key / domain
        ThrottleByTenant.php            ← Per-tenant rate limiting
        SetTenantDatabase.php           ← Connects SQLite for current tenant
        EnsureTenantActive.php          ← Rejects suspended tenants
    Models/
      User.php
      Tenant.php
      ApiKey.php
      Blok.php
      UsageLog.php
      Tenant/                           ← Models resolved against tenant SQLite
        BlokGrant.php
        TenantConfig.php
        BlokCache.php
        RenderEvent.php
        ContentOverride.php
    View/
      Components/
        Bloks/
          BaseBlok.php                  ← Abstract base for all blok components
          Slide01.php  → Slide150.php   ← 150 slide components
          StatCard.php
          GlassCard.php
          ProgressCard.php
          ProjectCard.php
          SkeletonLayout.php
          SkeletonCards.php
          AppShell.php
          GridContainer.php
          GridSystem.php
    Services/
      TenantManager.php                 ← Creates/destroys tenant DB, runs migrations
      BlokRenderer.php                  ← Renders a blok to HTML or JSON
      BlokDataResolver.php              ← Loads JSON data, applies overrides
      ApiKeyService.php                 ← Generate, hash, validate keys
      CdnBundler.php                    ← Assembles widget.js bundle per tenant
      UsageTracker.php                  ← Records usage_logs rows
    Support/
      TenantDatabaseManager.php         ← SQLite connection switcher
  resources/
    views/
      layouts/
        app.blade.php                   ← Breeze app layout
        api-doc.blade.php               ← API documentation layout
      dashboard/
        index.blade.php                 ← Tenant home: stats, quick start
        bloks.blade.php                 ← Blok manager grid
        api-keys.blade.php              ← Key list + create
        settings.blade.php              ← Branding, domains
        usage.blade.php                 ← Usage charts
      admin/
        tenants.blade.php               ← Super-admin tenant list
        blok-registry.blade.php         ← Manage platform bloks
      components/
        bloks/
          slide-01.blade.php            ← Blade component views (150 slides)
          ...slide-150.blade.php
          stat-card.blade.php
          glass-card.blade.php
          progress-card.blade.php
          project-card.blade.php
          skeleton-layout.blade.php
          app-shell.blade.php
        ui/
          blok-card.blade.php           ← Dashboard blok preview card
          api-key-row.blade.php
          usage-chart.blade.php
          tenant-badge.blade.php
      api-docs/
        index.blade.php                 ← Interactive API docs
  database/
    migrations/
      platform/                         ← MySQL/PG migrations
        2026_01_01_000001_create_users_table.php
        2026_01_01_000002_create_tenants_table.php
        2026_01_01_000003_create_api_keys_table.php
        2026_01_01_000004_create_bloks_table.php
        2026_01_01_000005_create_usage_logs_table.php
      tenant/                           ← SQLite migrations (run per-tenant)
        0001_create_blok_grants_table.php
        0002_create_tenant_config_table.php
        0003_create_blok_cache_table.php
        0004_create_render_events_table.php
        0005_create_content_overrides_table.php
    seeders/
      BlokSeeder.php                    ← Seeds all 150+ bloks into bloks table
      DemoTenantSeeder.php              ← Creates demo tenant with test key
  routes/
    api.php                             ← All /api/v1/* routes (Sanctum-guarded)
    web.php                             ← Dashboard Blade routes (Breeze auth)
  config/
    webblok.php                         ← Platform config (plans, quotas, CDN URL)
    tenancy.php                         ← Tenant DB path, driver config
```

---

## 6. API Design

### 6.1 Authentication

All API routes require a header:
```
Authorization: Bearer wbk_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxx
```
Or query param `?api_key=wbk_live_xxx` (for widget embeds only).

The key is validated by `AuthenticateApiKey` middleware:
1. Extract prefix `wbk_live_` or `wbk_test_`
2. Compute SHA-256 hash of full key
3. Find matching `api_keys` row (not revoked, not expired)
4. Load associated `tenant` (status = active)
5. Set `TenantContext::set($tenant)` in the request lifecycle
6. Connect tenant SQLite via `SetTenantDatabase` middleware

### 6.2 Endpoint Reference

#### `GET /api/v1/bloks`
List all bloks available to the authenticated tenant.

**Query params**: `?category=dashboard&lang=ar&page=1&per_page=20`

**Response**:
```json
{
  "data": [
    {
      "blok_id": "slide-01",
      "version": "1.0.0",
      "category": "dashboard",
      "chapter": 1,
      "volume": 1,
      "title": "Welcome Dashboard",
      "langs": ["en","ar","fr"],
      "has_skeleton": true,
      "has_interactive": true,
      "cdn_ready": true,
      "links": {
        "self":    "/api/v1/bloks/slide-01",
        "render":  "/api/v1/bloks/slide-01/render",
        "data":    "/api/v1/bloks/slide-01/data",
        "widget":  "https://cdn.webblok.io/widget.js"
      }
    }
  ],
  "meta": { "total": 150, "page": 1, "per_page": 20 },
  "tenant": { "id": "uuid", "name": "Acme Blog", "plan": "pro" }
}
```

#### `GET /api/v1/bloks/{blok_id}`
Get single blok metadata.

**Response**: Single blok object (same structure as list item).

#### `GET /api/v1/bloks/{blok_id}/data`
Get the raw JSON data payload for a blok (language-resolved).

**Query params**: `?lang=ar` (default: tenant's default lang or `en`)

**Response**:
```json
{
  "blok_id": "slide-01",
  "lang": "ar",
  "version": "1.0.0",
  "dir": "rtl",
  "font": "Cairo",
  "data": {
    "header": "مرحباً، أحمد جمال",
    "stats": {
      "ctc_balance": "12,450",
      "projects_joined": 8,
      "contributions_made": 47,
      "reputation_score": "2,340"
    },
    "enhancement_level": 73,
    "challenge_preview": "حل ٣ ألغاز منطقية خلال ساعة"
  },
  "overrides_applied": false
}
```

#### `GET /api/v1/bloks/{blok_id}/render`
Return fully rendered HTML for the blok.

**Query params**:
- `lang=ar` — Language code
- `theme=dark|light` — Theme (default: `dark`)
- `skeleton=true|false` — Include skeleton markup (default: `true`)
- `inline_css=true|false` — Inline all CSS (default: `true`)
- `cdn_js=true|false` — Append CDN script tags (default: `true`)
- `wrap=true|false` — Wrap in `<div id="wb-{blok_id}">` container (default: `true`)

**Response** (Content-Type: `text/html`):
```html
<div id="wb-slide-01" class="wb-blok" dir="rtl" data-lang="ar" data-version="1.0.0">
  <style>/* inlined design tokens + component CSS */</style>
  <div class="slide-container">
    <div class="slide-bg"></div>
    <!-- Skeleton -->
    <div id="skeleton" class="slide-inner">
      <!-- skeleton markup -->
    </div>
    <!-- Real Content -->
    <div id="content" class="slide-content" style="display:none;flex-direction:column">
      <!-- fully rendered Blade output with Arabic data -->
    </div>
    <div id="footer-nav"></div>
  </div>
  <script src="https://cdn.webblok.io/v1/runtime.js" data-blok="slide-01" data-lang="ar"></script>
</div>
```

#### `GET /api/v1/bloks/{blok_id}/render` with `Accept: application/json`
Returns structured render result:
```json
{
  "blok_id": "slide-01",
  "lang": "ar",
  "theme": "dark",
  "html": "<div id=\"wb-slide-01\"...>...</div>",
  "css_hash": "sha256-abc123",
  "render_ms": 12,
  "cached": true,
  "cache_ttl": 3600
}
```

#### `POST /api/v1/bloks/{blok_id}/data`
Override per-tenant blok data (content customization).

**Body**:
```json
{
  "lang": "ar",
  "overrides": {
    "header": "مرحباً، عميلنا الكريم",
    "stats.ctc_balance": "99,999"
  }
}
```

#### `GET /api/v1/tenant`
Get current tenant info + usage summary.

#### `GET /api/v1/usage`
Get tenant usage statistics.

**Query params**: `?from=2026-05-01&to=2026-05-31&blok_id=slide-01`

**Response**:
```json
{
  "period": { "from": "2026-05-01", "to": "2026-05-31" },
  "totals": {
    "requests": 14820,
    "renders": 3420,
    "json_calls": 11400,
    "unique_bloks_used": 47
  },
  "daily": [ { "date": "2026-05-01", "requests": 480, "renders": 110 } ],
  "top_bloks": [ { "blok_id": "slide-01", "requests": 2340 } ],
  "quota": { "plan": "pro", "monthly_limit": 100000, "used": 14820 }
}
```

#### `GET /api/v1/langs`
List supported languages with metadata.

#### `POST /api/v1/keys` / `DELETE /api/v1/keys/{id}`
Create / revoke API keys (tenant-admin scope).

### 6.3 Widget Embed (Third Response Type)

External site adds two lines:
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

`widget.js` behaviour:
1. Reads `data-*` attributes
2. Injects skeleton immediately (from embedded skeleton templates)
3. Fetches `/api/v1/bloks/{blok_id}/render?lang=ar&theme=dark`
4. Replaces skeleton with rendered HTML
5. Initialises Alpine.js / animation system
6. Tracks render event back to tenant analytics

---

## 7. Laravel Blade Components

### 7.1 Component Anatomy

Every blok is implemented as a **Laravel Blade Component class + view pair**:

```
app/View/Components/Bloks/Slide01.php
resources/views/components/bloks/slide-01.blade.php
```

**Base class** (`BaseBlok.php`):
```php
<?php
namespace App\View\Components\Bloks;

use Illuminate\View\Component;

abstract class BaseBlok extends Component
{
    public string  $blokId;
    public string  $lang;
    public string  $theme;
    public string  $dir;
    public string  $font;
    public bool    $showSkeleton;
    public bool    $inlineCss;
    public array   $data;

    public function __construct(
        string $lang       = 'en',
        string $theme      = 'dark',
        bool   $showSkeleton = true,
        bool   $inlineCss  = true,
        array  $dataOverrides = []
    ) {
        $this->lang         = $lang;
        $this->theme        = $theme;
        $this->showSkeleton = $showSkeleton;
        $this->inlineCss    = $inlineCss;
        $this->dir          = in_array($lang, ['ar','ur']) ? 'rtl' : 'ltr';
        $this->font         = in_array($lang, ['ar','ur']) ? 'Cairo' : 'Inter';
        $this->data         = $this->resolveData($dataOverrides);
    }

    /** Load JSON data for this blok + lang, apply tenant overrides */
    protected function resolveData(array $overrides = []): array
    {
        return app(BlokDataResolver::class)->resolve(
            $this->blokId, $this->lang, $overrides
        );
    }

    abstract public function render(): \Illuminate\Contracts\View\View;
}
```

**Concrete slide component** (`Slide01.php`):
```php
<?php
namespace App\View\Components\Bloks;

class Slide01 extends BaseBlok
{
    protected string $blokId = 'slide-01';

    public function render()
    {
        return view('components.bloks.slide-01');
    }
}
```

**Blade view** (`slide-01.blade.php`) — converted from `slides/slide-01.html`:
```blade
{{-- resources/views/components/bloks/slide-01.blade.php --}}
<div id="wb-{{ $blokId }}" class="wb-blok" dir="{{ $dir }}" data-lang="{{ $lang }}">
  @if($inlineCss)
    <style>{!! $cssTokens !!}</style>
  @endif

  <div class="slide-container">
    <div class="slide-bg"></div>

    {{-- Skeleton Loading State --}}
    @if($showSkeleton)
      <div id="skeleton-{{ $blokId }}" class="slide-inner">
        <x-bloks.skeleton-layout />
      </div>
    @endif

    {{-- Real Content --}}
    <div id="content-{{ $blokId }}" class="slide-content"
         @if($showSkeleton) style="display:none;flex-direction:column" @endif>

      {{-- Header --}}
      <x-bloks.slide-header
        :title="$data['header'] ?? ''"
        icon="🔔"
        :lang="$lang"
      />

      <div style="padding:0 16px;display:flex;flex-direction:column;gap:14px">

        {{-- Profile Badge --}}
        <div class="profile-badge fade-in">
          <div class="glow-ring">
            <div class="avatar avatar-xl">AJ</div>
          </div>
          <div class="profile-badge-info">
            <div class="profile-badge-name">{{ Str::after($data['header'] ?? '', ', ') }}</div>
            <div class="profile-badge-level">⚡ {{ $lang === 'ar' ? 'مستوى' : 'Level' }} 8</div>
            <div style="margin-top:8px">
              <div class="progress-track">
                <div class="progress-fill"
                     style="width:{{ $data['enhancement_level'] ?? 0 }}%"></div>
              </div>
            </div>
          </div>
        </div>

        {{-- CTC Balance --}}
        <x-bloks.stat-card
          icon="💎"
          :label="$lang === 'ar' ? 'رصيد CTC' : 'CTC Balance'"
          :value="($data['stats']['ctc_balance'] ?? '0') . ' CTC'"
          trend="+12%"
          trend-dir="up"
        />

        {{-- Stats Grid --}}
        <div class="grid-3 fade-in">
          <x-bloks.stat-card icon="🚀" :value="$data['stats']['projects_joined'] ?? 0"
            :label="$lang === 'ar' ? 'المشاريع' : 'Projects'" center />
          <x-bloks.stat-card icon="📝" :value="$data['stats']['contributions_made'] ?? 0"
            :label="$lang === 'ar' ? 'المساهمات' : 'Contributions'" center />
          <x-bloks.stat-card icon="⭐" :value="$data['stats']['reputation_score'] ?? 0"
            :label="$lang === 'ar' ? 'السمعة' : 'Reputation'" center />
        </div>

        {{-- Today's Challenge --}}
        <div class="action-card fade-in">
          <div class="action-card-title">
            {{ $lang === 'ar' ? "تحدي اليوم" : "Today's Challenge" }}
          </div>
          <div class="action-card-desc">{{ $data['challenge_preview'] ?? '' }}</div>
          <button class="btn btn-primary btn-sm">
            {{ $lang === 'ar' ? 'ابدأ' : 'Start' }} →
          </button>
        </div>

      </div>
      <div style="height:80px"></div>
    </div>

    {{-- Footer Nav --}}
    <x-bloks.slide-footer-nav
      :current="1"
      :total="150"
      :lang="$lang"
    />
  </div>

  @if($showSkeleton)
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        var sk = document.getElementById('skeleton-{{ $blokId }}');
        var ct = document.getElementById('content-{{ $blokId }}');
        if (sk) sk.style.display = 'none';
        if (ct) ct.style.display = 'flex';
      });
    </script>
  @endif
</div>
```

### 7.2 BlokDataResolver Service

```php
<?php
namespace App\Services;

class BlokDataResolver
{
    /**
     * Load JSON data for blok+lang, merge tenant overrides from SQLite.
     */
    public function resolve(string $blokId, string $lang, array $runtimeOverrides = []): array
    {
        // 1. Load base JSON from slides/data/{blok}.{lang}.json
        $padded = str_pad(str_replace('slide-','',$blokId), 3, '0', STR_PAD_LEFT);
        $path = base_path("slides/data/slide-{$padded}.{$lang}.json");

        if (!file_exists($path)) {
            // Fallback to English
            $path = base_path("slides/data/slide-{$padded}.en.json");
        }

        $base = file_exists($path) ? json_decode(file_get_contents($path), true) : [];

        // 2. Load tenant content_overrides from SQLite (if tenant context set)
        $tenantOverrides = $this->loadTenantOverrides($blokId, $lang);

        // 3. Merge: runtime > tenant > base
        return $this->deepMerge($base, $tenantOverrides, $runtimeOverrides);
    }

    private function loadTenantOverrides(string $blokId, string $lang): array
    {
        if (!TenantContext::hasTenant()) return [];
        // Query tenant SQLite content_overrides table
        $rows = \DB::connection('tenant')
            ->table('content_overrides')
            ->where('blok_id', $blokId)
            ->where('lang', $lang)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            data_set($result, $row->key_path, $row->value);
        }
        return $result;
    }

    private function deepMerge(array ...$arrays): array
    {
        $result = [];
        foreach ($arrays as $arr) {
            $result = array_merge_recursive($result, $arr);
        }
        return $result;
    }
}
```

### 7.3 BlokRenderer Service

```php
<?php
namespace App\Services;

class BlokRenderer
{
    public function renderHtml(string $blokId, array $options = []): string
    {
        $lang    = $options['lang']         ?? 'en';
        $theme   = $options['theme']        ?? 'dark';
        $skeleton = $options['skeleton']    ?? true;
        $inlineCss = $options['inline_css'] ?? true;

        // Check render cache in tenant SQLite
        $cacheKey = "{$blokId}:{$lang}:{$theme}:" . ($skeleton ? '1' : '0');
        if ($cached = $this->getCache($cacheKey)) {
            return $cached;
        }

        // Map blok_id → Component class
        $componentClass = $this->resolveComponentClass($blokId);

        // Render Blade component
        $html = view()->make("components.bloks.{$blokId}", [
            'lang'         => $lang,
            'theme'        => $theme,
            'showSkeleton' => $skeleton,
            'inlineCss'    => $inlineCss,
        ])->render();

        // Store in cache
        $this->putCache($cacheKey, $html, now()->addHour());

        return $html;
    }

    public function renderJson(string $blokId, string $lang = 'en'): array
    {
        return app(BlokDataResolver::class)->resolve($blokId, $lang);
    }

    private function resolveComponentClass(string $blokId): string
    {
        // "slide-01" → "App\View\Components\Bloks\Slide01"
        $parts = explode('-', $blokId);
        $class = implode('', array_map('ucfirst', $parts));
        return "App\\View\\Components\\Bloks\\{$class}";
    }

    private function getCache(string $key): ?string { /* ... */ }
    private function putCache(string $key, string $html, $ttl): void { /* ... */ }
}
```

---

## 8. Multi-Tenant Database Management

### 8.1 TenantManager Service

```php
<?php
namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;

class TenantManager
{
    public function create(array $attributes): Tenant
    {
        $tenant = Tenant::create([
            'uuid'     => \Str::uuid(),
            'name'     => $attributes['name'],
            'domain'   => $attributes['domain'],
            'owner_id' => $attributes['owner_id'],
            'plan'     => $attributes['plan'] ?? 'free',
            'status'   => 'active',
            'db_path'  => $this->dbPath(\Str::uuid()),
        ]);

        // Create directory
        $dir = dirname($tenant->db_path);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        // Run SQLite migrations for new tenant
        $this->runMigrations($tenant);

        // Seed default blok grants (all free-tier bloks)
        $this->seedDefaultGrants($tenant);

        return $tenant;
    }

    public function runMigrations(Tenant $tenant): void
    {
        config(['database.connections.tenant.database' => $tenant->db_path]);
        \DB::purge('tenant');
        Artisan::call('migrate', [
            '--path'     => 'database/migrations/tenant',
            '--database' => 'tenant',
            '--force'    => true,
        ]);
    }

    private function dbPath(string $uuid): string
    {
        return storage_path("tenants/{$uuid}/database.sqlite");
    }

    public function delete(Tenant $tenant): void
    {
        if (file_exists($tenant->db_path)) {
            unlink($tenant->db_path);
            @rmdir(dirname($tenant->db_path));
        }
        $tenant->delete();
    }
}
```

### 8.2 SetTenantDatabase Middleware

```php
<?php
namespace App\Http\Middleware;

class SetTenantDatabase
{
    public function handle($request, \Closure $next)
    {
        $tenant = TenantContext::current();

        if ($tenant) {
            config(['database.connections.tenant' => [
                'driver'   => 'sqlite',
                'database' => $tenant->db_path,
                'prefix'   => '',
                'foreign_key_constraints' => true,
                'busy_timeout' => 5000,        // 5s for WAL mode
                'journal_mode' => 'WAL',
            ]]);

            \DB::purge('tenant');
            \DB::reconnect('tenant');

            // Enable WAL mode for concurrent reads
            \DB::connection('tenant')->statement('PRAGMA journal_mode=WAL');
        }

        return $next($request);
    }
}
```

---

## 9. Routing

### 9.1 API Routes (`routes/api.php`)

```php
<?php
use App\Http\Controllers\Api\V1\{BlokController, TenantController, ApiKeyController, UsageController};

Route::prefix('v1')->middleware(['auth.apikey', 'resolve.tenant', 'tenant.active', 'set.tenant.db', 'throttle.tenant'])->group(function () {

    // Bloks
    Route::get('/bloks',                    [BlokController::class, 'index']);
    Route::get('/bloks/{blok}',             [BlokController::class, 'show']);
    Route::get('/bloks/{blok}/data',        [BlokController::class, 'data']);
    Route::get('/bloks/{blok}/render',      [BlokController::class, 'render']);
    Route::post('/bloks/{blok}/data',       [BlokController::class, 'storeOverride']);

    // Tenant info
    Route::get('/tenant',                   [TenantController::class, 'show']);
    Route::get('/langs',                    [BlokController::class, 'langs']);

    // Usage
    Route::get('/usage',                    [UsageController::class, 'index']);

    // API Keys (tenant-admin scope only)
    Route::middleware('scope:keys:manage')->group(function () {
        Route::get('/keys',                 [ApiKeyController::class, 'index']);
        Route::post('/keys',                [ApiKeyController::class, 'store']);
        Route::delete('/keys/{key}',        [ApiKeyController::class, 'destroy']);
    });
});
```

### 9.2 Web Routes (`routes/web.php`)

```php
<?php
// Breeze auth routes (login, register, password reset, verify)
require __DIR__.'/auth.php';

// Tenant Dashboard (authenticated)
Route::middleware(['auth', 'verified'])->prefix('dashboard')->name('dashboard.')->group(function () {
    Route::get('/',                   [DashboardController::class, 'index'])->name('home');
    Route::resource('bloks',          BlokManagerController::class)->only(['index','show','update']);
    Route::resource('api-keys',       ApiKeyWebController::class)->except(['edit','show']);
    Route::get('usage',               UsageController::class)->name('usage');
    Route::get('settings',            [SettingsController::class, 'edit'])->name('settings');
    Route::patch('settings',          [SettingsController::class, 'update'])->name('settings.update');
    Route::get('docs',                [DocsController::class, 'index'])->name('docs');
});

// Super-admin panel
Route::middleware(['auth','role:super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('tenants',        AdminTenantController::class);
    Route::resource('bloks',          AdminBlokController::class);
});
```

---

## 10. Plan Limits & Quota

```php
// config/webblok.php
return [
    'plans' => [
        'free' => [
            'monthly_requests'  => 10_000,
            'render_calls'      => 1_000,
            'max_bloks'         => 10,
            'max_api_keys'      => 1,
            'max_domains'       => 1,
            'cache_ttl'         => 300,    // 5 min
            'analytics_days'    => 7,
        ],
        'starter' => [
            'monthly_requests'  => 100_000,
            'render_calls'      => 20_000,
            'max_bloks'         => 50,
            'max_api_keys'      => 3,
            'max_domains'       => 3,
            'cache_ttl'         => 1800,   // 30 min
            'analytics_days'    => 30,
        ],
        'pro' => [
            'monthly_requests'  => 1_000_000,
            'render_calls'      => 200_000,
            'max_bloks'         => 150,    // all bloks
            'max_api_keys'      => 10,
            'max_domains'       => 10,
            'cache_ttl'         => 3600,   // 1 hour
            'analytics_days'    => 90,
        ],
        'enterprise' => [
            'monthly_requests'  => PHP_INT_MAX,
            'render_calls'      => PHP_INT_MAX,
            'max_bloks'         => 150,
            'max_api_keys'      => 100,
            'max_domains'       => PHP_INT_MAX,
            'cache_ttl'         => 86400,  // 24 hours
            'analytics_days'    => 365,
            'custom_branding'   => true,
            'white_label'       => true,
            'sla'               => '99.9%',
        ],
    ],
];
```

---

## 11. Blade Dashboard Views

### 11.1 Dashboard Home (`dashboard/index.blade.php`)

Sections to include:
1. **Quick-start banner** (show API key + embed snippet for first-time tenants)
2. **Usage summary widget** (requests today, renders this month, quota bar)
3. **Recently accessed bloks** (last 5 used)
4. **Blok catalog preview** (first 6 cards with category filters)
5. **API key status** (active key count, expiry warnings)
6. **Announcements** (new bloks added, API updates)

### 11.2 Blok Manager (`dashboard/bloks.blade.php`)

- Grid of all 150+ bloks as cards
- Each card: preview thumbnail (first render of blok), title, category, languages, enabled/disabled toggle
- Filters: by category, volume, chapter, language, interactive/static
- Per-blok detail page: live preview panel (AR/EN/FR tabs), JSON data view, embed code generator, override editor

### 11.3 API Keys Page (`dashboard/api-keys.blade.php`)

- List of keys: name, prefix, environment, scopes, last used, status
- Create key modal: name, environment, scopes checkboxes, expiry date
- One-time full key reveal on creation (copy button)
- Revoke button with confirmation

### 11.4 Usage Charts (`dashboard/usage.blade.php`)

- Line chart: requests/day (30 days)
- Bar chart: top 10 bloks by usage
- Table: by response type (JSON vs HTML vs widget)
- Quota meter: used/limit per plan

---

## 12. Conversion Plan: HTML Slides → Blade Components

### 12.1 Conversion Process (per slide)

For each of the 150 slides in `slides/slide-{001..150}.html`:

**Step 1 — Audit the source file**
- Identify `renderSlide(data, lang)` JS function output structure
- Map all `data.*` keys to the corresponding `slides/data/slide-{NNN}.en.json` fields
- List all child HTML components used (stat-card, action-card, grid-3, etc.)

**Step 2 — Create Component class**
```bash
php artisan make:component Bloks/Slide001
```

**Step 3 — Convert JS template literals → Blade syntax**
```
// JS                              // Blade equivalent
${data.header}                  → {{ $data['header'] }}
${isAr ? 'مرحبا' : 'Hello'}     → {{ $lang === 'ar' ? 'مرحبا' : 'Hello' }}
${data.stats.ctc_balance}       → {{ $data['stats']['ctc_balance'] ?? 0 }}
<div class="grid-3">            → <div class="grid-3">  (pass through)
<x-stat-card ...>               → use sub-components
```

**Step 4 — Extract child HTML into sub-component Blade files**

**Step 5 — Wire JSON data via `BlokDataResolver`**

**Step 6 — Test render via `/api/v1/bloks/slide-001/render?lang=ar`**

### 12.2 Component Category Map

| Category | Slides | Component Dir |
|----------|--------|--------------|
| Dashboard | 1–10 | `components/bloks/` |
| Cognitive | 11–20 | `components/bloks/` |
| Economy | 21–30 | `components/bloks/` |
| Governance | 31–40 | `components/bloks/` |
| Platform | 41–60 | `components/bloks/` |
| Projects | 61–80 | `components/bloks/` |
| Ecosystem | 81–100 | `components/bloks/` |
| Enterprise | 101–120 | `components/bloks/` |
| Technology | 121–140 | `components/bloks/` |
| Vision | 141–150 | `components/bloks/` |

### 12.3 Existing Blade Components to Reuse

These already exist (from `components/core/*.blade.php` and `components/cards/*.blade.php`):
- `slide-header.blade.php` → `<x-bloks.slide-header :title :icon :lang />`
- `slide-container.blade.php` → `<x-bloks.slide-container />`
- `slide-footer-nav.blade.php` → `<x-bloks.slide-footer-nav :current :total :lang />`
- `stat-card.blade.php` → `<x-bloks.stat-card :icon :label :value :trend />`
- `glass-card.blade.php` → `<x-bloks.glass-card>{{ $slot }}</x-bloks.glass-card>`
- `progress-card.blade.php` → `<x-bloks.progress-card :label :pct :milestone />`
- `project-card.blade.php` → `<x-bloks.project-card :project :lang />`

---

## 13. Artisan Commands

```bash
# Seed all bloks from source files into the platform bloks table
php artisan webblok:seed-bloks

# Create a new tenant + SQLite database
php artisan webblok:create-tenant {name} {domain} {owner-email}

# Run tenant migrations on all tenants (e.g. after schema update)
php artisan webblok:migrate-all-tenants

# Generate a preview render for all bloks (caches HTML previews)
php artisan webblok:cache-previews {--lang=en} {--theme=dark}

# Convert all slides/slide-*.html to Blade component stubs
php artisan webblok:convert-slides

# Export tenant data to JSON
php artisan webblok:export-tenant {tenant-uuid}

# Purge expired blok cache entries across all tenant SQLite DBs
php artisan webblok:purge-cache
```

---

## 14. Blok Seeder

```php
<?php
namespace Database\Seeders;

use App\Models\Blok;
use Illuminate\Database\Seeder;

class BlokSeeder extends Seeder
{
    // Generated from 2030b-config.json chapter/volume structure
    private array $chapters = [
        1  => ['title' => 'Dashboard & Identity',      'slides' => [1,10]],
        2  => ['title' => 'Cognitive Enhancement',     'slides' => [11,20]],
        3  => ['title' => 'CTC Economy',               'slides' => [21,30]],
        4  => ['title' => 'Governance & DAO',          'slides' => [31,40]],
        5  => ['title' => 'Platform Features',         'slides' => [41,50]],
        6  => ['title' => 'Projects & DVM',            'slides' => [51,70]],
        7  => ['title' => 'Community Ecosystem',       'slides' => [71,100]],
        8  => ['title' => 'Enterprise & NAE',          'slides' => [101,115]],
        9  => ['title' => 'Spatial & Blockchain',      'slides' => [116,130]],
        10 => ['title' => 'CGDP & Vision',             'slides' => [131,145]],
        11 => ['title' => 'Cosmic Mission',            'slides' => [146,150]],
    ];

    public function run(): void
    {
        for ($i = 1; $i <= 150; $i++) {
            $padded = str_pad($i, 3, '0', STR_PAD_LEFT);
            $chapter = $this->chapterForSlide($i);
            $volume  = $i <= 50 ? 1 : ($i <= 100 ? 2 : 3);

            Blok::updateOrCreate(['blok_id' => "slide-{$padded}"], [
                'version'         => '1.0.0',
                'category'        => 'dashboard',
                'chapter'         => $chapter,
                'volume'          => $volume,
                'title_en'        => $this->titleForSlide($i, 'en'),
                'title_ar'        => $this->titleForSlide($i, 'ar'),
                'title_fr'        => $this->titleForSlide($i, 'fr'),
                'langs'           => json_encode(['en','ar','fr']),
                'has_skeleton'    => true,
                'has_interactive' => $this->isInteractive($i),
                'cdn_ready'       => true,
                'component_class' => "App\\View\\Components\\Bloks\\Slide{$padded}",
                'source_file'     => "slides/slide-{$padded}.html",
                'is_published'    => true,
            ]);
        }

        // Non-slide component bloks
        $components = [
            'stat-card'       => 'StatCard',
            'glass-card'      => 'GlassCard',
            'progress-card'   => 'ProgressCard',
            'project-card'    => 'ProjectCard',
            'skeleton-default'=> 'SkeletonLayout',
            'app-shell'       => 'AppShell',
            'grid-2col'       => 'GridContainer',
            'grid-3col'       => 'GridSystem',
        ];

        foreach ($components as $id => $class) {
            Blok::updateOrCreate(['blok_id' => $id], [
                'version'         => '1.0.0',
                'category'        => 'component',
                'langs'           => json_encode(['en','ar','fr','es','de','tr','ur']),
                'has_skeleton'    => str_contains($id, 'skeleton'),
                'component_class' => "App\\View\\Components\\Bloks\\{$class}",
                'is_published'    => true,
            ]);
        }
    }
}
```

---

## 15. CDN Widget JS (`widget.js`)

```javascript
/* WebBlok CDN Widget v1.0.0 */
(function (w, d) {
  'use strict';

  const scripts = d.querySelectorAll('script[data-blok]');

  scripts.forEach(function (script) {
    const blokId  = script.dataset.blok;
    const lang    = script.dataset.lang    || 'en';
    const theme   = script.dataset.theme   || 'dark';
    const apiKey  = script.dataset.key     || '';
    const mountId = 'wb-' + blokId;
    const mount   = d.getElementById(mountId);

    if (!mount || !apiKey) return;

    // 1. Inject skeleton immediately
    mount.innerHTML = _skeleton(lang);

    // 2. Fetch rendered HTML
    const url = new URL('https://api.webblok.io/api/v1/bloks/' + blokId + '/render');
    url.searchParams.set('lang',     lang);
    url.searchParams.set('theme',    theme);
    url.searchParams.set('skeleton', 'false'); // skeleton already shown
    url.searchParams.set('wrap',     'false');

    fetch(url.toString(), {
      headers: { 'Authorization': 'Bearer ' + apiKey }
    })
    .then(function (r) { return r.text(); })
    .then(function (html) {
      mount.innerHTML = html;
      // Init animations
      mount.querySelectorAll('[data-progress]').forEach(function (el) {
        el.style.width = el.dataset.progress + '%';
      });
      mount.querySelectorAll('.fade-in').forEach(function (el, i) {
        el.style.opacity = '0';
        el.style.transform = 'translateY(16px)';
        setTimeout(function () {
          el.style.transition = 'opacity .4s ease, transform .4s ease';
          el.style.opacity = '1';
          el.style.transform = 'none';
        }, i * 80);
      });
      // Track render event
      _track(blokId, lang, 'widget', apiKey);
    })
    .catch(function (e) {
      mount.innerHTML = '<div style="color:#fc8181;padding:1rem">WebBlok: failed to load ' + blokId + '</div>';
    });
  });

  function _skeleton(lang) {
    const dir = ['ar','ur'].includes(lang) ? 'rtl' : 'ltr';
    return '<div class="wb-skeleton" dir="' + dir + '" style="padding:16px;display:flex;flex-direction:column;gap:12px">'
      + '<div class="wb-sk" style="height:24px;width:60%;border-radius:8px;background:rgba(255,255,255,.08);animation:wb-pulse 1.5s infinite"></div>'
      + '<div class="wb-sk" style="height:80px;border-radius:12px;background:rgba(255,255,255,.06);animation:wb-pulse 1.5s infinite .1s"></div>'
      + '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px">'
      + '<div class="wb-sk" style="height:60px;border-radius:10px;background:rgba(255,255,255,.06);animation:wb-pulse 1.5s infinite .2s"></div>'.repeat(3)
      + '</div></div>'
      + '<style>@keyframes wb-pulse{0%,100%{opacity:.6}50%{opacity:1}}</style>';
  }

  function _track(blokId, lang, type, key) {
    navigator.sendBeacon && navigator.sendBeacon(
      'https://api.webblok.io/api/v1/track',
      JSON.stringify({ blok_id: blokId, lang: lang, type: type, key: key })
    );
  }
})(window, document);
```

---

## 16. Phase-by-Phase Implementation Plan

### Phase 1 — Foundation (Week 1–2)
- [ ] Laravel 12 + Breeze install (`blade` stack)
- [ ] Configure dual database: MySQL (platform) + SQLite driver (tenant)
- [ ] Platform migrations: `users`, `tenants`, `api_keys`, `bloks`, `usage_logs`
- [ ] Tenant migrations: `blok_grants`, `tenant_config`, `blok_cache`, `render_events`, `content_overrides`
- [ ] `TenantManager` service (create/delete SQLite DBs, run migrations)
- [ ] `SetTenantDatabase` + `AuthenticateApiKey` middleware
- [ ] `BlokSeeder` — seed all 150 slide bloks + component bloks from config
- [ ] Artisan command: `webblok:create-tenant`

### Phase 2 — Blok Engine (Week 3–4)
- [ ] `BaseBlok` abstract Blade component class
- [ ] `BlokDataResolver` service (JSON loader + tenant override merge)
- [ ] `BlokRenderer` service (HTML render + JSON render + cache)
- [ ] Convert first 10 slides (1–10) to full Blade components
- [ ] Convert core shared components: `slide-header`, `slide-container`, `slide-footer-nav`, `stat-card`, `glass-card`, `skeleton-layout`
- [ ] Unit tests for resolver + renderer

### Phase 3 — API Layer (Week 5–6)
- [ ] `BlokController` — all 6 endpoints
- [ ] `UsageController` — usage stats
- [ ] `ApiKeyController` — key CRUD
- [ ] `ThrottleByTenant` middleware (per-plan rate limits)
- [ ] API response format standardization
- [ ] Feature tests for all API endpoints (authenticated + unauthenticated)
- [ ] API documentation view (`dashboard/docs.blade.php`)

### Phase 4 — Dashboard UI (Week 7–8)
- [ ] Breeze layout customization (WebBlok branding)
- [ ] Dashboard home: usage summary, quick start
- [ ] Blok manager: grid + filters + live preview + embed code
- [ ] API keys page: list + create + revoke
- [ ] Usage charts (Alpine.js + Chart.js CDN)
- [ ] Settings page: branding, domain management
- [ ] Super-admin tenant list + blok registry

### Phase 5 — Remaining Bloks (Week 9–12)
- [ ] Convert slides 11–50 to Blade components (Volume 1 complete)
- [ ] Convert slides 51–100 to Blade components (Volume 2 complete)
- [ ] Convert slides 101–150 to Blade components (Volume 3 complete)
- [ ] Convert all `components/` HTML directories to Blade sub-components
- [ ] Artisan command: `webblok:convert-slides` (auto-stub generator)

### Phase 6 — CDN Widget & Polish (Week 13–14)
- [ ] `widget.js` CDN bundle (skeleton injection + fetch + animate)
- [ ] `CdnBundler` service (per-tenant bundle with branding)
- [ ] CORS policy for CDN widget requests
- [ ] Cache warm-up command: `webblok:cache-previews`
- [ ] Rate limit headers (`X-RateLimit-Limit`, `X-RateLimit-Remaining`)
- [ ] Full end-to-end test: embed on external site → render → analytics
- [ ] README, API docs site

---

## 17. Key Design Decisions & Notes

### 17.1 Why SQLite per Tenant?
- **Physical isolation**: tenant data never bleeds across DBs
- **Zero-config provisioning**: `touch storage/tenants/{uuid}/database.sqlite` — no DB server needed
- **WAL mode**: handles concurrent reads from multiple API requests
- **Portability**: entire tenant can be exported as a single file
- **Cost**: no per-DB server overhead for small tenants

### 17.2 Slide → Blade Conversion Philosophy
- **Keep the HTML structure identical** to the source slides — no redesign
- **Replace `${var}` interpolation** with `{{ $data['var'] }}`
- **Replace `isAr ? '...' : '...'`** with `$lang === 'ar' ? '...' : '...'`
- **Convert inline `renderSlide()` JS output** to pure Blade server-side rendering
- **Skeleton markup** stays in Blade, revealed by lightweight `DOMContentLoaded` JS
- **All CSS** from `css/styles.css` can be either inlined (via `inline_css=true`) or loaded via CDN link

### 17.3 API Response Type Selection
| Consumer | Best Type | Reason |
|----------|-----------|--------|
| React/Vue/Next.js SPA | JSON | Handles rendering client-side |
| Static HTML site | HTML render | Drop in directly |
| Any CMS / WordPress | CDN widget | No server-side knowledge needed |
| Mobile app | JSON | Own rendering |
| Server-side PHP/Rails | HTML render | Curl + inject |

### 17.4 Language & RTL Handling
- `dir` attribute is derived from `lang` code in `BaseBlok` constructor
- Arabic (`ar`) and Urdu (`ur`) → `dir="rtl"`, font `Cairo`
- All others → `dir="ltr"`, font `Inter`
- JSON data files per language live in `slides/data/slide-NNN.{lang}.json`
- Missing language → falls back to English

### 17.5 Blok Versioning
- Each blok has a `version` field (`semver`)
- API consumers can pin to a version: `GET /bloks/slide-01?version=1.0.0`
- Breaking changes increment major version
- Render cache keys include version: `slide-01:ar:dark:1.0.0`

---

## 18. Environment Variables

```env
# Platform DB (MySQL/PostgreSQL)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=webblok_platform
DB_USERNAME=webblok
DB_PASSWORD=secret

# Tenant SQLite storage root
TENANT_DB_PATH=storage/tenants

# API
API_VERSION=v1
API_RATE_LIMIT_FREE=100          # req/min
API_RATE_LIMIT_STARTER=500
API_RATE_LIMIT_PRO=2000
API_RATE_LIMIT_ENTERPRISE=10000

# CDN
CDN_BASE_URL=https://cdn.webblok.io
WIDGET_JS_VERSION=1.0.0

# Cache
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1

# App
APP_NAME=WebBlok
APP_URL=https://app.webblok.io
WEBBLOK_SUPER_ADMIN_EMAIL=admin@webblok.io
```

---

## 19. Testing Strategy

```
tests/
  Unit/
    Services/
      BlokDataResolverTest.php      ← JSON load, override merge, fallback
      BlokRendererTest.php          ← HTML render, cache hit/miss
      TenantManagerTest.php         ← SQLite create/delete/migrate
      ApiKeyServiceTest.php         ← Key generation, hash, validate
    Models/
      TenantTest.php
      ApiKeyTest.php
  Feature/
    Api/
      BlokIndexTest.php             ← GET /api/v1/bloks (auth, pagination, filter)
      BlokShowTest.php              ← GET /api/v1/bloks/{id}
      BlokDataTest.php              ← GET /api/v1/bloks/{id}/data + overrides
      BlokRenderTest.php            ← GET /api/v1/bloks/{id}/render (html+json)
      UsageTest.php                 ← GET /api/v1/usage
      ApiKeyApiTest.php             ← POST/DELETE /api/v1/keys
    Auth/
      RegistrationTest.php          ← Breeze registration → tenant auto-create
      ApiKeyAuthTest.php            ← Invalid key, expired, revoked, suspended tenant
    Dashboard/
      DashboardTest.php
      BlokManagerTest.php
  Browser/                          ← Laravel Dusk (optional)
    EmbedWidgetTest.php
```

---

## 20. GitHub Repository Structure (for source material)

The owner will link a GitHub repository containing all the source files. The repository URL, branch, and access details will be provided separately. The project structure inside that repo matches section 1.1 exactly. When building WebBlok:

1. **Clone the source repo** into a `source/` subdirectory of the Laravel project (add to `.gitignore`)
2. **Copy `slides/data/*.json`** into `storage/app/blok-data/`
3. **Copy `css/styles.css`** into `public/vendor/webblok/styles.css`
4. **Copy `js/*.js`** into `public/vendor/webblok/js/`
5. **Reference component HTML** from `source/components/` during Blade conversion
6. **Run `php artisan webblok:seed-bloks`** to populate the bloks table
7. **Run `php artisan webblok:convert-slides`** to generate component stubs

---

## 21. Quick-Start Commands

```bash
# Install Laravel 12 with Breeze (Blade stack)
composer create-project laravel/laravel webblok
cd webblok
composer require laravel/breeze
php artisan breeze:install blade
npm install && npm run build

# Configure .env (MySQL platform DB + SQLite tenants)
cp .env.example .env
php artisan key:generate

# Run platform migrations
php artisan migrate

# Seed bloks registry
php artisan db:seed --class=BlokSeeder

# Create first demo tenant
php artisan webblok:create-tenant "Demo Site" "demo.localhost" "owner@demo.com"

# Start development server
php artisan serve
```

---

## 22. Summary Table

| Concern | Solution |
|---------|---------|
| Multi-tenancy | SQLite per tenant, `TenantManager`, `SetTenantDatabase` middleware |
| Isolation | Physical DB files in `storage/tenants/{uuid}/database.sqlite` |
| Authentication | Laravel Sanctum API tokens (`wbk_live_xxx`), hashed in DB |
| Bloks | 150 slide Blade components + shared sub-components, all from source HTML |
| Languages | 7 langs (AR/EN/FR/ES/DE/TR/UR), RTL for AR+UR, per-blok JSON data files |
| API responses | JSON (data), HTML (rendered), CDN widget (JS self-mount) |
| Skeleton loading | Blade-side markup, JS reveal on `DOMContentLoaded` |
| Caching | Per-tenant blok render cache in tenant SQLite + Redis for hot path |
| Usage tracking | `usage_logs` (platform) + `render_events` (tenant SQLite) |
| Rate limiting | Per-tenant, per-plan, Laravel `ThrottleRequests` variant |
| Admin | Super-admin web panel + Artisan commands |
| Testing | Pest: unit (services/models) + feature (all API endpoints) |
| CDN Widget | `widget.js` — skeleton → fetch → mount → animate |
| Source material | GitHub repo (index.html, a1–a5-index.html, slides/, components/) |
