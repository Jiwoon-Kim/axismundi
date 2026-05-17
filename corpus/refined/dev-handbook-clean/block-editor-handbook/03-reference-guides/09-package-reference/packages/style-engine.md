---
source_url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-style-engine/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: package-reference
slug: style-engine
parent_order: 3
sub_order: 9
page_order: 104
title: "@wordpress/style-engine"
code_quality: degraded
code_issue: pre_newline_loss
---

# @wordpress/style-engine

The Style Engine aims to provide a consistent API for rendering styling for blocks across both client-side and server-side applications.

Initially, it will offer a single, centralized agent responsible for generating block styles, and, in later phases, it will also assume the responsibility of processing and rendering optimized frontend CSS.

## Please note

This package is new as of WordPress 6.1 and therefore in its infancy.

Upcoming tasks on the roadmap include, but are not limited to, the following:

- Consolidate global and block style rendering and enqueuing (ongoing)
- Explore pre-render CSS rule processing with the intention of deduplicating other common and/or repetitive block styles. (ongoing)
- Extend the scope of semantic class names and/or design token expression, and encapsulate rules into stable utility classes.
- Explore pre-render CSS rule processing with the intention of deduplicating other common and/or repetitive block styles.
- Propose a way to control hierarchy and specificity, and make the style hierarchy cascade accessible and predictable. This might include preparing for CSS cascade layers until they become more widely supported, and allowing for opt-in support in Gutenberg via theme.json.
- Refactor all blocks to consistently use the “style” attribute for all customizations, that is, deprecate preset-specific attributes such as `attributes.fontSize`.

