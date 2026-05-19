# NEXT-SESSION.md — Post-v3.6.1 Handoff

> **Status**: v3.6.0 Ontology Theme Pilot and v3.6.1 Token Architecture
> Refactor are closed.
> **Use**: read at the start of the next Codex/Claude session.
> **Last updated**: 2026-05-20.

---

## 0) Reading Order

```txt
1. AGENTS.md or CLAUDE.md
2. CURRENT-STATE.md
3. PROJECT-CONTEXT.md
4. CHANGELOG.md latest entry
5. ROADMAP.md current tail
6. BACKLOG.md #41 / #43 / #21
7. docs/v3.6.1/TOKEN-ARCHITECTURE-REFACTOR-PHASE-5-CLOSE.md
8. docs/v3.6.1/TOKEN-ARCHITECTURE-REFACTOR-PHASE-1-CLOSE.md
9. docs/v3.6.1/TOKEN-ARCHITECTURE-REFACTOR-PHASE-3-VISUAL-QA.md
10. bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md §1-2
11. docs/v3.6.0/PILOT-LESSONS-AND-TOKEN-ARCHITECTURE.md
```

Repo docs remain authority. Chat is relay, not source of truth.

Default relay ownership:

```txt
Codex:
  implementation files and phase plan/report docs

Opus/Claude:
  review findings only, preferably as user-relayed text or
  docs/<cycle>/*-review.md if repo-based handoff is requested
```

## 1) Current State

```txt
v3.5.18  Pre-Pilot cleanup + Carousel reroute       ✓ CLOSED
v3.6.0   Ontology Theme Pilot v0                    ✓ CLOSED
v3.6.1   Token Architecture Refactor                ✓ CLOSED

Next route:
  v3.6.x Pilot feedback iteration or Wave 2 plan-first.
  Do not begin implementation without a fresh cycle plan.
```

Public repository:

```txt
https://github.com/Jiwoon-Kim/axismundi
https://jiwoon-kim.github.io/axismundi/
```

Local workspace:

```txt
C:\Users\thaum\dev\axismundi
```

## 2) v3.6.1 Close Summary

Implemented:

```txt
Token layers:
  tokens.ref.css
  tokens.sys.light.css
  tokens.sys.dark.css
  tokens.comp.css
  tokens.css empty compatibility shim

WordPress projections:
  wp-preset.bridge.css
  wp-custom.bridge.css
  theme.json settings.custom.axismundi.* downstream var() leaves

Dark mode:
  Pilot Light / Dark / Auto controls
  sys-layer remapping only; md-ref unchanged

Validation:
  Axis E md-sys -> md-ref lock
  Axis F bridge downstream lock
  Axis G theme.json custom downstream lock
  computed light/dark matrix + real click path
```

Closed:

```txt
BACKLOG #20 — settings.color.custom=false theme-only color policy
BACKLOG #42 — Token Architecture Refactor
```

Routed:

```txt
BACKLOG #43 / #41:
  Phase 3 visual QA table footer native border finding
  Phase 3 visual QA core/button semantic boundary finding

Future candidates:
  Axis H bridge/theme correspondence validator
  Auto-mode media emulation matrix
```

## 3) Lesson Locks

These are now close-time rules, not suggestions:

```txt
Lock 1 — wp-custom downstream-only

Every settings.custom.axismundi.* entry MUST be defined as:
  var(--comp-*) or var(--md-sys-*) or var(--md-ref-*)

Literal hex / rgb / px / number values are forbidden in this namespace.
Rationale: wp-custom is a downstream projection of M3, never a source.
Validator: tools/validators/validate_theme_pilot.py Axis G.
```

```txt
Lock 2 — md-sys color maps to md-ref

Every --md-sys-color-* entry MUST be defined as:
  var(--md-ref-palette-*)

Literal hex / rgb / hsl values are forbidden in the md-sys color layer.
Rationale: md-sys is the runtime semantic layer; md-ref is the primitive source.
Dark mode swaps sys -> ref mappings only.
Validator: tools/validators/validate_theme_pilot.py Axis E.
```

## 4) Resume Checklist

Start by running:

```powershell
cd C:\Users\thaum\dev\axismundi
git status --short
php -l products\reference-implementations\axismundi-pilot\functions.php
npm test
npm run validate:computed
```

If wp-env is not running:

```powershell
wp-env start
```

Then open/check relevant Pilot/styleguide surfaces for the next cycle. For
Pilot feedback work, include:

```txt
http://localhost:8888/
http://localhost:8888/?page_id=10
http://localhost:8888/?p=1
file:///C:/Users/thaum/dev/axismundi/styleguide/blocks.html#blocks-table
```

## 5) Next Decision

The next cycle should be chosen explicitly. Current likely routes:

```txt
Option A:
  v3.6.x WP core block specimen wall / variation audit
  Primary backlog: #43
  Feeds: #41 full block bridge expansion

Option B:
  v3.6.x / v3.7.x full block bridge expansion
  Primary backlog: #41
  Requires #43 specimen evidence before broad mapping claims

Option C:
  Wave 2 plan-first
  Navigation/Form split still likely per ROADMAP

Option D:
  BACKLOG #21 Interpreter Plugin strategy
  Now unblocked by the v3.6.1 strict-mode token architecture lock
```

Do not treat the Phase 3 visual QA findings as v3.6.1 regressions. They are
routed evidence for the next Pilot feedback cycle.
