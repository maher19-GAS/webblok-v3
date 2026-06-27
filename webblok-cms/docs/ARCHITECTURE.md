# WebBlok — Architecture & Technical Design

This document is the engineer's map of the WebBlok CMS SaaS platform: how multi-tenancy,
the blok system, auth, the marketplace, the community sandbox, the APIs and the no-SSH
operations fit together, and the non-negotiable quality gates.

> Audience: maintainers and contributors. End-user behavior lives in the audience guides
> (see [README](./README.md)).

---

## 1. Stack & ground rules

| Area | Choice |
|------|--------|
| Framework | Laravel **13.x** (PHP **8.4**) |
| Tests | **Pest 4** (unit, feature, arch) |
| Style | **Pint** (laravel preset + strict types, ordered/unused imports, single quotes, trailing commas) |
| Static analysis | **PHPStan / Larastan Level 10** + `phpstan-strict-rules` — **CI-enforced, no exceptions** |
| Datastore | **SQLite per tenant** (WAL) + a platform registry DB |
| Deployment | **No-SSH** (HTTP cron entrypoint) |
| Explicitly **not** used | **Livewire**, **Filament** |

Coding conventions enforced across the codebase:

- Every class is `final` and starts with `declare(strict_types=1);`.
- Models use `protected $guarded = []` with `/** @var array<string> */`.
- `mixed` is always narrowed (`is_string`/`is_array`/`is_numeric`/`is_object`) before
  use; no useless casts when the type is already known from phpdoc.

---

## 2. Multi-tenancy model

Two layers of database:

1. **Platform registry** — the default connection (`database/platform.sqlite`). Holds
   tenants, users, plans, marketplace items, and the community tables.
2. **Tenant databases** — one SQLite file per tenant at
   `storage/tenants/{slug}/database.sqlite` (WAL mode). Holds that tenant's pages, blok
   instances, media, etc.

`TenantContext::switchTo($tenant)` re-points the `tenant` connection at the right file
for the duration of a request.

### Tenant resolution order (middleware)

1. **Custom domain** → 2. **Subdomain** → 3. **`?tenant=slug`** fallback.

### Tenant lifecycle (`TenantStatus` enum)

```
PROVISIONING → ACTIVE ⇄ DORMANT
                 │
                 ├→ SUSPENDED (→ ACTIVE on reactivate)
                 └→ DELETED
```

`TenantMigrator::provision(Tenant)` creates the directory + SQLite file, switches the
connection, runs tenant migrations, seeds defaults, and flips status to `ACTIVE`.

---

## 3. Request middleware

Aliases registered in `bootstrap/app.php`:

| Alias | Responsibility |
|-------|----------------|
| `tenant.db` | Resolve tenant & bind the `tenant` connection |
| `tenant.active` | Reject suspended (403) / deleted (410) tenants |
| `api.key` | Authenticate API key + enforce **scope** (`bloks:read`, `sites:write`, `cms:write`, `*`) |
| `plan.limits` | Enforce plan quotas / rate limits (429 on RPM overflow) |
| `track.view` | Record page views |
| `super.admin` | `EnsureSuperAdmin` — `abort(403)` unless `User::isSuperAdmin()` |

---

## 4. Authentication — dual driver

Auth is pluggable via `config/auth_driver.php`:

- **Breeze** (default, offline) — self-serve register/login, local credentials.
- **GAS** (external) — redirects to an external identity provider (SSO).

Switching drivers changes the login flow without touching feature code.

Roles include `super_admin`, `tenant_owner`, and editor-level users.
`User::isSuperAdmin()` returns `$this->role === 'super_admin'`.

---

## 5. The Blok system

A **blok** is a reusable content component defined by JSON + rendered by Blade.

### Definitions

- `blok_definitions/*.schema.json` are seeded into `blok_definitions`
  (`BlokDefinitionSeeder` globs the folder).
