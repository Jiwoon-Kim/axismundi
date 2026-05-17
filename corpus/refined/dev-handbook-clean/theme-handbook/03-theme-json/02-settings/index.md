---
source_url: https://developer.wordpress.org/themes/global-settings-and-styles/settings/
synced: 2026-05-12
handbook: theme
chapter: theme-json
sub_chapter: settings
slug: index
parent_order: 3
sub_order: 2
page_order: 0
title: "Settings"
---

# Settings

The `settings` property in `theme.json` lets you configure a wide range of settings for a WordPress install. It covers everything from color presets, to enabling typography design tools, to layout, and a little bit of everything in between.

This document contains links for learning about each of these settings, which have their own individual documentation pages.

## The settings property

`settings` is a top-level property in `theme.json` and has multiple nested properties that you can define. And some of those nested properties have multiple levels of nesting of their own.

The following is an overarching look at these properties in the context of a `theme.json` file:


```json
{ "version": 2, "settings": { "appearanceTools": false, "border": {}, "color": {}, "custom": {}, "dimensions": {}, "layout": {}, "position": {}, "shadow": {}, "spacing": {}, "typography": {}, "useRootPaddingAwareAlignments": false, "blocks": {} }}
```

## Settings documentation

Use the following links to explore specific settings that you can configure in your `theme.json` file:

- **[`appearanceTools`](appearance-tools.md):** A catchall setting for enabling multiple other settings.
- **`border`:** Used for controlling the border width, style, color, and radius.
- **[`color`](color.md):** Lets you register a color palette, gradients, duotone and configure color-related settings.
- [`custom`](custom.md): An object for adding custom settings, which are output as CSS custom properties.
- **[`dimensions`](dimensions.md):** Lets you configure the minimum height setting.
- **[`layout`](layout.md):** Used for setting layout properties like the content and wide widths.
- **[`lightbox`](lightbox.md):** Lets you configure the image lightbox feature.
- **[`position`](position.md):** Currently lets you define support for sticky positioning.
- **[`shadow`](shadow.md):** Lets you configure box-shadow support and define custom shadow presets.
- **[`spacing`](spacing.md):** Used for configuring spacing-related settings, such as margin and padding,
- **[`typography`](typography.md):** Used for configuring typography-related settings, defining custom font sizes, and registering font families.
- **[`useRootPaddingAwareAlignments`](use-root-padding-aware-alignments.md):** A boolean setting for how padding on the root element should work.
- **[`blocks`](blocks.md):** An object for configuring per-block settings.

The Theme Handbook also maintains a [reference for available settings](settings-reference.md) based on the `theme.json` schema.
