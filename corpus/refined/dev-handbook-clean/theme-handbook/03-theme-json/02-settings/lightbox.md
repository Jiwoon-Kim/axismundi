---
source_url: https://developer.wordpress.org/themes/global-settings-and-styles/settings/lightbox/
synced: 2026-05-12
handbook: theme
chapter: theme-json
sub_chapter: settings
slug: lightbox
parent_order: 3
sub_order: 2
page_order: 8
title: "Lightbox"
---

# Lightbox

`settings.lightbox` is a specific setting that you can enable for supported blocks. It enables a lightbox feature that expands an image when a site visitor clicks on an image.

This setting is only available as of WordPress 6.4 and is specific to the core Image block (`core/image`).

## Lightbox settings

The `lightbox` setting is specific to the Image block, so the following examples will be shown in that context.

The `lightbox` property is an object that has two nested properties that you can configure:

- **`enabled`:** Whether to enable the lightbox feature for the Image block. The default value is `undefined` (the equivalent of being disabled).
- **`allowEditing`:** Whether to show the **Expand on click** option in the interface, which allows the user to enable/disable lightbox for individual images. Defaults to `true`.

Here is a look at the default `theme.json`:


```json
{ "version": 2, "settings": { "blocks": { "core/image": { "lightbox": { "allowEditing": true } } } }}
```

### Enabling lightbox for images

To enable the lightbox feature for Image blocks used throughout the site, you must set `settings.blocks.core/image.lightbox.enabled` to true in `theme.json`:


```json
{ "version": 2, "settings": { "blocks": { "core/image": { "lightbox": { "enabled": true } } } }}
```

On the front-end of the site, visitors will be able to expand the image when clicking on it. The image will then overlay the entire screen (including an **x** button for closing the overlay), as shown below:

### Disabling user editing

By default, WordPress will show an **Expand on Click** option under the **Settings** tab for the Image block:

This control allows your theme’s users to enable or disable the lightbox feature on a per-block basis.

To disallow user editing, you must set `settings.blocks.core/image.lightbox.allowEditing` to `false` in `theme.json`:


```json
{ "version": 2, "settings": { "blocks": { "core/image": { "lightbox": { "allowEditing": false } } } }}
```
