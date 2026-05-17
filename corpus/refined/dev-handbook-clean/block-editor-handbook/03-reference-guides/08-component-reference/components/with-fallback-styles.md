---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/with-fallback-styles/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: with-fallback-styles
parent_order: 3
sub_order: 8
page_order: 62
title: "WithFallbackStyles"
code_quality: degraded
code_issue: pre_newline_loss
---

# WithFallbackStyles

## Usage

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { withFallbackStyles, Button } from '@wordpress/components'; const { getComputedStyle } = window; const MyComponentWithFallbackStyles = withFallbackStyles( ( node, ownProps ) => { const buttonNode = node.querySelector( 'button' ); return { fallbackBackgroundColor: getComputedStyle( buttonNode ) .backgroundColor, fallbackTextColor: getComputedStyle( buttonNode ).color, }; })( ( { fallbackTextColor, fallbackBackgroundColor } ) => ( <div> <Button variant="primary">My button</Button> <div>Text color: { fallbackTextColor }</div> <div>Background color: { fallbackBackgroundColor }</div> </div>) );
```
