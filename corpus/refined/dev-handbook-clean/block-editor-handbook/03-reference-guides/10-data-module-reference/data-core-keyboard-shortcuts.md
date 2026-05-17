---
source_url: https://developer.wordpress.org/block-editor/reference-guides/data/data-core-keyboard-shortcuts/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: data-module-reference
slug: data-core-keyboard-shortcuts
parent_order: 3
sub_order: 10
page_order: 12
title: "The Keyboard Shortcuts Data"
code_quality: degraded
code_issue: pre_newline_loss
---

# The Keyboard Shortcuts Data

Namespace: `core/keyboard-shortcuts`.

## Selectors

### getAllShortcutKeyCombinations

Returns the shortcuts that include aliases for a given shortcut name.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { store as keyboardShortcutsStore } from '@wordpress/keyboard-shortcuts';import { useSelect } from '@wordpress/data';import { createInterpolateElement } from '@wordpress/element';import { sprintf } from '@wordpress/i18n'; const ExampleComponent = () => { const allShortcutKeyCombinations = useSelect( ( select ) => select( keyboardShortcutsStore ).getAllShortcutKeyCombinations( 'core/editor/next-region' ), [] ); return ( allShortcutKeyCombinations.length > 0 && ( <ul> { allShortcutKeyCombinations.map( ( { character, modifier }, index ) => ( <li key={ index }> { createInterpolateElement( sprintf( 'Character: <code>%s</code> / Modifier: <code>%s</code>', character, modifier ), { code: <code />, } ) } </li> ) ) } </ul> ) );};
```

*Parameters*

- *state* `Object`: Global state.
- *name* `string`: Shortcut name.

*Returns*

- `ShortcutKeyCombination[]`: Key combinations.

### getAllShortcutRawKeyCombinations

Returns the raw representation of all the keyboard combinations of a given shortcut name.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { store as keyboardShortcutsStore } from '@wordpress/keyboard-shortcuts';import { useSelect } from '@wordpress/data';import { createInterpolateElement } from '@wordpress/element';import { sprintf } from '@wordpress/i18n'; const ExampleComponent = () => { const allShortcutRawKeyCombinations = useSelect( ( select ) => select( keyboardShortcutsStore ).getAllShortcutRawKeyCombinations( 'core/editor/next-region' ), [] ); return ( allShortcutRawKeyCombinations.length > 0 && ( <ul> { allShortcutRawKeyCombinations.map( ( shortcutRawKeyCombination, index ) => ( <li key={ index }> { createInterpolateElement( sprintf( ' <code>%s</code>', shortcutRawKeyCombination ), { code: <code />, } ) } </li> ) ) } </ul> ) );};
```

*Parameters*

- *state* `Object`: Global state.
- *name* `string`: Shortcut name.

*Returns*

- `string[]`: Shortcuts.

### getCategoryShortcuts

Returns the shortcut names list for a given category name.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { store as keyboardShortcutsStore } from '@wordpress/keyboard-shortcuts';import { useSelect } from '@wordpress/data'; const ExampleComponent = () => { const categoryShortcuts = useSelect( ( select ) => select( keyboardShortcutsStore ).getCategoryShortcuts( 'block' ), [] ); return ( categoryShortcuts.length > 0 && ( <ul> { categoryShortcuts.map( ( categoryShortcut ) => ( <li key={ categoryShortcut }>{ categoryShortcut }</li> ) ) } </ul> ) );};
```

*Parameters*

- *state* `Object`: Global state.
- *name* `string`: Category name.

*Returns*

- `string[]`: Shortcut names.

### getShortcutAliases

Returns the aliases for a given shortcut name.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { store as keyboardShortcutsStore } from '@wordpress/keyboard-shortcuts';import { useSelect } from '@wordpress/data';import { createInterpolateElement } from '@wordpress/element';import { sprintf } from '@wordpress/i18n';const ExampleComponent = () => { const shortcutAliases = useSelect( ( select ) => select( keyboardShortcutsStore ).getShortcutAliases( 'core/editor/next-region' ), [] ); return ( shortcutAliases.length > 0 && ( <ul> { shortcutAliases.map( ( { character, modifier }, index ) => ( <li key={ index }> { createInterpolateElement( sprintf( 'Character: <code>%s</code> / Modifier: <code>%s</code>', character, modifier ), { code: <code />, } ) } </li> ) ) } </ul> ) );};
```

*Parameters*

- *state* `Object`: Global state.
- *name* `string`: Shortcut name.

*Returns*

- `ShortcutKeyCombination[]`: Key combinations.

### getShortcutDescription

