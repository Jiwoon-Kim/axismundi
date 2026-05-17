---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/form-toggle/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: form-toggle
parent_order: 3
sub_order: 8
page_order: 52
title: "FormToggle"
code_quality: degraded
code_issue: pre_newline_loss
---

# FormToggle

FormToggle switches a single setting on or off.

## Design guidelines

### Usage

#### When to use toggles

Use toggles when you want users to:

- Switch a single option on or off.
- Immediately activate or deactivate something.

**Do**  
Use toggles to switch an option on or off.

**Don’t**  
Don’t use radio buttons for settings that toggle on and off.

Toggles are preferred when the user is not expecting to submit data, as is the case with checkboxes and radio buttons.

#### State

When the user slides a toggle thumb (1) to the other side of the track (2) and the state of the toggle changes, it’s been successfully toggled.

#### Text label

Toggles should have clear inline labels so users know exactly what option the toggle controls, and whether the option is enabled or disabled.

Do not include any text (e.g. “on” or “off”) within the toggle element itself. The toggle alone should be sufficient to communicate the state.

### Behavior

When a user switches a toggle, its corresponding action takes effect immediately.

## Development guidelines

### Usage

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { useState } from 'react';import { FormToggle } from '@wordpress/components'; const MyFormToggle = () => { const [ isChecked, setChecked ] = useState( true ); return ( <FormToggle checked={ isChecked } onChange={ () => setChecked( ( state ) => ! state ) } /> );};
```

### Props

The component accepts the following props:

#### checked: boolean

If checked is true the toggle will be checked. If checked is false the toggle will be unchecked.  
If no value is passed the toggle will be unchecked.

- Required: No

#### disabled: boolean

If disabled is true the toggle will be disabled and apply the appropriate styles.

- Required: No

#### onChange: ( event: ChangeEvent&lt;HTMLInputElement&gt; ) =&gt; void

A callback function invoked when the toggle is clicked.

- Required: Yes

## Related components

- To select one option from a set, and you want to show them all the available options at once, use the `Radio` component.
- To select one or more items from a set, use the `CheckboxControl` component.
- To display a toggle with label and help text, use the `ToggleControl` component.
