# Omphalos — Button semantic route (decision, pre-implementation)

> **Purpose**: cut the semantic route for button-shaped core blocks BEFORE any
> CSS / `register_block_style()` lands, per the repo's core/button rule
> (route the semantics first) and the diagnostic-first lock.
> **Authority**: `lab/modules/button/docs/BUTTON-WP-MAPPING.md` (the canonical
> Button↔WordPress mapping). This doc adapts it to Omphalos's FSE core-block
> scope and grounds it in WP 7.0 actual markup.
> **Status**: route only. No block styles registered, no CSS written, no
> theme.json change. Implementation is the NEXT step, after review.
> **Date**: 2026-05-31 · WP 7.0.

---

## §1 — Diagnostic: actual WP 7.0 markup (grounding)

| Surface | Element emitted | Shared hook |
|---|---|---|
| `core/button` (href set) | `<a class="wp-block-button__link wp-element-button" href="#">` | `.wp-element-button` |
| `core/button` (no href) | `<a class="wp-block-button__link wp-element-button">` (still an `<a>`) | `.wp-element-button` |
| `core/search` text submit | `<button type="submit" class="wp-block-search__button wp-element-button">` | `.wp-element-button` |
| `core/search` icon submit | `<button class="wp-block-search__button has-icon wp-element-button">` + `<svg>` (Interactivity) | `.wp-element-button` |
| `core/file` download | `<a class="wp-block-file__button wp-element-button" download>` | `.wp-element-button` |
| `core/navigation` item | `<a>` (no `wp-element-button`) | — |
| `core/post-navigation-link`, `core/query-pagination-*` | `<a>` (no `wp-element-button`) | — |

**Two load-bearing facts:**

1. `core/button` is **always an `<a>` anchor** — it never emits a `<button>`.
   It is a *link styled as a button*. Real action `<button>`s come from other
   surfaces (search submit, comments submit, plugin forms).
2. `.wp-element-button` is the **single shared hook** WordPress puts on every
   button-shaped affordance (core/button, search submit, file download, comments
   submit). It is the natural one place to seat the M3 button *base*.

---

## §2 — The route

| Core block | Element | Semantics | Route |
|---|---|---|---|
| `core/button` | `<a … wp-element-button>` | Navigation styled as a button (link). | **M3 Button visual bridge.** Map core `fill`→Filled, `outline`→Outlined; register the missing **tonal / elevated / text**. Visual only — it stays a link. |
| `core/buttons` | `<div>` flex | Layout container | No button mapping (row layout; already fine). |
| `core/file` download button | `<a … wp-element-button download>` | Download link as button | **Consumes the button base** via `.wp-element-button`. No bespoke styling needed beyond the base (its own `wp-block-file__button` may add a layout nudge). |
| `core/search` text submit | `<button type=submit … wp-element-button>` | Form **action** (real button) | **Consumes the button base** + a search-specific layout override (sits against the input). Submit *behavior* is core/plugin, not theme. |
| `core/search` icon submit | `<button has-icon … wp-element-button>` + svg | **Icon** button (Interactivity-driven expand) | **Icon-button contract**, not the text Button. Separate route (icon sizing/shape); deferred. |
| `core/post-comments-form` submit | `<input type=submit>` / `<button>` (verify at contract) | Form action | Consumes the button base IF it carries `.wp-element-button` (confirm at contract). Form behavior = core/plugin. |
| `core/navigation` items | `<a>` | Navigation | **Not a button.** M3 navigation surface (nav item) — its own future contract. |
| `core/post-navigation-link`, `core/query-pagination-*` | `<a>` | Navigation | **Not a button.** Text / action-link contract, not button chrome. |

Matches the user's cut: `core/button` = button surface; file/search-submit = button-base consumers; nav / post-nav = links, not buttons.

---

## §3 — M3 variants (the 5)

Default = **Filled** (M3 highest emphasis). Core ships `fill` + `outline` as
block.json styles (client-side; not in the server `WP_Block_Styles_Registry` —
confirmed empty in the probe). So Omphalos:

- maps core `is-style-fill` → **Filled**, `is-style-outline` → **Outlined** (style only, no re-registration), and
- registers the three missing styles: **tonal**, **elevated**, **text**.

Variant → token (from the lab §9 contract, to be ported at implementation):

