# Beer CSS — Intake Contract


> **v3.4.8 Deletion Notice (added retrospectively):**
> This intake contract was authored during the v3.3.x–v3.4.6 cycle while the
> Beer CSS-derived interaction module family (carousel, ripple, search-expansion,
> popover, tooltip — 5 modules total) was being extracted. The intake family
> closed at **v3.4.6 tooltip**. At **v3.4.8**, the benchmark source files
> (`scripts/benchmark-interactions.js`, `stylesheets/benchmark-interactions.css`,
> `style-guide-benchmark.html`) were removed from the active tree.
>
> Two consequences for this contract:
>
> 1. Rule 4 of the intake workflow ("Add EXTRACTED markers to the benchmark copies")
>    is now **historical** — there are no benchmark copies to mark. The markers
>    that were added during v3.3.2–v3.4.7 are preserved in git history and zip
>    freezes; the active tree no longer carries them.
> 2. The line ranges quoted in the inventory table (item 1 Ripple, item 8 General
>    JS helpers, etc.) refer to **historical** locations in the deleted benchmark
>    sources. Each entry's audit doc carries its own per-module provenance.
>
> No future Beer CSS extractions are planned. If a future module ever needs to
> reference Beer CSS, it would author from scratch (not from a verbatim intake).

> Cross-module scope contract for any interaction patterns that originated
> from the Beer CSS reference. This document is *not* per-module; it sets
> the rules of engagement that every Beer-CSS-derived lab module
> (`lab-ripple.*`, `lab-search-expansion.*`, `lab-popover.*`, etc.) must
> honor.
>
> Authored at v3.3.3 before the first Beer-CSS-derived module
> (`lab-ripple`) was extracted. Per-module audits live in their own
> `<MODULE>-AUDIT.md` files and may reference this contract.
>
> **Relationship to the project charter (v3.4.1):**
> This contract is the Beer-CSS-specific elaboration of the rules in
> `ARCHITECTURE-BOUNDARIES.md`. The forbidden-ancestor list in §1 below
> is locked to `ARCHITECTURE-BOUNDARIES.md §5`; the component-scope
> allowlist composes with the charter's bucket classification (§3) and
> the theme-can/plugin-should split (§4). When a clause here and a
> clause in the charter conflict, the charter wins — open an issue
> to reconcile.

## Why this contract exists

The Beer-CSS-derived items in `benchmark-interactions.{css,js}` were not
authored as carefully curated Axismundi code. They were *opportunistic
imports* via GPT Codex: the goal at the time was to determine whether the
pattern is even portable (does it work side-by-side with the existing
Axismundi components), not to ship clean Axismundi-native code.

This is fundamentally different from the Material You morph slider that
v3.3.2 extracted as `lab-carousel`. The Material You slider was a
*reference-specific*, single-source pattern that had already been
re-tokenized into Axismundi vocabulary during the original benchmark
authoring. Beer-CSS-derived items did not get the same treatment.

So the v3.3.3+ procedure for Beer-CSS-derived patterns is:

```
1. Audit the benchmark code against this intake contract.
2. Decide: extract-with-refinement OR reimplement-from-spec.
3. Build the module as Axismundi-native (not a verbatim port).
4. Add EXTRACTED markers to the benchmark copies (audit trail).
5. Update this contract if new constraints surface.
```

## Reference inventory

The Beer CSS reference lives in the project's frozen `compare/` workspace:

```
backup/sample/compare/Beer CSS - Build material design ... .html
backup/sample/compare/Beer CSS - Build material design ... _files/
```

The `compare/` folder is not in the active source tree of this monorepo —
it is the user's personal reference workspace and is *not authoritative*.
Anything brought into Axismundi from Beer CSS must be re-evaluated against
the intake contract below, never directly mirrored.

## Beer-CSS-derived items currently in benchmark

