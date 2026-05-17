---
source_url: https://developer.wordpress.org/themes/global-settings-and-styles/settings/settings-reference/
synced: 2026-05-12
handbook: theme
chapter: theme-json
sub_chapter: settings
slug: settings-reference
parent_order: 3
sub_order: 2
page_order: 14
title: "Settings Reference"
---

# Settings Reference

The document is a reference to the available settings properties that you can configure via the `settings` object in `theme.json`. Each of the settings has an in-depth guide on how to use it within the [Settings documentation](index.md).

## Appearance Tools

`settings.appearanceTools` is a top-level property with no sub-properties nested beneath it. It is documented at [Settings: Appearance Tools](https://developer.wordpress.org/global-settings-and-styles/settings/appearance-tools/).

| Property | Type | Default |
| --- | --- | --- |
| `appearanceTools` | boolean | `false` |

## Border

`settings.border` is an object that supports the nested properties listed in the below table. It is documented at [Settings: Border](border.md).

| Property | Type | Default |
| --- | --- | --- |
| `color` | boolean | `false` |
| `radius` | boolean | `false` |
| `style` | boolean | `false` |
| `width` | boolean | `false` |

Enabling any one of the `color`, `style`, or `width` settings will automatically enable the other two since the properties are linked together.

## Color

`settings.color` is an object that supports the nested properties listed in the below table. It is documented at [Settings: Color](color.md).

| Property | Type | Default | Props |
| --- | --- | --- | --- |
| `background` | boolean | `true` | — |
| `custom` | boolean | `true` | — |
| `customDuotone` | boolean | `true` | — |
| `customGradient` | boolean | `true` | — |
| `defaultDuotone` | boolean | `true` | — |
| `defaultGradients` | boolean | `true` | — |
| `defaultPalette` | boolean | `true` | — |
| `duotone` | array &lt;object&gt; | `array` | `colors`, `name`, `slug` |
| `gradients` | array &lt;object&gt; | `array` | `gradient`, `name`, `slug` |
| `link` | boolean | `false` | — |
| `palette` | array &lt;object&gt; | `array` | `color`, `name`, `slug` |
| `text` | boolean | `true` | — |

## Custom

`settings.custom` is an object that supports any number of nested custom properties, as shown in the below table. It is documented at [Settings: Custom](custom.md).

| Property | Type | Default |
| --- | --- | --- |
| `custom.<custom>` | any | — |

## Dimensions

`settings.dimensions` is an object that supports the nested properties listed in the below table. It is documented at [Settings: Dimensions](dimensions.md).

| Property | Type | Default |
| --- | --- | --- |
| `minHeight` | boolean | `false` |

## Layout

`settings.layout` is an object that supports the nested properties listed in the below table. It is documented at [Settings: Layout](layout.md).

| Property | Type | Default |
| --- | --- | --- |
| `contentSize` | string | `""` |
| `wideSize` | string | `""` |

## Lightbox

`settings.lightbox` is an object that supports the nested properties listed in the below table. It is documented at [Settings: Lightbox](lightbox.md).

| Property | Type | Default |
| --- | --- | --- |
| `allowEditing` | boolean | `true` |
| `enabled` | boolean | `false` |

This setting is only available as of WordPress 6.4 and is specific to the core Image block (`core/image`).

## Position

`settings.position` is an object that supports the nested properties listed in the below table. It is documented at [Settings: Position](position.md).

| Property | Type | Default |
| --- | --- | --- |
| `sticky` | boolean | `false` |

## Shadow

`settings.shadow` is an object that supports the nested properties listed in the below table. It is documented at [Settings: Shadow](shadow.md).

| Property | Type | Default | Props |
| --- | --- | --- | --- |
| `defaultPresets` | boolean | `true` |  |
| `presets` | array &lt;object&gt; | `array` | `name`, `shadow`, `slug` |

## Spacing

`settings.spacing` is an object that supports the nested properties listed in the below table. It is documented at [Settings: Spacing](spacing.md).

| Property | Type | Default | Props |
| --- | --- | --- | --- |
| `blockGap` | boolean|null | `null` | — |
| `customSpacingSize` | boolean | `true` | — |
| `margin` | boolean | `false` | — |
| `padding` | boolean | `false` | — |
| `spacingScale` | object | `object` | `operator`, `increment`, `steps`, `mediumStep`, `unit` |
| `spacingSizes` | array &lt;object&gt; | `array` | `name`, `size`, `slug` |
| `units` | array &lt;string&gt; | `[ "px", "em", "rem", "vh", "vw", "%" ]` | — |

## Typography

`settings.typography` is an object that supports the nested properties listed in the below table. It is documented at [Settings: Typography](typography.md).

| Property | Type | Default | Props |
| --- | --- | --- | --- |
| `customFontSize` | boolean | `true` | — |
| `dropCap` | boolean | `true` | — |
| `fontFamilies` | array &lt;object&gt; | `array` | `fontFace`, `fontFamily`, `name`, `slug` |
| `fontSizes` | array &lt;object&gt; | `array` | `fluid`, `name`, `size`, `slug` |
| `fontStyle` | boolean | `true` | — |
| `fontWeight` | boolean | `true` | — |
| `fluid` | boolean | `false` | — |
| `letterSpacing` | boolean | `true` | — |
| `lineHeight` | boolean | `false` | — |
| `textColumns` | boolean | `false` | — |
| `textDecoration` | boolean | `true` | — |
| `textTransform` | boolean | `true` | — |
| `writingMode` | boolean | `false` | — |

## Use Root Padding Aware Alignments

`settings.useRootPaddingAwareAlignments` is a top-level property with no sub-properties nested beneath it. It is documented at [Settings: Use Root Padding Aware Alignments](use-root-padding-aware-alignments.md).

| Property | Type | Default |
| --- | --- | --- |
| `useRootPaddingAwareAlignments` | boolean | `false` |

This setting works together with `styles.spacing.padding` in `theme.json`. If enabled, `styles.spacing.padding` must be an object that defines the `top`, `right`,  `bottom`, and `left` styles separately.
