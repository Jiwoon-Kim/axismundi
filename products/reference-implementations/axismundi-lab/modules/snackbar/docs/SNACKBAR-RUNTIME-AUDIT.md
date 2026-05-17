# Snackbar Runtime Audit — v3.4.10

> Bucket: D (theme interaction runtime — lab module)
> Charter: see `lab/docs/ARCHITECTURE-BOUNDARIES.md` §1 (four layers), §3 (bucket D), §4 (theme can / plugin should), §5 (forbidden ancestors)
> Module taxonomy: Interaction module (see `lab/modules/README.md §Module taxonomy`)
>
> Phase 1 — skeleton. Phase 2 (implementation) and Phase 3 (verdict)
> sections to be completed in subsequent phases.

## §1 — Critical framing

```
Snackbar is a runtime module, not a Component Full-Spec Module.

The baseline .snackbar primitive already defines visual chrome.
v3.4.10 adds the missing runtime layer: positioning, queue management,
timeout policy, action/close behavior, live announcement, reduced
motion, and interaction safety.
```

한글 요약:

```
Snackbar는 Component Full-Spec Module이 아니라 Runtime Module이다.

baseline .snackbar primitive는 이미 visual chrome을 정의한다.
v3.4.10은 여기에 positioning, queue, timeout, action/close, live
announcement, reduced motion, interaction safety를 추가하는 작업이다.
```

### Module category — Interaction module (Runtime)

This release positions the snackbar module on the Interaction side of the v3.4.9-established taxonomy:

| Category | Authoring focus | This release |
|---|---|---|
| **Interaction module** | runtime behavior — event/state/dismiss/focus/queue/keyboard/reduced-motion | ✓ **Snackbar Runtime Module** belongs here |
| Component Full-Spec module | full M3 spec, measurements, variants, WordPress mapping | (Chip v3.4.9 was the first of this kind) |

Specifically, snackbar's primary work is **runtime**: queue management, timeout policy, show/dismiss animation orchestration, live announcement, and a11y safety. The visual primitive is already done in `components.css §14`. This is why the audit doc here is **a single document** (matches carousel, ripple, search-expansion, popover, tooltip, date-time pattern) rather than the three-document pattern used by chip.

### Provenance

This module is NOT a benchmark extraction. The baseline snackbar primitive was authored fresh in Axismundi and explicitly carved out runtime concerns: the CSS section header at `components.css §14` L2041 contains the comment *"positioning + queue management live in prototype JS. This stylesheet defines visual chrome only."* — which precisely defines this module's scope.

The module is also NOT a Beer-CSS-derived extraction; the Beer CSS interaction-module family closed at v3.4.6 tooltip. Snackbar follows date-time (v3.4.7) as the second Interaction module outside the Beer CSS lineage, with the difference that snackbar is "runtime fill on top of an existing baseline" rather than "verbatim extraction of a benchmark interaction layer".

### Closes the transient/feedback surface trio

After v3.4.10:

```
Transient surfaces — complete:
  Popover-as-menu       ✓ v3.4.5
  Tooltip-as-description ✓ v3.4.6
  Snackbar-as-feedback   v3.4.10  ← this release
```

These three surfaces share a common property: they appear transiently in response to context or user action, then disappear. Each has different a11y and runtime characteristics (popover focuses, tooltip is descriptive, snackbar is announcement-channel) — but the family closes cleanly here.

## §2 — Baseline / module split

```
BASELINE  styleguide layer
          components.css §14 Snackbar (UNCHANGED at v3.4.10)
          ~120 lines, 5 rule blocks:
            .snackbar             (container, full visual chrome)
            .snackbar--two-line   (height variant)
            .snackbar__label      (flex slot)
            .snackbar__action     (full state-layer Pattern A — UNCHANGED)
            .snackbar__close      (24×24 + icon size — UNCHANGED)
          style-guide.html#components-snackbar (UNCHANGED at v3.4.10)
          4 specimens

LAB MODULE  theme interaction runtime layer (this module)
          lab/modules/snackbar/
          ├── lab-snackbar.css       (5 sections, post Phase 0 correction)
          │     §1 Close button hit-area expansion (coarse pointer)
          │     §2 Positioning + safe-area host wrapper
          │     §3 Open / leaving runtime states
          │     §4 Reduced motion
          │     §5 Live region utility
          ├── lab-snackbar.js        (queue + timeout + show/dismiss +
          │                          live region update +
          │                          forbidden-ancestor bail-out)
          ├── lab-snackbar-pattern.html  (live runtime demo)
          └── docs/
              └── SNACKBAR-RUNTIME-AUDIT.md  (this file)

PLUGIN    federation / data binding layer (NOT touched at v3.4.10)
          - WordPress admin notices integration
          - Gutenberg editor notices
          - WP_Notice → snackbar bridge
          - Custom block snackbar triggers
```

