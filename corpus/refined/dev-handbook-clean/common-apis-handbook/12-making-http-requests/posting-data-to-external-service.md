---
source_url: https://developer.wordpress.org/apis/making-http-requests/posting-data-to-an-external-service/
synced: 2026-05-12
handbook: common-apis
chapter: making-http-requests
slug: posting-data-to-external-service
parent_order: 12
page_order: 2
title: "POSTing data to an external service"
code_quality: degraded
code_issue: pre_newline_loss
---

# POSTing data to an external service

POST is used to send data to the server for the server to act upon in some way. For example, a contact form. When you enter data into the form fields and click the submit button the browser takes the data and sends a POST request to the server with the text you entered into the form. From there the server will process the contact request.

## POSTing data to an API

The same helper methods (`wp_remote_retrieve_body()`, etc ) are available for all of the HTTP method requests, and utilized in the same fashion.

POSTing data is done using the `wp_remote_post()` function, and takes exactly the same parameters as `wp_remote_get()`.

To send data to the server you will need to build an associative array of data. This data will be assigned to the `'body'` value. From the server side of things the value will appear in the `$_POST` variable as you would expect. i.e. if `body => array( 'myvar' => 5 )` on the server `$_POST['myvar'] = 5`.

Because GitHub does not allow POSTing to the API used in the previous example, this example will pretend that it does. Typically if you want to POST data to an API you will need to contact the maintainers of the API and get an API key or some other form of authentication token. This simply proves that your application is allowed to manipulate data on the API the same way logging into a website as a user does to the website.

Let’s assume we are submitting a contact form with the following fields: name, email, subject, comment. To set up the body we do the following:


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
$body = array( 'name' => sanitize_text_field( 'Jane Smith' ), 'email' => sanitize_email( 'some@email.com' ), 'subject' => sanitize_text_field( 'Checkout this API stuff' ), 'comment' => sanitize_textarea_field( 'I just read a great tutorial. You gotta check it out!' ),);
```

Now we add the body to the `$args` array that will be passed as the second argument. (The second argument accepts many options, see Advanced section for more details)


```php
$args = array( 'body' => $body,);
```

Then of course to make the call


```php
$response = wp_remote_post( 'https://your-contact-form.com', $args );
```
