# Core Taxonomy Schema

WordPress taxonomy separates a term's shared lexical identity from its use in a
particular taxonomy. This is a local classification substrate. It does not by
itself model an ActivityStreams Collection, remote membership, actor authority,
or canonical collection URI.

```mermaid
flowchart LR
    terms["<b>wp_terms</b><br/>PK term_id bigint unsigned<br/>name varchar(200)<br/>slug varchar(200)<br/>term_group bigint<br/><br/>KEY slug(slug[191])<br/>KEY name(name[191])"]

    taxonomy["<b>wp_term_taxonomy</b><br/>PK term_taxonomy_id bigint unsigned<br/>term_id bigint unsigned<br/>taxonomy varchar(32)<br/>description longtext<br/>parent bigint unsigned<br/>count bigint<br/><br/>UQ term_id_taxonomy(term_id, taxonomy)<br/>KEY taxonomy(taxonomy)"]

    relationships["<b>wp_term_relationships</b><br/>PK object_id bigint unsigned<br/>PK term_taxonomy_id bigint unsigned<br/>term_order int<br/><br/>KEY term_taxonomy_id(term_taxonomy_id)"]

    termmeta["<b>wp_termmeta</b><br/>PK meta_id bigint unsigned<br/>term_id bigint unsigned<br/>meta_key varchar(255) NULL<br/>meta_value longtext NULL<br/><br/>KEY term_id(term_id)<br/>KEY meta_key(meta_key[191])"]

    posts["<b>wp_posts</b><br/>PK ID bigint unsigned<br/>... local object rows ..."]

    terms -. "term_id" .-> taxonomy
    taxonomy -. "parent (same taxonomy)" .-> taxonomy
    terms -. "term_id" .-> termmeta
    taxonomy -. "term_taxonomy_id" .-> relationships
    posts -. "object_id" .-> relationships
```

`wp_term_relationships.object_id` is intentionally generic: WordPress can use
it for objects other than posts. The diagram shows `wp_posts.ID` because that is
the relationship relevant to local content and attachment classification.

Source: [WordPress core `schema.php`](https://github.com/WordPress/wordpress-develop/blob/trunk/src/wp-admin/includes/schema.php).
