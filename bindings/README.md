# Bindings — D layer

Typed cross-ontology translation. **Not equivalence** — translation with explicit confidence and binding_pattern.

- `wordpress-material3/` — active (6 token + 32 component bindings, P0.5 audit PASS)
- `wordpress-activitypub/` — reserved (future)

**Rule**: a binding entry must declare:
- `wp_anchor`, `m3_anchor` (or equivalent for other axes)
- `confidence` (0.0–1.0)
- `binding_pattern` (e.g., role_to_slug, meta_flag_to_capability_bundle)
- `translation_rule` (prose explanation)
