---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/external-link/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: external-link
parent_order: 3
sub_order: 8
page_order: 44
title: "ExternalLink"
---

# ExternalLink

Link to an external resource.

## Usage

```jsx
import { ExternalLink } from '@wordpress/components'; const MyExternalLink = () => ( <ExternalLink href="https://wordpress.org">WordPress.org</ExternalLink>);
```

## Props

The component accepts the following props. Any other props will be passed through to the `a`.

### children: ReactNode

The content to be displayed within the link.

- Required: Yes

### href: string

The URL of the external resource.

- Required: Yes
