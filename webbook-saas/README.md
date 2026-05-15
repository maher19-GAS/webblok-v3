# WebBook SaaS — Backend API

> **API base URL:** `https://2030b.com/webbook/api`  
> **Project folder:** `webbook-saas/`  
> **PHP ≥ 8.1 + SQLite3 (PDO)**

---

## Architecture Overview

```
webbook-saas/
├── index.php           ← API router (entry point)
├── bootstrap.php       ← CORS, helpers, token utils, rate limiter, DB helpers
├── config.php          ← Constants (paths, secrets, limits)
├── .htaccess           ← Rewrite rules, security headers
│
├── lib/
│   ├── MainDB.php      ← Init main.sqlite (users + books registry)
│   └── BookDB.php      ← Init {bookId}.sqlite (per-book DB)
│
├── routes/
│   ├── auth.php        ← /auth/*
│   ├── books.php       ← /books/*
│   ├── chapters.php    ← /books/{id}/chapters/*
│   ├── sync.php        ← /books/{id}/sync/*  (IndexedDB mirror)
│   └── public.php      ← /public/*  (no auth)
│
├── schema/
│   ├── main.sql        ← DDL for main.sqlite (reference)
│   └── book.sql        ← DDL for per-book SQLite (reference)
│
└── data/               ← NEVER web-accessible (blocked by .htaccess)
    ├── main.sqlite     ← Users + books registry
    ├── books/          ← Per-book SQLite files  ({bookId}.sqlite)
    ├── rate/           ← Rate-limit counters
    └── error.log
```

### Database Design

| Database | File | Contents |
|---|---|---|
| **Main** | `data/main.sqlite` | `users`, `sessions`, `books` registry, `book_collaborators`, `platform_stats` |
| **Per-book** | `data/books/{bookId}.sqlite` | `chapters`, `chapter_revisions`, `reader_kv`, `reader_anns`, `reader_replies`, `reader_activity`, `reader_scrollpos`, `reader_mdcache`, `book_analytics`, `reader_stats` |

---

## Authentication

All protected endpoints require a **Bearer token** in the `Authorization` header:

```
Authorization: Bearer <token>
```

Tokens are HMAC-signed (sha256) with a 30-day TTL. They are created on `register` and `login`.

### Anonymous Reader Access

For public/unlisted books readers can omit the Bearer token by:

1. Passing the book's **`api_key`** in the `X-WB-Book` header  
2. Passing a client-generated UUID in `X-WB-Reader` header

---

## Rate Limiting

- **120 requests / 60 seconds** per IP  
- Returns `429 Too Many Requests` when exceeded

---

## Endpoints

### Health Check

```
GET /
```

```json
{
  "service": "WebBook SaaS API",
  "version": "1.0.0",
  "status": "ok",
  "endpoint": "https://2030b.com/webbook/api"
}
```

---

### Auth

#### `POST /auth/register`

Create a new author account.

**Body:**
```json
{
  "email": "author@example.com",
  "username": "myname",        // 3-32 chars, a-z 0-9 _ -
  "password": "••••••••",      // min 8 chars
  "display_name": "My Name"    // optional
}
```

**Response 201:**
```json
{ "token": "…", "user": { "id": "…", "email": "…", "username": "…", … } }
```

---

#### `POST /auth/login`

```json
{ "email": "author@example.com", "password": "••••••••" }
// or
{ "username": "myname", "password": "••••••••" }
```

**Response 200:** same shape as register.

---

#### `POST /auth/logout`

Revokes the current token. No body required.

---

#### `GET /auth/me`

Returns the authenticated user profile + `book_count`.

---

#### `PUT /auth/me`

Update profile. Updatable fields:

| Field | Notes |
|---|---|
| `display_name` | 1-80 chars |
| `bio` | max 500 chars |
| `website` | must be a valid URL |
| `avatar_url` | must be a valid URL |
| `current_password` + `new_password` | required pair to change password |

---

#### `GET /auth/sessions`

List all active sessions for the current user.

---

#### `DELETE /auth/sessions/{id}`

Revoke a specific session.

---

