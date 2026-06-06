# Omphalos — Theme switcher architecture (design, pre-implementation)

> **Purpose**: settle the theme-switcher architecture since it spans SSR
> `data-theme`, persistence, caching, editor preview, the Interactivity API, and
> native `color-scheme`.
> **Status**: implemented as a split surface. The Omphalos theme owns scheme
> application; the companion plugin owns the inserter block UI.
> **Date**: 2026-05-31 · WP 7.0.

---

## §1 — Confirmed architecture

| Axis | Decision |
|---|---|
| Form | Companion plugin custom dynamic block `omphalos/theme-switcher`; theme fallback can be plain HTML that writes the same cookie |
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
`wp_head`, so the front-end head script cannot run inside preview documents. A
small theme-owned `enqueue_block_editor_assets` script copies the same `omphalos_theme`
cookie onto editor-owned preview `<html data-theme>` roots. The normal editor
canvas is a same-origin iframe and can be patched directly; the Style Book uses a
`blob:` iframe, so the bridge rewrites editor-owned preview blobs with the
current `data-theme` and injects the Omphalos editor style cascade needed by the
custom block preview (tokens → block chrome → icons). The same pass also
rewrites the preview block's `aria-pressed` state when the companion block is
present, so a
scheme change made from a front-end theme switcher updates both Style Book colour
tokens and the active segment.
No second persistence channel is introduced: front, editor canvas, and Style Book
all consume the same cookie contract, and `auto` still follows
`prefers-color-scheme`.

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

### 3.4 CSS location → plugin block-scoped
`.wp-block-omphalos-theme-switcher` component styling lives in the companion
plugin's `blocks/theme-switcher/style.css`, enqueued via block.json `style`
(loads only when the block is present). The theme no longer registers or ships
the custom block, satisfying the WordPress.org plugin-territory boundary. The
theme still owns `color-scheme`, token CSS, Material Symbols font loading, and
the early cookie application script.

---

## §4 — UI

Default form: 3-state segmented control, Material Symbols icons:

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

### 4.1 Style variation — theme cycle icon button

Omphalos also exposes `is-style-theme-cycle` on `omphalos/theme-switcher`.
This maps the same three-state persistence channel to a single standard icon
button (`ax-icon-button is-standard has-state-layer t-theme-cycle`) that cycles
`auto → light → dark` on each activation. It is intentionally **not** a chip set:
the reference chrome is an icon-button affordance for compact header/footer
areas where the full segmented control is too heavy.

The plugin block writes the cookie + live `<html data-theme>` value. The theme's
early head script reads the same cookie on the next page load.

---

## §5 — File layout

```
products/distributables/plugins/omphalos-theme-switcher/
  omphalos-theme-switcher.php
  blocks/theme-switcher/
    block.json   (apiVersion 3; render: file:./render.php; editorScript;
                  editorStyle + style; example for Style Book / inserter;
                  viewScriptModule: file:./view.js; category: design)
    render.php   (SSR control + best-effort active from cookie)
    edit.js      (editor preview + cookie-writing controls)
    view.js      (Interactivity store: setScheme → set data-theme + cookie)
    style.css    (segmented control + theme-cycle icon button)

products/distributables/themes/omphalos/
  inc/theme-switcher.php
                (global inline head script + editor bridge only)
  assets/scripts/editor-theme-scheme.js
                (copy cookie scheme into editor canvas + Style Book previews)
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
