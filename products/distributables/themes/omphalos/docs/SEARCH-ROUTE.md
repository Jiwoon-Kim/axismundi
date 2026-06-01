# Omphalos — core/search semantic route (decision, pre-implementation)

> **Purpose**: cut the route for core/search BEFORE any CSS / theme.json change,
> per the diagnostic-first lock. Settles the earlier deferral (the search input
> pill is parent-TT5 residue) into a contract.
> **Authority**: lab/modules/search-bar/docs/SEARCH-BAR-WP-MAPPING.md (canonical
> Search-bar to WordPress mapping) + the lab .lab-search-bar-google-home specimen.
> **Status**: route only. No theme.json styles.blocks.core/search override, no
> blocks.css search rule, no block styles. Implementation is the NEXT step.
> **Date**: 2026-06-01 - WP 7.0 - M3 Expressive.

---

## section 1 - Diagnostic: actual WP 7.0 markup (grounding)

core/search emits a `<form role="search">` whose class encodes the variant; the
field lives in an inside-wrapper and the submit is a .wp-element-button:

```txt
.wp-block-search__inside-wrapper   = field shell
.wp-block-search__input            = input[type=search] (the query field)
.wp-block-search__button.wp-element-button = submit (text) or .has-icon (icon)
```

The eight VQA forms (button position x text/icon):

| Form class | Variant |
|---|---|
| __button-outside __text-button | button-outside + text (default) |
| __button-inside __text-button | button-inside + text |
| __no-button | input only |
| __button-only __searchfield-hidden __text-button | button-only + text |
| __button-outside __icon-button | button-outside + icon |
| __button-inside __icon-button | button-inside + icon |
| __no-button (icon n/a) | input only |
| __button-only __searchfield-hidden __icon-button | button-only + icon |

Load-bearing facts:
1. The input pill (border-radius 3.125rem, accent-6 border) is parent Twenty
   Twenty-Five residue (styles.blocks.core/search.css), not an Omphalos rule -
   Omphalos does not yet own styles.blocks.core/search.
2. The submit button already consumes the global .wp-element-button base
   (BUTTON-ROUTE section 1). The field shell is the un-owned surface.

---

## section 2 - Component correspondence (lab search-bar)

```txt
core/search                                  lab search-bar
  .wp-block-search__inside-wrapper        =  .search-bar          (field shell)
  .wp-block-search__input                 =  .search-bar__input   (query field)
  .wp-block-search__button.wp-element-button = .search-bar__trailing (control)
```

Per the lab mapping (SEARCH-BAR-WP-MAPPING sections 2-4): the theme styles the
shell / spacing / shape / state / trailing composition; the plugin owns query,
results, autocomplete, federated search. input[type=search] stays the real field;
the submit stays a real button.

---

## section 3 - Visual target: Google-home field, NOT the full Search Bar

The user's read (confirmed): the canonical M3 Search Bar feels vertically heavy
because it is an app-bar / navigation surface. core/search is a content/page
search field, which matches the lab .lab-search-bar-google-home specimen - a
lower, lighter field (search-bar__input { block-size: 50px }) rather than the
tall Search Bar.

```txt
full M3 Search Bar       = app top-bar / nav-centric UI        (NOT core/search)
Google-home-like field   = in-content / in-page search field   (core/search yes)
```

So when Omphalos owns styles.blocks.core/search, it overrides the parent pill /
accent-6 residue toward a Google-home-like field shape (tokenised), not the tall
Search Bar.

---

## section 4 - The route (per variant)

| Variant | Route |
|---|---|
| button-outside (default) | Respect as the WP form block: connected field + submit. WP-specific layout; not forced into a Search Bar. |
| button-inside + icon | The M3 search-ish bridge. inside-wrapper = the field shell; trailing .has-icon button = a default icon button (open/submit). Closest structural match. |
| button-inside + text | input + tonal submit button (.wp-element-button.is-style-tonal), like the Google-home ax-button is-tonal. |
| button-outside + icon/text | connected field + separate submit, WP-specific. |
| no-button | input-only search field (shell + states, no control). |
| button-only | standalone default icon button (the collapsed/expand trigger). |

Only button-inside (+ icon or tonal text) bridges toward the search-bar shell;
the rest stay honest WP search variants.

**Icon submit — geometry consumed (blocks.css section 13, DONE).** Only
`.wp-block-search__button.has-icon` consumes the S icon-button geometry tokens
(`--comp-icon-button-height-s` 40 / `--comp-icon-button-icon-size-s` 24 /
`--comp-icon-button-shape-round`), squaring the base pill (~72x40) to a 40x40
round default icon button. A **text** submit ("Search" / "Expand search field")
keeps the `.wp-element-button` base pill — the `.has-icon` isolation is
load-bearing: fixing a text button to 40px clips the label. The rule supports
core's inline `<svg>` AND `.material-symbols-outlined`, but does **not replace**
core's icon (option C — replacement is plugin / custom-block territory). The base
already supplies inline-flex centring + the M3 state layer; a11y `aria-label` is
core-owned (theme must not hide it). Colour stays the base filled for now —
per-variant colour is section 8.

