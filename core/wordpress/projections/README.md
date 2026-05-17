# WordPress Ontology — Projections

> RESERVED for v3.1+. A projection is a focused view over the full WordPress ontology, extracting a related subset for a specific concern.

Planned projections:

- **`block-supports.jsonld`** — subset of `ontology.jsonld` filtered to all 28 BlockSupport entities + their schema/runtime details. Used by builders/generators that need only the support model.
- **`theme-json.jsonld`** — subset filtered to ThemeJSON + ThemeToken + AppearanceTool + bridges. Used by `theme.json` emitters.
- **`rest-routes.jsonld`** — projection of REST API endpoints + permission contracts + REST schema. Reserved.
- **`block-registration.jsonld`** — Tier 1 Identity + Tier 4 Runtime properties of BlockType. Used by `register_block_type()` generators.

Projections are **derived**, not authored — generated from `ontology.jsonld` by `tools/builders/build_projections.py` (planned v3.1+). They live here as cached views for tools that don't want to walk the full 114-entity graph.

**Rule**: a projection is NEVER edited directly. If a projection looks wrong, fix `ontology.jsonld` and regenerate.
