---
source_url: https://developer.wordpress.org/block-editor/reference-guides/data/data-core-customize-widgets/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: data-module-reference
slug: data-core-customize-widgets
parent_order: 3
sub_order: 10
page_order: 7
title: "Customize Widgets"
code_quality: degraded
code_issue: pre_newline_loss
---

# Customize Widgets

Namespace: `core/customize-widgets`.

## Selectors

### isInserterOpened

Returns true if the inserter is opened.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { store as customizeWidgetsStore } from '@wordpress/customize-widgets';import { __ } from '@wordpress/i18n';import { useSelect } from '@wordpress/data'; const ExampleComponent = () => { const { isInserterOpened } = useSelect( ( select ) => select( customizeWidgetsStore ), [] ); return isInserterOpened() ? __( 'Inserter is open' ) : __( 'Inserter is closed.' );};
```

*Parameters*

- *state* `Object`: Global application state.

*Returns*

- `boolean`: Whether the inserter is opened.

## Actions

### setIsInserterOpened

Returns an action object used to open/close the inserter.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { useState } from 'react';import { store as customizeWidgetsStore } from '@wordpress/customize-widgets';import { __ } from '@wordpress/i18n';import { useDispatch } from '@wordpress/data';import { Button } from '@wordpress/components'; const ExampleComponent = () => { const { setIsInserterOpened } = useDispatch( customizeWidgetsStore ); const [ isOpen, setIsOpen ] = useState( false ); return ( <Button onClick={ () => { setIsInserterOpened( ! isOpen ); setIsOpen( ! isOpen ); } } > { __( 'Open/close inserter' ) } </Button> );};
```

*Parameters*

- *value* `boolean|Object`: Whether the inserter should be opened (true) or closed (false). To specify an insertion point, use an object.
- *value.rootClientId* `string`: The root client ID to insert at.
- *value.insertionIndex* `number`: The index to insert at.

*Returns*

- `Object`: Action object.
