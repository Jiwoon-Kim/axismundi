# Tools

Scripts that operate on layers but are not themselves a layer.

- `refine/` — corpus refinement (cleans `source/` → `refined/`)
- `builders/` — ontology builders (atlas + corpus → core)
- `generators/` — product emitters (core + bindings → product files like theme.json)
- `validators/` — cross-layer audits (e.g., theme-pilot ↔ binding_map)

**Rule**: tools never persist their state in layer directories. Tool output is layer content; tool internals stay in `tools/`.
