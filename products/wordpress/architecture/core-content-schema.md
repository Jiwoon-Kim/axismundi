# Core Content Schema

This is the physical WordPress core substrate most immediately relevant to
local-object projection. It deliberately does not state an ActivityStreams
mapping. The arrows show logical references inferred from the column meaning;
WordPress core does not declare foreign keys for them.

```mermaid
flowchart LR
    users["<b>wp_users</b><br/>PK ID bigint unsigned<br/>user_login varchar(60)<br/>user_pass varchar(255)<br/>user_nicename varchar(50)<br/>user_email varchar(100)<br/>user_url varchar(100)<br/>user_registered datetime<br/>user_activation_key varchar(255)<br/>user_status int<br/>display_name varchar(250)<br/><br/>KEY user_login_key(user_login)<br/>KEY user_nicename(user_nicename)<br/>KEY user_email(user_email)"]

    posts["<b>wp_posts</b><br/>PK ID bigint unsigned<br/>post_author bigint unsigned<br/>post_date datetime<br/>post_date_gmt datetime<br/>post_content longtext<br/>post_title text<br/>post_excerpt text<br/>post_status varchar(20)<br/>comment_status varchar(20)<br/>ping_status varchar(20)<br/>post_password varchar(255)<br/>post_name varchar(200)<br/>to_ping text<br/>pinged text<br/>post_modified datetime<br/>post_modified_gmt datetime<br/>post_content_filtered longtext<br/>post_parent bigint unsigned<br/>guid varchar(255)<br/>menu_order int<br/>post_type varchar(20)<br/>post_mime_type varchar(100)<br/>comment_count bigint<br/><br/>KEY post_name(post_name[191])<br/>KEY type_status_date(post_type, post_status, post_date, ID)<br/>KEY post_parent(post_parent)<br/>KEY post_author(post_author)<br/>KEY type_status_author(post_type, post_status, post_author)"]

    postmeta["<b>wp_postmeta</b><br/>PK meta_id bigint unsigned<br/>post_id bigint unsigned<br/>meta_key varchar(255) NULL<br/>meta_value longtext NULL<br/><br/>KEY post_id(post_id)<br/>KEY meta_key(meta_key[191])"]

    comments["<b>wp_comments</b><br/>PK comment_ID bigint unsigned<br/>comment_post_ID bigint unsigned<br/>comment_author tinytext<br/>comment_author_email varchar(100)<br/>comment_author_url varchar(200)<br/>comment_author_IP varchar(100)<br/>comment_date datetime<br/>comment_date_gmt datetime<br/>comment_content text<br/>comment_karma int<br/>comment_approved varchar(20)<br/>comment_agent varchar(255)<br/>comment_type varchar(20)<br/>comment_parent bigint unsigned<br/>user_id bigint unsigned<br/><br/>KEY comment_post_ID(comment_post_ID)<br/>KEY comment_approved_date_gmt(comment_approved, comment_date_gmt)<br/>KEY comment_date_gmt(comment_date_gmt)<br/>KEY comment_parent(comment_parent)<br/>KEY comment_author_email(comment_author_email[10])"]

    commentmeta["<b>wp_commentmeta</b><br/>PK meta_id bigint unsigned<br/>comment_id bigint unsigned<br/>meta_key varchar(255) NULL<br/>meta_value longtext NULL<br/><br/>KEY comment_id(comment_id)<br/>KEY meta_key(meta_key[191])"]

    usermeta["<b>wp_usermeta</b><br/>PK umeta_id bigint unsigned<br/>user_id bigint unsigned<br/>meta_key varchar(255) NULL<br/>meta_value longtext NULL<br/><br/>KEY user_id(user_id)<br/>KEY meta_key(meta_key[191])"]

    users -. "post_author" .-> posts
    posts -. "post_parent" .-> posts
    posts -. "post_id" .-> postmeta
    posts -. "comment_post_ID" .-> comments
    users -. "user_id" .-> comments
    comments -. "comment_parent" .-> comments
    comments -. "comment_id" .-> commentmeta
    users -. "user_id" .-> usermeta
```

## Well-known core post-meta rows

The following are examples of `wp_postmeta.meta_key` values used by core
features. They are neither an exhaustive registry nor columns in `wp_postmeta`;
their presence depends on the post type and feature used.

```mermaid
flowchart TB
    postmeta["wp_postmeta<br/>meta_key / meta_value rows"]

    thumbnail["_thumbnail_id<br/>attachment post ID"]
    template["_wp_page_template<br/>template slug or path"]
    editlast["_edit_last<br/>WordPress user ID"]
    editlock["_edit_lock<br/>timestamp:user ID string"]
    oldslug["_wp_old_slug<br/>previous slug"]
    trashstatus["_wp_trash_meta_status<br/>previous post status"]
    trashtime["_wp_trash_meta_time<br/>trash timestamp"]
    attachedfile["_wp_attached_file<br/>relative uploads path"]
    attachmentmetadata["_wp_attachment_metadata<br/>serialized attachment data"]
    imagealt["_wp_attachment_image_alt<br/>image alternative text"]
    backupsizes["_wp_attachment_backup_sizes<br/>serialized image-edit backups"]

    postmeta --> thumbnail
    postmeta --> template
    postmeta --> editlast
    postmeta --> editlock
    postmeta --> oldslug
    postmeta --> trashstatus
    postmeta --> trashtime
    postmeta --> attachedfile
    postmeta --> attachmentmetadata
    postmeta --> imagealt
    postmeta --> backupsizes
```

Two examples are logical references rather than core foreign keys:
`_thumbnail_id` identifies another `wp_posts` row whose `post_type` is normally
`attachment`, and `_edit_last` identifies a `wp_users` row. Attachment rows
are ordinary `wp_posts` rows; their attachment-page setting does not alter that
storage fact.

Source: [WordPress core `schema.php`](https://github.com/WordPress/wordpress-develop/blob/trunk/src/wp-admin/includes/schema.php).
