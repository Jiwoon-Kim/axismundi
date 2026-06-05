# Omphalos — Theme switcher architecture (design, pre-implementation)

> **Purpose**: settle the theme-switcher architecture before code, since it
> spans SSR `data-theme`, persistence, caching, editor preview, the Interactivity
> API, and native `color-scheme`. Design only — no block, JS, or render yet.
> **Status**: route/decisions. Implementation is phased (see §6), after review.
> **Date**: 2026-05-31 · WP 7.0.

---

## §1 — Confirmed architecture

| Axis | Decision |
|---|---|
| Form | **Custom dynamic block** `omphalos/theme-switcher` (not a pattern — needs active state, Interactivity, inserter placement) |
| Category | **Design** (UI/appearance control, not site content or template structure) |
| Modes | **auto / light / dark** (3-state) |
| Persistence | **cookie only** for now (logged-in user-meta deferred) |
| Apply | set `<html data-theme="auto|light|dark">` — nothing else |
| Editor | canvas reads the same cookie and sets iframe `<html data-theme>` |
| Front JS | **Interactivity API** |

`data-theme="auto"` is implemented here — note it was a deliberately deferred /
gated surface; this is its explicit authorization.

---

## §2 — Verified dependency: the token + color-scheme wiring already responds

The switcher only sets `data-theme` on `<html>`; everything else is already wired:

- `tokens.sys.dark.css` — `:root[data-theme="dark"]` (explicit, highest priority)
  AND `@media (prefers-color-scheme: dark) { :root:not([data-theme]),
  :root[data-theme="auto"] }` (auto follows OS). Colours + dark shadow
  suppression both keyed this way.
- `foundation.css` — sets `color-scheme: light|dark` off the same selectors, so
  native UA chrome (video/audio controls, form controls, scrollbars) follows.
- light is the `:root` default (no media match / `data-theme="light"`).

So: `data-theme="dark"` → dark everywhere; `"light"` → light; `"auto"`/unset →
follow OS. **No token or foundation change is needed** — the switcher is purely a
control + persistence + the one DOM write.

---

## §3 — Refinements to the plan (the parts that bite later)

### 3.1 FOUC + page-cache safety → inline head script is authoritative
PHP SSR of `data-theme` via `language_attributes` is **not cache-safe**: under
full-page caching the cached HTML carries the first visitor's mode. So:

- A tiny **blocking inline script in `<head>`** (printed very early on `wp_head`,
  GLOBAL — every page, not just pages with the block) reads the cookie and sets
  `document.documentElement.dataset.theme` before first paint. Cache-safe (runs
  per visitor) and FOUC-free. **This is the authoritative setter.**
- The cookie is the persistence; the head script reads it.
- `render.php` may still SSR the button's active state best-effort, but it is
  corrected by the Interactivity hydration (which reads the live `data-theme`).
  A brief button-only active flash is acceptable; the page colours never flash.

**Editor parity (implemented).** The block/site editor canvas does not execute
`wp_head`, so the front-end head script cannot run inside the iframe. A small
`enqueue_block_editor_assets` script copies the same `omphalos_theme` cookie onto
the editor canvas document's `<html data-theme>`. No second persistence channel
is introduced: front and editor both consume the same cookie contract, and
`auto` still follows `prefers-color-scheme`.

### 3.2 No build toolchain
Ship vanilla JS — no JSX/webpack:
- editor: `edit.js` registers the block with `wp.blocks.registerBlockType` +
  `wp.element.createElement` for a static preview (the 3-button control, `auto`
  active, icons). Enqueued via block.json `editorScript`.
- front: an **Interactivity API module** (`view.js`, block.json
  `viewScriptModule`) — `store('omphalos/theme-switcher', …)`. Source = shipped.

### 3.3 a11y — single-select control
Three modes, pick one → recommend `role="group"` + three `<button>` with
`aria-pressed` reflecting the active mode (simple, robust). `radiogroup`/`radio`
with arrow-key roving is the more precise single-select semantic — noted as an
alternative if we want it.

### 3.4 CSS location → block-scoped
`.wp-block-omphalos-theme-switcher` component styling lives in
`blocks/theme-switcher/style.css`, enqueued via block.json `style` (loads only
when the block is present). The track's inspectable defaults (background,
padding, radius) live in `theme.json styles.blocks.omphalos/theme-switcher`, so
the custom block is visible/editable from the Site Editor's Global Styles block
surface. `color-scheme` stays in foundation.css; icons.css already provides
`.material-symbols-outlined`.

---

## §4 — UI

3-state segmented control, Material Symbols icons:

| mode | icon (ligature) | label |
|---|---|---|
| auto | `brightness_auto` | Auto |
| light | `light_mode` | Light |
| dark | `dark_mode` | Dark |

Markup sketch (front, post-hydration): a `role="group" aria-label="Color scheme"`
wrapper with three `<button data-wp-on--click="actions.setScheme">`, each
`data-wp-context='{"mode":"…"}'` and `data-wp-bind--aria-pressed=
"state.isActive"`. Icon = `<span class="material-symbols-outlined" aria-hidden>`,
label text alongside (or visually-hidden for icon-only).

---

## §5 — File layout

```
blocks/theme-switcher/
  block.json     (apiVersion 3; render: file:./render.php; editorScript;
                  viewScriptModule: file:./view.js; style: file:./style.css;
                  category: design)
  render.php     (SSR control + best-effort active from cookie)
  edit.js        (static editor preview)
  view.js        (Interactivity store: setScheme → set data-theme + cookie)
  style.css      (segmented control behaviour; track defaults in theme.json)
assets/scripts/editor-theme-scheme.js
                (copy cookie scheme into the editor canvas iframe)
inc/theme-switcher.php
                (register_block_type + global inline head script + editor bridge)
```

Persistence: cookie `omphalos_theme` = `auto|light|dark`, root path, ~1yr,
SameSite=Lax.

---

## §6 — Phased implementation (after this design is confirmed)

1. **SSR plumbing**: cookie contract + global inline head `<script>` (cache-safe
   `data-theme`); confirm the page re-tokens + `color-scheme` flips on manual set.
2. **Block shell**: block.json + render.php + static `edit.js` preview +
   style.css; register; category Design; verify it inserts + previews (icons).
3. **Interactivity**: `view.js` store — setScheme sets `data-theme`, writes the
   cookie, drives `aria-pressed`.
4. **VQA**: add the block to a Design VQA surface; verify auto/light/dark toggle,
   active state, icon render (front + editor).
5. **Native check**: dark mode flips `color-scheme` → video/audio controls,
   scrollbars, form controls follow; no FOUC on reload.

---

## §7 — Open decisions (confirm before Phase 1)

1. **SSR strategy** — inline head script as authoritative (§3.1, recommended for
   cache safety) vs PHP-`language_attributes`-only (simpler, but stale under page
   cache). Recommend the inline script.
2. **a11y pattern** — `aria-pressed` toggle group (recommended) vs `radiogroup`.
3. **Label vs icon-only** — show text labels (Auto/Light/Dark) or icon-only with
   visually-hidden labels. Recommend labels for the first cut (clarity), icon-only
   later if space-constrained.
4. **Cookie name / lifetime** — `omphalos_theme`, 1yr, Lax. OK?

NOT in scope now: logged-in user-meta persistence, multi-site sync, per-post
overrides, transition animations on scheme change.
