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

1. Own styles.blocks.core/search in the child theme.json - override the parent
   pill/accent-6 residue toward a Google-home-like field (tokenised shape: likely
   corner small/extra-small, outline-variant border, surface field bg), with
   dark-mode colour linkage (the gap flagged earlier).
2. blocks.css search layout - connected button-inside shell; the icon submit
   consumes --comp-icon-button-* (default icon button); the text submit uses the
   tonal variant. Scope to .wp-block-search (never global input).
3. Verify computed front + editor, both schemes; confirm no aria-pressed and the
   native submit still works.
4. Reconcile with the icon-button lane: the button-only / button-inside+icon
   submit is the third surface (after social-links, theme-switcher) to converge on
   --comp-icon-button-*.