- A definition has `blok_key`, `label`, `category` (`content` | `layout`),
  `accepts_children`, `slots` (for containers), `default_config`, and
  `schema.steps[].fields[]`.

### Rendering

- `BlokRenderer::viewName()` → `"bloks.{$category}.".str_replace('_','-',$blokKey)`
  (so `stat_row` → `bloks.content.stat-row`). Missing views fall back to a config-preview
  wrapper.
- **Containers** (e.g. `section`, `columns`) set `accepts_children: true` + `slots`;
  child HTML is passed through `$config['__children_html']` (see `PageRenderer` /
  `BlokInstanceResolver::sectionedTree`).

### Form Wizard — 19 field types

`text, textarea, richtext, number, toggle, select, multiselect, radio, checkbox-group,
color (cssVar), image, gallery, icon, link, date, range, repeater, code, hidden`.

- **Conditional visibility:** `showIf: { field, equals }`.
- **Localization:** `localized: true` writes to `locale_config[locale]`.

---

## 6. Marketplace & themes

- Official content lives under `marketplace/official/{themes,templates}/*/manifest.json`.
- `MarketplaceSeeder` scans those dirs (slug = `Str::slug(name)`); `MarketplaceService`
  handles browse/install; `ThemeManager` reads `marketplace/official/themes/{slug}/theme.css`
  and exposes the active CSS (`:root { --wb-* }`, responsive + RTL rules).
- Templates carry `template.json` → `{ page: {...}, bloks: [{ blok_key, section, config }] }`.

Shipped officially: themes **Aurora** (light) and **Midnight** (dark); templates
**Startup Landing** and **Portfolio Basic**.

---

## 7. Community contribution system

Untrusted, user-submitted content — fully sandboxed.

### Data (platform DB)

`community_artifacts`, `community_ratings`, `community_reports`
(migration `0012_create_community_tables`). `CommunityArtifact` is `final`, uses
`HasUuids`, casts `status → ArtifactStatus`, `tags`/`locale_support → array`.

### State machine (`ArtifactStatus`)

```
draft → submitted → in_review → approved → published → delisted
          ↑            └────────── rejected ───────────┘
          └──────────────── (resubmit) ────────────────┘
```

Transitions are guarded by `allowedTransitions()` / `canTransitionTo()`.

### Sandbox (`ArtifactValidator`)

Rejects, before review: invalid JSON, missing per-type fields, and any of these
forbidden patterns — `<?php`, `<script`, `on…=`, `javascript:`, `@import`, `<iframe`,
`eval(`, `base64_decode(`. Per-type checks: **blok** needs `blok_key/label/schema/template`;
**template/site** must satisfy `SiteBundleData::from()`; **theme** needs `css_variables`.

### Restricted renderer (`CommunityBlokRenderer`)

A tiny, safe template language — `{{ field.x }}`, `{% if %}`, `{% for %}` — rendered as
loops → conditionals → interpolations, scoped in `<div class="wb-c-{instanceId}">`.

### Actions & flow

`SubmitArtifact` (validate → SUBMITTED → auto IN_REVIEW) · `ReviewArtifact`
(approve/reject with reviewer id + notes) · `PublishArtifact` (PUBLISHED + `published_at`,
mirrors into `marketplace_items` with `source = community`; bloks map to TEMPLATE type).

UI: **Creator Studio** (`/cms/studio`) for builders; **admin review queue**
(`/admin/community`) for Super Admins.

---

## 8. APIs

### V1 — Embed / headless (`/api/v1`, `api.key` + `plan.limits`)

- `GET /bloks/{blokKey}` — scope `bloks:read`.
- `POST /sites/generate` — scope `bloks:read` + `sites:write` (AI generation).
- `POST /tenants` — **outside** the api.key group; guarded by `X-Provision-Secret`,
  `throttle:10,1`; provisions a tenant end-to-end (201).

