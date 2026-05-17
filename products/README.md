# Products — E layer

Concrete deliverables (themes, plugins, block libraries) consuming the core + bindings layers.

- `theme-pilot/` — Axismundi Pilot v0.1 (validation target, NOT a release)
- `plugins/` — (future: hct-color-panel, typography-inspector, etc.)
- `block-library/` — (future: m3-blocks plugin)

**Rule**: products are thin consumers. They register block styles, enqueue assets, and provide WP integration code — but they do NOT define new ontology entities or binding rules. Those live in core/ and bindings/.
