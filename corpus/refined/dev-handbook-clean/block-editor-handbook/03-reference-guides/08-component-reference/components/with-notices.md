---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/with-notices/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: with-notices
parent_order: 3
sub_order: 8
page_order: 66
title: "WithNotices"
code_quality: degraded
code_issue: pre_newline_loss
---

# WithNotices

`withNotices` is a React [higher-order component](https://facebook.github.io/react/docs/higher-order-components.html) used typically in adding the ability to post notice messages within the original component.

Wrapping the original component with `withNotices` encapsulates the component with the additional props `noticeOperations`, `noticeUI`, and `noticeList`.

**noticeOperations**  
Contains a number of useful functions to add notices to your site.

[#](with-notices.md#createNotice) **createNotice**  
Function passed down as a prop that adds a new notice.

*Parameters*

- *notice* `object`: Notice to add.

[#](with-notices.md#createErrorNotice) **createErrorNotice**  
Function passed as a prop that adds a new error notice.

*Parameters*

- *msg* `string`: Error message of the notice.

[#](with-notices.md#removeAllNotices) **removeAllNotices**  
Function that removes all notices.

[#](with-notices.md#removeNotice) **removeNotice**  
Function that removes notice by ID.

*Parameters*

- *id* `string`: ID of notice to remove.

[#](with-notices.md#noticeUi)**noticeUi**  
The rendered `NoticeList`.

[#](with-notices.md#noticeList)**noticeList**  
The array of notice objects to be displayed.

## Usage

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { withNotices, Button } from '@wordpress/components'; const MyComponentWithNotices = withNotices( ( { noticeOperations, noticeUI } ) => { const addError = () => noticeOperations.createErrorNotice( 'Error message' ); return ( <div> { noticeUI } <Button variant="secondary" onClick={ addError }> Add error </Button> </div> ); });
```
