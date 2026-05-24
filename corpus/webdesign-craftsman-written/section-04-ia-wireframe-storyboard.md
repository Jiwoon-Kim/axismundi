# Section 04 - IA / Wireframe / Storyboard

Status: promoted refined corpus seed.

Retention policy: keep.

Source section: PART 03 CH01.

## Layer Separation

```txt
IA:
  underlying organization, relationships, labels, taxonomy, and content/function
  inventory

navigation:
  UI surface that exposes movement through IA

wireframe:
  content priority, page regions, IA exposure, major interactions

layout:
  spatial composition, grid, responsive behavior, and visual rhythm

storyboard:
  page-by-page implementation handoff
```

## IA Route Candidates

```txt
hierarchical -> page/template/category relationships
hub-and-spoke -> landing, catalog, documentation hubs
nested-doll -> stepwise onboarding or drill-down story
dashboard -> status/overview/admin-like surfaces
labeling -> naming/taxonomy layer
sequence -> tutorials, workflows, release steps
grid -> catalogs, card lists, specimen walls, comparison surfaces
network -> exploratory knowledge maps; high risk for default theme pages
```

## Layout Postures

```txt
fixed-width:
  avoid for Pilot/distributable pages except constrained specimens

fluid:
  useful for flexible regions, with readability bounds

responsive:
  default Axismundi posture for theme and Pilot work

adaptive:
  use only when distinct breakpoint-specific layouts justify maintenance cost
```

## Minimal Storyboard Fields

```txt
page_or_template_id
user_goal
entry_path
content_priority
IA_type
navigation_type
layout_type
WordPress_surface
block_or_pattern_sources
responsive_notes
accessibility_notes
assets_required
acceptance_gates
open_decisions
```

## WordPress Surface Mapping

```txt
templates -> structural layout destinations
template parts -> repeated site regions such as header/footer
patterns -> reusable block groups or sections
Navigation block -> implementation of a navigation decision
```

WordPress surfaces are implementation destinations, not IA source authority.

## Future Use

```txt
TT5 audit:
  evaluate templates, parts, patterns, navigation, and responsive layout

Google Sites extraction:
  filter layout evidence through IA/wireframe/storyboard fields

Pilot template pass:
  create page storyboards before editing templates
```

