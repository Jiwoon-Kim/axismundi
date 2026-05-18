# NEXT-SESSION.md — v3.6.0 Pilot Handoff

> **Status**: v3.5.18 Pre-Pilot cleanup + Carousel reroute closed.
> **Use**: read at the start of the next local Claude/Codex session.

---

## 0) Reading Order

```txt
1. AGENTS.md or CLAUDE.md
2. CURRENT-STATE.md
3. PROJECT-CONTEXT.md
4. docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
5. docs/v3.6.0/ONTOLOGY-THEME-PILOT-HANDOFF.md
6. docs/v3.5.18/BLOCKS-PROSE-PILOT-SPEC-VERIFY.md
7. docs/v3.5.18/PRE-PILOT-SMOKE-CHECKLIST.md
8. docs/v3.5.0/MODULE-STATUS-MATRIX.md
9. ROADMAP.md latest v3.5.18 / v3.6.0 entries
10. CHANGELOG.md latest entry (v3.5.18)
```

## 1) Current State

```txt
v3.5.13 — Wave 1 closure cleanup (#32/#33/Records)  ✓ CLOSED
v3.5.14 — Publish prep                               ✓ CLOSED
v3.5.15 — GitHub repository + Pages publish          ✓ CLOSED
v3.5.16 — Styleguide modernization/framing           ✓ CLOSED
v3.5.17 — Styleguide shell rebuild/mobile polish     ✓ CLOSED
v3.5.18 — Pre-Pilot cleanup + Carousel reroute       ✓ CLOSED
v3.6.0  — Ontology Theme Pilot                       ☐ NEXT
Validator                                            1.000 / 1.000 / 1.000 / 1.000 PASS
npm test                                             PASS
GitHub repository                                    LIVE
GitHub Pages                                         LIVE
```

Public URLs:

```txt
Repository:
  https://github.com/Jiwoon-Kim/axismundi

Pages:
  https://jiwoon-kim.github.io/axismundi/
```

Overall matrix:

```txt
13 DONE / 2 PARTIAL / 16 TODO / 3 RECORD + 3 infrastructure = 37 entries
```

Carousel #34 remains historically DONE from v3.5.12 but is excluded from the
v3.6.0 theme Pilot and routed to plugin territory via BACKLOG #38.

## 2) v3.5.18 Closure Summary

Closed scope:

```txt
Carousel reroute:
  Matrix row #34 amended
  DONE history preserved
  Pilot-excluded / plugin-routed
  BACKLOG #38 added

Process lessons:
  User Request Log — Do Not Abstract Away
  Global portal/overlay smoke test:
    trigger + runtime + host + contract + errors
  Added to AGENTS.md / CLAUDE.md / grounding doc

Pilot specification surfaces:
  blocks.html = WP core block extension spec
  prose.html  = post body rendering spec
  prose 390px overflow fixed
  blocks/prose shell polish deferred to BACKLOG #39

Pilot handoff:
  docs/v3.6.0/ONTOLOGY-THEME-PILOT-HANDOFF.md created
```

Verification:

```txt
Validator: 1.000 / 1.000 / 1.000 / 1.000 PASS
npm test:  PASS
Publish:   styleguide mirror regenerated
Smoke:
  styleguide/index.html PASS
  styleguide/blocks.html PASS
  styleguide/prose.html PASS
  typography-axis.html PASS
  lab/modules/*/lab-*-pattern.html PASS
```

## 3) v3.6.0 Pilot Route

Proceed with **Ontology Theme Pilot** as a theme-only WordPress block theme
proof.

Consumes:

```txt
Wave 1 minus Carousel:
  Button #1
  Icon button #2
  FAB / Extended FAB #3 / #4
  Button group #6
  Card #9
  Text field #16
  Search bar #17
  List #33

Infrastructure:
  popover/
  ripple/
  icon-system/

Specification surfaces:
  styleguide/index.html
  styleguide/blocks.html
  styleguide/prose.html
  lab/modules/* audit docs and specimens

Baseline assets:
  tokens.css
  components.css
  blocks.css
  prose.css
  theme.json as reference only unless Pilot plan decides otherwise
```

Excludes:

```txt
Carousel plugin/block
ActivityPub runtime
M3 Interpreter plugin
v4.0 directory restructure
blocks/prose shell consistency polish
```

Lane assignment:

```txt
1. Codex implements the Pilot.
2. Opus/GPT reviews ontology consistency.
3. Codex applies corrections.
```

## 4) First v3.6.0 Phase 0 Questions

```txt
1. Where exactly should the pilot theme live?
   Suggested: products/reference-implementations/axismundi-pilot-theme/

2. Which WordPress runtime will verify it?
   Local WP env / theme zip inspection / static file verification.

3. Does Pilot copy baseline CSS directly, enqueue generated assets, or create a
   theme-facing bundle?

4. Which block templates and patterns prove enough of the ontology?

5. Which WordPress block styles require functions.php registration?
```

## 5) Operational Commands

```powershell
cd C:\Users\thaum\dev\axismundi
python .\tools\validators\validate_theme_pilot.py
npm test
python .\tools\generators\publish_styleguide.py
git status --short
```
