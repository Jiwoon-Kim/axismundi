---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/date-picker/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: date-picker
parent_order: 3
sub_order: 8
page_order: 33
title: "DatePicker"
code_quality: degraded
code_issue: pre_newline_loss
---

# DatePicker

DatePicker is a React component that renders a calendar for date selection. It can be used independently or as part of the `DateTimePicker` component.

## Usage

Render a DatePicker.

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { useState } from 'react';import { DatePicker } from '@wordpress/components'; const MyDatePicker = () => { const [ date, setDate ] = useState( new Date() ); return ( <DatePicker currentDate={ date } onChange={ ( newDate ) => setDate( newDate ) } /> );};
```

## Props

The component accepts the following props:

### currentDate: Date | string | number | null

The current date at initialization. Optionally pass in a `null` value to specify no date is currently selected.

- Required: No
- Default: today’s date

### onChange: ( date: string ) =&gt; void

The function called when a new date has been selected. It is passed the `currentDate` as an argument.

- Required: No

### [events: { date: Date }\[\]](https://developer.wordpress.org/block-editor/reference-guides/components/date-picker/#events-date-date)

List of events to show in the date picker. Each event will appear as a dot on the day of the event.

- Required: No

### isInvalidDate: ( date: Date ) =&gt; boolean

A callback function which receives a Date object representing a day as an argument, and should return a Boolean to signify if the day is valid or not.

- Required: No

### onMonthPreviewed: ( date: string ) =&gt; void

A callback invoked when selecting the previous/next month in the date picker. The callback receives the new month date in the ISO format as an argument.

- Required: No

### startOfWeek: 0 | 1 | 2 | 3 | 4 | 5 | 6

The day that the week should start on. 0 for Sunday, 1 for Monday, etc.

- Required: No
- Default: 0
