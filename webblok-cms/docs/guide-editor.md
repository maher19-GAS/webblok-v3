# Editor Guide

You've been invited to a WebBlok site as an **Editor**. Your job is to keep content
fresh — write copy, swap images, tweak existing bloks — **without** touching site
structure, plans, billing, or the marketplace. This guide covers exactly what you can
do and how to do it safely.

> **Editor vs. Owner.** The tenant **Owner** controls pages, themes, plan limits,
> exports and who gets invited. As an **Editor** you focus on the *content inside*
> pages. Some buttons mentioned in the Owner guide may be hidden or disabled for you —
> that's expected.

---

## 1. Sign in

- **Self-serve (Breeze driver):** go to `/login` with the email/password you were given.
- **External SSO (GAS driver):** click **Login** and authenticate with the shared
  identity provider.

After login you land on the **Dashboard** (`/cms`) for the site you were invited to.
If you work on more than one site, switch between them from the Dashboard.

---

## 2. Find the page you need to edit (`/cms/pages`)

1. Open **Pages** from the top navigation.
2. Locate the page in the list (pages are shown by title and URL path, e.g.
   `/services/design`).
3. Click the page to open the **Builder**.

You generally do **not** create or delete pages as an editor — ask the Owner if the
page you need doesn't exist yet.

---

## 3. The Builder, the editor's view

The builder has three panes:

| Pane | What you use it for |
|------|---------------------|
| **Left — Blok Library** | Drag a *new* blok onto the canvas (if allowed) |
| **Center — Canvas** | The live preview of the page; click any blok to select it |
| **Right — Form Wizard** | Edit the selected blok's content, step by step |

Day-to-day editing happens in the **center** (select) and the **right** (edit).

---

## 4. Edit content with the Form Wizard

Click a blok on the canvas. Its settings open on the right as a guided, multi-step
form. WebBlok has **19 field types**; here's what you'll meet most often as an editor:

| Field | What you do |
|-------|-------------|
| **Text / Textarea** | Type headlines, labels, short copy |
| **Rich Text** | Format paragraphs (bold, lists, links) |
| **Image** | Upload or pick an image, set alt text |
| **Gallery** | Add/remove/reorder multiple images |
| **Link** | Set a button/link's URL, label and target |
| **Icon** | Pick an icon from the set |
| **Select / Radio / Toggle** | Choose a style/variant the Owner enabled |
| **Color** | Pick from the theme's color tokens |
| **Range / Number** | Spacing, counts, sizes |
| **Repeater** | Add repeating rows (e.g. stats, feature cards) |
| **Date** | Set publish/event dates |

Tips:

- **Conditional fields**: some fields only appear after you flip a related toggle or
  pick a certain option (e.g. choosing "Button" reveals a link field). This is normal.
- **Localized fields**: if a field shows a language indicator, your text is saved for
  the **currently selected locale** only — see §6.
- **Repeaters**: use the **+ Add** button to create a row, drag rows to reorder, and
  the trash icon to remove one. Don't delete rows you're unsure about.

Changes preview instantly in the center canvas. Keep an eye on it as you type.

---

## 5. Inline editing on the canvas

Text and rich-text content carries an editable marker on the canvas (`data-field`).
You can click directly on a headline or paragraph in the preview and type — it updates
the same field the Form Wizard would. Use whichever feels faster.

---

## 6. Working in multiple languages

If the site is multi-language:

1. Use the **locale switcher** to choose the language you're editing (e.g. `en`, `fr`).
2. Edit the **localized** fields — your text is stored per-locale, so English copy and
   French copy live side by side.
3. Non-localized fields (e.g. an image choice or a layout toggle) are shared across all
   languages — changing them affects every locale.

> For right-to-left languages (e.g. Arabic), the preview automatically flips direction.
> Just write naturally; the theme handles the layout.

---

## 7. Reordering and adding bloks

- **Reorder**: drag a blok up or down on the canvas to change its position within a
  section.
- **Add a blok** (if the Owner allows editors to add bloks): drag one from the left
  Blok Library onto the canvas where you want it, then fill in the Form Wizard.
- **Remove a blok**: select it and use the delete control. **Be careful** — deleting a
  blok removes its content. When in doubt, ask the Owner.

---

## 8. Save & preview

- Edits are saved as you work (draft state).
- Use the page's **Preview** to see the rendered result exactly as a visitor would.
- You typically **cannot publish** as an editor — once your content is ready, tell the
  Owner so they can review and **Publish** the page. (If the Owner has granted you
  publish rights, you'll see a Publish action; otherwise it's hidden.)

---

## 9. What editors should *not* do

To avoid breaking the site, leave these to the Owner / Super Admin:

- Creating, renaming, moving or deleting **pages**.
- Switching **themes** or editing global theme settings.
- Installing **marketplace** themes/templates.
- Changing the **plan** or anything billing-related.
- Inviting or removing **users**.
- Running **static exports**.

If you need any of the above, request it from the Owner.

---

## 10. Troubleshooting

| Symptom | Likely cause / fix |
|---------|--------------------|
| A field I expected is missing | It's conditional — flip the related toggle/select first. |
| My text didn't appear in another language | It's a localized field — switch locale and re-enter it there. |
| A button I need is greyed out | That action is Owner-only for your role. |
| My change vanished | You may have been editing a different locale, or the Owner reverted/republished. |
| Image won't upload | Check the file size/format; the site may be near its media limit — tell the Owner. |

---

## Quick reference

| I want to… | Where |
|------------|-------|
| Open a page to edit | **Pages → click the page** |
| Change a headline/paragraph | Click the text on canvas **or** use the Form Wizard |
| Swap an image | Select the blok → **Image** field |
| Add a stat/feature row | Select the blok → **Repeater → + Add** |
| Edit another language | **Locale switcher**, then edit localized fields |
| Get it published | Ask the **Owner** (unless you have publish rights) |

← Back to [Documentation index](./README.md)