Charter §4 application: theme renders the snackbar surface and runtime; plugin emits the messages and contextual triggers. The runtime contract (`window.labSnackbar.show(...)`) is a public API that plugins can call.

### `.snackbar` naming inconsistency (deferred)

The baseline uses `.snackbar` (no `ax-` prefix), unlike most other Axismundi classes. This is recorded in **BACKLOG #18** and explicitly NOT addressed at v3.4.10. Reasons:

1. Renaming would require coordinated updates to baseline `components.css`, baseline specimens in `style-guide.html`, and all references.
2. The naming sweep is scheduled for v3.5.0 Public Surface Reframe (BACKLOG #11) where multiple naming inconsistencies can be batched.
3. v3.4.10 stays focused on runtime work; mixing a rename pass would obscure the runtime audit.

## §3 — Inventory (Phase 0 output + Phase 2 correction)

### Phase 0 inventory correction (recorded 2026-05-15 during Phase 2)

```
Phase 0 correction:
Initial inventory under-counted §14 Snackbar baseline rules because
indented selectors were missed by the inventory regex. Actual baseline
§14 includes styled .snackbar, .snackbar--two-line, .snackbar__label,
.snackbar__action, and .snackbar__close — five rule blocks, not two.
```

한글:

```
Phase 0 정정:
초기 inventory는 indent된 selector를 놓쳐 §14 Snackbar baseline rule
수를 과소 계산했다. 실제 baseline §14는 .snackbar, .snackbar--two-line,
.snackbar__label, .snackbar__action, .snackbar__close를 모두 스타일링한다
— 2개가 아니라 5개의 rule block.
```

The correction was caught by the Static Visual QA Gate during Phase 2 implementation, before any baseline drift could occur. This is exactly the kind of mismatch the QA gate is designed to catch — Phase 0 inventory ≠ actual baseline state, with the gap surfacing when module CSS overlapped baseline CSS. The result is a smaller module scope (no action/close styling redundancy) and a more honest audit.

### Baseline (UNCHANGED at v3.4.10) — corrected counts

| File | Range | Lines | Notes |
|---|---|---:|---|
| `components.css §14 Snackbar` | L2041–L2160 | ~120 (incl. section header + chunk-end comment) | **5 rule blocks** (corrected from initial 2-block count) |
| `style-guide.html#components-snackbar` | L2941–L2975 | 35 | 1 sub-section, 4 specimens (Korean text) |
| `scripts/style-guide.js` (snackbar refs) | L12, L65 | 2 comment-only mentions | NO snackbar runtime in scripts |

### Baseline rule block inventory — 5 rules (corrected)

| L# | Selector | Properties / role |
|---:|---|---|
| 2053 | `.snackbar` | Container: inline-flex, min-height 48px, max-width 600px, padding sm/md, inverse-surface bg + inverse-on-surface text, corner-extra-small, level3 shadow, body-medium typography |
| 2074 | `.snackbar--two-line` | Height variant: min-height 68px, align-items flex-start, padding-block md |
| 2080 | `.snackbar__label` | Text slot: `flex: 1 1 auto`, `min-width: 0` |
| 2086 | `.snackbar__action` | **Full styling INCLUDING state-layer Pattern A** — height 36px, inverse-primary color, label-large typescale, corner-extra-small radius, `::before` pseudo-element for state-layer (hover / focus-visible / active opacity tokens), focus-visible outline 2px inverse-primary |
| 2143 | `.snackbar__close` | 24×24 inline-flex container, transparent bg, inverse-on-surface color, child `svg` / `.ax-icon` sized via `--comp-icon-size-md` |

### Baseline className inventory — corrected

| Class | Styled in baseline? | Used in baseline specimens? | Notes |
|---|:---:|:---:|---|
| `.snackbar` | **✓** (corrected from earlier "✓") | ✓ | Container |
| `.snackbar--two-line` | **✓** | ✓ (2 specimens) | Two-line height variant |
| `.snackbar__label` | **✓** (corrected from earlier ✗) | ✓ (4 specimens) | Text slot, flex layout |
| `.snackbar__action` | **✓** (corrected from earlier ✗) | ✓ (2 specimens) | **Full state-layer Pattern A** — module does NOT need to fill |
| `.snackbar__close` | **✓** (corrected from earlier ✗) | ✓ (1 specimen) | 24×24 container — module adds only coarse-pointer hit-area expansion |

### style-guide.html specimen inventory (4 variants)

| # | Composition | Korean text |
|---:|---|---|
| 1 | label only | 메시지가 전송되었습니다. |
| 2 | label + action | 초안이 저장되었습니다. + 실행 취소 |
| 3 | two-line, label only | 서버 응답이 늦어지고 있습니다. ... |
| 4 | two-line + action + close | 새 버전이 발견되었습니다. ... + 새로고침 + Close icon |

### M3 §28 spec coverage from Phase 0 (corrected)

| M3 §28 element | Baseline | Module work? |
|---|:---:|:---:|
| Container 48dp / 68dp two-line | ✓ | — |
| Inverse-surface bg + inverse-on-surface text | ✓ | — |
| Corner-extra-small + level3 elevation | ✓ | — |
| Body-medium typography | ✓ | — |
| **Action button styling (inverse-primary + label-large + state-layer Pattern A)** | **✓** (baseline §14 L2086–L2140 — corrected from earlier ✗) | — (module does NOT touch) |
| **Close icon button styling (24×24 + inverse-on-surface + icon size)** | **✓** (baseline §14 L2143–L2159 — corrected from earlier ✗) | — (module does NOT touch) |
| **Close button coarse-pointer hit-area expansion (WCAG SC 2.5.5 AAA / Material touch)** | ✗ | YES — `lab-snackbar.css §1` (only this remains as styling work) |
| Position fixed/bottom + safe-area | ✗ | YES — `lab-snackbar.css §2` |
| Show/visible state class + entry/exit animation | ✗ | YES — `lab-snackbar.css §3` |
| `prefers-reduced-motion` handling | ✗ | YES — `lab-snackbar.css §4` |
| Live region utility (visually-hidden) | ✗ | YES — `lab-snackbar.css §5` |
| **Queue / timeout / aria-live update** | ✗ (no JS) | YES — `lab-snackbar.js` |
| `aria-live` / `role` attribute policy | ✗ | YES — runtime sets role="status" + aria-live="polite" + aria-atomic="true" on live region (audit §5.1) |

### Resulting module scope (after Phase 0 correction)

The module's CSS-side work is significantly smaller than the original Phase 0 inventory suggested:

```
v3.4.10 module CSS work (post correction):
  - Close button coarse-pointer hit-area expansion ONLY
  - Positioning host wrapper + safe-area
  - .is-open / .is-leaving runtime states + transitions
  - prefers-reduced-motion handling
  - Live region visually-hidden utility

NOT module CSS work anymore (baseline already provides):
  - Action button base styling (inverse-primary + label-large +
    state-layer Pattern A)
  - Close button base styling (24×24 + icon size)
  - Snackbar label flex behavior
```

The module's JS-side work is unchanged: queue + timeout + show/dismiss + live region update + forbidden-ancestor trigger check + public API.

## §4 — Runtime policies

Phase 1 locks the following runtime policies. Phase 2 implements them in `lab-snackbar.js` and `lab-snackbar.css`.

### §4.1 Queue and concurrency

```
- One snackbar is visible at a time.
- Additional snackbars enter a FIFO queue.
- When the visible snackbar dismisses, the next queued snackbar shows.
- No stacking. Stacking belongs to a toast framework, not to the
  snackbar runtime module — see OUT-of-scope below.
```

### §4.2 Timeout policy

```
Default timeouts:
  - 5000 ms for message-only snackbar (no action)
  - 7000 ms for snackbar with an action button
  - 0 (persistent) for snackbar with explicit close affordance

Timeout is configurable per show() call.

Timeout MUST pause while pointer is hovering over the snackbar.
Timeout MUST pause while keyboard focus is inside the snackbar.
Timeout resumes after hover/focus leaves (debounced as needed).

Reason for 5000 ms (vs 4000 ms Android default):
  Web reading context tends to need more time than mobile Android.
  M3 §16 guidelines permit a range; 5000 ms is the conservative web
  default within that range.

Reason for pause-on-interaction:
  WCAG 2.2 SC 2.2.1 Timing Adjustable — users must be able to extend
  their reading time. Hover/focus pause is the lightweight implementation
  of "user can extend".
```

### §4.3 Public API surface

```js
window.labSnackbar.show("Message text", {
  actionText: "Action label",       // optional
  onAction: (event) => { /* ... */ },// optional
  timeout: 5000,                    // optional, overrides defaults
  closable: false                   // optional, shows close button if true
});

window.labSnackbar.dismiss();       // dismiss current snackbar
window.labSnackbar.dismissAll();    // dismiss + clear queue
```

### §4.4 Show/dismiss orchestration

```
Show:
  1. Insert snackbar DOM into a stable container at <body> scope.
  2. Wait one paint frame (requestAnimationFrame).
  3. Add .is-open class to trigger CSS-driven entry transition.
  4. Update the single live region's textContent with the message.
  5. Start timeout (unless 0).

Dismiss (timeout, action click, close click, or dismissAll):
  1. Remove .is-open class to trigger exit transition.
  2. After CSS transition end (or fallback timer), remove DOM.
  3. Clear timeout if active.
  4. Shift queue and show next snackbar if any.
```

### §4.5 Action / close button policy

```
- Action and close are real <button> elements (Principle 1).
- Action label is required when actionText option is provided.
- onAction callback fires before dismiss; default is to dismiss after.
- Close button is shown when closable: true OR when timeout: 0.
- Close button uses aria-label="Close" (or locale equivalent) since it
  has icon-only content.
- Action button visual chrome (inverse-primary + label-large +
  state-layer Pattern A) is provided ENTIRELY by baseline
  components.css §14 L2086–L2140. The module does NOT override.
- Close button visual chrome (24×24 container + icon size) is provided
  ENTIRELY by baseline components.css §14 L2143–L2159. The module adds
  ONLY coarse-pointer hit-area expansion (lab-snackbar.css §1) via
  ::after pseudo to avoid colliding with the action button's ::before
  state-layer.
```

## §5 — A11y hard rules

These policies are **hard rules** at v3.4.10. They are NOT subject to Phase 2 re-decision.

### §5.1 Visible surface vs announcement surface separation

```
A single stable live region is used for announcements.

  <div class="lab-snackbar-live"
       role="status"
       aria-live="polite"
       aria-atomic="true"
       style="position:absolute; inline-size:1px; block-size:1px; …">
  </div>

The visible snackbar is the interactive surface.

  <div class="snackbar is-open">
    <span class="snackbar__label">…</span>
    <button class="snackbar__action">…</button>
    <button class="snackbar__close" aria-label="Close">…</button>
  </div>

The two surfaces have separate responsibilities:
  - Live region: announces text feedback once when shown.
  - Visible snackbar: provides interactive controls (action, close),
    focus targets, hover surface, pointer affordance.
```

### §5.2 Hard rules (not negotiable in Phase 2)

```
Hard rule 1 — Visible snackbar MUST NOT be aria-hidden="true".

   Reason: action and close buttons inside the snackbar must remain
   accessible to screen readers when users navigate by tab. Setting
   aria-hidden on the snackbar would orphan focusable buttons inside
   a hidden subtree, which is both incorrect a11y and a browser
   warning condition.

Hard rule 2 — Timeout MUST pause while pointer hover or keyboard focus
   is inside the snackbar.

   Reason: WCAG 2.2 SC 2.2.1 Timing Adjustable. A snackbar that dismisses
   while the user is reading or about to click the action button is a
   timed-out interactive surface that fails the criterion. Pause-on-
   interaction is the lightweight conformant implementation.

Hard rule 3 — Action and close controls MUST be real <button> elements.

   Reason: Visible control principle (lab/modules/README.md §Design
   principles §1). Clickable-looking surfaces that are not buttons
   would be fake controls.

Hard rule 4 — role="alert" is NOT used by default.

   Reason: role="alert" implies assertive interruption of screen reader
   speech, suitable for urgent error states. Snackbar is non-urgent
   transient feedback by definition (M3 §16). role="status" + aria-live
   polite is the correct level. role="alert" remains available for an
   explicit caller (e.g., a future error variant) but is opt-in, not
   default.

Hard rule 5 — The live region announces text-only feedback.

   Reason: live region content is read by screen reader on every update.
   If buttons were nested inside the live region, they would be
   announced as part of the message and again when tabbed to —
   double announcement. The live region carries only the message text
   (snackbar__label content), not the action or close button labels.
```

### §5.3 Why polite (not assertive)

```
aria-live="polite" — announce when user is idle.
aria-live="assertive" — interrupt immediately.

Snackbar is non-urgent feedback per M3 §16. Polite is correct.
If a future error-snackbar variant needs assertive interruption,
that is a separate decision and would use role="alert" + assertive,
NOT a modification of this default.
```

## §6 — Reduced motion

```
Module CSS includes a @media (prefers-reduced-motion: reduce) block that:
  - Disables the show/hide transitions (snackbar appears/disappears
    instantly).
  - Keeps all other functionality intact (queue, timeout, dismiss).

The snackbar surface itself is functional with or without motion.
The motion is purely a presentational hint that something changed,
which the live region's announcement already conveys.
```

## §7 — Forbidden ancestor / boundary

```
The lab-snackbar.js init must bail out if the document.body
matches any of:
  .prose
  .wp-block-post-content
  .entry-content
  [contenteditable=""], [contenteditable="true"]

This list matches the date-time module's broader forbidden-ancestor
set (Charter §5) rather than the popover/tooltip narrower set, because
snackbar's container lives at <body> scope and should not initialize
inside WordPress block-content surfaces or contenteditable contexts.

In practice this bail-out is a defensive measure rather than a
likely-triggered case, since snackbar usage is always initiated by
script (window.labSnackbar.show()), not by an in-DOM trigger element.
The check still protects against accidental embedding of the live
region inside a wrong-scope subtree.
```

## §8 — Five-criterion verdict

| # | Criterion | Status |
|---:|---|:---:|
| 1 | **JS-off fallback** — baseline 4 specimens in `style-guide.html#components-snackbar` render with full visual chrome without JS; runtime injection requires JS but baseline is the no-JS reference shape | ✓ PASS |
| 2 | **M3 / state-layer compatibility** — baseline `.snackbar*` UNCHANGED (5 base selectors, 11 rules including state-layer Pattern A modifiers); module CSS uses M3 system tokens only (`--md-sys-motion-curve-*`, `--space-*`, `env(safe-area-inset-bottom)`, `--md-sys-shape-corner-*`); no token-by-token override of baseline rules; close hit-area uses `::after` to avoid colliding with baseline `.snackbar__action::before` | ✓ PASS |
| 3 | **Reduced motion** — `@media (prefers-reduced-motion: reduce)` in `lab-snackbar.css §4` disables transitions and transforms on `.is-open`/`.is-leaving` states; queue, timeout, live announcement, dismiss, focus management all remain functional | ✓ PASS |
| 4 | **Keyboard / a11y** — single stable live region with `role="status"` + `aria-live="polite"` + `aria-atomic="true"` (Hard rule); visible snackbar root NEVER `aria-hidden` (Hard rule 1 — verified by QA gate); action / close real `<button>` elements with baseline-provided focus rings (Hard rule 3); timeout pauses on pointerenter + focusin and resumes on pointerleave + focusout-with-relatedTarget-check (Hard rule 2 — WCAG 2.2 SC 2.2.1 Timing Adjustable); `role="alert"` NOT default (Hard rule 4); live region announces text-only feedback (Hard rule 5) | ✓ PASS (all 5 hard rules enforced) |
| 5 | **Prose / federation isolation** — forbidden-ancestor `show()` rejection on `.prose`, `.wp-block-post-content`, `.entry-content`, `[contenteditable]`; `lab-snackbar.js` is module-scoped IIFE; live region is single stable instance at `<body>` scope; runtime never inserts snackbars inside content surfaces | ✓ PASS |

### Verdict

```
PASS as a bounded Snackbar Runtime Module.

v3.4.10 adds the missing runtime layer for the existing baseline
snackbar primitive: queue management, timeout policy, hover/focus
pause, separated live announcement, fixed positioning, reduced
motion handling, and public lab API.

The baseline snackbar visual chrome remains UNCHANGED. A Phase 2
Static Visual QA Gate corrected the Phase 0 inventory: baseline §14
already styles .snackbar__label, .snackbar__action, and
.snackbar__close. The module therefore does not duplicate or
override action/close visual chrome; it only adds runtime
positioning, state, live-region behavior, queue/timeout logic, and
coarse-pointer close hit-area expansion.

Snackbar uses a single stable live region for announcements and
keeps the visible snackbar as the interactive surface. The visible
snackbar root is never aria-hidden. Action and close controls
remain real buttons. Timeout pauses on pointer hover and keyboard
focus.
```

한글 요약:

```
제한된 Snackbar Runtime Module로 PASS.

v3.4.10은 이미 존재하는 baseline snackbar primitive에 queue, timeout,
hover/focus pause, live region, positioning, reduced motion,
public lab API를 추가한다.

Phase 2 Static Visual QA Gate에서 Phase 0 inventory 누락을 정정했고,
baseline §14가 action/close visual chrome을 이미 제공한다는 사실을
반영했다. 따라서 module은 baseline visual을 중복하거나 override하지 않고
runtime layer만 추가한다.

Snackbar는 announcement용 단일 stable live region을 사용하고, visible
snackbar는 interactive surface로 유지된다. visible snackbar root에는
aria-hidden을 부여하지 않으며, action/close는 real button이고, timeout은
pointer hover와 keyboard focus에서 pause된다.
```

### Internal contract checks (Phase 3)

- **Charter §1 / Bucket D**: confirmed — theme interaction runtime, lab module only.
- **Module taxonomy**: confirmed — Interaction module (single audit doc pattern); NOT Component Full-Spec Module. Matches carousel/ripple/search-expansion/popover/tooltip/date-time pattern.
- **Single audit doc**: confirmed — single `SNACKBAR-RUNTIME-AUDIT.md`, NOT a 3-doc Component pattern.
- **Visible control principle (Principle 1)**: applied — all clickable controls are real `<button>` elements with real handlers (baseline-styled for action and close).
- **Hard rules**: all 5 enforced visually + via QA gate:
  - **Hard rule 1** (visible snackbar root never aria-hidden) — verified by Phase 2 QA gate: 0 violations on root, the only aria-hidden setter targets the close button's `<span>` icon (correct — button itself has `aria-label="Close"`).
  - **Hard rule 2** (timeout pause on hover + focus) — verified: 4 events (`pointerenter`, `pointerleave`, `focusin`, `focusout`) + `relatedTarget` check on focusout.
  - **Hard rule 3** (action/close real `<button>`) — verified: `document.createElement('button')` called for both; baseline supplies the styling.
  - **Hard rule 4** (`role="alert"` not default) — verified: live region uses `role="status"`; `role="alert"` not present anywhere in module.
  - **Hard rule 5** (live region announces text-only) — verified: `LiveRegion.announce()` updates `textContent`, never inserts buttons.
- **Phase 0 inventory correction**: recorded explicitly in §3 with bilingual notice. The correction reduced module scope from "fill 2 baseline gaps + 4 runtime layers" to "1 hit-area expansion + 4 runtime layers". Module CSS section count went 6 → 5.
- **`.snackbar` naming inconsistency**: NOT addressed (BACKLOG #18, v3.5.0).
- **Static Visual QA Gate**: PASS after Phase 0 correction (0 actual issues; earlier 2 false positives were the QA gate's earlier-version regex limitations, both manually confirmed safe).

## §9 — What this module does NOT do

```
OUT of scope at v3.4.10:

- .snackbar → .ax-snackbar rename (BACKLOG #18, scheduled for v3.5.0)
- components.css §14 baseline mutation
- style-guide.html#components-snackbar baseline mutation
- WordPress admin notices integration (plugin territory)
- Gutenberg editor notices integration (plugin territory)
- WP_Notice → snackbar bridge (plugin territory)
- Custom block snackbar triggers (plugin territory)
- Toast notification framework (stacking, position grid, layering)
- Snackbar stacking (single-at-a-time is the policy)
- Urgent alert system (role="alert" reserved for explicit future variant)
- Error-state snackbar variant (separate future decision)
- Live region announcement of action/close button labels (Hard rule 5)
- aria-hidden on visible snackbar (Hard rule 1)
- Auto-dismiss without pause on interaction (Hard rule 2)
- Backspace/Delete keyboard dismiss on focused snackbar (could be added
  as Phase 2 nice-to-have, but not in scope; would require explicit
  decision)
- Snackbar inside .prose / WordPress block content / contenteditable
  (Forbidden ancestor bail-out, §7)
```

## §10 — One-line summary

```
Snackbar runtime separates announcement from interaction:
one stable live region announces the message, while the visible
snackbar remains a real interactive surface with pauseable timeout
and real action/close controls.
```
