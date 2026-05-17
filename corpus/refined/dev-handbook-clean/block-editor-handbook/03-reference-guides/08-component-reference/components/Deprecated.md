---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/button-group/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: Deprecated
parent_order: 3
sub_order: 8
page_order: 49
title: "FocusableIframe"
---

---

This component is deprecated. Use `ToggleGroupControl` instead.

---


---

https://developer.wordpress.org/block-editor/reference-guides/components/clipboard-button/
This component is deprecated. Please use the `useCopyToClipboard` hook from the `@wordpress/compose` package instead.

---

---

https://developer.wordpress.org/block-editor/reference-guides/components/focusable-iframe/
# FocusableIframe

**Deprecated**

`<FocusableIframe />` is a component rendering an `iframe` element enhanced to support focus events. By default, it is not possible to detect when an iframe is focused or clicked within. This enhanced component uses a technique which checks whether the target of a window `blur` event is the iframe, inferring that this has resulted in the focus of the iframe. It dispatches an emulated [`FocusEvent`](https://developer.mozilla.org/en-US/docs/Web/API/FocusEvent) on the iframe element with event bubbling, so a parent component binding its own `onFocus` event will account for focus transitioning within the iframe.

[Deprecated]
FocusableIframe

[Reason]
- workaround for iframe focus detection limitation
- replaced by modern event handling patterns

[Status]
DO NOT USE

[Alternative]
- standard iframe + postMessage
- explicit event bridge

---

---

https://developer.wordpress.org/block-editor/reference-guides/components/isolated-event-container/

# IsolatedEventContainer
**Deprecated**

This is a container that prevents certain events from propagating outside of the container. This is used to wrap  
UI elements such as modals and popovers where the propagated event can cause problems. The event continues to work  
inside the component.

For example, a `mousedown` event in a modal container can propagate to the surrounding DOM, causing UI outside of the  
modal to be interacted with.

The current isolated events are:

- mousedown – This prevents UI interaction with other `mousedown` event handlers, such as selection

## Usage

Creates a custom component that won’t propagate `mousedown` events outside of the component.

```jsx
import { IsolatedEventContainer } from '@wordpress/components'; const MyModal = () => { return ( <IsolatedEventContainer className="component-some_component" onClick={ clickHandler } > <p>This is an isolated component</p> </IsolatedEventContainer> );};
```

## Props

All props are passed as-is to the `<IsolatedEventContainer />`

[Pattern]
Event Isolation

[Problem]
Events inside UI (modal/popover) propagate to parent DOM causing unintended interactions

[Solution]
- stopPropagation on critical events
- manage event boundaries explicitly
- use controlled overlay patterns

[Deprecated Implementation]
IsolatedEventContainer (DO NOT USE)

---

---

https://developer.wordpress.org/block-editor/reference-guides/components/navigation/
This component is deprecated. Consider using `Navigator` instead.

---

---

https://developer.wordpress.org/block-editor/reference-guides/components/radio-group/
This component is deprecated. Consider using `RadioControl` or `ToggleGroupControl` instead.

---