Returns the shortcut description given its name.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { store as keyboardShortcutsStore } from '@wordpress/keyboard-shortcuts';import { useSelect } from '@wordpress/data';import { __ } from '@wordpress/i18n';const ExampleComponent = () => { const shortcutDescription = useSelect( ( select ) => select( keyboardShortcutsStore ).getShortcutDescription( 'core/editor/next-region' ), [] ); return shortcutDescription ? ( <div>{ shortcutDescription }</div> ) : ( <div>{ __( 'No description.' ) }</div> );};
```

*Parameters*

- *state* `Object`: Global state.
- *name* `string`: Shortcut name.

*Returns*

- `?string`: Shortcut description.

### getShortcutKeyCombination

Returns the main key combination for a given shortcut name.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { store as keyboardShortcutsStore } from '@wordpress/keyboard-shortcuts';import { useSelect } from '@wordpress/data';import { createInterpolateElement } from '@wordpress/element';import { sprintf } from '@wordpress/i18n';const ExampleComponent = () => { const { character, modifier } = useSelect( ( select ) => select( keyboardShortcutsStore ).getShortcutKeyCombination( 'core/editor/next-region' ), [] ); return ( <div> { createInterpolateElement( sprintf( 'Character: <code>%s</code> / Modifier: <code>%s</code>', character, modifier ), { code: <code />, } ) } </div> );};
```

*Parameters*

- *state* `Object`: Global state.
- *name* `string`: Shortcut name.

*Returns*

- `ShortcutKeyCombination?`: Key combination.

### getShortcutRepresentation

Returns a string representing the main key combination for a given shortcut name.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { store as keyboardShortcutsStore } from '@wordpress/keyboard-shortcuts';import { useSelect } from '@wordpress/data';import { sprintf } from '@wordpress/i18n'; const ExampleComponent = () => { const { display, raw, ariaLabel } = useSelect( ( select ) => { return { display: select( keyboardShortcutsStore ).getShortcutRepresentation( 'core/editor/next-region' ), raw: select( keyboardShortcutsStore ).getShortcutRepresentation( 'core/editor/next-region', 'raw' ), ariaLabel: select( keyboardShortcutsStore ).getShortcutRepresentation( 'core/editor/next-region', 'ariaLabel' ), }; }, [] ); return ( <ul> <li>{ sprintf( 'display string: %s', display ) }</li> <li>{ sprintf( 'raw string: %s', raw ) }</li> <li>{ sprintf( 'ariaLabel string: %s', ariaLabel ) }</li> </ul> );};
```

*Parameters*

- *state* `Object`: Global state.
- *name* `string`: Shortcut name.
- *representation* `keyof FORMATTING_METHODS`: Type of representation (display, raw, ariaLabel).

*Returns*

- `?string`: Shortcut representation.

## Actions

### registerShortcut

Returns an action object used to register a new keyboard shortcut.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { useEffect } from 'react';import { store as keyboardShortcutsStore } from '@wordpress/keyboard-shortcuts';import { useSelect, useDispatch } from '@wordpress/data';import { __ } from '@wordpress/i18n'; const ExampleComponent = () => { const { registerShortcut } = useDispatch( keyboardShortcutsStore ); useEffect( () => { registerShortcut( { name: 'custom/my-custom-shortcut', category: 'my-category', description: __( 'My custom shortcut' ), keyCombination: { modifier: 'primary', character: 'j', }, } ); }, [] ); const shortcut = useSelect( ( select ) => select( keyboardShortcutsStore ).getShortcutKeyCombination( 'custom/my-custom-shortcut' ), [] ); return shortcut ? ( <p>{ __( 'Shortcut is registered.' ) }</p> ) : ( <p>{ __( 'Shortcut is not registered.' ) }</p> );};
```

*Parameters*

- *config* `ShortcutConfig`: Shortcut config.

*Returns*

- `Object`: action.

### unregisterShortcut

Returns an action object used to unregister a keyboard shortcut.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { useEffect } from 'react';import { store as keyboardShortcutsStore } from '@wordpress/keyboard-shortcuts';import { useSelect, useDispatch } from '@wordpress/data';import { __ } from '@wordpress/i18n'; const ExampleComponent = () => { const { unregisterShortcut } = useDispatch( keyboardShortcutsStore ); useEffect( () => { unregisterShortcut( 'core/editor/next-region' ); }, [] ); const shortcut = useSelect( ( select ) => select( keyboardShortcutsStore ).getShortcutKeyCombination( 'core/editor/next-region' ), [] ); return shortcut ? ( <p>{ __( 'Shortcut is not unregistered.' ) }</p> ) : ( <p>{ __( 'Shortcut is unregistered.' ) }</p> );};
```

*Parameters*

- *name* `string`: Shortcut name.

*Returns*

- `Object`: action.
