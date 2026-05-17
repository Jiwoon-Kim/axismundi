# Draft Repair — typography.md

**source_path**: `theme-handbook/03-theme-json/02-settings/typography.md`
**source_url**: https://developer.wordpress.org/themes/global-settings-and-styles/settings/typography/
**blocks**: 2

**Status legend**: `confirmed` / `needs_adjustment` / `reject` (default: `pending`)

---

## block_1

- **candidate_id**: `theme-handbook/03-theme-json/02-settings/typography.md::block_1`
- **section**: _Registering web fonts (font faces)_  (lines 124–128)
- **notes**: /assets/fonts directory tree — fence breaks parent `- /assets` bullet; indent children (fonts at 4-space, font files preserve their 4-space which becomes 6-space relative)
- **status**: `pending`

### before

````
```text
- `/fonts`
    - `/open-sans.woff2`
    - `/open-sans-italic.woff2`
```
````

### after

````
    - `/fonts`
        - `/open-sans.woff2`
        - `/open-sans-italic.woff2`
````

---

## block_2

- **candidate_id**: `theme-handbook/03-theme-json/02-settings/typography.md::block_2`
- **section**: _Registering custom font size presets_  (lines 214–217)
- **notes**: fluid min/max — fence breaks parent `- **fluid**:` bullet; indent children to 4-space
- **status**: `pending`

### before

````
```text
- **`min`:** The minimum value that the font size can scale down to. Must be a valid CSS size.
- **`max`:** The maximum value that the font size can scale up to. Must be a valid CSS size.
```
````

### after

````
    - **`min`:** The minimum value that the font size can scale down to. Must be a valid CSS size.
    - **`max`:** The maximum value that the font size can scale up to. Must be a valid CSS size.
````

---

