# Search Bar — WordPress Mapping (v3.5.8 Phase 1)

> **Status**: Phase 1 audit body authored. Phase 2 implementation pending.  
> **Component**: Search bar #17  
> **Companions**: `SEARCH-BAR-SPEC-AUDIT.md`, `SEARCH-BAR-MEASUREMENT-AUDIT.md`, `SEARCH-BAR-RUNTIME-AUDIT.md`

---

## §0 — Mapping Status

Search bar's primary WordPress mapping is `core/search`. The theme may style
and compose the visual search surface. Plugins own search data, query
behavior, autocomplete, federated search, and analytics.

This mapping does not edit WordPress templates or register block styles in
Phase 1.

---

## §1 — Inputs Read

```txt
docs/v3.5.8/SEARCH-BAR-PHASE-0-REPORT.md
docs/v3.5.8/SEARCH-BAR-PHASE-1-PLAN.md
components.css §10 Search bar
style-guide.html #components-search-bar
modules/search-expansion/docs/SEARCH-EXPANSION-AUDIT.md
Text field v3.5.7 WP-MAPPING
Card v3.5.3 WP-MAPPING precedent
```

---

## §2 — core/search Block Mapping

Primary mapping:

```txt
WordPress core/search block
  -> Axismundi Search bar shell
```

Theme can:

```txt
- style the search form shell as .search-bar where markup permits
- place leading search icon and trailing controls in templates/patterns
- provide static suggestions/results visual shell in controlled patterns
- style focused/rest/disabled states
```

Theme must not:

```txt
- replace WordPress query behavior
- decide result ranking
- fetch remote autocomplete data
- store query history
- implement federated search policy
```

---

## §3 — Search Form Markup Inventory

Expected safe primitives:

```html
<form role="search">
  <label class="search-bar">
    <span class="search-bar__leading-icon">...</span>
    <input class="search-bar__input" type="search" name="s" />
    <span class="search-bar__trailing">...</span>
  </label>
</form>
```

Native requirements:

```txt
- input[type="search"] remains the actual query field.
- name attribute must map to the WordPress search parameter.
- form submit remains browser/native unless plugin enhances it.
- trailing controls must be real buttons when interactive.
```

Phase 2 may need more than one specimen:

```txt
1. plain core/search-like form
2. enhanced Search bar with suggestions shell
3. disabled/static pattern specimen
4. forbidden prose/contenteditable negative specimen
```

---

## §4 — Theme-Can Surface

Theme territory:

```txt
- visual shell
- spacing, shape, typography, color, elevation
- leading icon affordance
- trailing icon-button/avatar composition
- static suggestion/result container markup
- state styling for rest/focus/disabled
- reduced-motion CSS
- template pattern placement
```

Theme may also provide the local runtime necessary for visual expansion and
keyboard movement through static suggestions, as evidenced by
`search-expansion/`. It may not decide the data source.

---

## §5 — Plugin-Should Surface

Plugin territory:

```txt
- WP_Query customization
- live search endpoint
- autocomplete data source
- remote API search
- ActivityPub / federated search
- query privacy policy
- personalized search history
- analytics and telemetry
- result ranking
- result rendering templates
- async status announcements for live results
```

Search bar's theme runtime may present a suggestions container, but real data
belongs outside the theme.

---

## §6 — Suggestions And Autocomplete Boundary

Current `search-expansion/` uses static demo suggestions:

```txt
최근 검색어
컴포넌트 토큰
상태 레이어
메뉴 인터랙션
```

This is acceptable for lab proof. It is not a production data contract.

Allowed in theme:

```txt
- static placeholder suggestions
- visual listbox surface
- keyboard/focus behavior
- clear/collapse policy
```

Plugin territory:

```txt
- filtering suggestions from a database
- fetching remote suggestions
- storing recent searches
- federated suggestions
- no-results logic based on real query results
```

---

## §7 — ActivityPub / Social-CMS Boundary

Federated search is explicitly plugin territory.

Reasons:

```txt
- privacy and federation policy are product/application decisions
- remote query routing can leak intent
- instance search capability differs
- moderation policy affects result display
- ActivityPub result rendering is not a theme primitive
```

Theme may render:

```txt
search input shell
suggestions panel shape
static examples
result-list visual pattern
```

Plugin must own:

```txt
federated data access
remote query policy
result ranking
privacy notices
async live-region announcements
```

---

## §8 — Accessible Name And Native Form Contract

Search input must have an accessible name.

Valid patterns:

```txt
- visible label around input, if visually appropriate
- aria-label on input
- labelled form field in a Search region
- placeholder as hint only, not sole durable label when avoidable
```

Trailing icon buttons must have accessible names:

```txt
Clear search
Voice search
Open filters
Back
```

Suggestion items must expose name/role/value through the runtime ARIA
contract owned by `SEARCH-BAR-RUNTIME-AUDIT.md`.

---

## §9 — Anti-Pattern Inventory

Forbidden or discouraged:

```txt
- Treating Search bar as Text field variant.
- Styling all input[type="search"] globally.
- Replacing native input with div role=searchbox.
- Making icon glyphs clickable without button semantics.
- Rendering suggestions without combobox/listbox relationship.
- Fetching autocomplete data from theme JS.
- Persisting search history in theme runtime.
- Federated search from theme layer.
- aria-live result announcements without plugin/application ownership.
- Coupling to popover/ before approved runtime architecture.
- Applying ripple to the Search field host.
```

---

## §10 — Runtime Disposition And Migration Note

Current state:

```txt
search-expansion/ is the current runtime evidence.
SEARCH-EXPANSION-AUDIT.md is the historical extraction audit.
SEARCH-BAR-RUNTIME-AUDIT.md is the v3.5.8 canonical runtime audit surface.
```

Phase 1 does not move files.

Phase 2 plan must decide:

```txt
Option A — absorb search-expansion into search-bar
Option B — keep search-expansion as referenced runtime lane
Option C — transitional wrapper/deprecation shell
```

WP mapping preference:

```txt
Search bar module should eventually own the public component contract.
search-expansion/ should not become cross-cutting infrastructure.
```

---

## §11 — Verdict

| Criterion | Status |
|---|---|
| core/search primary mapping | PASS |
| theme-can/plugin-should boundary | PASS |
| suggestions/autocomplete boundary | PASS |
| ActivityPub boundary | PASS |
| accessible-name contract | PASS |
| anti-pattern inventory | PASS |
| implementation verdict | Deferred to Phase 2/5 |

---

## §12 — Cross-References

```txt
SEARCH-BAR-SPEC-AUDIT.md
SEARCH-BAR-MEASUREMENT-AUDIT.md
SEARCH-BAR-RUNTIME-AUDIT.md
docs/v3.5.8/SEARCH-BAR-PHASE-0-REPORT.md
docs/v3.5.8/SEARCH-BAR-PHASE-1-PLAN.md
../search-expansion/docs/SEARCH-EXPANSION-AUDIT.md
../text-field/docs/TEXT-FIELD-WP-MAPPING.md
```

