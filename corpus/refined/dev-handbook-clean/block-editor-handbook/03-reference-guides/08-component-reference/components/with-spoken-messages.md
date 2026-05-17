---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/with-spoken-messages/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: with-spoken-messages
parent_order: 3
sub_order: 8
page_order: 67
title: "WithSpokenMessages"
code_quality: degraded
code_issue: pre_newline_loss
---

# WithSpokenMessages

## Usage

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { withSpokenMessages, Button } from '@wordpress/components'; const MyComponentWithSpokenMessages = withSpokenMessages( ( { speak, debouncedSpeak } ) => ( <div> <Button variant="secondary" onClick={ () => speak( 'Spoken message' ) } > Speak </Button> <Button variant="secondary" onClick={ () => debouncedSpeak( 'Delayed message' ) } > Debounced Speak </Button> </div> ));
```
