# Core Post To AS2 Object Mapping

This view records the proposed source boundary for a local `WP_Post` projected
as an ActivityStreams Object. It is not a claim that WordPress and AS2 have an
isomorphic schema: source facts, identity routing, activity history, access
policy, and collection queries have distinct owners.

Solid arrows are source-to-property transformations. Dashed arrows terminate
at an intentional non-mapping or a separately owned input. They are as
important as the solid arrows: a plausible WordPress column must not silently
acquire an ActivityStreams meaning.

```mermaid
flowchart LR
    subgraph source["WordPress Core source facts"]
        id["wp_posts.ID"]
        author["wp_posts.post_author"]
        date["wp_posts.post_date"]
        dategmt["wp_posts.post_date_gmt"]
        modified["wp_posts.post_modified"]
        modifiedgmt["wp_posts.post_modified_gmt"]
        content["wp_posts.post_content"]
        filtered["wp_posts.post_content_filtered"]
        title["wp_posts.post_title"]
        excerpt["wp_posts.post_excerpt"]
        status["wp_posts.post_status"]
        commentstatus["wp_posts.comment_status"]
        pingstatus["wp_posts.ping_status"]
        password["wp_posts.post_password"]
        slug["wp_posts.post_name"]
        pingfields["wp_posts.to_ping / pinged"]
        parent["wp_posts.post_parent"]
        guid["wp_posts.guid"]
        menuorder["wp_posts.menu_order"]
        posttype["wp_posts.post_type"]
        mimetype["wp_posts.post_mime_type"]
        commentcount["wp_posts.comment_count"]
        thumbnail["postmeta._thumbnail_id"]
        attachedfile["postmeta._wp_attached_file"]
        attachmentmetadata["postmeta._wp_attachment_metadata"]
        imagealt["postmeta._wp_attachment_image_alt"]
    end

    subgraph as2["ActivityStreams Object JSON-LD"]
        as2id["id\nstable Object URI"]
        type["type\nArticle / Image / Video / Audio / Document"]
        name["name"]
        body["content\nnormalized, policy-approved body"]
        summary["summary"]
        published["published"]
        updated["updated"]
        url["url\nLink or representation set"]
        media["mediaType / width / height\nLink or media representation"]
        image["image\nfeatured Image Object or Link"]
        attributed["attributedTo\ncanonical Actor URI"]
    end

    subgraph separate["No direct column mapping / separate owner"]
        identityroute["Object identity routing\nCore candidate: /?p={ID}"]
        attribution["proposed post meta\nActor identity selection"]
        activities["Activities ledger\nCreate / Update actor and delivery"]
        policy["visibility, audience, delivery, access policy"]
        threadgraph["URI thread graph\ninReplyTo, replies, thread root/depth"]
        routing["permalink and media URL APIs"]
        collection["collection membership / ordering"]
        localdisplay["site-local display timestamp\nno direct AS2 property"]
        noprop["no AS2 property"]
        altpolicy["no standard AS2 alt-text property\nrepresentation policy required"]
    end

    id --> identityroute --> as2id
    posttype --> type
    mimetype --> media
    title --> name
    content --> body
    excerpt --> summary
    dategmt --> published
    modifiedgmt --> updated
    thumbnail --> image
    attachedfile --> routing --> url
    attachmentmetadata --> media
    attribution --> attributed

    author -. "editor account, not Object author" .-> attribution
    date -.-> localdisplay
    modified -.-> localdisplay
    status -. "publication state is not Create" .-> policy
    commentstatus -.-> policy
    pingstatus -.-> noprop
    password -.-> policy
    slug -. "routing input, not Object id" .-> routing
    pingfields -.-> noprop
    parent -. "not inReplyTo" .-> threadgraph
    guid -. "not Object id or URL" .-> noprop
    menuorder -. "not collection order" .-> collection
    commentcount -. "not replies collection" .-> threadgraph
    filtered -.-> noprop
    imagealt -.-> altpolicy
```

## Reading the diagram

### Direct source transformations

| WordPress source fact | ActivityStreams member | boundary |
|---|---|---|
| `ID` | `id` | Not a raw cast. Core supplies a stable source-identity candidate; routing owns the final Object URI. |
| `post_type` plus `post_mime_type` | `type` | Core classification is mechanical for `post` and `attachment`; arbitrary CPTs need an explicit owning transformer. |
| `post_title` | `name` | Plain-text normalization belongs in representation policy. |
| `post_content` | `content` | The future body path receives the authored source unchanged, recognizes Classic and Block-editor forms, and emits allowed static HTML. It must not treat `the_content` output as source truth. A later Markdown authoring path joins this same source-to-representation contract. |
| `post_excerpt` | `summary` | A manual excerpt is source material. Automatic excerpt invention remains a representation decision. |
| GMT dates | `published`, `updated` | These are the only dates used for protocol time properties in this mapping. |
| attachment meta through Core APIs | `url`, `mediaType`, `width`, `height`, `image` | Raw file paths and serialized metadata do not leave the source adapter directly. |

### Intentional non-mappings

`post_author` is a WordPress editing account, not `attributedTo`. The proposed
post-specific Actor identity meta selects an Object's attribution; the
Activities ledger records Create/Update/Delete and delivery events after that
selection. `post_parent` is not `inReplyTo`; a URI thread graph owns reply
relationships. `post_status` does not mean a `Create` Activity, and
`comment_count` does not constitute an AS2 `replies` collection. `guid` and
`post_name` are neither automatically the AS2 `id` nor `url`.

AS2 properties with no direct Core-post column include `attributedTo`, `to`,
`cc`, `audience`, `attachment`, `tag`, `inReplyTo`, `replies`, `context`, and
`location`. They need a ledger, policy, parser, taxonomy/relationship source,
or another explicit domain owner rather than a convenient fallback.

`nameMap`, `contentMap`, and `summaryMap` are not ActivityStreams 2.0
Vocabulary properties. A multilingual representation extension may introduce
them later, but it must be labelled as an extension with its own language and
canonical-text policy.

Sources: [ActivityStreams 2.0 Vocabulary](https://www.w3.org/TR/activitystreams-vocabulary/),
[WordPress core `schema.php`](https://github.com/WordPress/wordpress-develop/blob/trunk/src/wp-admin/includes/schema.php),
and [the decided Core source boundary](../plugins/axismundi-object-projections/docs/CORE-WP-OBJECT-SOURCES.md).
