# Loading - Spec Audit (v3.6.14 Phase 2)

> **Component**: Loading #30
> **Category**: Component Full-Spec
> **Status**: Phase 2 implementation candidate
> **Companions**: `LOADING-MEASUREMENT-AUDIT.md`, `LOADING-WP-MAPPING.md`

## Scope

```txt
In scope:
  - Default circular loading indicator
  - Contained loading indicator
  - Small inline loading indicator
  - role="status" for standalone loading state
  - Decorative aria-hidden inline spinner case
  - Reduced-motion baseline behavior

Out of scope:
  - JavaScript runtime
  - Skeleton loading
  - Progressbar semantics
  - WordPress block registration
  - Baseline CSS mutation
```

## Semantics Boundary

Standalone Loading uses:

```html
<span class="ax-loading" role="status" aria-label="Loading content">
```

Inline decorative Loading inside a button uses `aria-hidden="true"` because the
button label owns the user-facing state.

## Baseline Contract

Baseline authority remains `components.css section 20`:

```txt
.ax-loading
.ax-loading__svg
.ax-loading__circle
.ax-loading.is-contained
.ax-loading.is-small
```

## Runtime Boundary

Loading is CSS/SVG only in v3.6.14. No RUNTIME audit is required.

## Verdict

Loading can proceed as a provider-free and JS-free Full-Spec lab module.
