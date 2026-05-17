# Draft Repair — deprecation.md

**source_path**: `block-editor-handbook/03-reference-guides/01-block-api-reference/deprecation.md`
**source_url**: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-deprecation/
**blocks**: 2

**Status legend**: `confirmed` / `needs_adjustment` / `reject` (default: `pending`)

---

## block_1

- **candidate_id**: `block-editor-handbook/03-reference-guides/01-block-api-reference/deprecation.md::block_1`
- **section**: _Deprecation_  (lines 56–62)
- **notes**: migrate's Parameters/Return — fence breaks parent `- migrate:` bullet; indent restored
- **status**: `pending`

### before

````
```text
- *Parameters*
    - `attributes`: The block’s old attributes.
    - `innerBlocks`: The block’s old inner blocks.
- *Return*
    - `Object | Array`: Either the updated block attributes or tuple array `[attributes, innerBlocks]`.
```
````

### after

````
  - *Parameters*
    - `attributes`: The block's old attributes.
    - `innerBlocks`: The block's old inner blocks.
  - *Return*
    - `Object | Array`: Either the updated block attributes or tuple array `[attributes, innerBlocks]`.
````

---

## block_2

- **candidate_id**: `block-editor-handbook/03-reference-guides/01-block-api-reference/deprecation.md::block_2`
- **section**: _Deprecation_  (lines 64–73)
- **notes**: isEligible's Parameters/Return — fence breaks parent `- isEligible:` bullet; indent restored
- **status**: `pending`

### before

````
```text
- *Parameters*
    - `attributes`: The raw block attributes as parsed from the serialized HTML, and before the block type code is applied.
    - `innerBlocks`: The block’s current inner blocks.
    - `data`: An object containing properties representing the block node and its resulting block object.
        - `data.blockNode`: The raw form of the block as a result of parsing the serialized HTML.
        - `data.block`: The block object, which is the result of applying the block type to the `blockNode`.
- *Return*
    - `boolean`: Whether or not this otherwise valid block is eligible to be migrated by this deprecation.
```
````

### after

````
  - *Parameters*
    - `attributes`: The raw block attributes as parsed from the serialized HTML, and before the block type code is applied.
    - `innerBlocks`: The block's current inner blocks.
    - `data`: An object containing properties representing the block node and its resulting block object.
      - `data.blockNode`: The raw form of the block as a result of parsing the serialized HTML.
      - `data.block`: The block object, which is the result of applying the block type to the `blockNode`.
  - *Return*
    - `boolean`: Whether or not this otherwise valid block is eligible to be migrated by this deprecation.
````

---