---

## section 5 - Default icon button, NOT toggle (M3 guideline)

Per M3 icon-button guidelines (default vs toggle): a default icon button opens or
runs an action (menu, search); a toggle icon button is a binary on/off/selected
control (favorite, bookmark).

```txt
core/search icon submit   = DEFAULT icon button   (open / submit / run search)
core/social-links icons   = DEFAULT icon link buttons
theme mode / favorite / bookmark = TOGGLE (binary selected) - the only toggle lane
```

Therefore the search icon button (and social icons) get hover / focus / pressed
state layer only - no aria-pressed, no selected shape morph. Adding aria-pressed
to core/search's button would also break its native form submit. (Recorded in
ICON-BUTTON-ROUTE section 5.)

---

## section 6 - Boundary (theme-can / plugin-should)

```txt
Field shell = SURFACE (theme).  Query/results = BEHAVIOR (plugin).

Theme can:    field shell shape/spacing/typography/colour/state; leading icon;
              trailing icon-button or tonal submit; reduced-motion CSS; pattern
              placement.
Theme must NOT: WP_Query behaviour, result ranking, autocomplete/remote data,
              federated search, query history, aria-live result announcements,
              global input[type=search] styling, ripple on the field host.
```

(From SEARCH-BAR-WP-MAPPING sections 4-9 anti-patterns.)

---

## section 7 - Explicitly NOT in this step

- No theme.json styles.blocks.core/search override; no blocks.css search rule; no
  register_block_style (tonal submit reuses the existing core/button tonal).
- No global input[type=search] styling (anti-pattern - scope to .wp-block-search).
- No suggestions / autocomplete runtime; no ripple; no search-expansion port.
- No change to the submit button base (already global via .wp-element-button).

---

## section 8 - Next (implementation, after review)

1. ~~Own styles.blocks.core/search~~ — **field shell DONE (blocks.css section 14)**,
   in blocks.css NOT theme.json: the parent pill / accent-6 / dark-bg residue is
   overridden by class specificity (parent `:root :where(...)` 0,1,0 < the
   `.wp-block-search ...` rules 0,2,0), so no theme.json css-string is needed and
   the contract stays with the other block contracts (sections 1-13). Google-home-
   like field: a full-pill shell on surface-container-high + outline-variant border,
   min 48px; for button-inside the inside-wrapper is the shell and the input is
   transparent; for outside / no-button the input is the field. Verified computed
   both schemes (light #ECE6F0 / #CAC4D0, dark #2B2930) — resolves the search
   dark-mode token-linkage gap.
2. **Phase 2 — button-inside Search Bar bridge DONE** (blocks.css section 14):
   the button-inside variant is promoted to an **M3 Search Bar bridge** — Search
   Bar tokens adapted to a **48px** container (the canonical Search Bar is 56dp;
   48px is an intentional in-content deviation — 56 reads too tall in block-theme
   content). Borderless + elevation-level3 (light only; dark token = none →
   surface-tint), input **body-large** (16 / 24 / .5), bg/box-shadow transition on
   **fast-effects** (150ms), focus-within = the Search Bar focus spec
   (**3dp / 2dp offset / secondary #625B71**; survives dark where the shadow is
   none). Trailing submit reskinned OFF the global filled base, both container-less
   (consistent in-field actions): icon = **STANDARD** icon button (transparent /
   on-surface-variant / state layer), text = **TEXT button** (transparent /
   **primary** label — tonal was reverted: secondary-container muddied against the
   surface-container-high field). Verified computed both schemes.
   **Remaining**: button-outside / button-only / no-button colour (outside keeps
   the base filled or a compact submit; button-only icon = standalone standard
   icon button), the connected button-outside layout, an optional Search Bar
   hover/pressed state layer (verify weight first), and a reduced-motion guard.
   Scope stays `.wp-block-search` (never global input).
3. Verify computed front + editor, both schemes; no aria-pressed; native submit works.
4. Icon-button lane: icon submit **geometry DONE** (blocks.css section 13,
   `.has-icon` only — 40x40 round S default icon button, both <svg> and Material
   Symbols). Remaining = per-variant COLOUR: button-inside = transparent /
   on-surface-variant; button-only = standard transparent; button-outside may stay
   a compact tonal submit. That colour pass composes with the field shell (item 1).
