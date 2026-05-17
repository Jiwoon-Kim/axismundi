---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/placeholder/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: placeholder
parent_order: 3
sub_order: 8
page_order: 84
title: "Placeholder"
code_quality: degraded
code_issue: pre_newline_loss
---

# Placeholder

## Usage

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { Placeholder } from '@wordpress/components';import { more } from '@wordpress/icons'; const MyPlaceholder = () => <Placeholder icon={ more } label="Placeholder" />;
```

## Props

### className: string

Class to set on the container div.

- Required: No

### icon: string|Function|Component|null

If provided, renders an icon next to the label.

- Required: No

### instructions: string

Instructions of the placeholder.

- Required: No

### isColumnLayout: boolean

Changes placeholder children layout from flex-row to flex-column.

- Required: No

### label: string

Title of the placeholder.

- Required: No

### notices: ReactNode

A rendered notices list

- Required: No

### preview: ReactNode

Preview to be rendered in the placeholder.

- Required: No

### withIllustration: boolean

Outputs a placeholder illustration.

- Required: No
