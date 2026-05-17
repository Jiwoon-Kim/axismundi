# v1.1a — Markdown Repair Candidates (P1 First Pass)

Scope: 47 anchor candidates, priority 1, degraded only.
Status (initial): all `repair_candidate`.
Reviewer assigns `confirmed_repair` or `deferred` per block.

**Judgment guide** (Ji-woon):
- `confirmed_repair` = 원본/문맥상 명백히 리스트·표·JSON·JS 등으로 복원 가능
- `deferred` = 원본 확인 필요하거나 코드/문서 경계가 애매함
- `repair_candidate` = 아직 미판정 (default)

**Block categories**:
- `LIKELY_MARKDOWN_LIST` — text fence with bullet lines (most likely repair target)
- `LIKELY_JSON_FRAGMENT` — text fence with quoted-key pattern (JSON sample mis-classified)
- `LIKELY_JS_OBJECT` — text fence with unquoted-key pattern (JS object literal)
- `LIKELY_TABLE` — text fence with pipe-separated columns
- `LIKELY_PROSE` — text fence reading as prose (likely should not be a code block)
- `UNKNOWN` — needs manual classification
- `DEGRADED_CODE` — already flagged with `> [!WARNING]` (collapse-affected, separate section)

## Summary

- **P1 degraded pages reviewed**: 15
- **Repair candidates (main section)**: 35
- **By category**:
  - `LIKELY_MARKDOWN_LIST`: 15
  - `LIKELY_JS_OBJECT`: 11
  - `UNKNOWN`: 9
- **DEGRADED_CODE blocks (appendix)**: 62

---

## rest-api / `extending-the-rest-api` / `routes-and-endpoints`

- **Path**: `rest-api-handbook/04-extending-the-rest-api/routes-and-endpoints.md`
- **Title**: Routes and Endpoints
- **Repair candidates**: 1  (degraded code blocks: 7, see appendix)
- **Source URL**: https://developer.wordpress.org/rest-api/extending-the-rest-api/routes-and-endpoints/

### Block #1 — LIKELY_MARKDOWN_LIST (lang=`text`)

- **Lines**: 30–34 (3 lines, max line 105 chars)
- **Section**: _Routes vs Endpoints_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
- `GET` triggers a `get_item` method, returning the post data to the client.
- `PUT` triggers an `update_item` method, taking the data to update, and returning the updated post data.
- `DELETE` triggers a `delete_item` method, returning the now-deleted post data to the client.
```

## rest-api / `using-the-rest-api` / `authentication`

- **Path**: `rest-api-handbook/03-using-the-rest-api/authentication.md`
- **Title**: Authentication
- **Repair candidates**: 1  (degraded code blocks: 2, see appendix)
- **Source URL**: https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/

### Block #1 — UNKNOWN (lang=`text`)

- **Lines**: 41–43 (1 lines, max line 157 chars)
- **Section**: _Cookie Authentication_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
options.beforeSend = function(xhr) { xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce); if (beforeSend) { return beforeSend.apply(this, arguments); }};
```

## block-editor / `reference-guides` / `block-api-reference` / `deprecation`

- **Path**: `block-editor-handbook/03-reference-guides/01-block-api-reference/deprecation.md`
- **Title**: Deprecation
- **Repair candidates**: 2  (degraded code blocks: 3, see appendix)
- **Source URL**: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-deprecation/

### Block #1 — LIKELY_MARKDOWN_LIST (lang=`text`)

- **Lines**: 56–62 (5 lines, max line 103 chars)
- **Section**: _Deprecation_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
- *Parameters*
    - `attributes`: The block’s old attributes.
    - `innerBlocks`: The block’s old inner blocks.
- *Return*
    - `Object | Array`: Either the updated block attributes or tuple array `[attributes, innerBlocks]`.
