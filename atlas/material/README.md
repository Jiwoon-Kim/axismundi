# Material Design 3 — Atlas

Rule-based knowledge for the Material Design 3 design system. Follows the same 6-slot DSL as `atlas/wordpress/` (see `../wordpress/_meta/dsl-spec.md`).

## Current rules

- `text-fields-spec.md` — M3 text-field anatomy + measurements (Filled / Outlined variants)
- `text-fields-impl.md` — Axismundi-specific implementation of M3 text-field

## Relationship to core/design-systems/material3/

- `atlas/material/` — rule-grain knowledge (WHEN / THEN, source citations, related rules)
- `core/design-systems/material3/` — type-grain ontology (DesignToken entities, families)

Both layers exist for the same reason as `atlas/wordpress/` + `core/wordpress/`: rules are how humans reason about a system; ontology is how machines bind to it. Per Constitution Article 1, neither can replace the other.
