---
source_url: https://developer.wordpress.org/block-editor/reference-guides/data/data-core-rich-text/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: data-module-reference
slug: data-core-rich-text
parent_order: 3
sub_order: 10
page_order: 17
title: "Rich Text"
code_quality: degraded
code_issue: pre_newline_loss
---

# Rich Text

Namespace: `core/rich-text`.

## Selectors

### getFormatType

Returns a format type by name.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { __, sprintf } from '@wordpress/i18n';import { store as richTextStore } from '@wordpress/rich-text';import { useSelect } from '@wordpress/data'; const ExampleComponent = () => { const { getFormatType } = useSelect( ( select ) => select( richTextStore ), [] ); const boldFormat = getFormatType( 'core/bold' ); return boldFormat ? ( <ul> { Object.entries( boldFormat )?.map( ( [ key, value ] ) => ( <li> { key } : { value } </li> ) ) } </ul> ) : ( __( 'Not Found' ) ;};
```

*Parameters*

- *state* `Object`: Data state.
- *name* `string`: Format type name.

*Returns*

- `?Object`: Format type.

### getFormatTypeForBareElement

Gets the format type, if any, that can handle a bare element (without a data-format-type attribute), given the tag name of this element.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { __, sprintf } from '@wordpress/i18n';import { store as richTextStore } from '@wordpress/rich-text';import { useSelect } from '@wordpress/data'; const ExampleComponent = () => { const { getFormatTypeForBareElement } = useSelect( ( select ) => select( richTextStore ), [] ); const format = getFormatTypeForBareElement( 'strong' ); return format && <p>{ sprintf( __( 'Format name: %s' ), format.name ) }</p>;};
```

*Parameters*

- *state* `Object`: Data state.
- *bareElementTagName* `string`: The tag name of the element to find a format type for.

*Returns*

- `?Object`: Format type.

### getFormatTypeForClassName

Gets the format type, if any, that can handle an element, given its classes.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { __, sprintf } from '@wordpress/i18n';import { store as richTextStore } from '@wordpress/rich-text';import { useSelect } from '@wordpress/data'; const ExampleComponent = () => { const { getFormatTypeForClassName } = useSelect( ( select ) => select( richTextStore ), [] ); const format = getFormatTypeForClassName( 'has-inline-color' ); return format && <p>{ sprintf( __( 'Format name: %s' ), format.name ) }</p>;};
```

*Parameters*

- *state* `Object`: Data state.
- *elementClassName* `string`: The classes of the element to find a format type for.

*Returns*

- `?Object`: Format type.

### getFormatTypes

Returns all the available format types.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { __, sprintf } from '@wordpress/i18n';import { store as richTextStore } from '@wordpress/rich-text';import { useSelect } from '@wordpress/data'; const ExampleComponent = () => { const { getFormatTypes } = useSelect( ( select ) => select( richTextStore ), [] ); const availableFormats = getFormatTypes(); return availableFormats ? ( <ul> { availableFormats?.map( ( format ) => ( <li>{ format.name }</li> ) ) } </ul> ) : ( __( 'No Formats available' ) );};
```

*Parameters*

- *state* `Object`: Data state.

*Returns*

- `Array`: Format types.

## Actions

Nothing to document.
