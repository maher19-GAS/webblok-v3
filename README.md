# 2030B – Be Smarter 🧠 · WebBook Platform

> **Full-Stack SaaS Book Publishing Platform** · Interactive reader + REST API backend · 16-language support · 11 themes · IndexedDB sync · Annotation system · Multi-tenant SQLite · Webhooks · Admin panel

---

## 🏗️ WebBook SaaS — Backend API v2.0 ⭐

**[`webbook-saas/`](webbook-saas/)** — Complete PHP/SQLite REST API backend for the multi-tenant WebBook SaaS platform. All endpoints are documented and testable via the included API test suite.

### Architecture Overview

```
Client (webbook-v8.html ← latest)  OR  webbook-v7.html
    ↕ REST API (JSON)
webbook-saas/index.php  ← Router
    ├── /auth/*         ← Auth, sessions, verify, reset, stats
    ├── /books/*        ← Books CRUD, chapters, sync, analytics
    ├── /upload/*       ← Media uploads
    ├── /webhooks/*     ← Webhook management
    ├── /admin/*        ← Admin panel
    └── /public/*       ← Public endpoints (no auth)
         ↕ SQLite (WAL)
    data/main.sqlite           ← Users, sessions, books registry
    data/books/{bookId}.sqlite ← Per-book: chapters, reader data, analytics
```

### API Endpoints Summary

| Group | Endpoints |
|-------|-----------|
| **Auth** | `POST /auth/register` · `POST /auth/login` · `POST /auth/logout` · `GET/PUT /auth/me` |
| **Verify** | `POST /auth/verify/send` · `GET /auth/verify/{token}` |
| **Reset** | `POST /auth/reset/request` · `POST /auth/reset/{token}` |
| **Stats** | `GET /auth/me/stats[/books\|/readers\|/activity]` |
| **Sessions** | `GET /auth/sessions` · `DELETE /auth/sessions/{id}` |
| **Books** | `GET/POST /books` · `GET/PUT/DELETE /books/{id}` · `GET /books/{id}/stats` · `GET /books/{id}/analytics` |
| **Chapters** | `GET/POST /books/{id}/chapters` · `GET/PUT/PATCH/DELETE /books/{id}/chapters/{ch}` · `GET revisions` · `POST reorder` |
| **Sync** | `POST/GET /books/{id}/sync` · `PUT /sync/kv` · `POST /sync/anns` · `POST /sync/replies` · `POST /sync/activity` · `POST /sync/scrollpos` · `GET /sync/mdcache` |
| **Upload** | `POST /upload/cover` · `POST /upload/avatar` · `GET /upload/list` · `DELETE /upload/{file}` |
| **Webhooks** | `GET/POST /webhooks` · `GET/PATCH/DELETE /webhooks/{id}` · `POST /webhooks/{id}/test` · `GET /webhooks/{id}/logs` |
| **Admin** | `GET /admin/stats` · `GET/PATCH/DELETE /admin/users/{id}` · `GET/DELETE /admin/books/{id}` · `POST /admin/users/{id}/impersonate` |
| **Public** | `GET /public/{bookId}/info` · `GET /public/{bookId}/chapters` · `GET /public/{bookId}/mdcache` |
| **Health** | `GET /` |

### Key Features

- ✅ **Multi-tenant** — Each book has its own SQLite database
- ✅ **Roles** — `reader` / `author` / `admin` with plan-based limits
- ✅ **Plans** — `free` (3 books) / `pro` (50 books) / `enterprise` (999 books)
- ✅ **IndexedDB Sync** — Push/pull reader data (KV, annotations, scroll positions, activity)
- ✅ **Analytics** — Daily `book_analytics` + `reader_stats` aggregation
- ✅ **Webhooks** — Signed delivery, auto-disable on 10 failures, delivery log
- ✅ **Email** — PHP `mail()` or SMTP (PHPMailer) for verify/reset flows
- ✅ **Rate Limiting** — File-based, 120 req/min per IP
- ✅ **JWT-style tokens** — HMAC-SHA256 signed, 30-day TTL
- ✅ **CORS** — Configurable allowed origins
- ✅ **WAL SQLite** — Concurrent reads without blocking