| # | Item | Benchmark file evidence | v3.3.x target |
|---|---|---|---|
| 1 | **Ripple** | `benchmark-interactions.css` L74-106, `benchmark-interactions.js` L35-64 `enableRipple()` | **v3.3.3 — Ripple Module Extraction** |
| 2 | **Anchored popover menu / Split-button menu** | CSS `.ax-menu.ax-benchmark-popover` block, JS `enableAnchoredMenuDemos()` / `enableSplitButtonMenus()` | v3.3.5 |
| 3 | **Search bar focus expansion** | CSS `.ax-search-bar.ax-benchmark-*` block, JS `enableSearchBar()` | **v3.3.4 — Done** |
| 4 | **Tooltip** | JS `createTooltip()` / `positionTooltip()` / `enableTooltips()` | **v3.4.6 — Done** |
| 5 | **Modal variants** | JS `setPortalOpen()` / `openBenchmarkModal()` / `enableBenchmarkModals()` | held — superseded by native `<dialog>` per v3.2.2 audit; revisit only if a theme need surfaces |
| 6 | **Date / Time picker (interaction layer)** | JS `enableDateBenchmarks()` / `enableTimeBenchmarks()` (visual structures already in main `components.css`) | v3.3.6 — interaction audit only, not module extraction |
| 7 | **Slider value chip** | partial — JS `updateSliderValue` / `enableSliderValueChips`. Pattern already in main `components.css` per v3.2.2 audit. | re-verify in v3.3.6 |
| 8 | **General JS helpers** (banner, qs/qsa, onReady, isDisabled, clamp) | top of `benchmark-interactions.js` | wholesale import **forbidden** — each module copies only what it needs |

## Intake contract — the rules every Beer-CSS-derived module must follow

### 1. Component-scope discipline

Allowed scope (these are the only DOM surfaces that Beer-CSS-derived
interactions may attach to or restyle):

```
.ax-button
.ax-icon-button
.ax-chip            (chips defined in components.css §11)
.wp-block-button__link  — only when rendered inside theme chrome / styleguide demo, NOT inside post content
.ax-menu__item, .nav-bar__item, .nav-rail__item, [role="tab"] — case-by-case per module
```

Forbidden scope:

```
* (global element selectors)
.prose *                     — long-form content territory
.wp-block-post-content *     — federated content territory
.entry-content *             — classic-theme post body
[contenteditable]            — editor surfaces
```

Every module must include a JS-side bail-out:

```js
if (event.target.closest('.prose, .wp-block-post-content, .entry-content, [contenteditable]')) {
  return;
}
```

before doing anything observable. The CSS side should additionally avoid
selectors that could match inside those scopes by accident (no overly
generic element selectors, no `:where(*)` traps).

### 2. Naming discipline

Drop `ax-benchmark-` prefix on extraction. Module classes are
`ax-<feature>` (e.g. `ax-ripple`, `ax-ripple-host`). The `benchmark`
identifier conveys lab-experimental status and does not belong on the
extracted module's surface.

Exception: if a benchmark class is referenced by external code that we
do not control, the legacy class stays as a compatibility alias and is
documented in the module's audit.

### 3. Tokenization discipline

No hardcoded magic numbers for tone, color, motion, or spacing. Every
literal becomes either:

- a `var(--md-sys-*)` token from `tokens.css`, OR
- a module-local custom property (e.g. `--ax-ripple-opacity`) declared at
  the module's `:root` or local scope, with a comment explaining why the
  M3 token didn't fit.

