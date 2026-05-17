---
source_url: https://developer.wordpress.org/block-editor/reference-guides/data/data-core-nux/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: data-module-reference
slug: data-core-nux
parent_order: 3
sub_order: 10
page_order: 14
title: "The NUX (New User Experience) Data"
---

# The NUX (New User Experience) Data

Namespace: `core/nux`.

## Selectors

### areTipsEnabled

Returns whether or not tips are globally enabled.

*Parameters*

- *state* `Object`: Global application state.

*Returns*

- `boolean`: Whether tips are globally enabled.

### getAssociatedGuide

Returns an object describing the guide, if any, that the given tip is a part of.

*Parameters*

- *state* `Object`: Global application state.
- *tipId* `string`: The tip to query.

*Returns*

- `?NUXGuideInfo`: Information about the associated guide.

### isTipVisible

Determines whether or not the given tip is showing. Tips are hidden if they are disabled, have been dismissed, or are not the current tip in any guide that they have been added to.

*Parameters*

- *state* `Object`: Global application state.
- *tipId* `string`: The tip to query.

*Returns*

- `boolean`: Whether or not the given tip is showing.

## Actions

### disableTips

Returns an action object that, when dispatched, prevents all tips from showing again.

*Returns*

- `Object`: Action object.

### dismissTip

Returns an action object that, when dispatched, dismisses the given tip. A dismissed tip will not show again.

*Parameters*

- *id* `string`: The tip to dismiss.

*Returns*

- `Object`: Action object.

### enableTips

Returns an action object that, when dispatched, makes all tips show again.

*Returns*

- `Object`: Action object.

### triggerGuide

Returns an action object that, when dispatched, presents a guide that takes the user through a series of tips step by step.

*Parameters*

- *tipIds* `string[]`: Which tips to show in the guide.

*Returns*

- `Object`: Action object.
