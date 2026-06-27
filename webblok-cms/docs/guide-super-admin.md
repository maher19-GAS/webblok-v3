# Super Admin Guide

You operate the **whole platform**: customers (tenants), pricing plans, the marketplace,
and the community contribution review queue. You are the only role that can reach `/admin`.

---

## 1. Sign in

1. Go to `/login`.
2. Use your super-admin credentials (default `admin@webblok.test` / `password` — change these!).
3. After login you'll see an **Admin** link (amber) in the top navigation. Click it, or go to `/admin`.

> If a normal user tries to open `/admin` they get **403 Forbidden**. Guests are redirected to `/login`. This is enforced by the `super.admin` middleware.

---

## 2. The Admin dashboard (`/admin`)

The landing page gives you platform-wide vitals at a glance:

- **Tenants** — total customer sites
- **Users** — total accounts
- **Active plans** — plans currently sellable
- **Marketplace items** — themes + templates available to install

Below the counters is a **Recent tenants** table (name, owner, plan, status, created).

Quick links across the top take you to: **Tenants · Plans · Marketplace · Review Queue**.

---

## 3. Manage tenants (`/admin/tenants`)

A paginated table of every tenant with name, subdomain, owner, plan and status.

**Actions per tenant:**

| Action | Effect |
|--------|--------|
| **Suspend** | Sets status to `suspended`. Visitors to that site get **403**. |
| **Reactivate** | Returns a suspended tenant to `active`. |

Tenant statuses you may see:

- `provisioning` — being set up
- `dormant` — created but not yet materialized (stored as a compressed snapshot; auto-wakes on first traffic)
- `active` — live
- `suspended` — blocked (403 to visitors)
- `deleted` — gone (410 to visitors)

> **Tip:** Suspend is the fastest lever for abuse or non-payment — it's instant and reversible.

---

## 4. Manage plans (`/admin/plans`)

Plans define the limits enforced across the platform. Seeded defaults:

| Plan | Monthly | Pages | Bloks | API req/min |
|------|---------|-------|-------|-------------|
| Free | $0 | 5 | 50 | 60 |
| Starter | $12 | 25 | 250 | 120 |
| Pro | $39 | 100 | 2,000 | 600 |
| Business | $99 | 1,000 | 20,000 | 3,000 |

**Create a plan:** fill the right-hand form (display name, monthly price, max pages, max
bloks, max API rpm) and submit. A unique internal name is generated automatically.

**Enable/disable a plan:** click the green **Active** / grey **Inactive** toggle. Inactive
plans can't be selected for new tenants but existing tenants keep theirs.

> The **API req/min** value directly powers the `plan.limits` rate limiter — exceeding it
> returns HTTP **429** to that tenant's API calls.

---

## 5. Manage the marketplace (`/admin/marketplace`)

Two tables:

1. **Pending community submissions** — items submitted by community builders awaiting a
   decision. Per item: **Approve** (publish/activate) or **Reject** (deactivate).
2. **Live items** — everything currently installable. Toggle **Feature** to spotlight an
   item (featured items sort to the top of the browse list).

> Official themes (Aurora, Midnight) and templates (Startup Landing, Portfolio Basic) are
> seeded as `source = official` and are always live.

---

## 6. Community Review Queue (`/admin/community/review`)

This is the human gate for **untrusted, community-authored** content. The contribution
sandbox (`ArtifactValidator`) has already rejected anything containing PHP, `<script>`,
inline event handlers, `javascript:` URLs, `@import`, or `<iframe>` **before** it reached you.

**Workflow:**

```
submitted/in_review ──Approve──▶ approved ──Publish──▶ published (mirrored to marketplace)
        │
        └──Reject──▶ rejected (author may revise & resubmit)
```

1. **Awaiting review** lists each artifact with type, author, version and description.
2. Click **Inspect payload (sandboxed)** to read the raw JSON safely (it is never executed).
3. **Approve** (optionally add review notes) or **Reject** (notes recommended — the author sees them).
4. Approved items move to **Approved (ready to publish)**. Click **Publish to marketplace** to
   mirror the artifact into `marketplace_items` with `source = community`, where the normal
   install pipeline takes over.

**State machine (enforced):** illegal jumps are blocked — e.g. you cannot publish something
that wasn't approved (the action throws and you'll see an error).

---

## 7. Operational tasks

### Scheduler (no SSH required)

WebBlok runs scheduled work (queue processing, scheduled publishing, export cleanup) via a
web-triggered runner. Point an external pinger at it once per minute:

```
https://your-domain/cron/run?secret=YOUR_WEBBLOK_CRON_SECRET
```

Or schedule the PHP file directly on hosts that allow it:

```
* * * * * php /path/to/public/cron.php
```

Set the secret via `WEBBLOK_CRON_SECRET`. A bad/missing secret returns **403**.

### Headless tenant provisioning (for partners)

Partners can create fully-provisioned tenants in one API call (see the Developer guide).
It's guarded by `WEBBLOK_PROVISION_SECRET` sent as the `X-Provision-Secret` header.

---

## 8. Quick reference

| Page | URL |
|------|-----|
| Dashboard | `/admin` |
| Tenants | `/admin/tenants` |
| Plans | `/admin/plans` |
| Marketplace | `/admin/marketplace` |
| Community Review | `/admin/community/review` |
| Cron trigger | `/cron/run?secret=…` |

**Security reminders:** rotate the default super-admin password, set strong
`WEBBLOK_PROVISION_SECRET` and `WEBBLOK_CRON_SECRET`, and prefer **suspend** over delete so
actions stay reversible.