Acceptable exceptions (must be justified in the module's audit):

- Geometric primitives (e.g. `50%` for a circle's `border-radius`) — these
  are math, not design tokens.
- Computed-in-JS values written to inline `style` (e.g. ripple position
  from pointer event) — these are runtime, not design tokens.

### 4. Reduced-motion discipline

Every module that animates must include an explicit
`@media (prefers-reduced-motion: reduce)` rule. Speeding the animation
to `1ms` is **not** sufficient — the user agent still fires `animationstart`
/ `animationend` events, the layout still occurs, and the effect still
registers as a flash. The rule must either suppress the animation
(`animation: none`) or replace it with an instant state change. The
module's audit must state which approach was chosen and why.

### 5. JS-disabled fallback discipline

When `lab-<feature>.js` does not run (script blocked, error before
attachment, user agent without JS), the page must still be usable. This
mirrors Criterion 1 of `INTERACTION-AUDIT.md`. For ripple specifically:
no ripple is fine; the button still works. For search expansion: the
input still accepts focus and submits a query. For popover: the trigger
falls back to a native `<details>` or `<select>` semantics where possible.

### 6. No global event-listener proliferation

A single `document.addEventListener('pointerdown', ...)` for ripple is
acceptable (and matches the M3 ripple pattern). But:

- Use **one** delegating listener per module — not one per element.
- Use a closed-set allowlist via `.closest(<selector>)` rather than
  `event.target.matches(<selector>)`.
- Apply the `.prose` / federation bail-out from §1.
- Mark the listener `{ passive: true }` whenever the handler does not
  call `event.preventDefault()`.

### 7. State-layer awareness

Axismundi components.css §0 already defines a State-layer foundation
(hover / focus / pressed tint via `color-mix` + state-layer-opacity
tokens). New interaction modules must **complement**, not duplicate,
that foundation. For example:

- Ripple is an animated radial wave on press; it sits on top of the
  pressed state layer. Both should be visible — the state layer
  remains until pointer-up, while the ripple completes its keyframe.
- Hover state layer is purely CSS (no JS); interaction modules should
  not also re-implement hover behavior.

### 8. Federation portability check

If the interaction affects content visible inside `core/post-content`
or any block that is part of the post body, the federation behavior
must be considered (federated consumers may not load the lab JS). For
v3.3.3 ripple this is trivially satisfied — ripple is theme chrome
only, never inside post content per §1.

### 9. Audit trail policy

When a Beer-CSS-derived item is extracted into a lab module, the
benchmark CSS/JS sections it came from get an `EXTRACTED: vX.Y.Z →
<path>` block comment and stay in place. This mirrors the v3.3.2
carousel policy. Benchmark-wide prune is deferred to v3.4.x / v3.5.0.

## Cross-references

| Module | Audit doc | Status |
|---|---|---|
| `modules/ripple/` | `modules/ripple/docs/RIPPLE-AUDIT.md` | **Done — v3.3.3** |
| `modules/search-expansion/` | `modules/search-expansion/docs/SEARCH-EXPANSION-AUDIT.md` | **Done — v3.3.4** |
| `modules/popover/` | `modules/popover/docs/POPOVER-AUDIT.md` | **Done — v3.4.5** |
| `modules/tooltip/` | `modules/tooltip/docs/TOOLTIP-AUDIT.md` | **Done — v3.4.6** |
| (date/time/modal) | revisit at later v3.4.x phase | held |

When a new module audit is added under this contract, append it here.

## Change log

- **v3.3.3 — initial draft.** Authored before the ripple module was
  extracted. Eight intake rules established (component scope, naming,
  tokenization, reduced motion, JS-disabled fallback, listener
  proliferation, state-layer awareness, federation portability) plus the
  v3.3.2 audit-trail policy carried forward as rule 9. Reference
  inventory captures eight items currently in benchmark.
- **v3.3.4 — search-expansion extraction confirmed contract works
  outside ripple's scope.** No rule changes. Per-instance listener
  pattern (vs ripple's delegated listener) confirmed compatible with
  §6 — that rule says "one delegating listener per module *when a
  delegating listener can cleanly cover all instances*", and search
  is naturally per-instance (each bar has its own ARIA wiring + popup
  id). Documented this nuance in `SEARCH-EXPANSION-AUDIT.md §Module-
  comparison notes`.
- **v3.4.0 — Lab Module Restructure (path conventions updated).** No
  rule changes. Module file locations moved from flat `lab/stylesheets/`
  + `lab/scripts/` + `lab/` + `lab/docs/` to nested
  `lab/modules/<name>/{lab-<name>.css, lab-<name>.js, lab-<name>-pattern.html, docs/}`.
  Per-module audit references in this contract's cross-reference table
  updated to point at the new locations. The `lab-<name>` file-prefix
  retained inside each module folder — rationale documented in
  `lab/modules/README.md §Why each file keeps its lab- prefix` (it
  preserves file identifiability after publish-surface flattening).
  New modules authored from v3.4.1 onward (popover, etc.) follow the
  module-folder layout from inception; this contract's clauses §1–§9
  apply identically regardless of whether the module lives in flat or
  nested layout.
- **v3.4.1 — charter alignment.** No rule changes. Added top-of-file
  note linking this contract to the new project charter
  `ARCHITECTURE-BOUNDARIES.md`. The forbidden-ancestor list in §1
  is now formally locked to charter §5 — any future change to the
  list must update both documents in the same commit. The component-
  scope allowlist (§1) is reframed in charter terms as a D-bucket
  (theme interaction) constraint — items in E/F buckets have their
  own per-module scope rules.

Future revisions append a new entry here when a constraint changes
materially (e.g. a new forbidden DOM surface is discovered, or a
tokenization rule is loosened with rationale).