### Books (CRUD)

All `/books` routes require Bearer auth.

#### `GET /books`

List the authenticated author's books.

**Query params:**
| Param | Default | Notes |
|---|---|---|
| `page` | 1 | Pagination |
| `limit` | 20 | Max 100 |
| `status` | *(all)* | `draft` / `published` / `archived` |

**Response:**
```json
{
  "books": [ … ],
  "total": 42,
  "page": 1,
  "limit": 20,
  "pages": 3
}
```

---

#### `POST /books`

Create a new book. Initialises a fresh per-book SQLite DB.

**Body:**
```json
{
  "title": "كن أذكى",
  "slug": "be-smart",           // auto-generated from title if omitted
  "description": "…",
  "language": "ar",
  "direction": "rtl",
  "cover_url": "https://…",
  "theme": "dark-night",
  "visibility": "private"       // private | unlisted | public
}
```

**Response 201:** Full book object including `api_key` and `reader_url`.

---

#### `GET /books/{bookId}`

Get full book metadata (owner or collaborator only).

---

#### `PUT /books/{bookId}` / `PATCH /books/{bookId}`

Update book fields. Same fields as create. Setting `status: published` auto-stamps `published_at`.

---

#### `DELETE /books/{bookId}`

Soft-delete (sets `status=archived`). Pass `?hard=1` to permanently delete the SQLite file.

---

#### `GET /books/{bookId}/stats`

**Query:** `page`, `limit`

Returns per-reader statistics + aggregate:

```json
{
  "aggregate": {
    "unique_readers": 120,
    "total_sessions": 340,
    "total_slides_read": 8500,
    "total_minutes": 1200
  },
  "readers": [ … ],
  "total": 120
}
```

---

#### `GET /books/{bookId}/analytics`

Daily aggregated analytics.

**Query:** `days` (default 30, max 365)

```json
{
  "days": 30,
  "from": "2025-03-29",
  "data": [ { "date": "2025-04-01", "unique_readers": 5, … } ],
  "totals": { … }
}
```

---

### Chapters (Markdown Source)

All `/books/{bookId}/chapters` routes require Bearer auth + at least `viewer` role.  
Write operations require `editor` role.

#### `GET /books/{bookId}/chapters`

List all chapters (no content body).

---

#### `POST /books/{bookId}/chapters`

Create a new markdown chapter.

**Body:**
```json
{
  "file_id": "chapter-01",       // unique slug within the book
  "title": "الفصل الأول",
  "part": "الجزء الأول",
  "sort_order": 0,
  "content": "# الفصل الأول\n\nمحتوى الفصل…"
}
```

Automatically:
- Saves an initial revision
- Syncs `reader_mdcache` so readers get the content immediately
- Updates `total_words` on the book registry

**Response 201:** Full chapter object.

---

#### `GET /books/{bookId}/chapters/{chapterId}`

Returns the chapter including its `content` field.

---

#### `PUT /books/{bookId}/chapters/{chapterId}` / `PATCH`

Update chapter fields. Content change creates a new revision entry.

Additional body field: `commit_message` (optional, stored in revision).

---

#### `DELETE /books/{bookId}/chapters/{chapterId}`

Permanently deletes the chapter and all its revisions.

---

#### `GET /books/{bookId}/chapters/{chapterId}/revisions`

**Query:** `limit` (default 10, max 50), `include_content=1`

```json
{
  "revisions": [
    { "id": "…", "word_count": 350, "author_id": "…", "message": "…", "created_at": 1714000000000 }
  ]
}
```

---

#### `POST /books/{bookId}/chapters/reorder`

Bulk-update `sort_order` for multiple chapters atomically.

**Body:**
```json
{
  "order": [
    { "id": "chapter-uuid-1", "sort_order": 0 },
    { "id": "chapter-uuid-2", "sort_order": 1 }
  ]
}
```

---

### Reader IndexedDB Sync

The WebBook v6 client pushes its local IndexedDB data to these endpoints so:
1. Author sees real reader analytics
2. Reader recovers data on a new device
3. Offline markdown cache is always fresh

