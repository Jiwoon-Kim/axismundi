---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/menu-item/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: menu-item
parent_order: 3
sub_order: 8
page_order: 75
title: "MenuItem"
code_quality: degraded
code_issue: pre_newline_loss
---

# MenuItem

MenuItem is a component which renders a button intended to be used in combination with the [DropdownMenu component](https://developer.wordpress.org/block-editor/reference-guide/components/dropdown-menu/).

## Usage

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { useState } from 'react';import { MenuItem } from '@wordpress/components'; const MyMenuItem = () => { const [ isActive, setIsActive ] = useState( true ); return ( <MenuItem icon={ isActive ? 'yes' : 'no' } isSelected={ isActive } onClick={ () => setIsActive( ( state ) => ! state ) } > Toggle </MenuItem> );};
```

## Props

MenuItem supports the following props. Any additional props are passed through to the underlying [Button](https://developer.wordpress.org/block-editor/reference-guide/components/button/).

### children

- Type: `Element`
- Required: No

Element to render as child of button.

### disabled

- Type: `boolean`
- Required: No

Refer to documentation for [Button’s `disabled` prop](https://developer.wordpress.org/block-editor/reference-guide/components/button/#disabled-boolean).

### info

- Type: `string`
- Required: No

Text to use as description for button text.

Refer to documentation for [`label`](menu-item.md#label).

### icon

- Type: `string`
- Required: No

Refer to documentation for [Button’s `icon` prop](https://developer.wordpress.org/block-editor/reference-guide/components/icon-button/#icon).

### iconPosition

- Type: `string`
- Required: No
- Default: `'right'`

Determines where to display the provided `icon`.

### isSelected

- Type: `boolean`
- Required: No

Whether or not the menu item is currently selected. `isSelected` is only taken into account when the `role` prop is either `"menuitemcheckbox"` or `"menuitemradio"`.

### shortcut

- Type: `string` or `object`
- Required: No

If shortcut is a string, it is expecting the display text. If shortcut is an object, it will accept the properties of `display` (string) and `ariaLabel` (string).

### role

- Type: `string`
- Require: No
- Default: `'menuitem'`

[Aria Spec](https://www.w3.org/TR/wai-aria-1.1/#aria-checked). If you need to have selectable menu items use menuitemradio for single select, and menuitemcheckbox for multiselect.

### suffix

- Type: `Element`
- Required: No

Allows for markup other than icons or shortcuts to be added to the menu item.
