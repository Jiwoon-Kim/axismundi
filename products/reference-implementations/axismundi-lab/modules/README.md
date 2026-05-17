# Lab modules

This directory contains lab modules extracted from benchmark or external reference experiments. Each module is a self-contained subfolder that bundles one interaction pattern's stylesheet, script, demo page, and audit/QA documentation.

## Why this exists

From v3.3.2 to v3.3.4, three modules accumulated in the lab as flat files (e.g. `stylesheets/lab-carousel.css`, `scripts/lab-carousel.js`). At three modules the flat layout was still readable, but the next planned module (popover, v3.4.1+) would have made it unwieldy. v3.4.0 Lab Module Restructure consolidated all three into the per-module folders below, before adding any more.

## Why each file keeps its `lab-` prefix

Files inside `modules/<name>/` could in theory be named generically (e.g. `module.css` inside `modules/carousel/`), but they retain the `lab-<name>` prefix. The reason is **flattening on publish**.

The publish-mirror generator (`tools/generators/publish_styleguide.py`) flattens module stylesheets into the top-level `styleguide/stylesheets/` directory, alongside the design-system files like `tokens.css` and `components.css`. If the module CSS were named `module.css`, multiple modules would collide on the flat publish surface and the resulting files would lose their identity. With the `lab-` prefix retained:

```
modules/carousel/lab-carousel.css         → styleguide/stylesheets/lab-carousel.css
modules/ripple/lab-ripple.css             → styleguide/stylesheets/lab-ripple.css
modules/search-expansion/lab-search-expansion.css → styleguide/stylesheets/lab-search-expansion.css
```

Each file remains identifiable both in the source tree (where the folder gives the same information twice) and on the publish surface (where the folder structure is gone).

A secondary benefit: IDE tab bars. With distinct file names, multiple module CSS files open simultaneously are immediately distinguishable. With generic names like `module.css`, every tab reads the same.

## What gets published, what stays lab-internal

| Asset | Path inside module | Published to `/styleguide/`? |
|---|---|---|
| Module CSS | `modules/<name>/lab-<name>.css` | **Yes** (flattened to `stylesheets/`) |
| Module JS | `modules/<name>/lab-<name>.js` | No — lab-internal |
| Pattern HTML | `modules/<name>/lab-<name>-pattern.html` | No — lab-internal |
| Module docs | `modules/<name>/docs/*.md` | No — lab-internal |

The asymmetry is intentional. CSS is innocuous on the publish surface (no HTML on the publish side links to it, so it sits as an orphan asset that does nothing). JS, pattern HTML, and docs would either need separate publish handling or risk being mistaken for canonical surfaces; until a module is promoted into the main styleguide, those artifacts stay in the lab.

## Charter cross-reference (added v3.5.0)

This README defines lab-module-level conventions. The repository-level architectural posture — the 4-tier Public / Lab / Baseline / Plugin architecture, the meaning of `style-guide.html` / `components.css` / `lab/modules/*` / `bindings/`, and the Infrastructure dependency principle — lives in:

- **`docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md`** — 4-tier architecture + surface meanings + Infrastructure dependency principle (DISTINCT but COUPLED).
- **`docs/v3.5.0/PROMOTION-CRITERIA.md`** — operating rules for status transitions + category-specific completion criteria + 26 validation gates G1–G26.
- **`docs/v3.5.0/MODULE-STATUS-MATRIX.md`** — 37-entry canonical matrix (34 component rows + 3 infrastructure rows).
- **`docs/v3.5.0/COMPONENT-COVERAGE-MAP.md`** — 3 distribution maps (TOC × Category, Wave × Status, Infrastructure dependency graph).
- **`docs/v3.5.0/33-COMPONENT-INVENTORY.md`** — TOC taxonomy inventory + Phase 0B reconciliation decisions.

### Infrastructure dependency principle (one-line)

```
Infrastructure modules may be public dependencies without becoming
public components.

A component module may depend on infrastructure runtime.
Infrastructure modules MUST NOT absorb consumer-specific semantics.
```

```
인프라 모듈은 public dependency가 될 수 있지만, public component
그 자체는 아니다. 컴포넌트는 인프라 런타임에 의존할 수 있지만,
인프라 모듈이 소비자 컴포넌트의 의미론을 흡수해서는 안 된다.
```

