---
source_url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-keycodes/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: package-reference
slug: keycodes
parent_order: 3
sub_order: 9
page_order: 72
title: "@wordpress/keycodes"
code_quality: degraded
code_issue: pre_newline_loss
---

# @wordpress/keycodes

Keycodes utilities for WordPress, used to check the key pressed in events like `onKeyDown`. Contains keycodes constants for keyboard keys like `DOWN`, `UP`, `ENTER`, etc.

## Installation

Install the module

```bash
npm install @wordpress/keycodes --save
```

*This package assumes that your code will run in an **ES2015+** environment. If you’re using an environment that has limited or no support for such language features and APIs, you should include [the polyfill shipped in `@wordpress/babel-preset-default`](https://github.com/WordPress/gutenberg/tree/HEAD/packages/babel-preset-default#polyfill) in your code.*

## Usage

Check which key was used in an `onKeyDown` event:

```js
import { DOWN, ENTER } from '@wordpress/keycodes'; // [...] onKeyDown( event ) { const { keyCode } = event; if ( keyCode === DOWN ) { alert( 'You pressed the down arrow!' ); } else if ( keyCode === ENTER ) { alert( 'You pressed the enter key!' ); } else { alert( 'You pressed another key.' ); }}
```

## API

### ALT

Keycode for ALT key.

### ariaKeyShortcut

An object that contains functions to get shortcuts in a format compatible with the [`aria-keyshortcuts` HTML attribute](https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Reference/Attributes/aria-keyshortcuts).

**Note**: The provided shortcut character strings (ie. not the modifiers) should follow the values specified in the [UI Events KeyboardEvent key Values spec](https://www.w3.org/TR/uievents-key/) — for example, “Enter”, “Tab”, “ArrowRight”, “PageDown”, “Escape”, “Plus”, or “F1”. The spacebar key should be represented with the “Space” string (an exception to the UI Events KeyboardEvent key Values spec).

*Related*

- [https://www.w3.org/TR/wai-aria-1.2/#aria-keyshortcuts](https://www.w3.org/TR/wai-aria-1.2/#aria-keyshortcuts)
- [https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Reference/Attributes/aria-keyshortcuts](https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Reference/Attributes/aria-keyshortcuts)
- [https://www.w3.org/TR/uievents-key/](https://www.w3.org/TR/uievents-key/)

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```text
// Assuming macOS:ariaKeyShortcut.primary( 'm' );// "Meta+M" ariaKeyShortcut.primaryAlt( 'm' );// "Meta+Alt+M" // Assuming Windows:ariaKeyShortcut.primary( 'm' );// "Control+M" ariaKeyShortcut.primaryAlt( 'm' );// "Control+Alt+M" ariaKeyShortcut.primaryShift( 'del' );// "Control+Shift+Delete"
```

### BACKSPACE

Keycode for BACKSPACE key.

### COMMAND

Keycode for COMMAND/META key.

### CTRL

Keycode for CTRL key.

### DELETE

Keycode for DELETE key.

### displayShortcut

An object that contains functions to display shortcuts.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```text
// Assuming macOS:displayShortcut.primary( 'm' );// "⌘M"
```

Keyed map of functions to display shortcuts.

### displayShortcutList

Return an array of the parts of a keyboard shortcut chord for display.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```text
// Assuming macOS:displayShortcutList.primary( 'm' );// [ "⌘", "M" ]
```

Keyed map of functions to shortcut sequences.

### DOWN

Keycode for DOWN key.

### END

Keycode for END key.

### ENTER

Keycode for ENTER key.

### ESCAPE

Keycode for ESCAPE key.

### F10

Keycode for F10 key.

### HOME

Keycode for HOME key.

### isAppleOS

Return true if platform is MacOS.

*Parameters*

- *\_window* `Window`: window object by default; used for DI testing.

*Returns*

- `boolean`: True if MacOS; false otherwise.

### isKeyboardEvent

An object that contains functions to check if a keyboard event matches a predefined shortcut combination.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```text
// Assuming an event for ⌘M key press:isKeyboardEvent.primary( event, 'm' );// true
```

Keyed map of functions to match events.

### LEFT

Keycode for LEFT key.

### modifiers

Object that contains functions that return the available modifier depending on platform.

*Type*

- `WPModifierHandler< WPModifier >`

### PAGEDOWN

Keycode for PAGEDOWN key.

### PAGEUP

Keycode for PAGEUP key.

### rawShortcut

An object that contains functions to get raw shortcuts.

These are intended for user with the KeyboardShortcuts.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```text
// Assuming macOS:rawShortcut.primary( 'm' );// "meta+m"
```

### RIGHT

Keycode for RIGHT key.

### SHIFT

Keycode for SHIFT key.

### shortcutAriaLabel

An object that contains functions to return an aria label for a keyboard shortcut.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
// Assuming macOS:shortcutAriaLabel.primary( '.' );// "Command + Period"
```

Keyed map of functions to shortcut ARIA labels.

### SPACE

Keycode for SPACE key.

### TAB

Keycode for TAB key.

### UP

Keycode for UP key.

### ZERO

Keycode for ZERO key.

## Contributing to this package

This is an individual package that’s part of the Gutenberg project. The project is organized as a monorepo. It’s made up of multiple self-contained software packages, each with a specific purpose. The packages in this monorepo are published to [npm](https://www.npmjs.com/) and used by [WordPress](https://make.wordpress.org/core/) as well as other software projects.

To find out more about contributing to this package or Gutenberg as a whole, please read the project’s main [contributor guide](https://github.com/WordPress/gutenberg/tree/HEAD/CONTRIBUTING.md).
