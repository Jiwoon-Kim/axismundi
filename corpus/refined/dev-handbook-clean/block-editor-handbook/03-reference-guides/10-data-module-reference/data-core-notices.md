---
source_url: https://developer.wordpress.org/block-editor/reference-guides/data/data-core-notices/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: data-module-reference
slug: data-core-notices
parent_order: 3
sub_order: 10
page_order: 13
title: "Notices Data"
code_quality: degraded
code_issue: pre_newline_loss
---

# Notices Data

Namespace: `core/notices`.

## Selectors

### getNotices

Returns all notices as an array, optionally for a given context. Defaults to the global context.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { useSelect } from '@wordpress/data';import { store as noticesStore } from '@wordpress/notices'; const ExampleComponent = () => { const notices = useSelect( ( select ) => select( noticesStore ).getNotices() ); return ( <ul> { notices.map( ( notice ) => ( <li key={ notice.ID }>{ notice.content }</li> ) ) } </ul> );};
```

*Parameters*

- *state* `Record< string, Array< Notice > >`: Notices state.
- *context* `string`: Optional grouping context.

*Returns*

- Array of notices.

## Actions

### createErrorNotice

Returns an action object used in signalling that an error notice is to be created. Refer to `createNotice` for options documentation.

*Related*

- createNotice

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { __ } from '@wordpress/i18n';import { useDispatch } from '@wordpress/data';import { store as noticesStore } from '@wordpress/notices';import { Button } from '@wordpress/components'; const ExampleComponent = () => { const { createErrorNotice } = useDispatch( noticesStore ); return ( <Button onClick={ () => createErrorNotice( __( 'An error occurred!' ), { type: 'snackbar', explicitDismiss: true, } ) } > { __( 'Generate a snackbar error notice with explicit dismiss button.' ) } </Button> );};
```

*Parameters*

- *content* `string`: Notice message.
- *options* `NoticeOptions`: Optional notice options.

*Returns*

- Action object.

### createInfoNotice

Returns an action object used in signalling that an info notice is to be created. Refer to `createNotice` for options documentation.

*Related*

- createNotice

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { __ } from '@wordpress/i18n';import { useDispatch } from '@wordpress/data';import { store as noticesStore } from '@wordpress/notices';import { Button } from '@wordpress/components'; const ExampleComponent = () => { const { createInfoNotice } = useDispatch( noticesStore ); return ( <Button onClick={ () => createInfoNotice( __( 'Something happened!' ), { isDismissible: false, } ) } > { __( 'Generate a notice that cannot be dismissed.' ) } </Button> );};
```

*Parameters*

- *content* `string`: Notice message.
- *options* `NoticeOptions`: Optional notice options.

*Returns*

- Action object.

### createNotice

Returns an action object used in signalling that a notice is to be created.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { __ } from '@wordpress/i18n';import { useDispatch } from '@wordpress/data';import { store as noticesStore } from '@wordpress/notices';import { Button } from '@wordpress/components'; const ExampleComponent = () => { const { createNotice } = useDispatch( noticesStore ); return ( <Button onClick={ () => createNotice( 'success', __( 'Notice message' ) ) } > { __( 'Generate a success notice!' ) } </Button> );};
```

*Parameters*

- *status* Notice status (“info” if undefined is passed).
- *content* `string`: Notice message.
- *options* `NoticeOptions`: Optional notice options.

*Returns*

- `Extract< ReducerAction, { type: 'CREATE_NOTICE'; } >`: Action object.

### createSuccessNotice

Returns an action object used in signalling that a success notice is to be created. Refer to `createNotice` for options documentation.

*Related*