Current infrastructure providers (per `MODULE-STATUS-MATRIX.md §4`):
- `popover/` — anchored-surface runtime; 5 consumers
- `ripple/` — state-layer Pattern A primitive; 13 consumers
- `icon-system/` — Material Symbols + SVG track + icon button base; 10 consumers

Component modules consuming infrastructure declare the dependency in their audit doc per `PROMOTION-CRITERIA.md §6` template.

## Module taxonomy

Modules fall into two categories, formalized at v3.4.9:

```
Interaction modules validate behavior.
Component modules expand baseline components into full-spec, measurement,
variant, and WordPress mapping surfaces.
```

| Category | Authoring focus | Typical artifacts | When it applies |
|---|---|---|---|
| **Interaction module** | runtime behavior — event/state/dismiss/focus/queue/keyboard/reduced-motion | `lab-*.js` (required), `lab-*.css` (runtime layer), pattern HTML with live demos, audit with a11y risk register | When a baseline visual primitive exists but its interaction layer needs isolated audit (carousel, ripple, search-expansion, popover, tooltip, date-time) |
| **Component module** | full M3 specification — measurements, variants, states, WordPress mapping | `lab-*.css` (full spec), pattern HTML with full variant matrix, multi-doc audit (spec / measurement / WP mapping) | When a baseline component needs richer documentation than fits in `style-guide.html` (chip, future text-field, future FAB) |

The categories are not exclusive — a Component module may grow a small `lab-*.js` later if filter-state or other behavior cannot be represented natively. The category records the **primary authoring focus**, not a strict capability boundary.

The v3.3.2 – v3.4.7 modules are all Interaction modules. The Beer-CSS-derived interaction-module family closed at v3.4.6 tooltip. Date-time (v3.4.7) is the only interaction module outside that lineage. **Chip (v3.4.9) is the first Component module.**

## Design principles (cross-module)

These principles apply to every module, both Interaction and Component categories.

### 1. Visible control must map to real runtime behavior

This is the core principle. Source: WordPress/M3 binding feedback (see `bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md`).

If a control is rendered on the page, it must:

- Take a real action, OR
- Carry real state (selected, expanded, checked), OR
- Be visibly disabled with a stated reason

