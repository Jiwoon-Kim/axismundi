# v3.6.1 — Token Architecture Refactor — Phase 3 Visual QA

Date: 2026-05-19

## Verdict

Phase 3 visual QA passes with two non-blocking WordPress core block findings
routed to BACKLOG #43.

The computed validator already proves the functional dark-mode chain:

```txt
click -> data-theme -> CSS variable -> rendered DOM
```

This visual QA pass confirms the visible surfaces are acceptable for v3.6.1
release close and records the remaining core-block option gap without expanding
the token architecture scope.

## Surface Matrix

```txt
axismundi-lab public styleguide
  light: PASS
  dark:  PASS

axismundi-pilot front page
  light: PASS
  dark:  PASS

axismundi-pilot pattern QA page
  URL:   http://localhost:8888/?page_id=10
  light: PASS with routed P3 findings
  dark:  PASS with routed P3 findings

Korean prose single/page surfaces
  light: PASS
  dark:  PASS
```

## Checks

```txt
Text contrast:
  PASS

Button variants:
  PASS

Surface containers:
  PASS

Korean prose rhythm:
  PASS

Dark mode visual character:
  PASS

Console / network errors:
  PASS
```

## Routed Findings

### Finding 1 — Table Footer Border

```txt
Priority:
  P3 / non-blocking

Surface:
  axismundi-pilot pattern QA page
  http://localhost:8888/?page_id=10

Selector:
  .wp-block-table tfoot

Observed core/default style:
  border-top: 3px solid;
  border-top-color: currentcolor;

Finding:
  Table footer top border reads stronger than intended against the current
  Axismundi/M3 surface treatment.

Route:
  BACKLOG #43 — WP core block specimen wall / full variation audit
  Candidate input for BACKLOG #41 if the final fix belongs in the broader
  WordPress block bridge/reset layer.

Rationale:
  This is a native/core block option leakage exposed by enabling the table
  footer variation. It does not invalidate v3.6.1 token architecture, but it
  confirms that full core block styles/options need specimen-wall coverage
  before broad block bridge expansion.
```

### Finding 2 — Core Button Semantic / Variant Boundary

```txt
Priority:
  P3 / non-blocking

Surface:
  axismundi-pilot pattern QA page
  WordPress core/button and core/buttons variants

Observed behavior:
  WordPress core/button renders link-based markup, so native link affordances
  such as underline can leak into the M3 button surface.

  Button text should also avoid accidental text selection/drag behavior inside
  the visual button affordance.

Finding:
  Axismundi styleguide button specimens use <button>, while WordPress core
  button blocks commonly render <a>. The visual M3 button variants therefore
  need a deliberate semantic boundary decision rather than a one-off reset.

Open questions:
  Should M3 button variants be implemented as additional core/buttons styles?
  Is a custom block needed for semantic or accessibility parity in some cases?
  Which variants should remain link-compatible, and which require actual
  button semantics?

Route:
  BACKLOG #43 — WP core block specimen wall / full variation audit
  Candidate input for BACKLOG #41 if the final fix belongs in the broader
  WordPress button bridge/state/ripple layer.

Rationale:
  Removing underline and preventing text selection are likely mechanical CSS
  pieces, but the real issue is whether the Pilot maps M3 button variants onto
  core/button link markup, adds core/buttons styles, or introduces a custom
  block only where semantics demand it. That decision belongs in the full core
  block option/specimen audit, not v3.6.1 token architecture close.
```

## Close Decision

Do not patch `.wp-block-table tfoot` or core/button link affordances inside
v3.6.1 Phase 3. The correct next step is to keep the token architecture close
narrow, preserve the passing light/dark validation matrix, and route the core
block style/semantic findings into the specimen audit.

Phase 3 visual QA is complete.
