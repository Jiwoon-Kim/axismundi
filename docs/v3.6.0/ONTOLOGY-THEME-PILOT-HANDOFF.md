# Axismundi v3.6.0 — Ontology Theme Pilot Handoff

Status: READY — v3.5.18 Phase 5 handoff  
Date: 2026-05-18  
Purpose: define the v3.6.0 theme-only Pilot scope before implementation

## §0. Verdict

v3.6.0 should enter as a **theme-only WordPress block theme Pilot**.

It should validate the Axismundi ontology in a real WordPress theme context
without absorbing plugin/runtime work into the theme.

## §1. Scope

The Pilot should create a real block theme proof that consumes existing public
surface work:

```txt
Theme files:
  theme.json
  functions.php
  templates/
  parts/
  patterns/
  styles or block-style registration as needed
```

The Pilot should prove:

```txt
1. Wave 1 component styles can be consumed by a WordPress theme.
2. non-component core block coverage can be expressed through blocks.css.
3. post body content can be rendered through prose.css.
4. theme/plugin boundaries remain explicit.
```

## §2. Explicit Exclusions

```txt
No Carousel block/plugin in the theme Pilot.
No Carousel extraction in v3.6.0 unless explicitly rerouted.
No custom block registration unless explicitly approved.
No ActivityPub runtime.
No M3 Interpreter plugin implementation.
No v4.0 directory restructure.
```

Carousel status:

```txt
Carousel #34 remains historically DONE (v3.5.12) as lab evidence.
It is excluded from v3.6.0 Pilot and routed to BACKLOG #38 plugin extraction.
```

## §3. Consumed Public Surface

### Component / module input

Pilot consumes **Wave 1 minus Carousel**:

```txt
Button #1
Icon button #2
FAB / Extended FAB #3+#4
Button group #6
Card #9
Text field #16
Search bar #17
List #33
```

Historical but excluded:

```txt
Carousel #34 -> BACKLOG #38 plugin extraction
```

### Infrastructure input

```txt
popover/
ripple/
icon-system/
```

### Theme product specification input

```txt
style-guide.html:
  component chrome catalog / public visual demo

blocks.html + blocks.css:
  WordPress core block coverage extension for non-component block surfaces

prose.html + prose.css:
  post body rendering contract

tokens.css:
  M3 token system

components.css:
  baseline component styling

WP-MAPPING docs:
  per-module theme-can / plugin-should guidance
```

## §4. Architecture Boundary

The Pilot is a consumer of public surface and binding docs.

```txt
Theme can:
  enqueue styles;
  register theme-supported block styles;
  compose templates/patterns;
  map WordPress core block output to Axismundi visual contracts.

Theme should not:
  define new ontology entities;
  implement plugin runtimes;
  absorb Carousel;
  register custom blocks by default;
  redefine Material tokens independently.
```

## §5. Expected File Tree

Exact path to be decided in v3.6.0 Phase 0, but expected shape:

```txt
products/reference-implementations/axismundi-pilot-theme/
  style.css
  theme.json
  functions.php
  templates/
    index.html
    single.html
    page.html
  parts/
    header.html
    footer.html
  patterns/
    hero.php or hero.html
    post-card.php or post-card.html
  README.md
```

## §6. Lane Assignment

```txt
Codex:
  implementation and validation.

Opus / GPT:
  ontology review after Codex implementation.

Codex:
  correction pass after review.
```

Do not run parallel dual implementation by default. The v3.5.10 lane experiment
showed that review is cheaper and safer than duplicate build work.

## §7. Validation Plan

Minimum:

```txt
python .\tools\validators\validate_theme_pilot.py
npm test
```

Pilot-specific:

```txt
WordPress theme activation check
front page render
single post render with prose content
core block sample render using blocks.html coverage
mobile 390px / tablet 768px / desktop 1280px visual QA
theme-can / plugin-should boundary review
```

## §8. Risks

| Risk | Mitigation |
|---|---|
| Carousel slips back into theme scope | Keep BACKLOG #38 as plugin extraction route and exclude from Pilot checklist. |
| Theme starts defining ontology | Review against CONSTITUTION Article 7 and PUBLIC-SURFACE-CHARTER. |
| blocks/prose spec drift blocks implementation | Use `BLOCKS-PROSE-PILOT-SPEC-VERIFY.md` before Phase 0 execution. |
| User requests get abstracted away | Use User Request Log protocol from v3.5.18. |
| Runtime host/trigger regressions | Use global portal/overlay smoke standard from v3.5.18. |

## §9. Entry Checklist

```txt
✓ v3.5.18 closed.
✓ Carousel matrix amendment applied.
✓ BACKLOG #38 exists.
✓ blocks/prose spec verification PASS.
✓ pre-pilot smoke checklist PASS.
□ user confirms v3.6.0 Pilot route.
```
