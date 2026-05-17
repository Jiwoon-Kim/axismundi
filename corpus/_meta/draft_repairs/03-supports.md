# Draft Repair — supports.md

**source_path**: `block-editor-handbook/03-reference-guides/01-block-api-reference/supports.md`
**source_url**: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/
**blocks**: 10

**Status legend**: `confirmed` / `needs_adjustment` / `reject` (default: `pending`)

---

## block_2

- **candidate_id**: `block-editor-handbook/03-reference-guides/01-block-api-reference/supports.md::block_2`
- **section**: _background_  (lines 132–135)
- **notes**: background subproperties — convert flat fence to nested sub-list under existing 'Subproperties' bullet
- **status**: `pending`

### before

````
```text
- `backgroundImage`: type `boolean`, default value `false`
- `backgroundSize`: type `boolean`, default value `false`
```
````

### after

````
  - `backgroundImage`: type `boolean`, default value `false`
  - `backgroundSize`: type `boolean`, default value `false`
````

---

## block_3

- **candidate_id**: `block-editor-handbook/03-reference-guides/01-block-api-reference/supports.md::block_3`
- **section**: _background_  (lines 153–162)
- **notes**: background attribute tree — fence breaks parent `- style:` bullet; indent fence body to nest under style (existing indents become deeper)
- **status**: `pending`

### before

````
```text
- `background`: an attribute of `object` type.
    - `backgroundImage`: an attribute of `object` type, containing information about the selected image
        - `url`: type `string`, URL to the image
        - `id`: type `int`, media attachment ID
        - `source`: type `string`, at the moment the only value is `file`
        - `title`: type `string`, title of the media attachment
    - `backgroundPosition`: an attribute of `string` type, defining the background images position, selected by FocalPointPicker and used in CSS as the [`background-position`](https://developer.mozilla.org/en-US/docs/Web/CSS/background-position) value.
    - `backgroundSize`: an attribute of `string` type. defining the CSS [`background-size`](https://developer.mozilla.org/en-US/docs/Web/CSS/background-size) value.
```
````

### after

