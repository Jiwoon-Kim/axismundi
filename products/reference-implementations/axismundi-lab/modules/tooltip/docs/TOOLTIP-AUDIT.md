# Tooltip Module Audit — v3.4.6


> **v3.4.8 Deletion Notice (added retrospectively):**
> The benchmark source files referenced throughout this document
> (`scripts/benchmark-interactions.js`, `stylesheets/benchmark-interactions.css`,
> and `style-guide-benchmark.html`) were **removed from the active tree at
> v3.4.8 Benchmark Surface Deletion**. Line ranges and quoted file paths in
> this document are HISTORICAL references — they describe where the source
> code lived during extraction, not where it lives now. Provenance is
> preserved in this audit document, CHANGELOG.md / ROADMAP.md, the
> v3.X.Y zip freezes, and git history.

> Bucket: D (theme interaction — runtime layer)
> Charter: see `lab/docs/ARCHITECTURE-BOUNDARIES.md` §1 (four layers), §4 (theme can / plugin should), §5 (forbidden ancestor list)
> Intake: see `lab/docs/BEER-CSS-INTAKE.md` (9 rules)
>
> Lab module extraction of the hover/focus tooltip runtime present in
> `benchmark-interactions.{css,js}` as Beer-CSS-derived demos.
> Reimplemented Axismundi-native into `lab/modules/tooltip/` per the
> established pattern (carousel v3.3.2, ripple v3.3.3,
> search-expansion v3.3.4, popover v3.4.5).
>
> Fifth and likely final Beer-CSS-derived interaction module —
> after v3.4.6 the Beer CSS interaction-module family is closed.
> Snackbar (BACKLOG #15) is intentionally Axismundi-native from
> scratch, not a Beer CSS extraction.
>
> Authored at v3.4.6 (Phase 1 — skeleton; Phase 2/3 sections to be
> completed during implementation).

## Critical framing — what this module IS and IS NOT

```
Tooltip is a lab module in v3.4.6, NOT a baseline interaction layer.

Tooltip differs from popover: it does not move focus, does not use
role="menu", and exists only as a descriptive surface connected
through aria-describedby.

The baseline styleguide retains static `.ax-tooltip.is-open` specimens
(both plain and rich variants), while live hover/focus/dismiss
behavior is verified ONLY inside the tooltip module pattern page.

Same posture as popover v3.4.5 and ripple v3.3.3: lab module only,
baseline promotion deferred as a separate future Charter §1 decision.
```

## TL;DR

```
Functions extracted    3 (createTooltip, positionTooltip, enableTooltips)
                        Total: 58 lines in benchmark
Code reimplemented in  lab/modules/tooltip/lab-tooltip.{css,js}
                        ├── lab-tooltip.js          10,017 B (~280 lines)
                        ├── lab-tooltip.css          3,526 B
                        ├── lab-tooltip-pattern.html 14,832 B (5 demo sections)
                        └── docs/TOOLTIP-AUDIT.md  (this file)
Style-guide changes    NONE to .ax-tooltip primitives in components.css
                        (kept as baseline visual specimen — 142 lines,
                         M3 §34.2 plain + §34.3 rich, L3089–L3230)
benchmark mark-up      /* EXTRACTED: v3.4.6 → modules/tooltip/lab-tooltip.js */
                        block comment above L473 createTooltip (~50 lines)
Trigger selector       Narrowed from 3 → 2 (Decision B):
                        before: [data-tooltip], .ax-icon-button[aria-label],
                                .ax-button[aria-label]
                        after:  [data-tooltip], .ax-icon-button[aria-label]
                        Text priority: data-tooltip > aria-label
Rich tooltip           Visual specimen ONLY at v3.4.6.
                        Pattern HTML §5 renders static `.is-rich.is-open`
                        with tabindex="-1" + aria-hidden="true".
                        Interactive wiring deferred to BACKLOG #16.
BEER-CSS-INTAKE.md     tooltip status updated: "held — necessity
                        re-evaluated in v3.3.6" → "v3.4.6 — Done"
                        popover status updated: "planned v3.4.1" →
                        "Done — v3.4.5" (correcting stale entry)
Publish surface        stylesheets count 12 → 13 (+lab-tooltip.css)
                        Total publish surface 19 → 20 files
Validator              1.000 / 1.000 / 1.000 / 1.000 PASS
                        (A schema / B theme / C css / D runtime)
```

## Tooltip vs Popover — explicit contract divergence

This is the single most important section of this audit. The popover
runtime cannot be copy-pasted for tooltip; the trigger model and ARIA
contract are different.

| Dimension | Popover (v3.4.5) | Tooltip (v3.4.6) |
|---|---|---|
| **Trigger event** | `click` (toggle) | `pointerover` + `focusin` (show), `pointerout` + `focusout` (hide) |
| **Toggle vs show-on-hover** | Toggle — same trigger opens and closes | Asymmetric — show on enter, hide on leave |
| **ARIA: trigger** | `aria-haspopup="menu"` + `aria-expanded` (dynamic) + `aria-controls` | `aria-describedby="<tooltip-id>"` (dynamic — set on show, removed on hide) |
| **ARIA: surface** | `role="menu"` + items `role="menuitem"` | `role="tooltip"` |
| **Focus movement** | Moves into menu (first non-disabled menuitem) | **DOES NOT MOVE FOCUS.** Trigger keeps focus throughout. |
| **Keyboard navigation inside surface** | ArrowDown/Up/Home/End + Tab dismiss | None. Tooltip is read-only. |
| **Dismiss paths** | Escape (single-step) + outside-pointerdown + Tab + menuitem activation | mouseleave + blur (plain interactive) + Escape (defensive, plain only). Rich variant is a **visual specimen only** at v3.4.6 — no interactive wiring (BACKLOG #16). |
| **Single-open invariant** | One menu open at a time (module-scoped state) | One tooltip visible at a time (single DOM element reused — already in benchmark) |
| **Forbidden-ancestor bail-out** | `.prose` + `[contenteditable]` checked on click + keydown | Same forbidden ancestors, checked on `pointerover` + `focusin` |
| **Single-step Escape** | Yes — Escape always closes immediately | Plain: not needed (mouseleave/blur sufficient). Rich: yes (rich tooltips have action buttons, may be focused) |
| **rAF-deferred outside-listener attach** | Required — open-tick and dismiss-tick must not collide | Not applicable — show/hide use separate event types (pointerover vs pointerout) so collision risk doesn't exist |
| **Baseline visual primitive** | `.ax-menu` in `components.css` L2752-L2779 | `.ax-tooltip` in `components.css` L3089-L3230 (plain + rich + actions) |

## Bucket / Charter alignment

| Charter clause | Application |
|---|---|
| §1 — Four layers | Tooltip sits in the *lab module* layer for v3.4.6. Promotion to *theme interaction* is a separate future decision; v3.4.6 does NOT promote. |
| §3 — Bucket D | This module is Bucket D throughout: theme interaction runtime, no F-track, no plugin-only behavior. |
| §4 — Theme can / Plugin should | Theme provides the hover/focus descriptive runtime. Plugins are expected to add tooltip *content* via `data-tooltip` or via `aria-label` on icon-only controls. The runtime never reaches across into plugin space for content discovery. |
| §5 — Forbidden ancestors | `.prose`, `[contenteditable]`, and any element matching the forbidden-ancestor list bail out at trigger event start. See §"Forbidden-ancestor bail-out" below. |
| §6 — Federation portability | Tooltip runtime is *theme-side only*. Syndicated content in `.prose` never triggers tooltip (federation bail-out applies). Federated viewers (Mastodon, Misskey) render tooltips, if at all, in their own UI conventions — Axismundi does not project theme tooltip JS through federation. |

## Beer CSS intake summary

`benchmark-interactions.js` carries `'Beer CSS'` and `'beer-css'` markers
on the tooltip section. Per `BEER-CSS-INTAKE.md`:

| Rule | Application to tooltip |
|---|---|
| 1. Opportunistic rough transplant — not authoritative source | Acknowledged. The benchmark code is reference, not source-of-truth. |
| 2. Reimplement Axismundi-native | Yes — `lab-tooltip.js` is hand-written, not copied. |
| 3. Scope to `.ax-button` / `.ax-icon-button` / `.ax-chip` only | Tooltip selector at v3.4.6 is narrowed to `[data-tooltip], .ax-icon-button[aria-label]` (Phase 0 decision B). The `.ax-icon-button[aria-label]` half holds the contract; `[data-tooltip]` is the universal opt-in. |
| 4. No global click handler | Tooltip's nature requires always-active hover/focus listeners (single-element model, multi-trigger). Mitigation: scroll/resize listeners attach only while a tooltip is *visible* — open-scoped where it can be open-scoped. Documented as a Beer-CSS-INTAKE rule-4 partial-compliance note. |
| 5. No `.prose` leak | Trigger detection bails out if ancestor matches forbidden list. Applied to both `pointerover` and `focusin` paths. |
| 6. Token-based styling | `.ax-tooltip` primitive in `components.css` already uses M3 tokens (inverse-surface / corner-extra-small / typescale-body-small / elevation-2 / motion-fast-effects). Unchanged. |
| 7. Reduced-motion respect | `.ax-tooltip` transition already gated by `prefers-reduced-motion` in `components.css`. Confirmed. |
| 8. Documented intake | This audit doc serves as the intake record. |
| 9. Promotion decision separate from intake | Lab-only at v3.4.6. Baseline promotion is a separate future charter decision, not this release. |

### Beer CSS rule 4 partial-compliance note

Popover (v3.4.5) closed rule 4 fully: all listeners (outside-pointerdown,
Escape, resize, scroll) attach on open and detach on close. Tooltip
cannot match that posture exactly: pointer/focus enter/leave listeners
must be live across the page at all times so any trigger can fire. The
mitigation is two-tier:

```
Tier A (always-on, light):
  - pointerover (delegated, capture)
  - pointerout  (delegated, capture)
  - focusin     (delegated, capture)
  - focusout    (delegated, capture)

Tier B (visible-scoped, heavier):
  - window.scroll (capture) — for reposition
  - window.resize             — for reposition
  - document.keydown          — for Escape (rich variant)
```

Tier A is the minimum acceptable always-on surface. Tier B is gated on
`tooltip.classList.contains('is-open')` and detaches when hidden.
Documented for charter §EXTRACTED-archive transparency.

## Inventory — benchmark-interactions.js tooltip functions

| L# | Function | Body lines | Purpose |
|---:|---|---:|---|
| 473 | `createTooltip()` | 6 | Build single tooltip DOM, append to body, set `role="tooltip"`. Called once at init. |
| 481 | `positionTooltip(trigger, tooltip)` | 10 | Top-anchored with bottom fallback on viewport overflow. 8px gap, 16px viewport margin. |
| 493 | `enableTooltips()` | 42 | Single-tooltip-element reuse pattern. Delegated listeners on document + window. |
| **Total** | | **58** | Smallest extraction so far (vs popover 138 lines). |

Event types in the section:

```
document.addEventListener: 4 (pointerover, pointerout, focusin, focusout)
window.addEventListener:   2 (scroll capture, resize)
closest():                 5
.prose / contenteditable:  0  ← Charter §5 violation (missing bail-out)
aria-describedby:          0  ← critical a11y gap
```

## Extraction plan (Phase 2)

### File layout

```
lab/modules/tooltip/
├── lab-tooltip.css                  (runtime-specific layer ONLY)
├── lab-tooltip.js                   (3 functions reimplemented + a11y fixes)
├── lab-tooltip-pattern.html         (hover demo + focus demo + forbidden-ancestor demo + rich demo)
└── docs/
    └── TOOLTIP-AUDIT.md             (this file)
```

### CSS scope discipline

```
components.css  .ax-tooltip primitive (plain + rich + actions)  ← UNCHANGED
                — 142 lines, L3089–L3230, M3 §34.2 + §34.3

lab-tooltip.css  runtime-specific layer ONLY:
                 - positioning helpers (fixed positioning when wired)
                 - module pattern page demo layout
                 - keyboard-focus ring on rich tooltip actions
                   (already in components.css, but verify)
                 - reduced-motion already handled in components.css
```

Same posture as popover v3.4.5: components.css keeps the visual
primitive; lab-tooltip.css adds only what *running* the tooltip adds.

### JS reimplementation principles (carryover from popover + tooltip-specific)

Carried over from popover v3.4.5:
1. **Module-scoped state**: single tooltip element, single `activeTrigger` ref.
2. **Forbidden-ancestor bail-out helper**: reuses popover's helper pattern (`.prose`, `[contenteditable]`).
3. **Tier-B listener gating**: scroll/resize/keydown attach when visible, detach when hidden.

Tooltip-specific additions:
4. **`aria-describedby` lifecycle**: on show, `trigger.setAttribute('aria-describedby', tooltipId)`. On hide, `trigger.removeAttribute('aria-describedby')` — but only if the attribute value still matches our tooltip ID (defensive against external code that may have set its own describedby).
5. **NO focus movement**: `lab-tooltip.js` never calls `.focus()` on the tooltip or its content. The trigger keeps focus throughout the show/hide lifecycle. (Exception: rich tooltip's `__action` buttons may be focused via Tab — that's a separate user-driven action, not a runtime forced focus move.)
6. **Asymmetric show/hide event pairs**:
   - `pointerover` shows, `pointerout` hides
   - `focusin` shows, `focusout` hides
   - The two pairs are independent — keyboard focus does not need pointer to be over the trigger, and vice versa.
7. **Trigger selector narrowed** (Phase 0 decision B):
   ```js
   const TOOLTIP_TRIGGER_SELECTOR =
     "[data-tooltip], .ax-icon-button[aria-label]";
   ```
   Text-content priority: `data-tooltip` > `aria-label`.
8. **Defensive Escape**: even though plain tooltips don't strictly need Escape (mouseleave/blur suffice), lab-tooltip.js attaches a visible-scoped Escape listener that hides whatever tooltip is active. This is a defensive guard against stuck-visible states caused by edge cases (e.g., scroll jumps, focus jumps via developer tools, programmatic focus moves outside this module). Rich tooltips are **visual specimens only** at v3.4.6 — their Tab-into-action / Escape-with-focus-restoration flow is deferred to BACKLOG #16.
9. **Self-hover preservation**: rich tooltips are dismissible — `pointer-events: auto` in components.css L3150. The hide logic must not fire when the pointer moves *from trigger into the tooltip itself* (otherwise the action buttons are unreachable). Use `event.relatedTarget` to check.

### benchmark `/* EXTRACTED */` marker (Phase 2 tail)

```js
// scripts/benchmark-interactions.js
/* EXTRACTED: v3.4.6 → modules/tooltip/lab-tooltip.js
 *   createTooltip, positionTooltip, enableTooltips
 * Originals retained for benchmark archival per Charter §EXTRACTED policy.
 *
 * NOTE: benchmark version had no aria-describedby wiring, no
 * forbidden-ancestor bail-out, no Tier-A/B listener split.
 * lab-tooltip.js fixes all three.
 */
```

## a11y risk register

| # | Risk | Mitigation |
|---:|---|---|
| 1 | `aria-describedby` not wired → screen readers cannot announce tooltip content | On every `show()`, set `trigger.setAttribute('aria-describedby', TOOLTIP_ID)`. On every `hide()`, remove it (only if value still matches our ID, to avoid clobbering external describedby). |
| 2 | Forbidden ancestor (`.prose` / `[contenteditable]`) → tooltip fires inside federated/editor content | `isInForbiddenAncestor(target)` check on every trigger event. Helper shared with popover. |
| 3 | Focus accidentally moved into tooltip → user lands in floating DOM | Tooltip JS NEVER calls `.focus()`. Audit covers every focus management code path. |
| 4 | Hide-on-mouseleave fires when pointer moves into rich tooltip body → action buttons unreachable | `pointerout` handler checks `event.relatedTarget`; if it is inside the active tooltip, skip hide. |
| 5 | Rich tooltip Tab-into-action / Escape path complexity (focus restoration when an action is focused and Escape is pressed) | **Out of scope at v3.4.6.** Rich variant is shipped as a visual specimen only (BACKLOG #16). Pattern HTML's rich demo uses `tabindex="-1"` on the action buttons and `aria-hidden="true"` on the rich tooltip container so the inert specimen does not appear in the tab order. Interactive wiring deferred. |

## Forbidden-ancestor bail-out policy

Identical to popover v3.4.5:

```
.prose
[contenteditable=""]
[contenteditable="true"]
```

Check applied at the start of:
- `pointerover` handler (mouse hover entry)
- `focusin` handler (keyboard focus entry)

Federation / editor reasoning is the same — federated viewers and the
WordPress block editor should not surface theme-side tooltips inside
user content.

## Reduced motion

`.ax-tooltip` and `.ax-tooltip.is-open` transitions are defined in
`components.css` L3097-L3101 and L3106-L3110. They use
`var(--md-sys-motion-curve-fast-effects-duration)` which is
gated by reduced-motion at the token level.

`lab-tooltip.css` does NOT add a separate reduced-motion override —
the baseline primitive already handles it. This is a difference from
popover v3.4.5, where `lab-popover.css` added an explicit
`@media (prefers-reduced-motion)` block because the menu transition
was not centrally token-driven.

If future tooltip-specific motion is added (e.g., entry/exit slide),
the reduced-motion override moves into `lab-tooltip.css`.

## Decisions captured (Phase 0)

### Decision B — Trigger selector narrowed

Status: ✓ Accepted in Phase 0.

```
Before (benchmark):
  [data-tooltip], .ax-icon-button[aria-label], .ax-button[aria-label]

After (v3.4.6):
  [data-tooltip], .ax-icon-button[aria-label]
```

Rationale: `.ax-button` typically has visible label text; surfacing
its `aria-label` as a tooltip duplicates information that is already
on screen. `aria-label` on `.ax-button` exists for accessibility
correction, not for visible-tooltip exposure. `.ax-icon-button` is
icon-only and benefits from tooltip exposure of its `aria-label`.

Authors who want a `.ax-button` to show a tooltip add `data-tooltip="…"`
explicitly — the universal opt-in route remains available.

### Decision Y — Touch / show-delay refinement deferred

Status: ✓ Accepted in Phase 0; routed to BACKLOG #16.

v3.4.6 implements the minimal accessible hover/focus runtime only:

```
hover show
focus show
mouseleave hide
blur hide
Escape hide (rich variant only)
aria-describedby
forbidden-ancestor bail-out
visible-scoped scroll/resize listener
```

Deferred to BACKLOG #16 "Tooltip delay and touch long-press refinement":
- hover show delay (~500ms per M3 §34)
- touch long-press trigger
- rich tooltip timing nuances
- mobile dismissal behavior
- pointer coarse/fine media-query switching
- reduced-motion delay tuning

## Five-criterion promotion verdict

| # | Criterion | Status |
|---:|---|:---:|
| 1 | **JS-off fallback** — static `.ax-tooltip.is-open` specimens (plain + rich) remain readable in components.css; live runtime is progressive enhancement scoped to this pattern page | ✓ PASS |
| 2 | **M3 / state-layer compatibility** — reuses existing `.ax-tooltip` visual primitive (M3 §34.2 plain + §34.3 rich) in `components.css`; lab-tooltip.css adds only token-aligned runtime helpers (no parallel visual primitive) | ✓ PASS |
| 3 | **Reduced motion** — primitive transitions use `--md-sys-motion-curve-fast-effects-duration` (token-gated by reduced-motion); lab-tooltip.css intentionally adds no separate motion override | ✓ PASS |
| 4 | **Keyboard / a11y (plain tooltip)** — `aria-describedby` lifecycle (set on show, removed on hide with defensive guard); `role="tooltip"` on singleton; focus stays on trigger throughout; defensive Escape dismisses any stuck-visible state. **Rich tooltip interactive wiring deferred to BACKLOG #16.** | ✓ PASS |
| 5 | **Prose / federation isolation** — `isInForbiddenAncestor()` bail-out checks `.prose`, `[contenteditable=""]`, `[contenteditable="true"]` on both `pointerover` and `focusin` entry paths. Pattern HTML §4 includes explicit forbidden-ancestor demo with both `.ax-icon-button` and `[data-tooltip]` triggers inside `.prose` (must not fire). | ✓ PASS |

### Verdict

```
PASS as a lab module.

The tooltip runtime satisfies the v3.4.6 lab-module criteria for plain
hover/focus descriptive tooltips: aria-describedby lifecycle,
role="tooltip", forbidden-ancestor isolation, visible-scoped heavy
listeners, Escape dismiss, and reduced-motion compatibility.

It is NOT promoted to the baseline styleguide or theme interaction
layer in v3.4.6. Promotion is a separate future decision under Charter
§1 and is intentionally deferred.

Rich tooltip behavior remains visual-specimen-only and is deferred to
BACKLOG #16 (Tooltip delay and touch long-press refinement, expanded
to include rich tooltip interactive wiring at refinement time).
```

한글 요약:

```
v3.4.6 기준 tooltip은 lab module로 PASS.
다만 baseline styleguide나 theme interaction layer로 승격된 것은 아니다.
Rich tooltip의 interactive 동작은 visual specimen으로만 두고
BACKLOG #16으로 연기됨.
```

### Internal contract checks (for traceability — not part of the user-facing 5-criterion verdict)

These confirm the same conclusions from a different angle:

- **Charter §1 / Bucket D**: confirmed — runtime sits in lab module layer, never touches baseline.
- **Beer CSS intake (BEER-CSS-INTAKE.md, 9 rules)**: confirmed — see §"Beer CSS intake summary" above. Notable: rule 4 ("no global click handler") is satisfied with the **Tier A / Tier B partial-compliance pattern** documented in this audit. Tier A (pointer + focus enter/leave) stays always-on by tooltip nature; Tier B (scroll/resize/keydown Escape) attaches only while visible.
- **a11y risk register (5 risks)**: confirmed for items 1–4. Item 5 ("rich tooltip Tab + Escape complexity") is **out of scope** at v3.4.6 by design — rich variant is visual-specimen-only.
- **Forbidden-ancestor bail-out (Charter §5)**: confirmed — single helper `isInForbiddenAncestor()`, shared shape with popover v3.4.5, applied on both `pointerover` and `focusin` event paths.
- **Reversibility (Charter EXTRACTED policy)**: confirmed — benchmark originals retained verbatim at `scripts/benchmark-interactions.js` L473–L535 with ~50-line block comment above documenting the extraction.
- **Beer CSS interaction family closure**: confirmed — after v3.4.6, all five Beer-CSS-derived interactions (carousel v3.3.2, ripple v3.3.3, search-expansion v3.3.4, popover v3.4.5, tooltip v3.4.6) are extracted as lab modules. Snackbar (BACKLOG #15) is explicitly Axismundi-native from scratch, not a Beer CSS extraction.

## What this module does NOT do

- Does not promote tooltip/menu to baseline theme interaction layer
  (deferred — separate Charter §1 decision).
- Does not implement hover show delay or touch long-press (BACKLOG #16).
- Does not redesign `.ax-tooltip` visual primitive in `components.css`.
- Does not expose `aria-label` from `.ax-button` as tooltip text
  (Decision B — narrowed selector).
- Does not handle snackbar (BACKLOG #15, separate Axismundi-native module).
- Does not change the styleguide-vs-module UX layout — that is
  BACKLOG #11, v3.5.0 candidate.
- Does not edit `NOTICE.md` / `LICENSE-MATRIX.md` "Code audit pending"
  language — that is a separate Public Surface / License Audit phase,
  not a Beer CSS extraction concern.