### Installation

📖 See **[`webbook-saas/INSTALL.md`](webbook-saas/INSTALL.md)** for the complete server installation and configuration guide, covering:
- Apache & Nginx configuration with full VirtualHost/server block examples
- PHP-FPM setup and `php.ini` recommendations
- Environment variables, secrets generation
- SSL/TLS (Let's Encrypt / Certbot)
- File permissions, CORS, email, uploads, rate limiting
- Backup & maintenance cron jobs
- Troubleshooting guide
- v1→v2 upgrade steps

### Quick API Test

Open **[`webbook-api-test.html`](webbook-api-test.html)** — an interactive dark-themed test console with:
- ⚡ **Auto Test Runner** — 20 automated end-to-end tests
- 🔧 **Config Panel** — Set API base URL, token, book/chapter IDs
- 📋 **Full endpoint coverage** — Auth, books, chapters, sync, uploads, webhooks, admin

---

## 🖥️ WebBook Management UI — Dashboard & Editor Suite ⭐ NEW

Seven fully-featured management pages (dark-night theme, RTL Arabic, responsive) built as static HTML/CSS/JS frontends that communicate with the WebBook SaaS API.

### Pages Overview

| File | Role | Key Features |
|------|------|-------------|
| [`webbook-dashboard.html`](webbook-dashboard.html) | **Main Hub** | Unified navigation, stats cards, activity feed, iframe book preview, quick actions |
| [`webbook-create.html`](webbook-create.html) | **Book Creator** | 4-step wizard: info → chapters → appearance → review & publish |
| [`webbook-edit.html`](webbook-edit.html) | **Chapter Editor** | EasyMDE markdown editor, live split preview, slide preview panel, comments, revisions |
| [`webbook-fork.html`](webbook-fork.html) | **Book Fork** | Search & clone any public book — full / selective / template / theme-only modes |
| [`webbook-author-dashboard.html`](webbook-author-dashboard.html) | **Author Dashboard** | Books grid, reader table, charts, comments/replies, share panel, notes, plan usage |
| [`webbook-user-dashboard.html`](webbook-user-dashboard.html) | **Reader Dashboard** | Reading progress, streak tracker, annotations, activity timeline, notifications, discover |
| [`webbook-admin-dashboard.html`](webbook-admin-dashboard.html) | **Admin Panel** | Platform stats, user/book management, impersonation, webhooks, system logs, health check |

### Design System

All pages share a consistent design language:

```
Theme:       Dark Night (#0d1117 background · GitHub-inspired)
Typography:  Cairo (Arabic UI) · JetBrains Mono (code/editor)
Icons:       Font Awesome 6.5
Charts:      Chart.js 4.4 (line, bar, doughnut)
Editor:      EasyMDE 2.18 + Marked.js (markdown parsing)
Styling:     Tailwind CSS (utility classes) + Custom CSS variables
Direction:   RTL Arabic (switchable via CFG.lang)
```

### Skeleton Loading

All dashboards implement full skeleton screens while data loads:
- `webbook-dashboard.html` — 3-column book skeleton grid
- `webbook-author-dashboard.html` — table row skeletons + chart placeholders
- `webbook-admin-dashboard.html` — paginated table skeletons
- `webbook-user-dashboard.html` — reading card and annotation skeletons

### iframe Integration

WebBook v6 can be embedded in any PHP page using the generated `<iframe>` code:

```html
<!-- Generated by webbook-create.html Step 4 -->
<iframe
  src="https://2030b.com/webbook/{bookId}?embed=1&theme=dark-night&lang=ar"
  style="width:100%;height:700px;border:none;border-radius:16px"
  allow="fullscreen"
  title="WebBook Reader">
</iframe>
```

The PHP backend dynamically injects the `CFG` JavaScript object:

```javascript
const CFG = {
  title: 'عنوان الكتاب',
  files: [
    { path: 'book/prologue-toc.md', part: 'المقدمة', id: 'prologue' },
    { path: 'book/chapter-01.md',   part: 'الجزء الأول', id: 'ch01' },
    // ...more chapters
  ],
  mob:  { min: 120, max: 170 },
  desk: { min: 450, max: 550 },
  wpm:  200,
  dbName:    'wb6_v6',
  dbVersion: 1
};
```

### Key Features by Page

#### `webbook-create.html` — Book Creator
- **Step 1**: Title, description, cover upload (+ AI cover generator), slug auto-generation, category, 12-language selector with RTL/LTR detection
- **Step 2**: Drag-sortable chapter list, import Markdown / GitHub / URL, add/remove chapters
- **Step 3**: 10-theme visual picker, reading speed settings, mobile/desktop slide sizing, IndexedDB config, visibility (public/unlisted/private)
- **Step 4**: Review panel with generated `CFG` object + `<iframe>` embed code (copyable), publish to API

#### `webbook-edit.html` — Chapter Editor
- **EasyMDE** full Markdown editor with custom dark theme (CodeMirror)
- **4 view modes**: Write only · Split (editor + live preview) · Preview only · Slides
- **Slide toolbar**: Insert pre-made templates — heading, quote, image, video, list, code, divider, custom background, animation
- **Slide preview panel**: Real-time slide breakdown with type badges (heading/content/image/quote)
- **Right meta panel**: Chapter settings (id, path, part, order, published toggle), comments thread with replies, revision history (up to 50)
- **Auto-save**: every 3 seconds of inactivity, with status bar showing cursor position, word count, slide count, read time, revision number
- **Keyboard shortcuts**: `Ctrl+S` save, `Ctrl+P` publish

#### `webbook-fork.html` — Book Fork
- Search any public book or browse own books
- **4 fork modes**: Full clone · Selective chapters (checkboxes) · Structure only · Theme/settings only
- Fork progress overlay with step-by-step animation (5 stages)
- New book settings: title, slug, description, visibility, theme
- Attribution toggle for crediting original author

#### `webbook-author-dashboard.html` — Author Dashboard
- **8 stat cards**: books, readers, slide views, read minutes, comments, shares, bookmarks, avg rating
- **Chart.js charts**: 30-day reader+session area chart · Doughnut book distribution
- **Books grid**: filterable by status (all/published/draft), with per-book edit/share/stats actions
- **Readers table**: real-time reader list with progress bars, session counts, last-seen
- **Comments panel**: threaded comments with reply/like/note-to actions, inline reply input
- **Notes system**: author writing notes / to-do for book development
- **Share panel**: 6 social platforms (WhatsApp, Telegram, X, Facebook, LinkedIn, Email) + copy link
- **Plan usage**: visual bars for books/chapters/storage quotas
- **Bookmarks**: recent reader bookmark activity

#### `webbook-user-dashboard.html` — Reader Dashboard
- **Profile hero**: avatar, bio, reading streak calendar (30-day grid), reading badges
- **Reading in progress**: 3 active books with progress bars, continue buttons, bookmark
- **Weekly chart**: bar chart of daily reading minutes
- **Annotations panel**: highlighted text with user notes, share/note-to/delete actions
- **Activity timeline**: chronological reading events
- **Notifications**: unread badges, new chapter alerts, streak achievements
- **Discover books**: recommendation grid with start reading buttons
- **Notes-to-author**: form to send typed notes directly to the book's author
- **IndexedDB sync**: reads from `wb6_v6` database to restore last reading position

#### `webbook-admin-dashboard.html` — Admin Panel
- **5 platform-wide stats**: total users, authors, books, slide views, active errors
- **6 tabs**: Overview · Users · Books · Webhooks · Logs · Health
- **Users table**: search, filter by role/plan, paginated; actions: view, edit, impersonate (1hr token), delete
- **Books table**: filter by status/language; actions: view, edit, delete
- **Webhooks**: list active webhooks with delivery counts, fail status, test/logs/delete
- **System logs**: color-coded levels (error/warn/info/success), search, export, clear
- **Health check**: SQLite, filesystem, email SMTP, rate-limit, SSL cert, API response time
- **Server resources**: CPU, RAM, disk, DB size, response time, uptime bars
- **Backup panel**: download main.sqlite, books/, uploads/ individually
- **Emergency modal**: maintenance mode, rate-reset, cache-clear, block-all
- **Impersonate modal**: safe user impersonation with confirmation dialog

### API Integration Pattern

All pages use the same fetch pattern against the SaaS API:

```javascript
const CFG_API = {
  base:  'https://2030b.com/webbook/api',
  token: localStorage.getItem('wb_token') || ''
};

// Example: Load author stats
const r = await fetch(`${CFG_API.base}/auth/me/stats`, {
  headers: { Authorization: `Bearer ${CFG_API.token}` }
});
const data = await r.json();
```

Pages gracefully fall back to demo/skeleton data when the API is offline.

### Data Flow: IndexedDB ↔ SQLite Sync

```
Reader Browser (IndexedDB wb6_v6)
    ├── kv store        → POST /books/{id}/sync/kv
    ├── anns store      → POST /books/{id}/sync/anns
    ├── replies store   → POST /books/{id}/sync/replies
    ├── activity store  → POST /books/{id}/sync/activity
    ├── scrollpos store → POST /books/{id}/sync/scrollpos
    └── mdcache store   → GET  /books/{id}/sync/mdcache

Per-book SQLite (data/books/{bookId}.sqlite)
    ├── reader_kv, reader_anns, reader_replies
    ├── reader_activity, reader_scrollpos, reader_mdcache
    ├── book_analytics  (daily unique_readers, sessions, slides)
    └── reader_stats    (per-reader aggregates)
```

---

## 🚀 WebBook v6 — Latest Version ⭐

**[`webbook-v6.html`](webbook-v6.html)** — Premium interactive book reader (v6.0, April 2026). Built on v5 with five major upgrades for the 2030B dev department's CRUD roadmap.

### ✅ v6 New Features

| Feature | Details |
|---------|---------|
| **📍 Swal Restore Dialog** | On every reload/reopen, a SweetAlert2 modal (with 12s timer + progress bar) asks the user to continue from their last slide. Shows slide title + slide number. "Continue" scrolls there instantly; "Start" stays at beginning. Deep-link (`?slide=`) takes priority. |
| **🌙 Full Dark/Light Mode Fix** | Every theme CSS file now ships complete `[data-mode="light"]` overrides: body, nav, aside panels, annotation cards, stat cards, tables, toast, skeleton, loading overlay, SweetAlert2, selection menu — all repainted correctly. Works across all 11 themes. |
| **⏳ Bottom Loader** | A 3px animated gradient bar fixed at screen bottom + a small label pill. Fires on theme load (`🎨 تحميل المظهر…`) and language load (`🌐 تحميل اللغة…`). Auto-hides when done. |
| **💾 Offline IndexedDB MD Cache** | New `mdcache` store in IndexedDB. On first visit each markdown file is fetched & stored. On every subsequent visit (including offline) files are read from IndexedDB — zero network requests for content. Legacy `cache` store is promoted to `mdcache` automatically. Console reports `X from IndexedDB cache`. |
| **🦴 Per-Slide Skeleton (0.5s)** | Every slide starts in `sk-loading` state — a shimmer overlay hides content. IntersectionObserver fires when slide enters viewport and removes the skeleton after 500ms, then plays a smooth `slideReveal` entrance animation. First 3 slides reveal instantly (already on screen). |

---

## 📖 WebBook v5

**[`webbook-v5.html`](webbook-v5.html)** — Full-featured interactive book reader (v5.0, April 2026). Integrates all v4 fixes plus a complete new suite of UI/UX features.

### ✅ v5 New Features

| Feature | Details |
|---------|---------|
| **🌐 16-Language i18n System** | `i18n/*.json` files for AR, EN, FR, ES, DE, TR, UR, FA, ZH, RU, PT, ID, HI, SW, MS, JA. Browser-language auto-detection + IndexedDB persistence. Language aside panel with live search. |
| **🎨 10 Themes + Light/Dark Mode** | `themes/*.css` CSS variable files: Dark Night, Deep Ocean, Emerald, Sunset, Royal Purple, Rose Gold, Slate, Monochrome, Desert, Neon + Light Clean. Auto-selected by time of day (night=dark, day=light). Theme aside panel. |
| **🌙 Dark/Light Toggle** | Moon/Sun icon button in top nav. Toggles mode without changing theme. IndexedDB persists choice. |
| **🎞️ Animated Backgrounds** | Cover/chapter slides get animated gradient + background image overlay. CTA/science slides get animated mesh. Images included in OG/Twitter meta tags for social sharing. |
| **📍 Restore Previous Position** | On page reload, shows a toast with "Continue from: [title]" button — restores exact slide index from IndexedDB. Works correctly with deep link detection. |
| **💬 SweetAlert2 Dialogs** | All `prompt()` and `alert()` replaced with themed Swal2 dialogs matching the active theme/mode. Applied to: note input, comment input, clear stats confirmation, upload notice. |
| **📤 Extended Share Panel** | Share aside now includes: WhatsApp, Telegram, Twitter/X, Facebook, LinkedIn, Email, Copy Link — for both full book and individual highlighted excerpts. |
| **📦 LocalStorage Size Display** | Stats panel shows current localStorage usage with a progress bar (vs 5MB limit) and a server upload button (alert for now). |
| **🔗 OG Image per Slide** | `og:image` and `twitter:image` meta tags updated dynamically — cover/chapter slides use background image URLs from the curated set. |
| **🛡️ All v4 Fixes Retained** | Overlapping highlight routing, mobile tooltip fix, tooltip-hide-on-action, scroll position timeline, URL SEO params. |

---

## 📖 Reader Versions

| File | Version | Status |
|------|---------|--------|
| [`webbook-v7.html`](webbook-v7.html) | v7.0 | ✅ **Latest** — SaaS API connected |
| [`webbook-v6.html`](webbook-v6.html) | v6.0 | Stable |
| [`webbook-v5.html`](webbook-v5.html) | v5.0 | Legacy |
| [`webbook-v4.html`](webbook-v4.html) | v4.0 | Legacy |
| [`webbook-v3.html`](webbook-v3.html) | v3.0 | Legacy |
| [`webbook-api-test.html`](webbook-api-test.html) | v2.0 | ✅ **API Test Suite** |

### Management UI Pages

| File | Role | Status |
|------|------|--------|
| [`webbook-dashboard.html`](webbook-dashboard.html) | Main Hub Dashboard | ✅ Complete |
| [`webbook-create.html`](webbook-create.html) | Book Creator (4-step wizard) | ✅ Complete |
| [`webbook-edit.html`](webbook-edit.html) | Chapter Editor (EasyMDE + live preview) | ✅ Complete |
| [`webbook-fork.html`](webbook-fork.html) | Book Fork / Clone | ✅ Complete |
| [`webbook-author-dashboard.html`](webbook-author-dashboard.html) | Author Analytics Dashboard | ✅ Complete |
| [`webbook-user-dashboard.html`](webbook-user-dashboard.html) | Reader Dashboard | ✅ Complete |
| [`webbook-admin-dashboard.html`](webbook-admin-dashboard.html) | Admin / System Panel | ✅ Complete |
| [`webbook-saas/`](webbook-saas/) | v2.0 | ✅ **PHP API Backend** |

---

## 🌐 Language Files (`i18n/`)

| Code | Language | Dir | Flag |
|------|----------|-----|------|
| `ar` | العربية | RTL | 🇸🇦 |
| `en` | English | LTR | 🇬🇧 |
| `fr` | Français | LTR | 🇫🇷 |
| `es` | Español | LTR | 🇪🇸 |
| `de` | Deutsch | LTR | 🇩🇪 |
| `tr` | Türkçe | LTR | 🇹🇷 |
| `ur` | اردو | RTL | 🇵🇰 |
| `fa` | فارسی | RTL | 🇮🇷 |
| `zh` | 中文 | LTR | 🇨🇳 |
| `ru` | Русский | LTR | 🇷🇺 |
| `pt` | Português | LTR | 🇧🇷 |
| `id` | Indonesia | LTR | 🇮🇩 |
| `hi` | हिन्दी | LTR | 🇮🇳 |
| `sw` | Kiswahili | LTR | 🇰🇪 |
| `ms` | Melayu | LTR | 🇲🇾 |
| `ja` | 日本語 | LTR | 🇯🇵 |

**Auto-detection order:** IndexedDB saved preference → browser `navigator.language` → fallback `ar`

---

## 🎨 Theme Files (`themes/`)

| ID | Name | Mode | Primary |
|----|------|------|---------|
| `dark-night` | Dark Night | Dark | `#1a4fff` Blue |
| `deep-ocean` | Deep Ocean | Dark | `#0097a7` Teal |
| `emerald` | Emerald | Dark | `#059669` Green |
| `sunset` | Sunset | Dark | `#e11d48` Red-Orange |
| `royal-purple` | Royal Purple | Dark | `#7c3aed` Purple |
| `rose-gold` | Rose Gold | Light | `#e11d48` Rose |
| `slate` | Slate | Dark | `#38bdf8` Sky |
| `monochrome` | Monochrome | Dark | `#ffffff` White |
| `desert` | Desert | Dark | `#d97706` Amber |
| `neon` | Neon | Dark | `#39ff14` Green |
| `light-clean` | Light Clean | Light | `#1a4fff` Blue |

**Auto-mode:** Hours 20:00–07:00 → Dark, 07:00–20:00 → Light

---

## 🗄️ Data Model

### v6 — IndexedDB `wb6_v6` v1

| Store | Key | Fields | Notes |
|-------|-----|--------|-------|
| `kv` | `k` | Generic key-value: lastSlide, sessions, lang, theme, themeMode, longestSession, totalMinutes | Persists all user preferences |
| `mdcache` | `id` | Markdown files: id, content, ts | **NEW v6** — offline support, survives tab close |
| `cache` | `id` | Legacy markdown cache (auto-promoted to mdcache) | Kept for backward compat |
| `anns` | `id` (auto) | type, selText, noteText, slideIdx, hlId, created, updated | Indexes: type, slideIndex, hlId |
| `replies` | `id` (auto) | annId, text, author, ts | Index: annId |
| `activity` | `id` (auto) | type, label, meta, ts (max 250 entries, FIFO) | Indexes: ts, type |
| `scrollpos` | `id` (auto) | slideIndex, slideTitle, startTs, endTs, durationMs (max 500, FIFO) | Indexes: slideIndex, startTs |

### v5 — IndexedDB `wb5_v5` v1 (legacy, separate DB)

---

## 🔗 URL Parameters

| Param | Example | Purpose |
|-------|---------|---------|
| `slide` | `?slide=42` | 0-based slide index (deep link + position restore) |
| `sid` | `&sid=s43` | Human-readable slide ID (SEO) |
| `title` | `&title=...` | Chapter title URL-encoded (Open Graph, max 60 chars) |
| `hl` | `&hl=hl-...` | Highlight ID for scrolling to annotation |

---

## 📁 Project Structure

```
webbook-v6.html          — Main interactive book (current ⭐)
webbook-v5.html          — Previous version
webbook-v4.html          — Legacy
webbook-v3.html          — Legacy
webbook-agent-api.html   — MD Structure API reference

i18n/
  ar.json   en.json   fr.json   es.json   de.json
  tr.json   ur.json   fa.json   zh.json   ru.json
  pt.json   id.json   hi.json   sw.json   ms.json   ja.json

themes/
  dark-night.css    deep-ocean.css   emerald.css
  sunset.css        royal-purple.css rose-gold.css
  slate.css         monochrome.css   desert.css
  neon.css          light-clean.css

book/
  ar/   (10 .md files — Arabic RTL — 2030B كن أذكى)
  en/   (10 .md files — English LTR — 2030B Be Smarter)
  prologue-toc.md          chapter-01.md   (legacy V6/V7)
  chapter-02.md            chapters-03-04.md
  chapters-05-06-07.md     chapters-08-12-epilog.md
```

---

## ✅ Completed Features (v2.0)

1. **REST API Backend** — Full PHP/SQLite SaaS API with 40+ endpoints
2. **Reader IndexedDB Sync** — Push/pull all stores to server (cross-device)
3. **Multi-tenant** — Per-book SQLite + per-user uploads + plan quotas
4. **Auth system** — JWT-style tokens, email verify, password reset
5. **Analytics** — Daily book analytics + per-reader stats
6. **Webhooks** — Signed delivery with delivery log + auto-disable
7. **Admin panel** — User/book management + impersonation
8. **API Test Suite** — 20 automated tests, interactive dark console

---

## 📚 WebBook V8 — Multi-Language Reader ⭐ NEW

**[`webbook-v8.html`](webbook-v8.html)** — Major upgrade from V7 with full multi-language book support, auto-detection of available language folders, and image prompt card rendering.

### V8 Key Upgrades over V7

| Feature | V7 | V8 |
|---------|----|----|
| Language system | i18n UI only (16 langs, single content) | **Per-language book content** (`BOOKS` object) |
| Content folders | `book/chapter-XX.md` (Arabic only) | `book/ar/` + `book/en/` + future langs |
| Language detection | Browser language → UI only | **Auto-detects available `book/{lang}/` folders** |
| Unavailable langs | Grayed out | **"Translate this book" notice + email CTA** |
| Image prompts | Not supported | **`[IMAGE: ...]` → Copyable AI prompt card** |
| Themes | 4 themes | **5 themes** (+ Cosmic) |
| IndexedDB name | `wb7_saas` | `wb8_2030b` |
| Language in URL | Not tracked | `?lang=ar` / `?lang=en` |
| Keyboard nav | RTL-only arrows | **Dir-aware** (flips for LTR/RTL) |
| Swipe nav | RTL-only | **Dir-aware touch swipe** |

### BOOKS Configuration Object

```javascript
const BOOKS = {
  ar: {
    title:     '2030B — كن أذكى',
    subtitle:  'رحلة كارداشيف نحو الإنسان الكامل',
    dir:       'rtl',
    font:      'Cairo',
    fontUrl:   'https://fonts.googleapis.com/css2?family=Cairo:...',
    wpm:       180,
    available: true,   // verified at runtime via fetch
    files: [
      {path:'book/ar/00_prologue.md', part:'المقدمة',    id:'ar-prologue'},
      // ... 10 files
    ]
  },
  en: {
    title:     '2030B — Be Smarter',
    dir:       'ltr',
    font:      'Inter',
    wpm:       230,
    available: true,
    files: [
      {path:'book/en/00_prologue.md', part:'Prologue', id:'en-prologue'},
      // ... 10 files
    ]
  },
  fr: { available: false, files: [] },  // → shows "Translate" notice
  // ...6 more languages
};
```

### Auto-Detection Flow

1. **At boot**, `detectAvailableLangs()` runs parallel GET requests to `book/{lang}/00_*.md` for all configured languages
2. Languages with `200` / `206` responses → `available: true`
3. Languages with missing files → `available: false` → shown grayed out with "غير متاح" badge
4. Clicking unavailable language → SweetAlert2 modal with "Request Translation" → `mailto:` link
5. Initial language: URL `?lang=` > stored `book_lang` in IndexedDB KV > browser language > `ar`

### Image Prompt Card Rendering

V8 detects the following syntaxes in Markdown content:

```markdown
[IMAGE: A futuristic city powered by solar energy, ultra-realistic, cinematic]

<!-- img-prompt: Kardashev Type 1 civilization, planetary energy grid -->

<!-- prompt:
A stellar civilization harvesting energy from a binary star system,
Hubble-style photography, deep space, vibrant nebula colors
-->

[IMG_PROMPT: Abstract human figure ascending a cosmic ladder, digital art]
```

All are parsed into `{type:'img_prompt', prompt:'...'}` blocks and rendered as:
- **Card with gradient border** matching current theme color
- **Copy button** — copies prompt text to clipboard (with fallback)
- **Midjourney link** — opens `midjourney.com`
- **DALL·E link** — opens ChatGPT DALL·E

Prompts are stored in `IMG_PROMPT_REGISTRY` (a JS object keyed by ID) to avoid unsafe inline string escaping in HTML attributes.

### Book Content Structure

The "2030B — Be Smarter / كن أذكى" book content:

```
book/
  ar/                          # Arabic (RTL) — 10 files, ~82KB total
    00_prologue.md             # المقدمة — قبل السلّم: السؤال الذي لم يُسأل
    01_chapter1.md             # سلّم كارداشيف والنقطة 0.7
    02_chapter2.md             # النوع الأول: الحضارة الكوكبية
    03_chapter3.md             # النوع الثاني: الحضارة النجمية
    04_chapter4.md             # النوع الثالث: الحضارة المجرّية
    05_chapter5.md             # النوع الرابع: الحضارة الكونية
    06_chapter6.md             # النوعان الخامس والسادس: العوالم المتعددة
    07_chapter7.md             # النوع السابع: محرّرو الواقع
    08_chapter8.md             # النوع الثامن: حضارة الإنسان الكامل (أصيل لـ 2030B)
    09_epilogue.md             # رسالة ماهر لمن وصل إلى هنا

  en/                          # English (LTR) — 10 files, ~60KB total
    00_prologue.md             # Before the Ladder: The Question No One Asked
    01_chapter1.md             # The Kardashev Ladder and the 0.7 Point
    02_chapter2.md             # Type 1: The Planetary Civilization
    03_chapter3.md             # Type 2: The Stellar Civilization
    04_chapter4.md             # Type 3: The Galactic Civilization
    05_chapter5.md             # Type 4: The Universal Civilization
    06_chapter6.md             # Types 5 & 6: Multiverse and Source Code of Reality
    07_chapter7.md             # Type 7: Editors of Reality
    08_chapter8.md             # Type 8: The Civilization of the Complete Human
    09_epilogue.md             # A Letter From Maher To Whoever Reached Here
```

### V8 URL Parameters

| Param | Example | Purpose |
|-------|---------|---------|
| `slide` | `?slide=12` | Jump to slide N |
| `lang` | `&lang=en` | Set language (ar/en/etc.) |
| `book` | `&book=abc123` | SaaS book ID (API key) |
| `key` | `&key=xyz` | SaaS API key |

### V8 Entry Point

```
webbook-v8.html                           — Default (auto-detects lang)
webbook-v8.html?lang=en                   — Force English
webbook-v8.html?lang=ar&slide=5           — Arabic, slide 5
webbook-v8.html?book=abc&key=xyz          — SaaS mode
```

---

## 🔮 Planned Next Steps

1. **PWA / Service Worker** — Cache all assets for full offline reading
2. **Audio narration** — Per-slide audio player with waveform (audiobook transformation)
3. **Progress certificates** — Shareable completion badge (PDF/image)
4. **Collaborator management** — API endpoints to add/remove book collaborators
5. **RTL themes** — Dedicated calligraphy-inspired themes for Arabic/Urdu/Farsi
6. **More languages** — Korean (ko), Dutch (nl), Italian (it), Polish (pl)
7. **Book → Video/Movie** — Transform slides into video scenes with AI narration (future)
8. **Book → Audiobook** — Per-chapter TTS/narration pipeline (future)
7. **Stripe/payment integration** — Plan upgrades (free → pro → enterprise)

---

## 🛠️ Tech Stack

- **Pure HTML/CSS/JS** — no build step, no framework
- **Tailwind CSS** (CDN) — utility classes
- **Font Awesome 6.5** (CDN) — icons
- **SweetAlert2 11** (CDN) — dialog system
- **Google Fonts** — Cairo (AR), Inter (EN/EU), language-specific fonts
- **IndexedDB** — all persistence (6 stores)
- **localStorage** — size monitoring only
- **IntersectionObserver** — slide visibility + animations
