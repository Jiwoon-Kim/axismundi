---
source_url: https://developer.wordpress.org/apis/xml-rpc/
synced: 2026-05-12
handbook: common-apis
chapter: xml-rpc
slug: xml-rpc
parent_order: 22
page_order: 0
title: "XML-RPC"
---

# XML-RPC

XML-RPC API that supersedes the legacy Blogger, MovableType, and metaWeblog APIs. Some clients also exist for different programming languages.

## Components

- Posts (for posts, pages, and custom post types) – Added in [WordPress 3.4](https://developer.wordpress.org/support/wordpress-version/version-3-4/)
```text
- [wp.getPost](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_getpost/)
- [wp.getPosts](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_getposts/)
- [wp.newPost](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_newpost/)
- [wp.editPost](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_editpost/)
- [wp.deletePost](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_deletepost/)
- [wp.getPostType](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_getposttype/)
- [wp.getPostTypes](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_getposttypes/)
- [wp.getPostFormats](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_getpostformats/)
- [wp.getPostStatusList](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_getpoststatuslist/)
```

- Taxonomies (for categories, tags, and custom taxonomies) – Added in [WordPress 3.4](https://developer.wordpress.org/support/wordpress-version/version-3-4/)
```text
- [wp.getTaxonomy](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_gettaxonomy/)
- [wp.getTaxonomies](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_gettaxonomies/)
- [wp.getTerm](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_getterm/)
- [wp.getTerms](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_getterms/)
- [wp.newTerm](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_newterm/)
- [wp.editTerm](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_editterm/)
- [wp.deleteTerm](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_deleteterm/)
```
- Media – Added in [WordPress 3.1](https://developer.wordpress.org/support/wordpress-version/version-3-4/)
```text
- [wp.getMediaItem](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_getmediaitem/)
- [wp.getMediaLibrary](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_getmedialibrary/)
- wp.uploadFile
```
- Comments – Added in [WordPress 2.7](https://developer.wordpress.org/support/wordpress-version/version-3-4/)
```text
- [wp.getCommentCount](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_getcommentcount/)
- [wp.getComment](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_getcomment/)
- [wp.getComments](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_getcomments/)
- [wp.newComment](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_newcomment/)
- [wp.editComment](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_editcomment/)
- [wp.deleteComment](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_deletecomment/)
- [wp.getCommentStatusList](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_getcommentstatuslist/)
```
- Options – Added in [WordPress 2.6](https://developer.wordpress.org/support/wordpress-version/version-2-6/)
```text
- [wp.getOptions](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_getoptions/)
- [wp.setOptions](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_setoptions/)
```
- Users – Added in [WordPress 3.5](https://developer.wordpress.org/support/wordpress-version/version-3-5/)
```text
- [wp.getUsersBlogs](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_getusersblogs/)
- [wp.getUser](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_getuser/)
- [wp.getUsers](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_getusers/)
- [wp.getProfile](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_getprofile/)
- [wp.editProfile](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_editprofile/)
- [wp.getAuthors](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/wp_getauthors/)
```

## Obsolete Components

- Categories – use Taxonomies instead, with taxonomy=’category’
```text
- wp.getCategories
- wp.suggestCategories
- wp.newCategory
- wp.deleteCategory
```
- Tags – use Taxonomies instead, with taxonomy=’post\_tag’
```text
- wp.getTags
```
- Pages – use Posts instead, with post\_type=’page’
```text
- wp.getPage
- wp.getPages
- wp.getPageList
- wp.newPage
- wp.editPage
- wp.deletePage
- wp.getPageStatusList
- wp.getPageTemplates
```

## Clients

- [rubypress](https://github.com/zachfeldman/rubypress): WordPress XML-RPC client for Ruby projects. Mirrors this documentation closely, full test suite built in
- [wordpress-xmlrpc-client](http://letrunghieu.github.io/wordpress-xmlrpc-client/): PHP client with full test suite. This library implement WordPress API closely to this documentation.
- [WordPressSharp](http://abrudtkuhl.github.io/WordPressSharp/): XML-RPC Client for C#.net
- [plugins/jetpack](https://wordpress.org/plugins/jetpack): Jetpack by WordPress.com enables a JSON API for sites that run the plugin
- [plugins/json-api](https://wordpress.org/plugins/json-api/): WordPress JSON api
