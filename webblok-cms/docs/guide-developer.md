# Developer / API Consumer Guide

This guide is for engineers integrating with WebBlok: pulling blok data into an external
app, managing content over REST, embedding live bloks into any web page, exporting static
sites, and provisioning tenants programmatically.

WebBlok exposes **two API surfaces**:

- **V1 — Embed / headless** — lightweight, read-first, ideal for static sites and widgets.
- **V2 — REST CMS** — full content management (pages + bloks) for app integrations.

Plus a **partner provisioning** endpoint for spinning up new tenants.

---

## 1. Authentication

### Tenant API keys (V1 & V2)

Every V1/V2 request authenticates with a **tenant API key** sent as a header:

```
Authorization: Bearer <YOUR_API_KEY>
```

API keys are **scoped**. The middleware enforces the scope a route requires:

| Scope | Grants |
|-------|--------|
| `bloks:read` | Read blok data (V1 `GET /v1/bloks/{key}`) |
| `sites:write` | Generate sites (V1 `POST /v1/sites/generate`) |
| `cms:write` | Full V2 CMS read/write |
| `*` | All scopes (wildcard) |

A key with insufficient scope gets **HTTP 403**.

### Provisioning secret (tenant creation)

Creating tenants does **not** use a tenant key — it uses a platform-level header:

```
X-Provision-Secret: <PROVISION_SECRET>
```

(configured as `webblok.provision_secret`). This endpoint is partner-only.

---

## 2. Tenant resolution

WebBlok is multi-tenant (one SQLite DB per tenant). The platform resolves which tenant
a request targets, in this order:

1. **Custom domain** (e.g. `acme.com`)
2. **Subdomain** (e.g. `acme.webblok.app`)
3. **`?tenant=slug` fallback** (e.g. `?tenant=acme`)

Your API key is already bound to a tenant, so in practice you call the tenant's own
host (or append `?tenant=slug` in development).

---

## 3. Rate limits & lifecycle responses

Plan limits are enforced by the `plan.limits` middleware on every V1/V2 call.

| Situation | Response |
|-----------|----------|
| Over the plan's requests-per-minute | **HTTP 429** Too Many Requests |
| Tenant is **suspended** | **HTTP 403** |
| Tenant is **deleted** | **HTTP 410** Gone |
| Bad/expired API key | **HTTP 401** |
| Insufficient scope | **HTTP 403** |

Requests-per-minute by plan:

| Plan | RPM | Price/mo |
|------|-----|----------|
| Free | 60 | $0 |
| Starter | 120 | $12 |
| Pro | 600 | $39 |
| Business | 3000 | $99 |

The provisioning endpoint additionally has a fixed `throttle:10,1` (10 req/min).

---

## 4. V1 — Embed / headless API

Base prefix: `/api/v1` · Auth: `Authorization: Bearer <key>` · Requires `plan.limits`.

### 4.1 Get a rendered blok

```
GET /api/v1/bloks/{blokKey}
```
Scope: `bloks:read`. Returns the rendered HTML/data for a blok so you can drop it into
any page. Used by the embed widget and the static-export live-data bridge.

```bash
curl -H "Authorization: Bearer $KEY" \
  "https://acme.webblok.app/api/v1/bloks/hero"
```

### 4.2 Generate a site

```
POST /api/v1/sites/generate
```
Scope: `bloks:read` **and** `sites:write`. Kicks off headless site generation
(AI-assisted page/blok creation).

```bash
curl -X POST -H "Authorization: Bearer $KEY" \
  -H "Content-Type: application/json" \
  -d '{"prompt":"a landing page for a coffee subscription"}' \
  "https://acme.webblok.app/api/v1/sites/generate"
```

---

## 5. V2 — REST CMS API

Base prefix: `/api/v2` · Auth: `Authorization: Bearer <key>` · Scope: `cms:write` ·
Requires `plan.limits`.

### Pages

| Method & path | Purpose |
|---------------|---------|
| `GET /api/v2/pages` | List pages |
| `GET /api/v2/pages/{slug}` | Get one page by slug |
| `POST /api/v2/pages` | Create a page |
| `POST /api/v2/pages/{id}/publish` | Publish a page (draft → live) |

### Bloks on a page

| Method & path | Purpose |
|---------------|---------|
| `GET /api/v2/pages/{pageId}/bloks` | List a page's bloks |
| `POST /api/v2/pages/{pageId}/bloks` | Add a blok instance to a page |
| `POST /api/v2/blok-instances/reorder` | Reorder blok instances |
| `GET /api/v2/pages/{pageId}/render` | Render the full page (server-side HTML) |

### Example — create a page, add a hero, publish

```bash
# 1) Create a page
PAGE=$(curl -s -X POST -H "Authorization: Bearer $KEY" \
  -H "Content-Type: application/json" \
  -d '{"title":"Home","slug":"home","is_homepage":true}' \
  "$BASE/api/v2/pages")

PAGE_ID=$(echo "$PAGE" | jq -r '.data.id')

# 2) Add a hero blok
curl -s -X POST -H "Authorization: Bearer $KEY" \
  -H "Content-Type: application/json" \
  -d '{"blok_key":"hero","section":"main","config":{"title":"Welcome"}}' \
  "$BASE/api/v2/pages/$PAGE_ID/bloks"

# 3) Publish
curl -s -X POST -H "Authorization: Bearer $KEY" \
  "$BASE/api/v2/pages/$PAGE_ID/publish"
```

