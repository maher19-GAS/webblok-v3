# Tenant Owner / Site Builder Guide

You own a website on WebBlok. This guide takes you from logging in to a **published,
exportable, multi-page, multi-language site** using the drag-and-drop builder.

---

## 1. Get an account & sign in

- **Self-serve (Breeze driver):** go to `/register`, create your account, then `/login`.
- **External SSO (GAS driver):** click **Login** — you'll be redirected to the identity
  provider and back. (Registration is handled there.)

After login you land on the **Dashboard** (`/cms`).

---

## 2. The control panel at a glance

Top navigation:

| Link | What it's for |
|------|---------------|
| **Dashboard** | Your tenants/sites overview |
| **Pages** | Create, organize and publish pages |
| **Export** | Download your site as a static ZIP |
| **Studio** | Contribute bloks/templates to the community (optional) |

---

## 3. Pages — the skeleton of your site (`/cms/pages`)

A website is a tree of pages. From the Pages panel you can:

- **Create a page** — give it a title; a URL slug is generated.
- **Set parent/child** — nesting updates the page's `full_path` (e.g. `/services/design`).
- **Mark a homepage** — the page served at `/`.
- **Publish** — flip a page from draft to live.
- **Delete** — remove a page.

> Each page also supports per-locale SEO and (on higher plans) scheduled publishing — a page
> with a future `scheduled_at` is published automatically by the cron task.

**Recommended first steps:** create a `Home` page → mark it homepage → add an `About` and a
`Contact` page.

---

## 4. The Builder — assemble a page (`/cms/pages/{id}/build`)

Open a page and click **Build**. The builder is a three-pane app:

```
┌───────────┬───────────────────────────┬───────────────┐
│  PALETTE  │   CANVAS (live preview)   │   INSPECTOR   │
│  (bloks)  │  header / body /          │  Form Wizard  │
│  search & │  sidebar / footer zones   │  for selected │
│  drag ──▶ │  drop, sort, nest         │  blok         │
└───────────┴───────────────────────────┴───────────────┘
```

### Core actions

| Do this | How |
|---------|-----|
| **Add a blok** | Drag from the **Palette** into a canvas section/slot |
| **Reorder** | Drag a blok up/down within or across sections |
| **Nest** | Drop into a slot of a container blok (Section, Columns) — respects max children & the depth-10 limit |
| **Select** | Click a blok → its settings open in the Inspector |
| **Edit inline** | Click directly on text/rich-text on the canvas and type |
| **Configure** | Use the Inspector's Form Wizard (steps: Content, Style, Layout…) |
| **Hide/show** | Toggle visibility per blok |
| **Delete** | Remove a blok (and its children) |
| **Device preview** | Switch desktop / tablet / mobile widths |
| **Locale switch** | Edit translations for the active language |
| **Save** | Writes a revision (enables restore/undo) |
| **Autosave** | Happens automatically a moment after you stop editing |

### The blocks you can use

**Content:** Hero, Heading, Rich Text, Image, Gallery, Button, Stat Row, Feature Grid,
CTA Banner, Contact Form, HTML Embed.
**Layout (containers):** Section, Columns, Spacer.

Installed **community** and **marketplace** bloks appear in the same palette.

### The Form Wizard (19 field types)

Each blok's settings are grouped into steps. Field types you'll meet include: text,
textarea, rich text, number, toggle, select, multiselect, radio, checkbox-group, **color**
(maps to a theme CSS variable), **image**/**gallery** pickers, **icon**, **link** (URL +
target), date, **range** slider, **repeater** (add/remove rows — e.g. stats or features),
restricted code, and hidden. Fields can show/hide conditionally (e.g. "Columns" only appears
when Layout = Grid).

---

## 5. Make it look right — themes

Your active theme provides the colors, fonts and spacing via CSS variables
(`--wb-primary`, `--wb-bg`, …). Install a theme from the marketplace; **color** fields in
the Form Wizard write directly to these variables, so a single change restyles consistently.
Seeded options: **Aurora** (light) and **Midnight** (dark). Right-to-left languages
(Arabic/Urdu) render correctly.

---

## 6. Start faster with templates

Templates are ready-made page/site presets (a Site Bundle). Install one from the
marketplace and apply it to a new page to get a full layout instantly:

- **Startup Landing** — hero → features → stats → CTA
- **Portfolio Basic** — heading → gallery → contact form

You can also generate a starting point with **AI** (`POST /v1/sites/generate`) — it returns
a Site Bundle you can import (offline-safe stub driver by default).

---

## 7. Multi-language

Add locales to your site (e.g. `en`, `ar`). In the builder, switch the active locale and
edit text — translations are stored per-locale while the structure stays shared. RTL
locales flip layout automatically.

---

## 8. Go live & share

1. In the builder or Pages panel, click **Publish** on each finished page.
2. Your site is served at your tenant address:
   - custom domain (if configured), or
   - `https://{subdomain}.yourplatform`, or
   - locally: `/site?tenant={your-slug}`.
3. A `sitemap.xml` is generated automatically at `/site/sitemap.xml`.

---

## 9. Export a static copy (`/cms/export`)

Need a portable site (CDN, archive, hand-off)? Create an export — WebBlok builds HTML for
every page and locale, adds `sitemap.xml`, injects the `api-client.js` bridge (so live data
can still refresh), and zips it. Download the ZIP when its status reads **complete**.

---

## 10. Know your limits (plan)

| Plan | Pages | Bloks | API req/min |
|------|-------|-------|-------------|
| Free | 5 | 50 | 60 |
| Starter | 25 | 250 | 120 |
| Pro | 100 | 2,000 | 600 |
| Business | 1,000 | 20,000 | 3,000 |

Hitting a limit? Ask your platform admin to move you to a higher plan.

---

## 11. Bring in help — invite editors

Add team members with the **editor** role to update content without touching structure or
settings. See the [Editor guide](./guide-editor.md). Role ladder:
`super_admin > tenant_owner > editor > viewer`.

---

## Troubleshooting

| Symptom | Likely cause / fix |
|---------|--------------------|
| "Can't add more pages/bloks" | Plan limit reached → upgrade |
| Site shows 403 to visitors | Tenant suspended → contact admin |
| Blok shows a config preview box | That blok has no view yet (custom/community) — pick another |
| Changes not visible publicly | Page still draft → **Publish** |
| Scheduled page didn't publish | Ensure the platform cron is running |