#### Auth for Sync

| Scenario | How to authenticate |
|---|---|
| Logged-in reader | `Authorization: Bearer <token>` |
| Anonymous reader of public book | `X-WB-Book: <api_key>` + `X-WB-Reader: <client-uuid>` |
| Anonymous reader of unlisted book | Same as above |

---

#### `POST /books/{bookId}/sync` — Full Push

Push all IndexedDB stores in one request.

**Body:**
```json
{
  "kv":        [ { "k": "lastSlide", "v": "42", "updated_at": 1714000000000 } ],
  "anns":      [ { "id": "ann-uuid", "type": "note", "sel_text": "…", … } ],
  "replies":   [ { "id": "rep-uuid", "ann_id": "ann-uuid", "text": "…", … } ],
  "activity":  [ { "id": "act-uuid", "type": "slide_view", "label": "…", "ts": … } ],
  "scrollpos": [ { "id": "sp-uuid", "slide_index": 5, "start_ts": …, "end_ts": …, "duration_ms": … } ]
}
```

**Response:**
```json
{ "ok": true, "results": { "kv": 3, "anns": 1, … }, "reader_id": "user-uuid" }
```

---

#### `GET /books/{bookId}/sync` — Full Pull

Returns all stored data for the reader.

```json
{
  "reader_id": "…",
  "kv":        [ { "k": "lastSlide", "v": "42", "updated_at": … } ],
  "anns":      [ … ],
  "replies":   [ … ],
  "activity":  [ … ],
  "scrollpos": [ … ]
}
```

---

#### `PUT /books/{bookId}/sync/kv`

Upsert key-value pairs only.

```json
{ "kv": [ { "k": "theme", "v": "\"dark-night\"" } ] }
```

---

#### `POST /books/{bookId}/sync/anns`

Upsert annotations. Conflict resolution: **newer `updated_at` wins**.

```json
{
  "anns": [
    {
      "id": "ann-uuid",
      "type": "note",           // note | bookmark | comment | share
      "sel_text": "selected…",
      "note_text": "my note",
      "slide_idx": 12,
      "hl_id": "hl-uuid",
      "created_at": 1714000000000,
      "updated_at": 1714000001000,
      "deleted": 0
    }
  ]
}
```

---

#### `POST /books/{bookId}/sync/replies`

Upsert annotation replies.

```json
{
  "replies": [
    { "id": "rep-uuid", "ann_id": "ann-uuid", "text": "reply text", "author": "أنا", "ts": … }
  ]
}
```

---

#### `POST /books/{bookId}/sync/activity`

Push activity log rows (server capped at 250/reader).

```json
{
  "activity": [
    { "id": "act-uuid", "type": "slide_view", "label": "Introduction", "meta": {}, "ts": … }
  ]
}
```

---

#### `POST /books/{bookId}/sync/scrollpos`

Push scroll position records (server capped at 500/reader).

```json
{
  "scrollpos": [
    { "id": "sp-uuid", "slide_index": 5, "slide_title": "Ch 1", "start_ts": …, "end_ts": …, "duration_ms": 4500 }
  ]
}
```

---

#### `GET /books/{bookId}/sync/mdcache`

Returns all markdown content for the book (author-authenticated).

```json
{
  "book_id": "…",
  "mdcache": [ { "id": "chapter-01", "content": "# Heading…", "ts": … } ],
  "total": 6
}
```

---

### Public Endpoints (No Auth)

For published books only.

#### `GET /public/{bookId}/info`

Returns safe public metadata (title, cover, theme, word count, etc.).

---

#### `GET /public/{bookId}/chapters`

Returns published chapter list (no content body).

---

#### `GET /public/{bookId}/mdcache`

Returns markdown content for all **published** chapters.  
Supports `?since={ts}` for delta updates and `ETag` / `304 Not Modified`.

```
GET /public/{bookId}/mdcache?since=1714000000000
Cache-Control: public, max-age=300
```

---

## WebBook v6 Client Integration

### JavaScript — Initial Load (replace fetch with API)

