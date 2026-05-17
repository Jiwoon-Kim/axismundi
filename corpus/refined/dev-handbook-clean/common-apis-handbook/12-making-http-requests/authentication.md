---
source_url: https://developer.wordpress.org/apis/making-http-requests/authentication/
synced: 2026-05-12
handbook: common-apis
chapter: making-http-requests
slug: authentication
parent_order: 12
page_order: 5
title: "Authentication"
code_quality: degraded
code_issue: pre_newline_loss
---

# Authentication

Many APIs will require you to make authenticated requests to access some endpoints. A common authentication method is called HTTP Basic Authentication. It can be used in WordPress using the ‘Authorization’ header `wp_remote_get()`.


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
$args = array( 'headers' => array( 'Authorization' => 'Basic ' . base64_encode( YOUR_USERNAME . ':' . YOUR_PASSWORD ) ));wp_remote_get( $url, $args );
```

HTTP Basic Auth is very insecure because it exposes the username and password and is only used for testing and development. Check the documentation of the API you want to access for more information on how to authenticate.

If you want to make authenticated requests to the WordPress REST API, check [this article](../../rest-api-handbook/03-using-the-rest-api/authentication.md).