### V2 — REST CMS (`/api/v2`, `api.key:cms:write` + `plan.limits`)

`GET /pages`, `GET /pages/{slug}`, `POST /pages`, `POST /pages/{id}/publish`,
`GET /pages/{pageId}/bloks`, `POST /pages/{pageId}/bloks`,
`POST /blok-instances/reorder`, `GET /pages/{pageId}/render`.

### Lifecycle responses

401 (bad key) · 403 (scope/suspended) · 410 (deleted) · 429 (RPM exceeded).

### Browser bridges (served from `/js`)

- `widget.js` — `window.WebBlokWidget` mounts `[data-webblok-widget]` from the V1 API.
- `api-client.js` — `window.WebBlokClient.hydrate()` hydrates
  `[data-wb-blok][data-wb-live]` using `window.WEBBLOK = { base, apiKey }`.

---

## 9. Operations — no SSH

`public/cron.php` boots Laravel and forwards to `CronRunner::run()`. It's guarded by
`webblok.cron_secret` (via `?secret=` or argv); CLI invocation skips the secret. Point an
external scheduler at `/cron.php?secret=…` once per minute for dormant-tenant checks and
maintenance.

Static export produces a self-contained HTML/CSS/JS ZIP; live bloks ship with the
`api-client.js` bridge so exported sites can still pull fresh data.

---

## 10. Plans & limits

| Plan | Pages | Bloks | RPM | $/mo |
|------|------:|------:|----:|-----:|
| Free | 5 | 50 | 60 | 0 |
| Starter | 25 | 250 | 120 | 12 |
| Pro | 100 | 2000 | 600 | 39 |
| Business | 1000 | 20000 | 3000 | 99 |

`Plan` fields: `name, display_name, max_pages, max_bloks, max_locales, max_media_mb,
max_exports, max_api_rpm, price_monthly, price_yearly, features, is_active`.

---

## 11. Directory map

```
app/
  Actions/Community/        SubmitArtifact, ReviewArtifact, PublishArtifact
  Enums/                    ArtifactStatus, TenantStatus
  Exceptions/               InvalidArtifactException
  Http/
    Controllers/Admin/      Plan/Marketplace/Community/Tenant management
    Controllers/Api/V1/     BlokData, SiteGeneration, TenantProvision
    Controllers/Api/V2/     Page, BlokInstance
    Controllers/Cms/        CreatorStudio
    Middleware/             EnsureSuperAdmin (+ tenant/api/plan middleware)
  Models/Platform/          Tenant, User, Plan, MarketplaceItem, CommunityArtifact
  Services/                 BlokRenderer, PageRenderer, BlokInstanceResolver,
                            MarketplaceService, ThemeManager, TenantMigrator, CronRunner
  Services/Community/       ArtifactValidator, CommunityBlokRenderer
blok_definitions/           *.schema.json (14 bloks)
marketplace/official/       themes/{aurora,midnight}, templates/{startup-landing,portfolio-basic}
public/js/                  api-client.js, widget.js
public/                     cron.php
database/migrations/platform/  incl. 0012_create_community_tables
resources/views/bloks/      content/*, layout/*
resources/views/admin/      dashboard, tenants, plans, marketplace, community
resources/views/cms/        studio.blade.php
tests/                      Unit (Arch + units), Feature
docs/                       this file + audience guides
```

---

## 12. Quality gates (CI)

| Gate | Command | Required result |
|------|---------|-----------------|
| Static analysis | `vendor/bin/phpstan analyse` | **Level 10, 0 errors** |
| Style | `vendor/bin/pint --test` | clean (changed files) |
| Tests | `vendor/bin/pest` | all green (**42 tests / 60 assertions**) |

Arch tests (Pest, Section 27) additionally assert: all classes `final`, drivers implement
their contracts, **no Livewire / no Filament**, and no debug helpers (`dd`, `dump`, …).

---

← Back to [Documentation index](./README.md)
