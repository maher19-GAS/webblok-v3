# Community Builder Guide

Community Builders extend WebBlok for everyone by contributing **bloks, templates,
site bundles and themes** through the **Creator Studio**. Contributions are
**sandboxed, reviewed and published** into the marketplace where any tenant can install
them. This guide explains what you can build, the exact payload formats, the safety
rules your content must pass, and the lifecycle from draft to published.

---

## 1. Open the Creator Studio (`/cms/studio`)

From the top navigation, click **Studio**. You'll see:

- **My contributions** — every artifact you've created, with its current status.
- **New artifact** — the form to start a new contribution.

You contribute as your signed-in user; your authorship is recorded on each artifact.

---

## 2. Artifact types

| Type | What it is | Installs as |
|------|------------|-------------|
| `blok` | A reusable content block with its own schema + template | Marketplace template entry |
| `template` | A ready-made page (page + a list of bloks) | Marketplace template |
| `site` | A multi-page **Site Bundle** | Marketplace template |
| `theme` | A set of CSS design tokens | Marketplace theme |

Every artifact's **payload is JSON**. The Studio validates it the moment you submit.

---

## 3. Payload formats

### 3.1 Blok (`type: blok`)

Must contain `blok_key`, `label`, `schema`, and `template`:

```json
{
  "blok_key": "quote_card",
  "label": "Quote Card",
  "schema": {
    "steps": [
      {
        "label": "Content",
        "fields": [
          { "key": "quote", "type": "textarea", "label": "Quote" },
          { "key": "author", "type": "text", "label": "Author" }
        ]
      }
    ]
  },
  "template": "<blockquote>{{ field.quote }}<cite>{{ field.author }}</cite></blockquote>"
}
```

The `template` uses the **restricted community template language** (see §4) — *not* raw
Blade or PHP.

### 3.2 Template / Site bundle (`type: template` or `site`)

Must match the **Site Bundle schema** (validated via `SiteBundleData::from()`):

```json
{
  "page": { "title": "Landing", "slug": "landing" },
  "bloks": [
    { "blok_key": "hero",        "section": "main", "config": { "title": "Hello" } },
    { "blok_key": "feature_grid","section": "main", "config": { "columns": 3 } }
  ]
}
```

### 3.3 Theme (`type: theme`)

Must declare `css_variables`:

```json
{
  "name": "Sunrise",
  "css_variables": {
    "--wb-primary": "#ff7a18",
    "--wb-bg": "#fffaf3",
    "--wb-text": "#1a1a1a"
  }
}
```

---

## 4. The restricted community template language

Community blok templates run through `CommunityBlokRenderer`, **not** Blade. You get a
small, safe syntax:

| Syntax | Meaning |
|--------|---------|
| `{{ field.x }}` | Output the value of field `x` (escaped) |
| `{% if field.x %} … {% endif %}` | Conditional block |
| `{% for item in field.list %} … {% endfor %}` | Loop over a repeater/list |

The rendered output is scoped inside a wrapper (`<div class="wb-c-{instanceId}">`) so
your styles can't leak into the host page. There is **no** access to PHP, the database,
files, or arbitrary functions — only your fields.

---

## 5. The sandbox: what is forbidden

Before an artifact can enter the review queue, `ArtifactValidator` checks it. It
**rejects** any payload that:

- Is not valid JSON.
- Is missing the required fields for its type (see §3).
- Contains any of these forbidden patterns (case-insensitive) **anywhere** in the
  payload:

| Pattern | Why it's blocked |
|---------|------------------|
| `<?php` | No executable PHP |
| `<script` | No JavaScript injection |
| `on…=` (e.g. `onclick=`, `onload=`) | No inline event handlers |
| `javascript:` | No script URLs |
| `@import` | No external CSS imports |
| `<iframe` | No embedded frames |
| `eval(` | No dynamic code execution |
| `base64_decode(` | No obfuscated payloads |

If any check fails you get an `InvalidArtifactException` with a clear message and the
artifact **cannot** be submitted. Fix the payload and try again.

> **Design tip:** keep templates declarative. Use fields + the `{{ }}`/`{% %}` syntax
> for everything. If you find yourself wanting a `<script>`, that's a sign the feature
> belongs in core, not a community blok.

---

## 6. The publishing lifecycle (state machine)

Your artifact moves through a strict state machine. You drive the early steps; reviewers
(Super Admins) drive the rest.

```
draft → submitted → in_review → approved → published → delisted
          ↑   └──────── rejected ─────────┘
          └──────────── (resubmit) ───────┘
```

| State | Who sets it | Meaning |
|-------|-------------|---------|
| `draft` | You | Work in progress in the Studio |
| `submitted` | You (Submit) | Sent for review; validated by the sandbox |
| `in_review` | System / reviewer | A reviewer has picked it up |
| `approved` | Reviewer | Passed review, awaiting publish |
| `rejected` | Reviewer | Sent back with notes; you can fix and resubmit |
| `published` | Reviewer | Live in the marketplace, installable by tenants |
| `delisted` | Reviewer | Removed from the marketplace (can be re-published) |

Allowed transitions are enforced — e.g. you can't jump `draft → published`. When you
hit **Submit**, the artifact validates, becomes `submitted`, and auto-advances to
`in_review`.

---

## 7. Step-by-step: contribute a blok

1. **Studio → New artifact.** Choose type `blok`, give it a name.
2. **Paste your JSON payload** (§3.1). Use the restricted template language (§4).
3. **Save** — it's now a `draft`. Iterate as much as you like.
4. **Submit.** The sandbox validates it; if clean it becomes `submitted → in_review`.
5. **Wait for review.** A Super Admin approves or rejects (with notes).
6. **If rejected**, read the notes, edit your draft, and **resubmit**.
7. **Once approved and published**, your blok appears in the marketplace and mirrors
   into `marketplace_items` (with `source = community`). Tenants can now install it.

The same flow applies to templates, site bundles and themes — only the payload format
differs.

---

## 8. After publishing

- **Discoverability:** published artifacts show in the marketplace browse list.
- **Installs:** each install increments the artifact's install count.
- **Ratings & reports:** the community can rate your work; problematic content can be
  reported and may be **delisted** by a reviewer.
- **Updates:** publish a new/updated version through the same lifecycle.

---

## 9. Troubleshooting

| Message / symptom | Fix |
|-------------------|-----|
| "Artifact payload is not valid JSON." | Validate your JSON (commas, quotes, braces). |
| "Artifact contains forbidden content…" | Remove scripts, `<?php`, `on…=`, `javascript:`, `@import`, `<iframe`, `eval(`, `base64_decode(`. |
| "Blok is missing required field: …" | Add `blok_key`, `label`, `schema`, `template`. |
| "Bundle does not match the Site Bundle schema…" | Match the `{ page, bloks[] }` shape (§3.2). |
| "Theme must declare css_variables." | Add a `css_variables` object (§3.3). |
| "Unknown artifact type." | Use one of `blok`, `template`, `site`, `theme`. |
| Can't move to a state | Transitions are enforced; follow the lifecycle order (§6). |
| Rejected | Read reviewer notes, edit the draft, resubmit. |

---

## Quick reference

| I want to… | Where / how |
|------------|-------------|
| Start a contribution | **Studio → New artifact** |
| See my artifacts & statuses | **Studio → My contributions** |
| Validate before review | Just **Submit** — the sandbox checks instantly |
| Use dynamic content in a blok | `{{ field.x }}`, `{% if %}`, `{% for %}` |
| Fix a rejection | Edit draft → **Submit** again |
| Get it into the marketplace | Approved + published by a reviewer |

← Back to [Documentation index](./README.md)
