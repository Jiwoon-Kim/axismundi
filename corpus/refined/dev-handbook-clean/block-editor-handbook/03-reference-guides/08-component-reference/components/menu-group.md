---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/menu-group/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: menu-group
parent_order: 3
sub_order: 8
page_order: 74
title: "MenuGroup"
---

# MenuGroup

`MenuGroup` wraps a series of related `MenuItem` components into a common section.

## Design guidelines

### Usage

A `MenuGroup` should be used to indicate that two or more individual MenuItems are related. When other menu items exist above or below a `MenuGroup`, the group should have a divider line between it and the adjacent item. A `MenuGroup` can optionally include a label to describe its contents.

1. `MenuGroup` label
2. `MenuGroup` dividers

## Development guidelines

### Usage

```jsx
import { MenuGroup, MenuItem } from '@wordpress/components'; const MyMenuGroup = () => ( <MenuGroup label="Settings"> <MenuItem>Setting 1</MenuItem> <MenuItem>Setting 2</MenuItem> </MenuGroup>);
```

## Related Components

- `MenuGroup`s are intended to be used in a `DropDownMenu`.
- To use a single button in a menu, use `MenuItem`.
- To allow users to toggle between a set of menu options, use `MenuItemsChoice` inside of a `MenuGroup`.
