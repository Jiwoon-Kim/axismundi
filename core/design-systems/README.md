# Design Systems

Each design system is an isolated subdirectory with its own token + component ontology.

| System | Status |
|---|---|
| `material3/` | Active (v0.1, 273 entities) |
| `fluent/` | Reserved |
| `carbon/` | Reserved |
| `cupertino/` | Reserved |

To add a new design system:
1. Create `core/design-systems/<name>/` with `token_ontology.jsonld`, `specs/`, `runtime/`
2. Create `bindings/wordpress-<name>/` with binding rules + confidence
3. Optionally create `products/theme-<name>/` consuming the binding

The platform ontology (`core/wordpress/`) is **never modified** when adding a design system.