For more information about the roadmap, please refer to [Block editor styles: initiatives and goals](https://make.wordpress.org/core/2022/06/24/block-editor-styles-initiatives-and-goals/) and the [GitHub project board](https://github.com/orgs/WordPress/projects/19).

If you’re making changes or additions to the Style Engine, please take a moment to read the [notes on contributing](https://github.com/WordPress/gutenberg/tree/HEAD/packages/style-engine/CONTRIBUTING.md).

## Backend API

### wp_style_engine_get_styles()

Global public function to generate styles from a single style object, e.g., the value of a [block’s attributes.style object](../../07-theme-json-reference/version-3-reference.md#styles) or the [top level styles in theme.json](../../01-block-api-reference/supports.md).

See also [Using the Style Engine to generate block supports styles](https://github.com/WordPress/gutenberg/tree/HEAD/packages/style-engine/docs/using-the-style-engine-with-block-supports.md).

*Parameters*

- *$block\_styles* `array` A block’s `attributes.style` object or the top level styles in theme.json
- *$options* `array<string|boolean>` An array of options to determine the output.
```php
- *context* `string` An identifier describing the origin of the style object, e.g., ‘block-supports’ or ‘global-styles’. Default is ‘block-supports’. When both `context` and `selector` are set, the Style Engine will store the CSS rules using the `context` as a key.
- *convert\_vars\_to\_classnames* `boolean` Whether to skip converting CSS var:? values to var( –wp–preset–\* ) values. Default is `false`.
- *selector* `string` When a selector is passed, `generate()` will return a full CSS rule `$selector { ...rules }`, otherwise a concatenated string of properties and values.
```

*Returns*  
`array<string|array>|null`

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
array( 'css' => (string) A CSS ruleset or declarations block formatted to be placed in an HTML `style` attribute or tag. 'declarations' => (array) An array of property/value pairs representing parsed CSS declarations. 'classnames' => (string) Classnames separated by a space.);
```

It will return compiled CSS declarations for inline styles, or, where a selector is provided, a complete CSS rule.

To enqueue a style for rendering in the site’s frontend, the `$options` array requires the following:

1. **selector (string)** – this is the CSS selector for your block style CSS declarations.
2. **context (string)** – this tells the Style Engine where to store the styles. Styles in the same context will be stored together.

`wp_style_engine_get_styles` will return the compiled CSS and CSS declarations array.

#### Usage

As mentioned, `wp_style_engine_get_styles()` is useful whenever you wish to generate CSS and/or classnames from a **block’s style object**. A good example is [using the Style Engine to generate block supports styles](https://github.com/WordPress/gutenberg/tree/HEAD/packages/style-engine/docs/using-the-style-engine-with-block-supports.md).

In the following snippet, we’re taking the style object from a block’s attributes and passing it to the Style Engine to get the styles. By passing a `context` in the options, the Style Engine will store the styles for later retrieval, for example, should you wish to batch enqueue a set of CSS rules.

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
$block_attributes = array( 'style' => array( 'spacing' => array( 'padding' => '100px' ), ),); $styles = wp_style_engine_get_styles( $block_attributes['style'], array( 'selector' => '.a-selector', 'context' => 'block-supports', ));print_r( $styles ); /*array( 'css' => '.a-selector{padding:100px}' 'declarations' => array( 'padding' => '100px' ))*/
```

### wp_style_engine_get_stylesheet_from_css_rules()

Use this function to compile and return a stylesheet for any CSS rules. The Style Engine will automatically merge declarations and combine selectors.

This function acts as a CSS compiler, but will also register the styles in a store where a `context` string is passed in the options.

*Parameters*

- *$css\_rules* `array<array>`
- *$options* `array<string|bool>` An array of options to determine the output.
```text
- *context* `string` An identifier describing the origin of the style object, e.g., ‘block-supports’ or ‘global-styles’. Default is ‘block-supports’. When set, the Style Engine will attempt to store the CSS rules.
- *prettify* `bool` Whether to add new lines and indents to output. Default is to inherit the value of the global constant `SCRIPT_DEBUG`, if it is defined.
- *optimize* `bool` Whether to optimize the CSS output, e.g., combine rules. Default is `false`.
```

*Returns*  
`string` A compiled CSS string based on `$css_rules`.

#### Usage

Useful for when you wish to compile a bespoke set of CSS rules from a series of selector + declaration items.

The Style Engine will return a sanitized stylesheet. By passing a `context` identifier in the options, the Style Engine will store the styles for later retrieval, for example, should you wish to batch enqueue a set of CSS rules.

You can call `wp_style_engine_get_stylesheet_from_css_rules()` multiple times, and, so long as your styles use the same `context` identifier, they will be stored together.

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
$styles = array( array( 'selector' => '.wp-pumpkin', 'declarations' => array( 'color' => 'orange' ) ), array( 'selector' => '.wp-tomato', 'declarations' => array( 'color' => 'red' ) ), array( 'selector' => '.wp-tomato', 'declarations' => array( 'padding' => '100px' ) ),); $stylesheet = wp_style_engine_get_stylesheet_from_css_rules( $styles, array( 'context' => 'block-supports', // Indicates that these styles should be stored with block supports CSS. ));print_r( $stylesheet ); // .wp-pumpkin{color:orange}.wp-tomato{color:red;padding:100px}
```

It’s also possible to build simple, nested CSS rules using the `rules_group` key.

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
$styles = array( array( 'rules_group' => '@media (min-width: 80rem)', 'selector' => '.wp-carrot', 'declarations' => array( 'color' => 'orange' ) ), array( 'rules_group' => '@media (min-width: 80rem)', 'selector' => '.wp-tomato', 'declarations' => array( 'color' => 'red' ) ),); $stylesheet = wp_style_engine_get_stylesheet_from_css_rules( $styles, array( 'context' => 'block-supports', // Indicates that these styles should be stored with block supports CSS. ));print_r( $stylesheet ); // @media (min-width: 80rem){.wp-carrot{color:orange}}@media (min-width: 80rem){.wp-tomato{color:red;}}
```

### wp_style_engine_get_stylesheet_from_context()

Returns compiled CSS from a stored context, if found.

*Parameters*

- *$store\_name* `string` An identifier describing the origin of the style object, e.g., ‘block-supports’ or ‘ global-styles’. Default is ‘block-supports’.
- *$options* `array<bool>` An array of options to determine the output.
```text
- *prettify* `bool` Whether to add new lines and indents to output. Default is to inherit the value of the global constant `SCRIPT_DEBUG`, if it is defined.
- *optimize* `bool` Whether to optimize the CSS output, e.g., combine rules. Default is `false`.
```

*Returns*  
`string` A compiled CSS string from the stored CSS rules.

#### Usage

Use this function to generate a stylesheet using all the styles stored under a specific context identifier.

A use case would be when you wish to enqueue all stored styles for rendering to the frontend. The Style Engine will merge and deduplicate styles upon retrieval.

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
// First, let's gather and register our styles.$styles = array( array( 'selector' => '.wp-apple', 'declarations' => array( 'color' => 'green' ) ),); wp_style_engine_get_stylesheet_from_css_rules( $styles, array( 'context' => 'fruit-styles', )); // Later, we fetch compiled rules from context store.$stylesheet = wp_style_engine_get_stylesheet_from_context( 'fruit-styles' ); print_r( $stylesheet ); // .wp-apple{color:green;} if ( ! empty( $stylesheet ) ) { wp_register_style( 'my-stylesheet', false, array(), true, true ); wp_add_inline_style( 'my-stylesheet', $stylesheet ); wp_enqueue_style( 'my-stylesheet' );}
```

## Installation (JS only)

Install the module

```bash
npm install @wordpress/style-engine --save
```

*This package assumes that your code will run in an **ES2015+** environment. If you’re using an environment that has limited or no support for such language features and APIs, you should include [the polyfill shipped in `@wordpress/babel-preset-default`](https://github.com/WordPress/gutenberg/tree/HEAD/packages/babel-preset-default#polyfill) in your code.*

## Usage

### compileCSS

Generates a stylesheet for a given style object and selector.

*Parameters*

- *style* `Style`: Style object, for example, the value of a block’s attributes.style object or the top level styles in theme.json
- *options* `StyleOptions`: Options object with settings to adjust how the styles are generated.

*Returns*

- `string`: A generated stylesheet or inline style declarations.

*Changelog*

`6.1.0` Introduced in WordPress core.

### getCSSRules

Returns a JSON representation of the generated CSS rules.

*Parameters*

- *style* `Style`: Style object, for example, the value of a block’s attributes.style object or the top level styles in theme.json
- *options* `StyleOptions`: Options object with settings to adjust how the styles are generated.

*Returns*

- `GeneratedCSSRule[]`: A collection of objects containing the selector, if any, the CSS property key (camelcase) and parsed CSS value.

*Changelog*

`6.1.0` Introduced in WordPress core.

### getCSSValueFromRawStyle

Returns a WordPress CSS custom var value from incoming style preset value, if one is detected.

The preset value is a string and follows the pattern `var:description|context|slug`.

Example:

`getCSSValueFromRawStyle( 'var:preset|color|heavenlyBlue' )` // returns ‘var(–wp–preset–color–heavenly-blue)’

*Parameters*

- *styleValue* `StyleValue`: A string representing a raw CSS value. Non-strings won’t be processed.

*Returns*

- `StyleValue`: A CSS custom var value if the incoming style value is a preset value.

## Glossary

A guide to the terms and variable names referenced by the Style Engine package.

- Block style (Gutenberg internal)
```text
- An object comprising a block’s style attribute that contains a block’s style values. E.g., `{ spacing: { margin: '10px' }, color: { ... }, ... }`
```
- Context
```text
- An identifier for a group of styles that share a common origin or purpose, e.g., ‘block-supports’ or ‘global-styles’. The context is also used as a key to fetch CSS rules from the store.
```
- CSS declaration or (CSS property declaration)
```text
- A CSS property paired with a CSS value. E.g., `color: pink`
```
- CSS declarations block
```text
- A set of CSS declarations usually paired with a CSS selector to create a CSS rule.
```
- CSS property
```text
- Identifiers that describe stylistic, modifiable features of an HTML element. E.g., `border`, `font-size`, `width`…
```
- CSS rule
```text
- A CSS selector followed by a CSS declarations block inside a set of curly braces. Usually found in a CSS stylesheet.
```
- CSS selector (or CSS class selector)
```php
- The first component of a CSS rule, a CSS selector is a pattern of elements, classnames or other terms that define the element to which the rule’s CSS definitions apply. E.g., `p.my-cool-classname > span`. A CSS selector matches HTML elements based on the contents of the “class” attribute. See [MDN CSS selectors article](https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_Selectors).
```
- CSS stylesheet
```text
- A collection of CSS rules contained within a file or within an [HTML style tag](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/style).
```
- CSS value
```text
- The value of a CSS property. The value determines how the property is modified. E.g., the `10vw` in `height: 10vw`.
```
- CSS variables (vars) or CSS custom properties
> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
- Properties, whose values can be reused in other CSS declarations. Set using custom property notation (e.g., `--wp--preset--olive: #808000;`) and accessed using the `var()` function (e.g., `color: var( --wp--preset--olive );`). See [MDN article on CSS custom properties](https://developer.mozilla.org/en-US/docs/Web/CSS/Using_CSS_custom_properties).
```
- Global styles (Gutenberg internal)
```text
- A merged block styles object containing values from a theme’s theme.json and user styles settings.
```
- Inline styles
```text
- Inline styles are CSS declarations that affect a single HTML element, contained within a style attribute
```
- Processor
```text
- Performs compilation and optimization on stored CSS rules. See the class `[WP_Style_Engine_Processor](https://developer.wordpress.org/reference/classes/wp_style_engine_processor/)`.
```
- Store
```text
- A data object that contains related styles. See the class `[WP_Style_Engine_CSS_Rules_Store](https://developer.wordpress.org/reference/classes/wp_style_engine_css_rules_store/)`.
```

---

https://developer.wordpress.org/block-editor/reference-guides/packages/packages-style-engine/using-the-style-engine-with-block-supports/
# @wordpress/style-engine Using the Style Engine to generate block supports styles

[Block supports](../../01-block-api-reference/supports.md) is the API that allows a block to declare support for certain features.

Where a block declares support for a specific style group or property, e.g., “spacing” or “spacing.padding”, the block’s attributes are extended to include a **style object**.

For example:

```json
{ "attributes": { "style": { "spacing": { "margin": { "top": "10px" }, "padding": "1em" }, "typography": { "fontSize": "2.2rem" } } }}
```

Using this object, the Style Engine can generate the classes and CSS required to style the block element.

The global function `wp_style_engine_get_styles` accepts a style object as its first argument, and will output compiled CSS and an array of CSS declaration property/value pairs.

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
$block_styles = array( 'spacing' => array( 'padding' => '10px', 'margin' => array( 'top' => '1em') ), 'typography' => array( 'fontSize' => '2.2rem' ),);$styles = wp_style_engine_get_styles( $block_styles);print_r( $styles ); /*array( 'css' => 'padding:10px;margin-top:1em;font-size:2.2rem', 'declarations' => array( 'padding' => '10px', 'margin-top' => '1em', 'font-size' => '2.2rem' ))*/
```

## Use case

When [registering a block support](https://developer.wordpress.org/reference/classes/wp_block_supports/register/), it is possible to pass an ‘apply’ callback in the block support config array to add or extend block support attributes with “class” or “style” properties.

If a block has opted into the block support, the values of “class” and “style” will be applied to the block element’s “class” and “style” attributes accordingly when rendered in the frontend HTML. Note, this applies only to server-side rendered blocks, for example, the [Site Title block](../../02-core-blocks-reference/index.md#site-title).

The callback receives `$block_type` and `$block_attributes` as arguments. The `style` attribute within `$block_attributes` only contains the raw style object, if any styles have been set for the block, and not any CSS or classnames to be applied to the block’s HTML elements.

Here is where `wp_style_engine_get_styles` comes in handy: it will generate CSS and, if appropriate, classnames to be added to the “style” and “class” HTML attributes in the final rendered block markup.

Here is a *very* simplified version of how the [color block support](https://github.com/WordPress/gutenberg/tree/HEAD/lib/block-supports/color.php) works:

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
function gutenberg_apply_colors_support( $block_type, $block_attributes ) { // Get the color styles from the style object. $block_color_styles = isset( $block_attributes['style']['color'] ) ? $block_attributes['style']['color'] : null; // Since we only want the color styles, pass the color styles only to the Style Engine. $styles = wp_style_engine_get_styles( array( 'color' => $block_color_styles ) ); // Return the generated styles to be applied to the block's HTML element. return array( 'style' => $styles['css'], 'class' => $styles['classnames'] );} // Register the block support.WP_Block_Supports::get_instance()->register( 'colors', array( 'register_attribute' => 'gutenberg_register_colors_support', 'apply' => 'gutenberg_apply_colors_support', ));
```

It’s important to note that, for now, the Style Engine will only generate styles for the following, core block supports:

- border
- color
- spacing
- typography

In future releases, it will be possible to extend this list.

## Checking for block support and skip serialization

Before passing the block style object to the Style Engine, you’ll need to take into account:

1. whether the theme has elected to support a particular block style, and
2. whether a block has elected to “skip serialization” of that particular block style, that is, opt-out of automatic application of styles to the block’s element (usually in order to do it via the block’s internals). See the [block API documentation](../../../04-explanations/01-architecture/styles.md#block-supports-api) for further information.

If a block either:

- has no support for a style, or
- skips serialization of that style

it’s likely that you’ll want to remove those style values from the style object before passing it to the Style Engine with help of two functions:

- wp\_should\_skip\_block\_supports\_serialization()
- [block_has_support()](https://developer.wordpress.org/reference/functions/block_has_support/)

We can now update the “apply” callback code above so that we’ll only return “style” and “class”, where a block has support, and it doesn’t skip serialization:

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
function gutenberg_apply_colors_support( $block_type, $block_attributes ) { // The return value. $attributes = array(); // Return early if the block skips all serialization for block supports. if ( gutenberg_should_skip_block_supports_serialization( $block_type, 'color' ) ) { return $attributes; } // Checks for support and skip serialization. $has_text_support = block_has_support( $block_type, array( 'color', 'text' ), false ); $has_background_support = block_has_support( $block_type, array( 'color', 'background' ), false ); $skips_serialization_of_color_text = wp_should_skip_block_supports_serialization( $block_type, 'color', 'text' ); $skips_serialization_of_color_background = wp_should_skip_block_supports_serialization( $block_type, 'color', 'background' ); // Get the color styles from the style object. $block_color_styles = isset( $block_attributes['style']['color'] ) ? $block_attributes['style']['color'] : null; // The mutated styles object we're going to pass to wp_style_engine_get_styles(). $color_block_styles = array(); // Set the color style values according to whether the block has support and does not skip serialization. $spacing_block_styles['text'] = null; $spacing_block_styles['background'] = null; if ( $has_text_support && ! $skips_serialization_of_color_text ) { $spacing_block_styles['text'] = $block_color_styles['text'] ?? null; } if $has_background_support && ! $skips_serialization_of_color_background ) { $spacing_block_styles['background'] = $block_color_styles['background'] ?? null; } // Pass the color styles, excluding those that have no support or skip serialization, to the Style Engine. $styles = wp_style_engine_get_styles( array( 'color' => $block_color_styles ) ); // Return the generated styles to be applied to the block's HTML element. return array( 'style' => $styles['css'], 'class' => $styles['classnames'] );}
```

## Generating classnames and CSS custom selectors from presets

Many of theme.json’s presets will generate both CSS custom properties and CSS rules (consisting of a selector and the CSS declarations) on the frontend.

Styling a block using these presets normally involves adding the selector to the “className” attribute of the block.

For styles that can have preset values, such as text color and font family, the Style Engine knows how to construct the classnames using the preset slug.

To discern CSS values from preset values, the Style Engine expects a special format.

Preset values must follow the pattern `var:preset|<PRESET_TYPE>|<PRESET_SLUG>`.

When the Style Engine encounters these values, it will parse them and create a CSS value of `var(--wp--preset--font-size--small)` and/or generate a classname if required.

Example:

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
// Let's say the block attributes styles contain a fontSize preset slug of "small".// $block_attributes['fontSize'] = 'var:preset|font-size|small';$preset_font_size = "var:preset|font-size|{$block_attributes['fontSize']}";// Now let's say the block attributes styles contain a backgroundColor preset slug of "blue".// $block_attributes['backgroundColor'] = 'var:preset|color|blue';$preset_background_color = "var:preset|color|{$block_attributes['backgroundColor']}"; $block_styles = array( 'typography' => array( 'fontSize' => $preset_font_size ), 'color' => array( 'background' => $preset_background_color ), 'spacing' => array( 'padding' => '10px', 'margin' => array( 'top' => '1em') ),); $styles = wp_style_engine_get_styles( $block_styles);print_r( $styles ); /*array( 'css' => 'background-color:var(--wp--preset--color--blue);padding:10px;margin-top:1em;font-size:var(--wp--preset--font-size--small);', 'declarations' => array( 'background-color' => 'var(--wp--preset--color--blue)', 'padding' => '10px', 'margin-top' => '1em', 'font-size' => 'var(--wp--preset--font-size--small)', ), 'classnames' => 'has-background has-blue-background-color has-small-font-size',)*/
```

If you don’t want the Style Engine to output the CSS custom vars in the generated CSS string as well, which you might not if you’re applying both the CSS rules and classnames to the block element, you can pass `'convert_vars_to_classnames' => true` in the options array.

This flag means “convert the vars to classnames and don’t output the vars to the CSS”. The Style Engine will therefore **only** generate the required classnames and omit the CSS custom vars in the CSS.

Using the above example code, observe the different output when we pass the option:

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
$options = array( 'convert_vars_to_classnames' => true,); $styles = wp_style_engine_get_styles( $block_styles, $options);print_r( $styles ); /*array( 'css' => 'padding:10px;margin-top:1em;', 'declarations' => array( 'padding' => '10px', 'margin-top' => '1em', ), 'classnames' => 'has-background has-blue-background-color has-small-font-size',)*/
```

Read more about [global styles](../../../04-explanations/01-architecture/styles.md#global-styles) and [preset CSS custom properties](https://developer.wordpress.org/block-editor/how-to-guides/themes/global-settings-and-styles.md#css-custom-properties-presets-custom) and [theme supports](../../../02-how-to-guides/05-themes/theme-support.md).