````
  - `background`: an attribute of `object` type.
      - `backgroundImage`: an attribute of `object` type, containing information about the selected image
          - `url`: type `string`, URL to the image
          - `id`: type `int`, media attachment ID
          - `source`: type `string`, at the moment the only value is `file`
          - `title`: type `string`, title of the media attachment
      - `backgroundPosition`: an attribute of `string` type, defining the background images position, selected by FocalPointPicker and used in CSS as the [`background-position`](https://developer.mozilla.org/en-US/docs/Web/CSS/background-position) value.
      - `backgroundSize`: an attribute of `string` type. defining the CSS [`background-size`](https://developer.mozilla.org/en-US/docs/Web/CSS/background-size) value.
````

---

## block_4

- **candidate_id**: `block-editor-handbook/03-reference-guides/01-block-api-reference/supports.md::block_4`
- **section**: _color_  (lines 186–194)
- **notes**: color subproperties — sub-list indent under existing 'Subproperties:' bullet
- **status**: `pending`

### before

````
```text
- `background`: type `boolean`, default value `true`
- `button`: type `boolean`, default value `false`
- `enableContrastChecker`: type `boolean`, default value `true`
- `gradients`: type `boolean`, default value `false`
- `heading`: type `boolean`, default value `false`
- `link`: type `boolean`, default value `false`
- `text`: type `boolean`, default value `true`
```
````

### after

````
  - `background`: type `boolean`, default value `true`
  - `button`: type `boolean`, default value `false`
  - `enableContrastChecker`: type `boolean`, default value `true`
  - `gradients`: type `boolean`, default value `false`
  - `heading`: type `boolean`, default value `false`
  - `link`: type `boolean`, default value `false`
  - `text`: type `boolean`, default value `true`
````

---

## block_14

- **candidate_id**: `block-editor-handbook/03-reference-guides/01-block-api-reference/supports.md::block_14`
- **section**: _dimensions_  (lines 435–440)
- **notes**: dimensions subproperties — sub-list indent
- **status**: `pending`

### before

````
```text
- `height`: type `boolean`, default value `false`
- `minHeight`: type `boolean`, default value `false`
- `minWidth`: type `boolean`, default value `false`
- `width`: type `boolean`, default value `false`
```
````

### after

````
  - `height`: type `boolean`, default value `false`
  - `minHeight`: type `boolean`, default value `false`
  - `minWidth`: type `boolean`, default value `false`
  - `width`: type `boolean`, default value `false`
````

---

## block_15

- **candidate_id**: `block-editor-handbook/03-reference-guides/01-block-api-reference/supports.md::block_15`
- **section**: _filter_  (lines 461–463)
- **notes**: filter subproperties (single: duotone) — sub-list indent
- **status**: `pending`

### before

````
```text
- `duotone`: type `boolean`, default value `false`
```
````

### after

````
  - `duotone`: type `boolean`, default value `false`
````

---

## block_17

- **candidate_id**: `block-editor-handbook/03-reference-guides/01-block-api-reference/supports.md::block_17`
- **section**: _interactivity_  (lines 516–519)
- **notes**: interactivity subproperties — sub-list indent
- **status**: `pending`

### before

````
```text
- `clientNavigation`: type `boolean`, default value `false`
- `interactive`: type `boolean`, default value `false`
```
````

### after

````
  - `clientNavigation`: type `boolean`, default value `false`
  - `interactive`: type `boolean`, default value `false`
````

---

## block_18

- **candidate_id**: `block-editor-handbook/03-reference-guides/01-block-api-reference/supports.md::block_18`
- **section**: _layout_  (lines 534–545)
- **notes**: layout subproperties — sub-list indent
- **status**: `pending`

### before

````
```text
- `default`: type `Object`, default value null
- `allowSwitching`: type `boolean`, default value `false`
- `allowEditing`: type `boolean`, default value `true`
- `allowInheriting`: type `boolean`, default value `true`
- `allowSizingOnChildren`: type `boolean`, default value `false`
- `allowVerticalAlignment`: type `boolean`, default value `true`
- `allowJustification`: type `boolean`, default value `true`
- `allowOrientation`: type `boolean`, default value `true`
- `allowWrap`: type `boolean`, default value `true`
- `allowCustomContentAndWideSize`: type `boolean`, default value `true`
```
````

### after

````
  - `default`: type `Object`, default value null
  - `allowSwitching`: type `boolean`, default value `false`
  - `allowEditing`: type `boolean`, default value `true`
  - `allowInheriting`: type `boolean`, default value `true`
  - `allowSizingOnChildren`: type `boolean`, default value `false`
  - `allowVerticalAlignment`: type `boolean`, default value `true`
  - `allowJustification`: type `boolean`, default value `true`
  - `allowOrientation`: type `boolean`, default value `true`
  - `allowWrap`: type `boolean`, default value `true`
  - `allowCustomContentAndWideSize`: type `boolean`, default value `true`
````

---

## block_19

- **candidate_id**: `block-editor-handbook/03-reference-guides/01-block-api-reference/supports.md::block_19`
- **section**: _position_  (lines 667–669)
- **notes**: position subproperties — sub-list indent
- **status**: `pending`

### before

````
```text
- `sticky`: type `boolean`, default value `false`
```
````

### after

````
  - `sticky`: type `boolean`, default value `false`
````

---

## block_21

- **candidate_id**: `block-editor-handbook/03-reference-guides/01-block-api-reference/supports.md::block_21`
- **section**: _spacing_  (lines 742–746)
- **notes**: spacing subproperties — sub-list indent
- **status**: `pending`

### before

````
```text
- `margin`: type `boolean` or `array`, default value `false`
- `padding`: type `boolean` or `array`, default value `false`
- `blockGap`: type `boolean` or `array`, default value `false`
```
````

### after

````
  - `margin`: type `boolean` or `array`, default value `false`
  - `padding`: type `boolean` or `array`, default value `false`
  - `blockGap`: type `boolean` or `array`, default value `false`
````

---

## block_22

- **candidate_id**: `block-editor-handbook/03-reference-guides/01-block-api-reference/supports.md::block_22`
- **section**: _typography_  (lines 780–784)
- **notes**: typography subproperties — sub-list indent
- **status**: `pending`

### before

````
```text
- `fontSize`: type `boolean`, default value `false`
- `lineHeight`: type `boolean`, default value `false`
- `textAlign`: type `boolean` or `array`, default value `false`
```
````

### after

````
  - `fontSize`: type `boolean`, default value `false`
  - `lineHeight`: type `boolean`, default value `false`
  - `textAlign`: type `boolean` or `array`, default value `false`
````

---

