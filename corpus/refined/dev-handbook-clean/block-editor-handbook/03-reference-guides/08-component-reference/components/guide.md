---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/guide/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: guide
parent_order: 3
sub_order: 8
page_order: 56
title: "Guide"
code_quality: degraded
code_issue: pre_newline_loss
---

# Guide

`Guide` is a React component that renders a *user guide* in a modal. The guide consists of several pages which the user can step through one by one. The guide is finished when the modal is closed or when the user clicks *Finish* on the last page of the guide.

## Usage

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
function MyTutorial() { const [ isOpen, setIsOpen ] = useState( true ); if ( ! isOpen ) { return null; } return ( <Guide onFinish={ () => setIsOpen( false ) } pages={ [ { content: <p>Welcome to the ACME Store!</p>, }, { image: <img src="https://acmestore.com/add-to-cart.png" />, content: ( <p> Click <i>Add to Cart</i> to buy a product. </p> ), }, ] } /> );}
```

## Props

The component accepts the following props:

### className

A custom class to add to the modal.

- Type: `string`
- Required: No

### contentLabel

This property is used as the modal’s accessibility label. It is required for accessibility reasons.

- Type: `string`
- Required: Yes

### finishButtonText

Use this to customize the label of the *Finish* button shown at the end of the guide.

- Type: `string`
- Required: No
- Default: `'Finish'`

### nextButtonText

Use this to customize the label of the *Next* button shown on each page of the guide.

- Type: `string`
- Required: No
- Default: `'Next'`

### previousButtonText

Use this to customize the label of the *Previous* button shown on each page of the guide except the first.

- Type: `string`
- Required: No
- Default: `'Previous'`

### onFinish

A function which is called when the guide is finished. The guide is finished when the modal is closed or when the user clicks *Finish* on the last page of the guide.

- Type: `( event?: KeyboardEvent< HTMLDivElement > | SyntheticEvent ) => void`
- Required: Yes

### pages

A list of objects describing each page in the guide. Each object **must** contain a `'content'` property and may optionally contain a `'image'` property.

- Type: `{ content: ReactNode; image?: ReactNode; }[]`
- Required: No
- Default: `[]`