| Variant | Background | Foreground | Extra |
|---|---|---|---|
| Filled (`fill`) | `primary` | `on-primary` | — |
| Tonal | `secondary-container` | `on-secondary-container` | — |
| Elevated | `surface-container-low` | `primary` | level-1 shadow |
| Outlined (`outline`) | transparent | `on-surface-variant` | inset outline-variant hairline |
| Text | transparent | `primary` | reduced inline padding |

Base (all): `inline-flex`, height `--comp-button-height` (40px), pill radius
`--comp-button-radius`, `label-large` typescale. → promotes the Bucket-A button
tokens from the comp-token audit.

---

## §4 — Boundary (theme-can / plugin-should)

```txt
Button = SURFACE (theme). Form = BEHAVIOR (core/plugin). They compose; neither
owns the other.

Theme can:    visual button base + variants; states (hover/focus-visible/
              pressed/disabled); search/file layout overrides.
Theme should NOT: form submission, validation, AJAX, nonce/auth, federation
              actions (Follow/Like/Boost), or custom button blocks.
```

`core/button`'s anchor never carries action behavior; real submits
(`<button type=submit>`) keep their native form mechanics owned by core/plugin.

---

## §5 — Open decisions (resolve before/at implementation)

1. **Scope of the button base.** `.wp-element-button` is cross-cutting (appears
   in search, file, comments — often *outside* post content). Options:
   (a) style `.wp-element-button` globally (theme.json `styles.elements.button`
   or an unscoped rule) so every button surface gets the M3 base everywhere, then
   layer variants; (b) keep it `.wp-block-post-content`-scoped like the other
   Omphalos chrome, accepting that search/comments buttons in template parts go
   unstyled for now. Card precedent chose (b). Button leans (a) because its
   surfaces are inherently global — **needs a call.**
2. **`fill`/`outline` mapping mechanics.** Style core's existing slugs
   (`is-style-fill` / `is-style-outline`) directly, vs. registering parallel
   `filled`/`outlined` names. Lab maps the native slugs (no duplicate names) —
   recommend the same.
3. **core/file & core/search consumption.** Confirm both inherit the base cleanly
   via `.wp-element-button`; decide the search-submit layout override and whether
   the file download button needs anything beyond the base (the earlier
   "File download button styling deferred" item resolves here).
4. **Icon search button + icon-button** are a separate route (Interactivity +
   icon sizing). Out of this Button step.

---

## §6 — Explicitly NOT in this step

- No `register_block_style()` calls, no blocks.css button section, no theme.json.
- No form / submit / federation behavior (plugin territory).
- No icon-button, FAB, or split-button.
- No nav / post-nav / pagination styling (links, not buttons).

---

## §7 — Contract status

**Implemented** (verified computed, front + editor, both schemes):
- Global base on `.wp-element-button` (theme.json elements.button: label-large
  type, pill radius, Filled colour, padding block-0 / inline space-lg → 40px) +
  blocks.css §9 (interaction layout, focus ring). §5.1 resolved = **global**.
- Five variants: Filled (default) / Tonal / Elevated / Outlined / Text — exact M3
  role tokens; outline padding reset so all five are the 40px pill.
- Interaction states (M3 state layer, currentColor veil below the label via
  isolation/z-index): hover .08, focus-visible .10 (+ a11y focus ring),
  pressed `:active` .10 with a pill→corner-medium shape morph; `prefers-reduced-
  motion` disables transitions. `user-select:none`. Prose link colour/underline
  excluded from `.wp-element-button`.

**Deferred — recorded as spec, not built (avoid matrix sprawl):**
- **Size matrix.** Only the 40px default is contracted (`--comp-button-height`).
  The xs..xl token matrix + any `compact` / `large` block styles stay future; do
  not open until a real size need lands.
- **Toggle / selected.** `core/button` has no toggle semantics; real toggle
  behaviour is plugin/JS. A theme could style `.wp-element-button[aria-pressed]`,
  but the M3 selected treatment differs per variant (filled/tonal → stronger
  container; outlined/text → selected container / state layer), which opens a
  variant × selected matrix. Left for a separate design pass; not seeded yet
  (core/button markup can't carry `aria-pressed` without breaking block save).
- **Disabled.** Not implemented (intentionally; revisit later).
- **file / search / comments layout overrides.** They already inherit the global
  base; their connected-layout / icon-position overrides come with the
  Widgets/Theme VQA groups (search-submit is text vs icon; icon = icon-button).
