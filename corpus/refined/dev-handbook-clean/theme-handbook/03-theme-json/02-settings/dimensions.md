---
source_url: https://developer.wordpress.org/themes/global-settings-and-styles/settings/dimensions/
synced: 2026-05-12
handbook: theme
chapter: theme-json
sub_chapter: settings
slug: dimensions
parent_order: 3
sub_order: 2
page_order: 6
title: "Dimensions"
---

# Dimensions

The `settings.dimensions` property in `theme.json` gives you control over the global dimensions settings for blocks. This property lets you decide which dimension controls are available in the user interface.

In this document, you will learn what the `dimensions` property is for and how you can use it in your theme.

## Dimensions settings

`dimensions` is an object that’s nested directly within the top-level `settings` property in `theme.json`. Currently, it only lets you set a single property: 

- **`minHeight`:** A boolean value for enabling block support for the **Minimum Height** control.

Take a look at the `dimensions` property in the context of a `theme.json` file with its default values:


```json
{ "version": 2, "settings": { "dimensions": { "minHeight": false } }}
```

### Minimum Height

The `settings.dimensions.minHeight` property lets you control whether the **Minimum Height** field appears for blocks that have opted into support for the feature. As of WordPress 6.3, the only core WordPress blocks that do are Group and Post Content.

To enable support for the control, you must set the property’s value to `true` in `theme.json`:


```json
{ "version": 2, "settings": { "dimensions": { "minHeight": true } }}
```

This will enable the control in the interface. As shown in this screenshot, the **Minimum Height** field appears for the Group block (Stack variation):
