---
source_url: https://developer.wordpress.org/block-editor/reference-guides/data/data-core-viewport/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: data-module-reference
slug: data-core-viewport
parent_order: 3
sub_order: 10
page_order: 18
title: "The Viewport Data"
code_quality: degraded
code_issue: pre_newline_loss
---

# The Viewport Data

Namespace: `core/viewport`.

## Selectors

### isViewportMatch

Returns true if the viewport matches the given query, or false otherwise.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { store as viewportStore } from '@wordpress/viewport';import { useSelect } from '@wordpress/data';import { __ } from '@wordpress/i18n';const ExampleComponent = () => { const isMobile = useSelect( ( select ) => select( viewportStore ).isViewportMatch( '< small' ), [] ); return isMobile ? ( <div>{ __( 'Mobile' ) }</div> ) : ( <div>{ __( 'Not Mobile' ) }</div> );};
```

*Parameters*

- *state* `ViewportState`: Viewport state object.
- *query* `ViewportQuery`: Query string. Includes operator and breakpoint name, space separated. Operator defaults to &gt;=.

*Returns*

- `boolean`: Whether viewport matches query.

## Actions

The actions in this package shouldn’t be used directly.

Nothing to document.