```

### Block #2 — LIKELY_MARKDOWN_LIST (lang=`text`)

- **Lines**: 64–73 (8 lines, max line 123 chars)
- **Section**: _Deprecation_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
- *Parameters*
    - `attributes`: The raw block attributes as parsed from the serialized HTML, and before the block type code is applied.
    - `innerBlocks`: The block’s current inner blocks.
    - `data`: An object containing properties representing the block node and its resulting block object.
        - `data.blockNode`: The raw form of the block as a result of parsing the serialized HTML.
… (+3 more lines)
```

## block-editor / `reference-guides` / `block-api-reference` / `supports`

- **Path**: `block-editor-handbook/03-reference-guides/01-block-api-reference/supports.md`
- **Title**: Supports
- **Repair candidates**: 22  (degraded code blocks: 2, see appendix)
- **Source URL**: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/

### Block #1 — UNKNOWN (lang=`text`)

- **Lines**: 66–68 (1 lines, max line 61 chars)
- **Section**: _anchor_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
// Declare support for anchor links.supports: { anchor: true}
```

### Block #2 — LIKELY_MARKDOWN_LIST (lang=`text`)

- **Lines**: 132–135 (2 lines, max line 58 chars)
- **Section**: _background_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
- `backgroundImage`: type `boolean`, default value `false`
- `backgroundSize`: type `boolean`, default value `false`
```

### Block #3 — LIKELY_MARKDOWN_LIST (lang=`text`)

- **Lines**: 153–162 (8 lines, max line 252 chars)
- **Section**: _background_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
- `background`: an attribute of `object` type.
    - `backgroundImage`: an attribute of `object` type, containing information about the selected image
        - `url`: type `string`, URL to the image
        - `id`: type `int`, media attachment ID
        - `source`: type `string`, at the moment the only value is `file`
… (+3 more lines)
```

### Block #4 — LIKELY_MARKDOWN_LIST (lang=`text`)

- **Lines**: 186–194 (7 lines, max line 63 chars)
- **Section**: _color_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
- `background`: type `boolean`, default value `true`
- `button`: type `boolean`, default value `false`
- `enableContrastChecker`: type `boolean`, default value `true`
- `gradients`: type `boolean`, default value `false`
- `heading`: type `boolean`, default value `false`
… (+2 more lines)
```

### Block #5 — LIKELY_JS_OBJECT (lang=`text`)

- **Lines**: 229–237 (7 lines, max line 157 chars)
- **Section**: _color.background_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
When a user chooses from the list of preset background colors, the preset slug is stored in the `backgroundColor` attribute.

