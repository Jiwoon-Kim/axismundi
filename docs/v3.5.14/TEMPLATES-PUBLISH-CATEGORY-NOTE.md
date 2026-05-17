# Templates Publish Category Note — v3.5.14

> Status: category note  
> Scope: ontology and publish-route framing only  
> Non-scope: template implementation

---

## 0. Decision

Use `templates/` as the preferred name for the next composition layer.

This replaces the earlier working phrase `hifi-prototype` for the public route
and source category because the intended surface is a page-layout / template /
composition bridge after component validation, not another component module.

## 1. Source Authority

Preferred future source location:

```txt
products/reference-implementations/axismundi-lab/templates/
```

This keeps the source inside the Products layer and Lab tier, consistent with
the Constitution and Public Surface Charter.

## 2. Publish Route

Preferred future publish route:

```txt
/templates/
```

The route should be separate from `/styleguide/`.

`/styleguide/` remains the canonical component styleguide mirror. `templates/`
is for page layouts, template parts, and composition prototypes that consume
validated components.

## 3. Boundary

`templates/` is:

- a lab composition preview category,
- a bridge between component blocks and a production WordPress pilot,
- a future GitHub Pages publish surface.

`templates/` is not:

- a baseline component catalog,
- a component module,
- the WordPress pilot theme,
- plugin territory,
- implemented in v3.5.14.

## 4. Next Step

When scheduled, the templates lane should get its own plan-first cycle:

```txt
Phase 0  template/page-layout ontology and route map
Phase 1  template inventory and component dependency map
Phase 2  minimal source + publish generator support
Phase 3  browser QA
Phase 5  publish route close
```