- createNotice

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { __ } from '@wordpress/i18n';import { useDispatch } from '@wordpress/data';import { store as noticesStore } from '@wordpress/notices';import { Button } from '@wordpress/components'; const ExampleComponent = () => { const { createSuccessNotice } = useDispatch( noticesStore ); return ( <Button onClick={ () => createSuccessNotice( __( 'Success!' ), { type: 'snackbar', icon: '', } ) } > { __( 'Generate a snackbar success notice!' ) } </Button> );};
```

*Parameters*

- *content* `string`: Notice message.
- *options* `NoticeOptions`: Optional notice options.

*Returns*

- Action object.

### createWarningNotice

Returns an action object used in signalling that a warning notice is to be created. Refer to `createNotice` for options documentation.

*Related*

- createNotice

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { __ } from '@wordpress/i18n';import { useDispatch } from '@wordpress/data';import { store as noticesStore } from '@wordpress/notices';import { Button } from '@wordpress/components'; const ExampleComponent = () => { const { createWarningNotice, createInfoNotice } = useDispatch( noticesStore ); return ( <Button onClick={ () => createWarningNotice( __( 'Warning!' ), { onDismiss: () => { createInfoNotice( __( 'The warning has been dismissed!' ) ); }, } ) } > { __( 'Generates a warning notice with onDismiss callback' ) } </Button> );};
```

*Parameters*

- *content* `string`: Notice message.
- *options* `NoticeOptions`: Optional notice options.

*Returns*

- Action object.

### removeAllNotices

Removes all notices from a given context. Defaults to the default context.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { __ } from '@wordpress/i18n';import { useDispatch, useSelect } from '@wordpress/data';import { store as noticesStore } from '@wordpress/notices';import { Button } from '@wordpress/components'; export const ExampleComponent = () => { const notices = useSelect( ( select ) => select( noticesStore ).getNotices() ); const { removeAllNotices } = useDispatch( noticesStore ); return ( <> <ul> { notices.map( ( notice ) => ( <li key={ notice.id }>{ notice.content }</li> ) ) } </ul> <Button onClick={ () => removeAllNotices() }> { __( 'Clear all notices', 'woo-gutenberg-products-block' ) } </Button> <Button onClick={ () => removeAllNotices( 'snackbar' ) }> { __( 'Clear all snackbar notices', 'woo-gutenberg-products-block' ) } </Button> </> );};
```

*Parameters*

- *noticeType* The context to remove all notices from.
- *context* `string`: The optional context to remove all notices from.

*Returns*

- `Extract< ReducerAction, { type: 'REMOVE_ALL_NOTICES'; } >`: Action object.

### removeNotice

Returns an action object used in signalling that a notice is to be removed.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { __ } from '@wordpress/i18n';import { useDispatch } from '@wordpress/data';import { store as noticesStore } from '@wordpress/notices';import { Button } from '@wordpress/components'; const ExampleComponent = () => { const notices = useSelect( ( select ) => select( noticesStore ).getNotices() ); const { createWarningNotice, removeNotice } = useDispatch( noticesStore ); return ( <> <Button onClick={ () => createWarningNotice( __( 'Warning!' ), { isDismissible: false, } ) } > { __( 'Generate a notice' ) } </Button> { notices.length > 0 && ( <Button onClick={ () => removeNotice( notices[ 0 ].id ) }> { __( 'Remove the notice' ) } </Button> ) } </> );};
```

*Parameters*

- *id* `string`: Notice unique identifier.
- *context* `string`: Optional context (grouping) in which the notice is intended to appear. Defaults to ‘default’ context.

*Returns*

- `Extract< ReducerAction, { type: 'REMOVE_NOTICE'; } >`: Action object.

### removeNotices

Returns an action object used in signalling that several notices are to be removed.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { __ } from '@wordpress/i18n';import { useDispatch, useSelect } from '@wordpress/data';import { store as noticesStore } from '@wordpress/notices';import { Button } from '@wordpress/components'; const ExampleComponent = () => { const notices = useSelect( ( select ) => select( noticesStore ).getNotices() ); const { removeNotices } = useDispatch( noticesStore ); return ( <> <ul> { notices.map( ( notice ) => ( <li key={ notice.id }>{ notice.content }</li> ) ) } </ul> <Button onClick={ () => removeNotices( notices.map( ( { id } ) => id ) ) } > { __( 'Clear all notices' ) } </Button> </> );};
```

*Parameters*

- *ids* `Array< string >`: List of unique notice identifiers.
- *context* `string`: Optional context (grouping) in which the notices are intended to appear. Defaults to ‘default’ context.

*Returns*

- `Extract< ReducerAction, { type: 'REMOVE_NOTICES'; } >`: Action object.