> **Blok keys** use underscores in storage (e.g. `stat_row`) and map to views with
> hyphens (`bloks.content.stat-row`). When posting `blok_key`, use the key as defined
> by the blok definition (e.g. `stat_row`).

---

## 6. Embedding live bloks in any web page

WebBlok ships two browser scripts (served from `/js`):

### 6.1 Widget (`/js/widget.js`)

Drop-in embed for third-party sites. Add a placeholder and the script:

```html
<div data-webblok-widget
     data-base="https://acme.webblok.app"
     data-key="YOUR_PUBLIC_KEY"
     data-blok="hero"></div>

<script src="https://acme.webblok.app/js/widget.js"></script>
```
`window.WebBlokWidget` finds every `[data-webblok-widget]`, calls the V1 API, and
injects the returned HTML.

### 6.2 API client / hydration (`/js/api-client.js`)

For exported static sites that need *live* data. Configure once, then hydrate:

```html
<script>
  window.WEBBLOK = { base: "https://acme.webblok.app", apiKey: "YOUR_PUBLIC_KEY" };
</script>
<div data-wb-blok="hero" data-wb-live></div>
<script src="https://acme.webblok.app/js/api-client.js"></script>
<script>WebBlokClient.hydrate();</script>
```
`window.WebBlokClient` exposes `fetchBlok(key)` and `hydrate()`. `hydrate()` finds every
`[data-wb-blok][data-wb-live]` and replaces it with fresh data from
`/api/v1/bloks/{key}`.

---

## 7. Static export

Owners can export a site as a self-contained static ZIP (see the Tenant Owner guide).
For developers, the relevant points:

- The export is **plain HTML/CSS/JS** — host it anywhere (S3, Netlify, GitHub Pages…).
- Bloks that need **live** data include the `api-client.js` bridge (§6.2). Set
  `window.WEBBLOK` on the host page (or it's baked into the export) so hydration can
  reach the V1 API.
- Static-only bloks render fully at export time — no API calls needed at runtime.

---

## 8. Provisioning tenants programmatically

```
POST /api/v1/tenants
Header:  X-Provision-Secret: <PROVISION_SECRET>
Rate:    10 requests / minute
```

Body fields:

| Field | Rules |
|-------|-------|
| `name` | required |
| `subdomain` | required, **unique**, matches subdomain regex |
| `owner_email` | required, email |
| `owner_name` | required |
| `plan` | required (plan name, e.g. `free`) |
| `domain` | optional custom domain |

Behavior:

1. `firstOrCreate` the owner `User` with role `tenant_owner`.
2. Create the `Tenant` in status `PROVISIONING`.
3. Run `TenantMigrator::provision()` — creates `storage/tenants/{slug}/database.sqlite`
   (WAL), runs tenant migrations, seeds defaults, flips status to `ACTIVE`.
4. Returns **HTTP 201** with the tenant payload.

```bash
curl -X POST \
  -H "X-Provision-Secret: $PROVISION_SECRET" \
  -H "Content-Type: application/json" \
  -d '{
        "name":"Acme Inc",
        "subdomain":"acme",
        "owner_email":"owner@acme.com",
        "owner_name":"Ada Owner",
        "plan":"free"
      }' \
  "https://platform.webblok.app/api/v1/tenants"
```

A wrong/missing `X-Provision-Secret` → **HTTP 403**.

---

## 9. Cron without SSH (`/cron.php`)

No-SSH deployments trigger scheduled work via an HTTP-callable entrypoint:

```
GET /cron.php?secret=<CRON_SECRET>
```

It boots Laravel and runs `CronRunner::run()` (dormant-tenant checks, maintenance,
etc.). The `?secret=` must equal `webblok.cron_secret`. CLI invocation
(`php public/cron.php`) skips the secret check. Point your host's scheduler (or an
external cron service) at this URL once per minute.

---

## 10. Conventions & gotchas

- **Strict typing everywhere** — the codebase is PHPStan **Level 10**, all classes
  `final` with `declare(strict_types=1)`. If you extend it, match that bar (CI enforces
  it, no exceptions).
- **No Livewire / no Filament** — the admin and builder are plain Blade + JS.
- **Blok key casing** — storage uses `snake_case` keys; views use `kebab-case`
  (`stat_row` → `bloks.content.stat-row`).
- **Tenant isolation** — never assume a shared DB; each tenant is its own SQLite file.
- **Scopes are least-privilege** — request the narrowest scope your integration needs.

---

## Quick reference

| Task | Call |
|------|------|
| Read a blok | `GET /api/v1/bloks/{key}` (`bloks:read`) |
| Generate a site | `POST /api/v1/sites/generate` (`sites:write`) |
| List pages | `GET /api/v2/pages` (`cms:write`) |
| Create page | `POST /api/v2/pages` |
| Add blok | `POST /api/v2/pages/{pageId}/bloks` |
| Publish page | `POST /api/v2/pages/{id}/publish` |
| Render page | `GET /api/v2/pages/{pageId}/render` |
| Embed widget | `/js/widget.js` + `[data-webblok-widget]` |
| Hydrate static | `/js/api-client.js` + `WebBlokClient.hydrate()` |
| Provision tenant | `POST /api/v1/tenants` (`X-Provision-Secret`) |
| Trigger cron | `GET /cron.php?secret=...` |

← Back to [Documentation index](./README.md)
