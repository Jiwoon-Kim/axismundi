# Badge Record Audit

> **Status**: v3.5.13 Phase 5 closed record-only audit.  
> **Category**: Baseline-only Record.  
> **Matrix row**: #25 Badge.  
> **Verdict**: RECORD remains RECORD; no lab module required.

---

## §0 — Record Status

Badge is a Baseline-only Record.

```txt
No lab-badge.css
No lab-badge.js
No lab-badge-pattern.html
No SPEC / MEASUREMENT / WP-MAPPING trio
```

Badge is a small visual attachment primitive. Its real complexity is host
placement, not standalone component behavior.

---

## §1 — Baseline Inventory

Baseline source:

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css §18
```

Selectors:

```txt
.ax-badge
.ax-badge:empty
.ax-badge.is-large
.ax-badge.is-inline
.has-badge
```

Current baseline:

```txt
Small dot:
  6px × 6px
  3px radius
  empty content

Large numeric:
  min-width 16px
  height 16px
  padding-inline 4px
  8px radius
  label-small typography

Positioning:
  absolute badge anchored to host icon corner
  transform translate(50%, -50%)
  .has-badge marks the positioning parent
```

Color:

```txt
Container: error
Label:     on-error
```

---

## §2 — M3 / WordPress Mapping

M3 role:

```txt
Badge communicates count, presence, or status attached to another surface.
It is not a standalone control.
```

WordPress mapping:

```txt
Core block mapping:
  none as a standalone block.

Theme territory:
  visual badge primitive and host helper.

Plugin territory:
  notification counts,
  unread state,
  async updates,
  commerce/cart counters,
  moderation counters.
```

Badge should be consumed by host components such as Icon button, App bar, Nav
bar, Nav rail, Tabs, Menu, or custom plugin chrome.

---

## §3 — Dependency / Composition Notes

Dependencies:

```txt
ripple/       NONE. Host owns ripple if interactive.
icon-system/  NONE by itself; badge often attaches to icon hosts.
popover/      NONE by itself.
state-layer   NONE by itself.
```

Host-positioning contract:

```txt
For icon-only hosts:
  `.has-badge` may be placed on the icon wrapper or button inner icon slot.

For label-bearing hosts:
  `.has-badge` should be placed on the icon wrapper, not the outer button, so
  the badge anchors to the icon corner rather than the full label-bearing
  surface.
```

This contract is already documented in the baseline comments.

---

## §4 — Accessibility / Reduced Motion

Badge itself is not focusable.

Accessible name policy:

```txt
Decorative badge:
  aria-hidden="true" is acceptable when the host already communicates status.

Informative count:
  The host control/link should include the count in its accessible name or
  description.

Async count update:
  Plugin/runtime layer may need aria-live; badge baseline does not own it.
```

Reduced motion:

```txt
N/A. Badge has no animation or runtime in baseline.
```

---

## §5 — Why No Lab Module Is Needed

Badge does not need a module because:

```txt
- it has no independent runtime,
- it has no independent interaction semantics,
- it is visually complete as a baseline primitive,
- host components own accessible names and actions,
- plugins own live count data.
```

A future host component may include badge specimens, but that does not make
Badge itself a full module.

---

## §6 — Record Verdict

```txt
Baseline inventory      PASS
Host contract           PASS
WordPress mapping       PASS
Accessibility framing   PASS
No-module decision      PASS
```

Badge remains `RECORD` in the module matrix.

---

## §7 — Cross-References

- `docs/v3.5.0/MODULE-STATUS-MATRIX.md` row #25
- `products/reference-implementations/axismundi-lab/stylesheets/components.css` §18
- Icon button #2
- Nav bar #12 / Nav rail #13 future cycles
- App bar #11 future cycle
- `docs/v3.5.13/WAVE-1-CLEANUP-PHASE-0-REPORT.md`

---

## §8 — Non-Goals

This record does not:

- create a lab module,
- edit `components.css`,
- add runtime counters,
- add aria-live behavior,
- register WordPress block styles,
- define App bar / Nav / Tabs badge compositions,
- make Badge an interactive component.
