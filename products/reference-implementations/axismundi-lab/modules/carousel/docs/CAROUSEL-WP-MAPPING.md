# Carousel — WordPress Mapping

> **Status**: v3.5.12 release closed; Phase 3 visual QA PASS.  
> **Component**: Carousel #34  
> **Companions**: `CAROUSEL-SPEC-AUDIT.md`, `CAROUSEL-MEASUREMENT-AUDIT.md`, `CAROUSEL-RUNTIME-AUDIT.md`

---

## §0 — Mapping Status

This document owns the WordPress boundary for Carousel. It carries forward the
existing ontology lock:

```txt
Gallery is not Carousel.
```

No direct `core/gallery -> Carousel` mapping is allowed.

---

## §1 — Inputs Read

```txt
docs/v3.5.12/CAROUSEL-PHASE-0-REPORT.md
products/reference-implementations/axismundi-lab/modules/carousel/docs/CAROUSEL-ONTOLOGY-CHECK.md
products/reference-implementations/axismundi-lab/style-guide.html #components-carousel
products/reference-implementations/axismundi-lab/stylesheets/components.css §30
```

---

## §2 — Ontology Rule

The existing Carousel ontology check is authoritative:

```txt
Gallery is a media collection.
Carousel is a temporal / horizontally navigable presentation of items.
```

Therefore:

```txt
core/gallery alone -> Gallery
core/gallery + explicit carousel style/pattern + interaction layer -> Carousel candidate
```

Silent conversion is forbidden.

---

## §3 — Candidate Core Block Mapping

| WordPress surface | Mapping | Notes |
| --- | --- | --- |
| `core/gallery` | Conditional candidate | Only with explicit carousel style/pattern and fallback. |
| `core/image` group | Pattern composition | Could feed static carousel items, not automatic. |
| `core/group` / `core/columns` | Pattern composition | Author-controlled layout only. |
| Query Loop media cards | Plugin/pattern candidate | Needs content selection logic. |
| Custom carousel block | Plugin territory | Editor UI and schema are plugin work. |

---

## §4 — Theme-Can / Plugin-Should Boundary

Theme can:

```txt
provide static carousel CSS
provide lab/runtime reference pattern
provide horizontal scroll-snap fallback
provide conditional style class semantics
provide reduced-motion runtime behavior
provide "Show all" affordance styling
```

Plugin should:

```txt
provide editor carousel picker UI
persist carousel-specific block attributes
own custom slide schema
own drag/reorder editing UI
own remote/federated media rules
own analytics/autoplay scheduling
```

---

## §5 — Binding Conditions

A WordPress block surface may be treated as a Carousel only when all conditions
are explicit:

```txt
1. Author chose a carousel style/pattern.
2. Content is suitable for carousel display.
3. Static fallback remains usable.
4. Progressive interaction layer is loaded.
5. Reduced-motion behavior is honored.
6. A non-horizontal access path exists when required.
```

If any condition is missing, the surface remains Gallery or static media.

---

## §6 — Accessibility Mapping

M3 guidance requires an accessible way to view all carousel items without
horizontal scrolling on vertically scrolling pages.

WordPress mapping implication:

```txt
theme may style a Show all link/button
plugin may route Show all to an archive/lightbox/gallery page
pattern may place a static list fallback below or near the carousel
```

Do not ship a carousel binding that traps content exclusively in horizontal
scrolling.

---

## §7 — Anti-Patterns

Forbidden:

```txt
auto-converting every core/gallery into Carousel
using Carousel for long-form prose lists
using Carousel where a static Gallery preserves content meaning better
requiring JS for basic media access
adding autoplay in theme CSS/JS
hiding items from keyboard users
making drag the only movement path
placing Carousel runtime inside editor/prose surfaces without a guard
using remote image loading as a geometry dependency in release QA
```

---

## §8 — Binding Map Stub

Future binding-map shape:

```json
{
  "binding_id": "core_gallery_carousel_conditional",
  "source": "core/gallery",
  "target": "carousel",
  "conditions": [
    { "kind": "author_opt_in", "value": "carousel_style_or_pattern" },
    { "kind": "fallback", "value": "gallery_or_static_scroll_snap" },
    { "kind": "interaction_layer_present", "value": "carousel_runtime" },
    { "kind": "reduced_motion", "value": "honored" }
  ],
  "without_conditions": "Gallery",
  "with_conditions": "Carousel candidate"
}
```

This is not written to the active binding map in v3.5.12 unless Phase 5
explicitly scopes it.

---

## §9 — WP-MAPPING Phase 2 Implications

Phase 2 pattern HTML should include:

```txt
static carousel specimen
interactive lab carousel specimen
Show all / view-all affordance note or specimen
caption explaining Gallery is not automatically Carousel
```

No WordPress registration or block-style PHP work in Phase 2.

---

## §10 — Gating

WP-MAPPING supports:

```txt
G7 Principle 1: visible carousel affordances map to real behavior
G8 Principle 2: native controls / labels / ARIA state
G9 WCAG accuracy: accessible alternate path and keyboard operation
G10 audit mapping completeness
```

---

## §11 — Verdict

Phase 5 WP-MAPPING verdict:

```txt
PASS for ontology boundary.
PASS for conditional binding framing.
PASS for Phase 2 pattern evidence.
No active binding-map mutation in v3.5.12.
Gallery remains distinct from Carousel; conditional binding only.
```

---

## §12 — Cross-References

```txt
CAROUSEL-SPEC-AUDIT.md
CAROUSEL-MEASUREMENT-AUDIT.md
CAROUSEL-RUNTIME-AUDIT.md
CAROUSEL-ONTOLOGY-CHECK.md
docs/v3.5.12/CAROUSEL-PHASE-0-REPORT.md
```

---

## §13 — What This Mapping Does NOT Do

This mapping does not:

```txt
change binding_map.json
register a block style
create a custom carousel block
create editor UI
rewrite core/gallery semantics
solve remote media federation
```
