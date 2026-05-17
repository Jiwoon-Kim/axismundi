# Draft Repair — routes-and-endpoints.md

**source_path**: `rest-api-handbook/04-extending-the-rest-api/routes-and-endpoints.md`
**source_url**: https://developer.wordpress.org/rest-api/extending-the-rest-api/routes-and-endpoints/
**blocks**: 1

**Status legend**: `confirmed` / `needs_adjustment` / `reject` (default: `pending`)

---

## block_1

- **candidate_id**: `rest-api-handbook/04-extending-the-rest-api/routes-and-endpoints.md::block_1`
- **section**: _Routes vs Endpoints_  (lines 30–34)
- **notes**: nested UL: 3 endpoints under 'This route has 3 endpoints:'
- **status**: `pending`

### before

````
```text
- `GET` triggers a `get_item` method, returning the post data to the client.
- `PUT` triggers an `update_item` method, taking the data to update, and returning the updated post data.
- `DELETE` triggers a `delete_item` method, returning the now-deleted post data to the client.
```
````

### after

````
  - `GET` triggers a `get_item` method, returning the post data to the client.
  - `PUT` triggers an `update_item` method, taking the data to update, and returning the updated post data.
  - `DELETE` triggers a `delete_item` method, returning the now-deleted post data to the client.
````

---

