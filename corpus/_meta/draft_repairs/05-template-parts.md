# Draft Repair — template-parts.md

**source_path**: `theme-handbook/04-templates/template-parts.md`
**source_url**: https://developer.wordpress.org/themes/templates/template-parts/
**blocks**: 2

**Status legend**: `confirmed` / `needs_adjustment` / `reject` (default: `pending`)

---

## block_1

- **candidate_id**: `theme-handbook/04-templates/template-parts.md::block_1`
- **section**: _Organizing template parts_  (lines 79–84)
- **notes**: parts/ directory tree — fence breaks parent `- parts/` bullet; indent children to 4-space
- **status**: `pending`

### before

````
```text
- `comments.html`
- `footer.html`
- `header.html`
- `sidebar.html`
```
````

### after

````
    - `comments.html`
    - `footer.html`
    - `header.html`
    - `sidebar.html`
````

---

## block_2

- **candidate_id**: `theme-handbook/04-templates/template-parts.md::block_2`
- **section**: _Registering custom areas_  (lines 157–165)
- **notes**: area_tag's HTML tag options — fence breaks parent `- **area_tag**:` bullet; indent children to 4-space
- **status**: `pending`

### before

````
```text
- `div`
- `article`
- `aside`
- `footer`
- `header`
- `main`
- `section`
```
````

### after

````
    - `div`
    - `article`
    - `aside`
    - `footer`
    - `header`
    - `main`
    - `section`
````

---