Background color presets are sourced from the `editor-color-palette` [theme support](../../02-how-to-guides/05-themes/theme-support.md#block-color-palettes).

The block can apply a default preset background color by specifying its own attribute with a default. For example:
… (+2 more lines)
```

### Block #6 — LIKELY_JS_OBJECT (lang=`text`)

- **Lines**: 239–245 (5 lines, max line 156 chars)
- **Section**: _color.background_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
When a custom background color is selected (i.e. using the custom color picker), the custom color value is stored in the `style.color.background` attribute.

The block can apply a default custom background color by specifying its own attribute with a default. For example:

    attributes: { style: { type: 'object', default: { color: { background: '#aabbcc', } } }}
```

### Block #7 — LIKELY_JS_OBJECT (lang=`text`)

- **Lines**: 264–270 (5 lines, max line 156 chars)
- **Section**: _color.button_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
When a button color is selected, the color value is stored in the `style.elements.button.color.text` and `style.elements.button.color.background` attribute.

The block can apply a default button colors by specifying its own attribute with a default. For example:

    attributes: { style: { type: 'object', default: { elements: { button: { color: { text: 'var:preset|color|contrast', background: '#000000', } } } } }}
```

### Block #8 — LIKELY_JS_OBJECT (lang=`text`)

- **Lines**: 303–309 (5 lines, max line 109 chars)
- **Section**: _color.gradients_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
When a user chooses from the list of preset gradients, the preset slug is stored in the `gradient` attribute.

The block can apply a default preset gradient by specifying its own attribute with a default. For example:

    attributes: { gradient: { type: 'string', default: 'some-preset-gradient-slug', }}
```

### Block #9 — LIKELY_JS_OBJECT (lang=`text`)

- **Lines**: 311–317 (5 lines, max line 152 chars)
- **Section**: _color.gradients_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
When a custom gradient is selected (i.e. using the custom gradient picker), the custom gradient value is stored in the `style.color.gradient` attribute.

The block can apply a default custom gradient by specifying its own attribute with a default. For example:

    attributes: { style: { type: 'object', default: { color: { gradient: 'linear-gradient(135deg,rgb(170,187,204) 0%,rgb(17,34,51) 100%)', } } }}
```

### Block #10 — LIKELY_JS_OBJECT (lang=`text`)

- **Lines**: 336–342 (5 lines, max line 159 chars)
- **Section**: _color.heading_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
When a heading color is selected, the color value is stored in the `style.elements.heading.color.text` and `style.elements.heading.color.background` attribute.

The block can apply default heading colors by specifying its own attribute with a default. For example:

    attributes: { style: { type: 'object', default: { elements: { heading: { color: { text: 'var:preset|color|contrast', background: '#000000', } } } } }}
```

### Block #11 — LIKELY_JS_OBJECT (lang=`text`)

- **Lines**: 359–365 (5 lines, max line 170 chars)
- **Section**: _color.link_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
When a link color is selected, the color value is stored in the `style.elements.link.color.text` and `style.elements.link.:hover.color.text` attribute.

The block can apply default link colors by specifying its own attribute with a default. For example:

    attributes: { style: { type: 'object', default: { elements: { link: { color: { text: 'var:preset|color|contrast', }, ":hover": { color: { text: "#000000" } } } } } }}
```

### Block #12 — LIKELY_JS_OBJECT (lang=`text`)

- **Lines**: 388–394 (5 lines, max line 112 chars)
- **Section**: _color.text_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
When a user chooses from the list of preset text colors, the preset slug is stored in the `textColor` attribute.

The block can apply a default preset text color by specifying its own attribute with a default. For example:

    attributes: { textColor: { type: 'string', default: 'some-preset-text-color-slug', }}
```

### Block #13 — LIKELY_JS_OBJECT (lang=`text`)

- **Lines**: 396–402 (5 lines, max line 144 chars)
- **Section**: _color.text_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
When a custom text color is selected (i.e. using the custom color picker), the custom color value is stored in the `style.color.text` attribute.

The block can apply a default custom text color by specifying its own attribute with a default. For example:

    attributes: { style: { type: 'object', default: { color: { text: '#aabbcc', } } }}
```

### Block #14 — LIKELY_MARKDOWN_LIST (lang=`text`)

- **Lines**: 435–440 (4 lines, max line 52 chars)
- **Section**: _dimensions_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
- `height`: type `boolean`, default value `false`
- `minHeight`: type `boolean`, default value `false`
- `minWidth`: type `boolean`, default value `false`
- `width`: type `boolean`, default value `false`
```

### Block #15 — UNKNOWN (lang=`text`)

- **Lines**: 461–463 (1 lines, max line 50 chars)
- **Section**: _filter_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
- `duotone`: type `boolean`, default value `false`
```

### Block #16 — LIKELY_JS_OBJECT (lang=`text`)

- **Lines**: 483–487 (3 lines, max line 104 chars)
- **Section**: _filter.duotone_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
The block can apply a default duotone color by specifying its own attribute with a default. For example:

    attributes: { style: { type: 'object', default: { color: { duotone: [ '#FFF', '#000' ] } } }}
```

### Block #17 — LIKELY_MARKDOWN_LIST (lang=`text`)

- **Lines**: 516–519 (2 lines, max line 59 chars)
- **Section**: _interactivity_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
- `clientNavigation`: type `boolean`, default value `false`
- `interactive`: type `boolean`, default value `false`
```

### Block #18 — LIKELY_MARKDOWN_LIST (lang=`text`)

- **Lines**: 534–545 (10 lines, max line 71 chars)
- **Section**: _layout_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
- `default`: type `Object`, default value null
- `allowSwitching`: type `boolean`, default value `false`
- `allowEditing`: type `boolean`, default value `true`
- `allowInheriting`: type `boolean`, default value `true`
- `allowSizingOnChildren`: type `boolean`, default value `false`
… (+5 more lines)
```

### Block #19 — UNKNOWN (lang=`text`)

- **Lines**: 667–669 (1 lines, max line 49 chars)
- **Section**: _position_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
- `sticky`: type `boolean`, default value `false`
```

### Block #20 — LIKELY_JS_OBJECT (lang=`text`)

- **Lines**: 729–735 (5 lines, max line 97 chars)
- **Section**: _shadow_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
When a shadow is selected, the color value is stored in the `style.shadow`.

The block can apply a default shadow by specifying its own attribute with a default. For example:

    attributes: { style: { type: 'object', default: { shadow: "var:preset|shadow|deep" } }}
```

### Block #21 — LIKELY_MARKDOWN_LIST (lang=`text`)

- **Lines**: 742–746 (3 lines, max line 62 chars)
- **Section**: _spacing_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
- `margin`: type `boolean` or `array`, default value `false`
- `padding`: type `boolean` or `array`, default value `false`
- `blockGap`: type `boolean` or `array`, default value `false`
```

### Block #22 — LIKELY_MARKDOWN_LIST (lang=`text`)

- **Lines**: 780–784 (3 lines, max line 63 chars)
- **Section**: _typography_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
- `fontSize`: type `boolean`, default value `false`
- `lineHeight`: type `boolean`, default value `false`
- `textAlign`: type `boolean` or `array`, default value `false`
```

## theme / `theme-json` / `settings` / `spacing`

- **Path**: `theme-handbook/03-theme-json/02-settings/spacing.md`
- **Title**: Spacing
- **Repair candidates**: 1  (degraded code blocks: 1, see appendix)
- **Source URL**: https://developer.wordpress.org/themes/global-settings-and-styles/settings/spacing/

### Block #1 — UNKNOWN (lang=`text`)

- **Lines**: 83–85 (1 lines, max line 202 chars)
- **Section**: _Enabling block spacing (block gap)_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
.wp-container-17.wp-container-17 > :first-child:first-child { margin-block-start: 0;} .wp-container-17.wp-container-17 > * { margin-block-start: var(--wp--preset--spacing--plus-4); margin-block-end: 0 … (+2 chars)
```

## theme / `theme-json` / `settings` / `typography`

- **Path**: `theme-handbook/03-theme-json/02-settings/typography.md`
- **Title**: Typography
- **Repair candidates**: 2  (degraded code blocks: 2, see appendix)
- **Source URL**: https://developer.wordpress.org/themes/global-settings-and-styles/settings/typography/

### Block #1 — LIKELY_MARKDOWN_LIST (lang=`text`)

- **Lines**: 124–128 (3 lines, max line 31 chars)
- **Section**: _Registering web fonts (font faces)_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
- `/fonts`
    - `/open-sans.woff2`
    - `/open-sans-italic.woff2`
```

### Block #2 — LIKELY_MARKDOWN_LIST (lang=`text`)

- **Lines**: 214–217 (2 lines, max line 94 chars)
- **Section**: _Registering custom font size presets_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
- **`min`:** The minimum value that the font size can scale down to. Must be a valid CSS size.
- **`max`:** The maximum value that the font size can scale up to. Must be a valid CSS size.
```

## theme / `theme-json` / `styles` / `applying-styles`

- **Path**: `theme-handbook/03-theme-json/03-styles/applying-styles.md`
- **Title**: Applying Styles
- **Repair candidates**: 2  (degraded code blocks: 1, see appendix)
- **Source URL**: https://developer.wordpress.org/themes/global-settings-and-styles/styles/applying-styles/

### Block #1 — UNKNOWN (lang=`text`)

- **Lines**: 92–94 (1 lines, max line 88 chars)
- **Section**: _Styling elements_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
.wp-element-button, .wp-block-button__link { background-color: #aa3f33; color: #ffffff;}
```

### Block #2 — UNKNOWN (lang=`text`)

- **Lines**: 140–142 (1 lines, max line 42 chars)
- **Section**: _Styling blocks_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
.wp-block-image img { border-radius: 6px;}
```

## block-editor / `reference-guides` / `interactivity-api-reference` / `directives-and-store`

- **Path**: `block-editor-handbook/03-reference-guides/04-interactivity-api-reference/directives-and-store.md`
- **Title**: Directives and Store
- **Repair candidates**: 2  (degraded code blocks: 22, see appendix)
- **Source URL**: https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/directives-and-store/

### Block #1 — UNKNOWN (lang=`text`)

- **Lines**: 239–241 (1 lines, max line 108 chars)
- **Section**: _wp-on-window_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
store( 'myPlugin', { callbacks: { logWidth() { console.log( 'Window width: ', window.innerWidth ); }, },} );
```

### Block #2 — UNKNOWN (lang=`text`)

- **Lines**: 260–262 (1 lines, max line 108 chars)
- **Section**: _wp-on-document_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
store( 'myPlugin', { callbacks: { logKeydown( event ) { console.log( 'Key pressed: ', event.key ); }, },} );
```

## theme / `templates` / `template-parts`

- **Path**: `theme-handbook/04-templates/template-parts.md`
- **Title**: Template Parts
- **Repair candidates**: 2  (degraded code blocks: 1, see appendix)
- **Source URL**: https://developer.wordpress.org/themes/templates/template-parts/

### Block #1 — LIKELY_MARKDOWN_LIST (lang=`text`)

- **Lines**: 79–84 (4 lines, max line 17 chars)
- **Section**: _Organizing template parts_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
- `comments.html`
- `footer.html`
- `header.html`
- `sidebar.html`
```

### Block #2 — LIKELY_MARKDOWN_LIST (lang=`text`)

- **Lines**: 157–165 (7 lines, max line 11 chars)
- **Section**: _Registering custom areas_
- **Disposition**: `repair_candidate`  `[ ] confirmed_repair`  `[ ] deferred`

```
- `div`
- `article`
- `aside`
- `footer`
- `header`
… (+2 more lines)
```

---

# Appendix — DEGRADED_CODE Inventory

These blocks already carry a `> [!WARNING]` callout from the pipeline (collapse-affected). Listed for completeness; out of scope for v1.1a markdown repair (separate v1.1 track).

- `block-editor-handbook/03-reference-guides/04-interactivity-api-reference/directives-and-store.md` — 22 degraded blocks
- `rest-api-handbook/04-extending-the-rest-api/schema.md` — 12 degraded blocks
- `rest-api-handbook/04-extending-the-rest-api/routes-and-endpoints.md` — 7 degraded blocks
- `block-editor-handbook/03-reference-guides/01-block-api-reference/deprecation.md` — 3 degraded blocks
- `theme-handbook/03-theme-json/02-settings/shadow.md` — 3 degraded blocks
- `rest-api-handbook/03-using-the-rest-api/authentication.md` — 2 degraded blocks
- `block-editor-handbook/03-reference-guides/01-block-api-reference/supports.md` — 2 degraded blocks
- `block-editor-handbook/03-reference-guides/10-data-module-reference/data-core-block-editor.md` — 2 degraded blocks
- `theme-handbook/03-theme-json/06-template-parts.md` — 2 degraded blocks
- `theme-handbook/03-theme-json/02-settings/typography.md` — 2 degraded blocks
- `rest-api-handbook/04-extending-the-rest-api/controller-classes.md` — 1 degraded blocks
- `theme-handbook/03-theme-json/02-settings/color.md` — 1 degraded blocks
- `theme-handbook/03-theme-json/02-settings/spacing.md` — 1 degraded blocks
- `theme-handbook/03-theme-json/03-styles/applying-styles.md` — 1 degraded blocks
- `theme-handbook/04-templates/template-parts.md` — 1 degraded blocks

