# WebBlok CMS — Documentation

WebBlok is a **multi-tenant SaaS website builder & CMS**. Every customer (a *tenant*)
gets an isolated website they assemble by **dragging blocks** ("bloks") onto a canvas,
configuring them through a Form Wizard, editing inline, then publishing or exporting a
static site. It runs on Laravel 13 + Blade + Alpine.js + SortableJS — **no Livewire,
no Filament** — and is enforced at **PHPStan Level 10** in CI.

---

## Who is this for? (pick your guide)

This project serves several distinct audiences. Each has a dedicated, step-by-step guide:

| You are… | What you do | Your guide |
|----------|-------------|------------|
| **Super Admin** | Operate the whole platform — tenants, plans, marketplace, community review | [`guide-super-admin.md`](./guide-super-admin.md) |
| **Tenant Owner / Site Builder** | Build & publish your own website with the drag-drop builder | [`guide-tenant-owner.md`](./guide-tenant-owner.md) |
| **Editor** | Edit content & pages on a site someone else set up | [`guide-editor.md`](./guide-editor.md) |
| **Developer / API consumer** | Integrate via the V1 embed API, V2 REST API, embeds & static export | [`guide-developer.md`](./guide-developer.md) |
| **Community Builder** | Author & publish bloks/templates/themes to the marketplace | [`guide-community-builder.md`](./guide-community-builder.md) |
| **Public Visitor** | Browse a published WebBlok site | [`guide-visitor.md`](./guide-visitor.md) |

> **Testing as every audience?** Read them in the order above — that is also the natural
> "operator → builder → contributor → consumer → visitor" flow.

**Engineers / maintainers:** see [`ARCHITECTURE.md`](./ARCHITECTURE.md) for the technical
design — multi-tenancy, the blok system, auth, marketplace, community sandbox, APIs,
no-SSH ops, and the CI quality gates.

---

## 60-second concept map

```
PLATFORM (one database: platform.sqlite)
 ├── Plans (Free, Starter, Pro, Business)
 ├── Users (super_admin, tenant_owner, editor, viewer)
 ├── Tenants ──────────────┐  each tenant has its OWN database:
 ├── Marketplace items      │  storage/tenants/{slug}/database.sqlite
 └── Community artifacts     │   ├── Pages (+ locales, SEO)
                            └──▶ ├── Blok instances (nested tree)
                                 ├── Menus, Themes, Templates
                                 └── Media, Forms, Analytics
```

- **Blok** = a reusable building block (Hero, Heading, Gallery, Columns, Contact Form…).
- **Blok instance** = one blok placed on a page, with its own config + per-locale config.
- **Section** = where a blok lives on the page: `header`, `body`, `sidebar`, `footer`.
- **Site Bundle** = the portable JSON format for templates, AI output, import/export.

---

## Running it locally

```bash
cd webblok-cms
composer install
cp .env.example .env          # then edit as needed (see below)
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve             # http://127.0.0.1:8000
```

Seeding creates **4 plans** and **1 super-admin**.

### Default super-admin credentials

| Setting | Env var | Default |
|---------|---------|---------|
| Email | `WEBBLOK_SUPERADMIN_EMAIL` | `admin@webblok.test` |
| Password | `WEBBLOK_SUPERADMIN_PASSWORD` | `password` |

> ⚠️ Change these before any non-local deployment.

### Important env vars

| Var | Purpose | Default |
|-----|---------|---------|
| `WEBBLOK_NAME` | Brand name shown in the UI | `WebBlok CMS` |
| `WEBBLOK_SUPERADMIN_EMAIL` / `_PASSWORD` | Super-admin login | see above |
| `WEBBLOK_PROVISION_SECRET` | Auth for `POST /v1/tenants` | `change-me` |
| `WEBBLOK_CRON_SECRET` | Auth for `/cron/run` & `public/cron.php` | `change-me` |
| `AUTH_DRIVER` (`config/auth_driver.php`) | `breeze` (offline) or `gas` (external) | `breeze` |

### Local multi-tenant tip

Tenants resolve by **custom domain → subdomain → `?tenant={slug}`**. On localhost,
use the query fallback, e.g. `http://127.0.0.1:8000/site?tenant=acme`.

---

## Quality gates (what "done" means here)

All three must be green — they run in CI and locally:

```bash
vendor/bin/pint --test      # code style
vendor/bin/phpstan analyse  # static analysis, LEVEL 10 (no exceptions)
vendor/bin/pest             # 42 tests, 60 assertions
```

Current status: **Pint clean · PHPStan Level 10 [OK] No errors · 42 passed**.

---

## Where things live

| Path | What |
|------|------|
| `blok_definitions/*.schema.json` | Declarative blok catalogue (seeded into `blok_definitions`) |
| `resources/views/bloks/{category}/*.blade.php` | Blok render templates |
| `marketplace/official/themes/*` | Official themes (`manifest.json` + `theme.css`) |
| `marketplace/official/templates/*` | Official templates (`manifest.json` + `template.json`) |
| `public/js/builder*.js`, `form-wizard.js` | Builder front-end |
| `public/js/api-client.js`, `widget.js` | Static-export bridge & embed widget |
| `public/cron.php`, `/cron/run` | No-SSH scheduler entrypoints |
| `app/Services/Community/*` | Contribution sandbox & safe renderer |
| `docs/*` | These guides |

---

For the full product specification see `WEBBLOK-CMS-SAAS-PROMPT-V2.md` in the repo root.
