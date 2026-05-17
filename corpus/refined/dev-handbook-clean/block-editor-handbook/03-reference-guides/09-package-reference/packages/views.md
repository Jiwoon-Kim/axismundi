---
source_url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-views/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: package-reference
slug: views
parent_order: 3
sub_order: 9
page_order: 114
title: "@wordpress/views"
---

# @wordpress/views

A lightweight package for managing DataViews view state with persistence using WordPress preferences.

The `@wordpress/views` package provides:

- **Persistence**: Automatically saves and restores DataViews state using `@wordpress/preferences`
- **View Modification Detection**: Tracks when views differ from their default state
- **Reset Functionality**: Simple reset to default view capability
- **Clean Integration**: Drop-in replacement for manual view state management

## Installation

Install the module

```bash
npm install @wordpress/views --save
```

## API Reference

### loadView

Async function for loading view state in route loaders.

*Parameters*

- *config* `ViewConfig`: Configuration object for loading the view.
- *config.kind* `ViewConfig`: Entity kind (e.g., ‘postType’, ‘taxonomy’, ‘root’).
- *config.name* `ViewConfig`: Specific entity name.
- *config.slug* `ViewConfig`: View identifier.
- *config.defaultView* `ViewConfig`: Default view configuration.
- *config.activeViewOverrides* `ViewConfig`: View overrides applied on top but never persisted.
- *config.queryParams* `ViewConfig`: Object with `page` and/or `search` from URL.

*Returns*

- Promise resolving to the loaded view object.

### useView

Hook for managing DataViews view state with local persistence.

*Parameters*

- *config* `ViewConfig`: Configuration object for loading the view.

*Returns*

- `UseViewReturn`: Object with current view, modification state, and update functions.

### useViewConfig

A hook that retrieves the view configuration for a given entity from the core data store.

*Parameters*

- *params* `Object`:
- *params.kind* `string`: The kind of the entity.
- *params.name* `string`: The name of the entity.

*Returns*

- `Object`: An object containing the `default_view`, `default_layouts`, and `view_list` configuration for the entity.