```javascript
// webbook-v6.html — replace direct MD fetch with server mdcache
async function loadMdFromServer(bookId) {
    const res  = await fetch(`https://2030b.com/webbook/api/public/${bookId}/mdcache`);
    const data = await res.json();
    // Store each entry into IndexedDB mdcache
    for (const entry of data.mdcache) {
        await idbPut('mdcache', { id: entry.id, content: entry.content, ts: entry.ts });
    }
}
```

### JavaScript — Push IndexedDB to Server

```javascript
async function syncToServer(bookId, token) {
    const [kv, anns, replies, activity, scrollpos] = await Promise.all([
        idbAll('kv'), idbAll('anns'), idbAll('replies'),
        idbAll('activity'), idbAll('scrollpos')
    ]);
    await fetch(`https://2030b.com/webbook/api/books/${bookId}/sync`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
        },
        body: JSON.stringify({ kv, anns, replies, activity, scrollpos }),
    });
}
```

### Anonymous Reader (public book)

```javascript
// Use book api_key + client UUID (stored in localStorage)
const readerId = localStorage.getItem('wb_reader_id') || crypto.randomUUID();
localStorage.setItem('wb_reader_id', readerId);

fetch(`https://2030b.com/webbook/api/books/${bookId}/sync`, {
    method: 'POST',
    headers: {
        'Content-Type':  'application/json',
        'X-WB-Book':     BOOK_API_KEY,
        'X-WB-Reader':   readerId,
    },
    body: JSON.stringify({ kv, anns, … }),
});
```

---

## Error Responses

All errors follow the same shape:

```json
{ "error": "Human-readable message", "code": 422 }
```

| Code | Meaning |
|---|---|
| 400 | Bad request |
| 401 | Unauthenticated |
| 403 | Forbidden (wrong owner / plan limit) |
| 404 | Resource not found |
| 409 | Conflict (duplicate email, slug, etc.) |
| 422 | Validation error |
| 429 | Rate limit exceeded |
| 500 | Internal server error |

---

## Deployment Checklist

1. Set `WB_TOKEN_SECRET` environment variable (never use the default in production)
2. Remove the `'*'` wildcard from `WB_ALLOWED_ORIGINS` in `config.php`
3. Ensure `data/` directory is **not** web-accessible (`.htaccess` blocks it)
4. Set proper file permissions: `data/` → `750`, `data/*.sqlite` → `640`
5. Configure PHP error logging to `data/error.log`
6. Point your web server's document root to `webbook-saas/`
7. Enable `mod_rewrite` (Apache) or configure Nginx rewrite

### Nginx config snippet

```nginx
location /webbook/api/ {
    try_files $uri $uri/ /webbook/api/index.php?$query_string;
}
location ~* /webbook/api/data/ {
    deny all;
}
```

---

## Implemented Features ✅

- [x] User registration / login / logout / profile update
- [x] HMAC-signed tokens with 30-day TTL + session revocation
- [x] File-based rate limiting (120 req/min per IP)
- [x] Books CRUD (create, list, get, update, soft/hard delete)
- [x] Per-book SQLite isolation
- [x] Book collaborators table (viewer / editor / co-author)
- [x] Chapter (Markdown) CRUD with revision history (last 50)
- [x] Chapter reorder (bulk `sort_order`)
- [x] Auto-sync `reader_mdcache` on chapter save
- [x] IndexedDB full push / pull
- [x] Individual store sync: kv, anns, replies, activity, scrollpos
- [x] Reader stats summary + daily book analytics
- [x] Public endpoints with ETag caching for mdcache
- [x] Anonymous reader support via `api_key` + `X-WB-Reader`
- [x] CORS with configurable allowed origins

## Planned Features 🔜

- [ ] Author statistics dashboard endpoint (`/auth/me/stats`)
- [ ] WebBook v6 HTML updated to push IndexedDB automatically
- [ ] Email verification flow
- [ ] Password-reset via token
- [ ] Media / cover image upload endpoint
- [ ] Plan enforcement (free: 3 books, pro: unlimited)
- [ ] Webhook for book publish events
- [ ] Admin endpoints (`/admin/*`)
