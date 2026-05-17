---
source_url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-nux/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: package-reference
slug: nux
parent_order: 3
sub_order: 9
page_order: 82
title: "@wordpress/nux"
code_quality: degraded
code_issue: pre_newline_loss
---

# @wordpress/nux

The NUX module exposes components, and `wp.data` methods useful for onboarding a new user to the WordPress admin interface. Specifically, it exposes *tips* and *guides*.

A *tip* is a component that points to an element in the UI and contains text that explains the element’s functionality. The user can dismiss a tip, in which case it never shows again. The user can also disable tips entirely. Information about tips is persisted between sessions using `localStorage`.

A *guide* allows a series of tips to be presented to the user one by one. When a user dismisses a tip that is in a guide, the next tip in the guide is shown.

## Installation

Install the module

```bash
npm install @wordpress/nux --save
```

*This package assumes that your code will run in an **ES2015+** environment. If you’re using an environment that has limited or no support for such language features and APIs, you should include [the polyfill shipped in `@wordpress/babel-preset-default`](https://github.com/WordPress/gutenberg/tree/HEAD/packages/babel-preset-default#polyfill) in your code.*

## DotTip

`DotTip` is a React component that renders a single *tip* on the screen. The tip will point to the React element that `DotTip` is nested within. Each tip is uniquely identified by a string passed to `tipId`.

See [the component’s README](https://github.com/WordPress/gutenberg/tree/HEAD/packages/nux/src/components/dot-tip/README.md) for more information.

```jsx
<button onClick={ ... }> Add to Cart <DotTip tipId="acme/add-to-cart"> Click here to add the product to your shopping cart. </DotTip></button>}
```

## Determining if a tip is visible

You can programmatically determine if a tip is visible using the `isTipVisible` select method.

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
const isVisible = select( 'core/nux' ).isTipVisible( 'acme/add-to-cart' );console.log( isVisible ); // true or false
```

## Manually dismissing a tip

`dismissTip` is a dispatch method that allows you to programmatically dismiss a tip.

```js
<button onClick={ () => { dispatch( 'core/nux' ).dismissTip( 'acme/add-to-cart' ); } }> Dismiss tip</button>
```

## Disabling and enabling tips

Tips can be programmatically disabled or enabled using the `disableTips` and `enableTips` dispatch methods. You can query the current setting by using the `areTipsEnabled` select method.

Calling `enableTips` will also un-dismiss all previously dismissed tips.

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
const areTipsEnabled = select( 'core/nux' ).areTipsEnabled();return ( <button onClick={ () => { if ( areTipsEnabled ) { dispatch( 'core/nux' ).disableTips(); } else { dispatch( 'core/nux' ).enableTips(); } } } > { areTipsEnabled ? 'Disable tips' : 'Enable tips' } </button>);
```

## Triggering a guide

You can group a series of tips into a guide by calling the `triggerGuide` dispatch method. The given tips will then appear one by one.

A tip cannot be added to more than one guide.

```text
dispatch( 'core/nux' ).triggerGuide( [ 'acme/product-info', 'acme/add-to-cart', 'acme/checkout',] );
```

## Getting information about a guide

`getAssociatedGuide` is a select method that returns useful information about the state of the guide that a tip is associated with.

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
const guide = select( 'core/nux' ).getAssociatedGuide( 'acme/add-to-cart' );console.log( 'Tips in this guide:', guide.tipIds );console.log( 'Currently showing:', guide.currentTipId );console.log( 'Next to show:', guide.nextTipId );
```

## Contributing to this package

This is an individual package that’s part of the Gutenberg project. The project is organized as a monorepo. It’s made up of multiple self-contained software packages, each with a specific purpose. The packages in this monorepo are published to [npm](https://www.npmjs.com/) and used by [WordPress](https://make.wordpress.org/core/) as well as other software projects.

To find out more about contributing to this package or Gutenberg as a whole, please read the project’s main [contributor guide](https://github.com/WordPress/gutenberg/tree/HEAD/CONTRIBUTING.md).
