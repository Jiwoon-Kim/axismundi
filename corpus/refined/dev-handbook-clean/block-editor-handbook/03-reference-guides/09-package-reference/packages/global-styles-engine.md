---
source_url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-global-styles-engine/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: package-reference
slug: global-styles-engine
parent_order: 3
sub_order: 9
page_order: 57
title: "@wordpress/global-styles-engine"
code_quality: degraded
code_issue: pre_newline_loss
---

# @wordpress/global-styles-engine

A generic library for reading and writing global styles data structures (theme.json format).

## Overview

This is a framework-agnostic utility library that provides functions for manipulating global styles objects. It handles the complex nested structure of theme.json format, including block-specific styles and settings.

## API

### Reading Data

#### getStyle(globalStyles, path, blockName?, shouldDecodeEncode?)

Get a style value from the global styles object.

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
// Get global text colorconst textColor = getStyle( globalStyles, 'color.text' ); // Get button background colorconst buttonBg = getStyle( globalStyles, 'color.background', 'core/button' ); // Get raw value without CSS variable resolutionconst rawValue = getStyle( globalStyles, 'color.text', undefined, false );
```

#### getSetting(globalStyles, path, blockName?)

Get a setting value with fallback hierarchy (block-specific → global).

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
// Get global color paletteconst colors = getSetting( globalStyles, 'color.palette' ); // Get paragraph-specific font sizesconst fontSizes = getSetting( globalStyles, 'typography.fontSizes', 'core/paragraph');
```

### Writing Data

#### setStyle(globalStyles, path, newValue, blockName?)

Immutably set a style value. Returns a new global styles object.

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
// Set global text colorconst updated = setStyle( globalStyles, 'color.text', '#333333' ); // Set button background colorconst updated = setStyle( globalStyles, 'color.background', '#0073aa', 'core/button');
```

#### setSetting(globalStyles, path, newValue, blockName?)

Immutably set a setting value. Returns a new global styles object.

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
// Set global color paletteconst updated = setSetting( globalStyles, 'color.palette', newPalette ); // Set block-specific settingsconst updated = setSetting( globalStyles, 'typography.fontSizes', sizes, 'core/heading');
```

### Utility Functions

#### getPalettes(globalStyles)

Extract color palettes organized by origin (theme, custom, default).

#### generateGlobalStyles(globalStyles)

Generate CSS from global styles object.

#### mergeGlobalStyles(base, user)

Merge multiple global styles objects with proper precedence.

## Data Structure

The global styles object follows the theme.json schema:

```text
{ styles: { color: { text: '#000', background: '#fff' }, typography: { fontSize: '16px' }, elements: { button: { color: { background: 'blue' } } }, blocks: { 'core/paragraph': { color: { text: '#333' } } } }, settings: { color: { palette: [...] }, typography: { fontSizes: [...] }, blocks: { 'core/paragraph': { color: { text: true } } } }}
```

## Features

- **Immutable Updates**: All setter functions return new objects
- **Type Safety**: Full TypeScript support
- **CSS Variable Resolution**: Automatic resolution of CSS custom properties
- **Block-Specific Styles**: Support for block and element-specific styling
- **Fallback Hierarchy**: Smart fallback from block → global → default settings
- **Framework Agnostic**: No dependencies on React, WordPress, or other frameworks

## Usage

This library is designed to be used as the foundation for any global styles editing interface. It provides the core data manipulation functions while remaining completely generic and reusable.

It is a prerequisite for use that blocks and their styles be globally registered, because block styles are fetched from the global blocks store.
