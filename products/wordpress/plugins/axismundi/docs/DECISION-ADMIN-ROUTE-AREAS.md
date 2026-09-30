# Admin Route Areas

## Status

Accepted for the first Admin shell refactor. This document records the implementation boundary for the route-area change; it does not decide the future Icon Registry API or the final sidebar namespace.

## Context

The initial Admin prototype assembled Operations, Design, the global header, the Inspector, and preview selection in `src/apps/admin/app.js`. It visually resembled an editor shell, but the application root knew every route-specific composition detail.

Gutenberg's Site Editor instead lets routes provide layout areas while `Layout` places those areas. Axismundi adopts that principle without importing `@wordpress/edit-site` private router, store, or lock/unlock APIs.

Reference source inspected locally:

- `C:/Users/thaum/dev/gutenberg/packages/edit-site/src/components/layout/index.jsx`
- `C:/Users/thaum/dev/gutenberg/packages/edit-site/src/components/site-editor-routes/styles.jsx`
- `C:/Users/thaum/dev/gutenberg/packages/edit-site/src/components/site-editor-routes/templates.jsx`
- `C:/Users/thaum/dev/gutenberg/packages/edit-site/src/components/sidebar/index.jsx`

## Decision

The MVP route contract contains only three optional areas:

```js
{
	path: '/design/styles',
	areas: {
		sidebar: ( context ) => <DesignSidebar { ...context } />,
		content: ( context ) => <StylesContent { ...context } />,
		preview: () => <FrontendPreview label="Styles" />,
	},
}
```

`content` means the complete route-owned application area. It may include a heading, toolbar, list, editor, empty state, or detail view; it is not a prose-only body region.

`AdminLayout` is route-neutral. It places the resolved `sidebar`, `content`, and optional `preview` areas but does not inspect the workspace or select individual route UI.

The global Inspector is removed from this MVP shell. An inspector becomes an explicit future route area only after a route has a concrete inspection contract.

## Navigation

Sidebar entries are real links. The browser keeps its normal handling for modified or non-primary clicks; only an unmodified primary click is enhanced with `history.pushState()`.

Route/history state and sidebar navigation state remain different systems:

```text
route/history state
  current resource route and browser history

sidebar navigation state
  forward/back direction, focus-return target, screen transition
```

This refactor does not yet add nested sidebar screens. Therefore it removes trailing drilldown chevrons that previously implied navigation behavior which did not exist. A later change may add the navigation state, focus restoration, and animation together.

## Compatibility Boundaries

The existing local sidebar port and its `edit-site-*` compatibility classes remain unchanged in this step. Replacing that temporary compatibility surface with Axismundi-owned names is deliberately separate from the route-area refactor, as is the Icon Registry spike.

The admin URL is supplied by PHP using `admin_url()` rather than being hard-coded as `/wp-admin/`.

## Verification

- `npm run build` completes successfully.
- `git diff --check` completes successfully.
- A fresh `p=/design/styles` page renders route-owned content and preview areas.
- Sidebar items expose real link destinations.
- A normal sidebar click updates the URL, active item, and preview without full navigation.
- Browser back and a page reload restore the route from `p`.