A control that **looks clickable but does nothing** (or appears to change state but doesn't propagate to runtime) is a UX anti-pattern. It signals that the rest of the surface is untrustworthy. The principle applies inside pattern pages, inside module audits' demo sections, and inside any future Theme/Plugin integration.

### 2. Prefer native form semantics over JS-emulated state

When a behavior is natively representable, prefer the native primitive:

- **Selected toggle** — `<input type="checkbox">` / `<input type="radio">` with `:checked` styling. Not a `<button aria-pressed>` unless the action is genuinely a button (one-shot command), not a state toggle.
- **Disabled** — the `disabled` attribute on form controls, not `aria-disabled` alone on a non-control element.
- **Group of mutually exclusive options** — `<input type="radio">` group, not a custom roving-tabindex listbox unless the listbox semantics are actually required.

A module may add JS when behavior **cannot** be represented natively (e.g., menu popover positioning, tooltip aria-describedby lifecycle, carousel scroll snap orchestration). Native-first is a default, not a rule against JS.

### 3. No fake controls in demos

Demo sections inside pattern HTML files must follow the same rule as real implementations. A demo that shows three "filter chips" without `:checked` state is a fake control — it suggests filter behavior that doesn't exist. Either:

- Wire the demo to native form state and let users actually toggle it, OR
- Render the chips as static visual specimens with `aria-hidden="true"` and a visible label clarifying "visual specimen — not interactive".

The forbidden pattern is "clickable visual that pretends to be a control".

### 4. Lab-internal scope is enforced at the publish-surface boundary

The publish generator does not flatten module JS, pattern HTML, or docs to `styleguide/`. This is intentional. A module's runtime is verified inside its pattern page; baseline promotion is a separate Charter §1 decision. Mixing a module's live runtime into the baseline styleguide before promotion creates a "is this a stable baseline or a lab experiment?" ambiguity that the publish boundary prevents.

## Module inventory

| Module | Extracted | Source | Pattern reference | Promotion status |
|---|---|---|---|---|
| `carousel/` | v3.3.2 | benchmark Material You section | `compare/Material You Slider.html` | Lab-internal; pending visual QA |
| `ripple/` | v3.3.3 | benchmark `enableRipple()` | Beer CSS (frozen ref) | Lab-internal; PASS five-criterion audit; promotion eligible |
| `search-expansion/` | v3.3.4 | benchmark `enableSearchBar()` | Beer CSS (frozen ref) | Lab-internal; PASS five-criterion audit; promotion eligible |
| `icon-system/` | v3.4.2 + v3.4.3 + v3.4.4 | scope audit + ax-icon-button runtime + Pass 2 conversion + WP wmark specimen | Google Material Symbols + `@wordpress/icons` + Social Icons + Icon Block | Lab-internal; 7 docs incl. 2 mapping audits; 50 chrome glyphs converted (ax-icon-button 40 + ax-button-icon 5 + chip 4 + search-bar 1); FAB/list/menu/text-field/sg-chrome conversion deferred |
| `popover/` | v3.4.5 | benchmark menu/popover (6 functions): `makeMenu`, `positionMenu`, `openBenchmarkMenu`, `closeBenchmarkMenu`, `enableAnchoredMenuDemos`, `enableSplitButtonMenus` | Beer CSS (frozen ref) | Lab-internal; live runtime only inside `lab-popover-pattern.html`; explicitly NOT promoted to baseline theme interaction layer per v3.4.5 decision (same posture as ripple) |
| `tooltip/` | v3.4.6 | benchmark tooltip (3 functions): `createTooltip`, `positionTooltip`, `enableTooltips` | Beer CSS (frozen ref) | Lab-internal; live runtime only inside `lab-tooltip-pattern.html`; explicitly NOT promoted to baseline. **Fifth and final Beer-CSS-derived interaction module** — closes the family. Rich variant is visual-specimen-only at v3.4.6; interactive wiring deferred to BACKLOG #16 |
| `date-time/` | v3.4.7 | benchmark date/time picker (2 functions + helpers): `enableDateBenchmarks`, `enableTimeBenchmarks`. 1,112 lines combined (684 JS + 428 CSS) — largest extraction by an order of magnitude | **GPT Codex** (NOT Beer CSS) | Lab-internal; live runtime only inside `lab-date-time-pattern.html`; explicitly NOT promoted to baseline. **First interaction-module extraction outside the Beer CSS lineage** — Beer CSS family closed at v3.4.6 tooltip. WAI-ARIA Date Picker grid navigation pattern intentionally NOT wired (carry-over policy) — routed to BACKLOG #19 |
| `chip/` | v3.4.9 | **No extraction** — Component module expanding the existing baseline `.chip` primitive (`components.css §11`, L1626–L1743, UNCHANGED). | n/a (Component module) | Lab-internal; live runtime only inside `lab-chip-pattern.html`. **First Component Full-Spec Module** — establishes the 3-doc audit template (`-SPEC` / `-MEASUREMENT` / `-WP-MAPPING`) for future Component modules. JS deferred (filter chip state via real `<input type="checkbox/radio">` + `<label>`; close button via real `<button>` + `aria-label`). Elevated variants → BACKLOG #23. BACKLOG #4 (Chip Measurement Audit) closed here. |
| `snackbar/` | v3.4.10 | **No extraction** — Runtime module filling the runtime layer that the baseline explicitly carved out (`components.css §14` L2041 comment: *"positioning + queue management live in prototype JS"*). Baseline §14 (5 base selectors, full state-layer Pattern A, 11 total rule blocks) UNCHANGED. | n/a (Runtime module) | Lab-internal; live runtime only inside `lab-snackbar-pattern.html`. **Second Interaction module outside Beer CSS lineage** (date-time was first). **Closes the transient/feedback surface trio** (popover v3.4.5 + tooltip v3.4.6 + snackbar v3.4.10). Public API: `window.labSnackbar.{show, dismiss, dismissAll}`. 5 Hard rules locked (visible root never aria-hidden, hover/focus pause, real buttons, no role=alert default, text-only live region). Phase 0 inventory correction explicitly recorded in audit §3 (bilingual). BACKLOG #15 closed; #18 (.snackbar naming) carried to v3.5.0. |

## Cross-module documents

Documents that govern more than one module live at `../docs/` (i.e. `lab/docs/`), not inside a module folder:

- `../docs/ARCHITECTURE-BOUNDARIES.md` — **project charter** (v3.4.1+): four-layer model (baseline / module / theme interaction / plugin), theme-state-vs-control rule, 7-bucket reclassification, theme-can/plugin-should split, forbidden-ancestor list, federation portability rule, frontier-theme failure-mode policy. **Read this first** when authoring a new module or deciding whether something is a module, a theme interaction, or a plugin.
- `../docs/BEER-CSS-INTAKE.md` — the Beer-CSS-specific elaboration of the charter (established v3.3.3). Every Beer-CSS-derived module must honor its nine clauses. The forbidden-ancestor list is locked to charter §5.
- `../docs/INTERACTION-AUDIT.md` — historical v3.2.2 audit that pre-dates both the module structure and the charter.

Module-specific audits live inside their own module's `docs/` folder:

- `carousel/docs/CAROUSEL-AUDIT.md`, `CAROUSEL-ONTOLOGY-CHECK.md`, `CAROUSEL-VISUAL-QA.md`
- `ripple/docs/RIPPLE-AUDIT.md`
- `search-expansion/docs/SEARCH-EXPANSION-AUDIT.md`
- `popover/docs/POPOVER-AUDIT.md`
- `tooltip/docs/TOOLTIP-AUDIT.md`
- `date-time/docs/DATE-TIME-AUDIT.md`
- `chip/docs/CHIP-SPEC-AUDIT.md`, `CHIP-MEASUREMENT-AUDIT.md`, `CHIP-WP-MAPPING.md`
- `snackbar/docs/SNACKBAR-RUNTIME-AUDIT.md`

## Adding a new module

The procedure established by v3.3.2–v3.3.4 and consolidated by v3.4.0, with charter alignment added in v3.4.1:

1. **Consult `../docs/ARCHITECTURE-BOUNDARIES.md` first.** Decide which bucket (A–G) the candidate belongs in. If it's bucket A, B, or C it's not a module — it goes into the canonical styleguide. If it's bucket D it may be a theme interaction without needing a full module. If it's bucket E it's a module. If it's bucket F it's plugin territory. If it's bucket G it should be archived, not built.
2. Audit the candidate code (in `benchmark-interactions.{css,js}` if Beer-CSS-derived, or against an applicable single-source reference like Material You Slider for carousel-shaped patterns) against `../docs/BEER-CSS-INTAKE.md` (if Beer-CSS-derived) or against the charter's forbidden-ancestor list and federation portability rule (for all modules).
3. Create `modules/<new-name>/`.
4. Author `lab-<new-name>.css`, `lab-<new-name>.js`, `lab-<new-name>-pattern.html`, and `docs/<NEW-NAME>-AUDIT.md` inside the module folder. The audit MUST include a `Bucket:` field per charter §3.
5. (Historical, retired at v3.4.8) Earlier modules added `EXTRACTED` block comments to `benchmark-interactions.{js,css}` per Charter §EXTRACTED policy. Those source files were deleted at v3.4.8 Benchmark Surface Deletion. New modules from v3.4.9 onward do not perform this step — each module's audit doc records its own provenance independently, with `v3.4.8 Deletion Notice` blocks in pre-v3.4.8 audit docs noting that quoted line numbers are historical.
6. Run `python3 tools/generators/publish_styleguide.py` to flatten the new module CSS into the publish surface.
7. Run `python3 tools/validators/validate_theme_pilot.py` and confirm 1.000 / 1.000.
8. Update `../docs/BEER-CSS-INTAKE.md` (if applicable — Beer CSS family closed at v3.4.6; future modules typically will not be Beer-CSS-derived) cross-reference table.
9. Write the CHANGELOG entry.

The publish generator's module discovery is simply a `glob` over `modules/*/lab-*.css`, so adding a new module requires no tooling changes.
