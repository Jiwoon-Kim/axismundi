---
source_url: https://developer.wordpress.org/apis/filesystem/
synced: 2026-05-12
handbook: common-apis
chapter: filesystem
slug: filesystem
parent_order: 7
page_order: 0
title: "Filesystem"
code_quality: degraded
code_issue: pre_newline_loss
---

# Filesystem

## Overview

The **Filesystem API**, added in [WordPress 2.6](https://developer.wordpress.org/support/wordpress-version/version-2.6), was originally created for WordPress’ own automatic updates feature.

The Filesystem API abstracts out the functionality needed for reading and writing local files to the filesystem to be done securely, on a variety of host types.

It does this through the [WP_Filesystem_Base](https://developer.wordpress.org/reference/classes/wp_filesystem_base/) class, and several subclasses which implement different ways of connecting to the local filesystem, depending on individual host support.

Any theme or plugin that needs to write files locally should do so using the `WP_Filesystem` family of classes.

## Purpose

Different hosting systems have different limitations in the way that their webservers are configured.

In particular, many hosting systems have the webserver running as a different user than the owner of the WordPress files. When this is the case, a process writing files from the webserver user will have the resulting files owned by the webserver’s user account instead of the actual user’s account. This can lead to a security problem in shared hosting situations, where multiple users are sharing the same webserver for different sites.

`WP_Filesystem` is capable of detecting when the users for written files will not match, and switches to a method using FTP or similar instead. Depending on the available PHP libraries, [WP_Filesystem](https://developer.wordpress.org/reference/functions/wp_filesystem/) supports three different methods of using FTP (via extension, sockets, or over-SSH) and will automatically choose the correct method.

In such a case, the plugin or theme implementing this code needs to request FTP credentials from the user. Functions have been added to make this easy to do and to standardize the look and feel of the credentials entry form.

## Filesystem API Class Reference

- Class: [WP_Filesystem_Base](https://developer.wordpress.org/reference/classes/wp_filesystem_base/)
- Class: [WP_Filesystem_Direct](https://developer.wordpress.org/reference/classes/wp_filesystem_direct/)
- Class: [WP_Filesystem_FTPext](https://developer.wordpress.org/reference/classes/wp_filesystem_ftpext/)
- Class: [WP_Filesystem_ftpsocket](https://developer.wordpress.org/reference/classes/wp_filesystem_ftpsockets/)
- Class: [WP_Filesystem_SSH2](https://developer.wordpress.org/reference/classes/wp_filesystem_ssh2/)
- Function: [request_filesystem_credentials()](https://developer.wordpress.org/reference/functions/request_filesystem_credentials/)

## Getting Credentials

The first step in using the WP\_Filesystem is requesting credentials from the user. The normal way this is accomplished is at the time when you’re saving the results of a form input, or you have otherwise determined that you need to write to a file.

The credentials form can be displayed onto an admin page by using the following code:


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
$url = wp_nonce_url( 'themes.php?page=example', 'example-theme-options' );if ( false === ( $creds = request_filesystem_credentials( $url, '', false, false, null ) ) ) { return; // stop processing here}
```

The [request_filesystem_credentials()](https://developer.wordpress.org/reference/functions/request_filesystem_credentials/) call takes five arguments.

- The URL to which the form should be submitted (a nonced URL to a theme page was used in the example above)
- A method override (normally you should leave this as the empty string: “”)
- An error flag (normally false unless an error is detected, see below)
- A context directory (false, or a specific directory path that you want to test for access)
- Form fields (an array of form field names from your previous form that you wish to “pass-through” the resulting credentials form, or null if there are none)

The `request_filesystem_credentials` call will test to see if it is capable of writing to the local filesystem directly without credentials first. If this is the case, then it will return true and not do anything. Your code can then proceed to use the `WP_Filesystem` class.

The `request_filesystem_credentials` call also takes into account hardcoded information, such as hostname or username or password, which has been inserted into the `wp-config.php` file using defines. If these are pre-defined in that file, then this call will return that information instead of displaying a form, bypassing the form for the user.

If it does need credentials from the user, then it will output the FTP information form and return false. In this case, you should stop processing further, in order to allow the user to input credentials. Any form fields names you specified will be included in the resulting form as hidden inputs, and will be returned when the user resubmits the form, this time with FTP credentials.

Note: Do not use the reserved names of `hostname`, `username`, `password`, `public_key`, or `private_key` for your own inputs. These are used by the credentials form itself. Alternatively, if you do use them, the `request_filesystem_credentials` function will assume that they are the incoming FTP credentials.

When the credentials form is submitted, it will look in the incoming POST data for these fields, and if found, it will return them in an array suitable for passing to WP\_Filesystem, which is the next step.

## Initializing WP_Filesystem_Base

Before the WP\_Filesystem can be used, it must be initialized with the proper credentials. This can be done like so:


```php
if ( ! WP_Filesystem( $creds ) ) { request_filesystem_credentials( $url, '', true, false, null ); return;}
```

First you call the `WP_Filesystem` function, passing it the credentials from before. It will then attempt to verify the credentials. If they are good, then it will return true. If not, then it will return false.

In the case of bad credentials, the above code then makes another call to `request_filesystem_credentials()`, but this time with the error flag set to true. This forces the function to display the form again, this time with an error message for the user saying that their information was incorrect. The user can then re-enter their information and try again.

## Using the WP_Filesystem_Base Class

Once the class has been initialized, then the global `$wp_filesystem` variable becomes defined and available for you to use. The `WP_Filesystem_Base` class defines several methods you can use to read and write local files. For example, to write a file, you could do this:


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
global $wp_filesystem;$wp_filesystem->put_contents( '/tmp/example.txt', 'Example contents of a file', FS_CHMOD_FILE // predefined mode settings for WP files);
```

Other available methods include `get_contents()` and `get_contents_array()` to read files; `wp_content_dir()`, `wp_plugins_dir()`, and `wp_themes_dir()` which will return the filesystem paths to those directories; `mkdir()` and `rmdir()` to make and remove directories; along with several other handy filesystem related functions.

## Tips and Tricks

**When can you call `request_filesystem_credentials()`?**  
One of the initial challenges for developers using the WP Filesystem API is you cannot initialize it just anywhere. The `request_filesystem_credentials()` function isn’t available until AFTER the `wp_loaded` action hook, and is only included in the admin area. One of the earliest hooks you can utilize is admin\_init.

**The WP Filesystem API Methodology**  
Another problem with calling `request_filesystem_credentials()` directly is you cannot determine if you will have direct access to the file system or if the user will be prompted for credentials. From a UX standpoint this becomes problematic if you want to make changes to files when a plugin is activated. Just imagine, a user goes to install your plugin via their admin area, enters their FTP details, completes the installation and activates your plugin. But as soon as they do, they are prompted to enter their FTP details again and are left scratching their head as to why.

A better solution is to add a notice (using admin\_notice for instance) that explains to the user that your plugin needs to write to the file system to complete the installation. Along with that notice, you would add a button or link which triggers your function call to `request_filesystem_credentials()`.

But let’s expand on this scenario further and say this plugin needs to access the file system every time the plugin updated. If you’re regularly releasing updates and bug fixes, it soon becomes tenuous for users to click your actionable button every time they upgrade. What would be nice is to determine if we have direct write access before calling `request_filesystem_credentials()` and silently do the installation. That’s where the `get_filesystem_method()` function comes into play.


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
$access_type = get_filesystem_method();if ( $access_type === 'direct' ){ /* you can safely run request_filesystem_credentials() without any issues and don't need to worry about passing in a URL */ $creds = request_filesystem_credentials( site_url() . '/wp-admin/', '', false, false, array() ); /* initialize the API */ if ( ! WP_Filesystem( $creds ) ) { /* any problems and we exit */ return false; } global $wp_filesystem; /* do our file manipulations below */} else{ /* don't have direct write access. Prompt user with our notice */ add_action( 'admin_notices', 'you_admin_notice_function' ); }
```

This approach works well for all involved. Users who don’t have direct write permissions get prompted to make changes to the file system, while the plugin goes unnoticed (in a good way) on sites who can directly write to the file system.

**Working with Paths**  
WordPress developers worth their salt should be familiar with setting up constants or variables to access their plugin’s path. It usually looks like this:


```php
define( 'MY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
```

What you need to take into consideration when working with the Filesystem API is the path to the files won’t always be the same. When using the direct method you can safely use the `MY_PLUGIN_DIR` constant, but if you tried to do the same when the FTP or SSH method is used then you can run into problems. This is because FTP and SSH are usually rooted to a directory somewhere along the absolute path. Now, the Filesystem API gives us ways of overcoming this problem with methods like `$wp_filesystem->wp_content_dir()` and `$wp_filesystem->wp_plugins_dir()`, but it isn’t practical to define the path to your plugin twice.


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
/* replace the 'direct' absolute path with the Filesystem API path */ $plugin_path = str_replace( ABSPATH, $wp_filesystem->abspath(), MY_PLUGIN_DIR );/* Now we can use $plugin_path in all our Filesystem API method calls */if ( ! $wp_filesystem->is_dir( $plugin_path . '/config/' ) ) { /* directory didn't exist, so let's create it */ $wp_filesystem->mkdir( $plugin_path . '/config/' );}
```

**`unzip_file($file, $to);`**

While this function requires the Filesystem API to be initialized, it isn’t a method of the `$wp_filesystem` object, which might be why its arguments are at odds with each other. The first parameter, `$file`, needs to be the absolute ‘direct’ path to the file, while the `$toparameter` needs to point to the absolute path of the Filesystem.


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
define( 'MY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) ); global $wp_filesystem; // already initialised the Filesystem API previously$plugin_path = str_replace( ABSPATH, $wp_filesystem->abspath(), MY_PLUGIN_DIR ); // get remote system absolute path/* Acceptable way to use the function */$file = MY_PLUGIN_DIR . '/plugin-file.zip'; $to = $plugin_path;$result = unzip_file( $file, $to ); if ( $result !== true ) { // unzip failed. Handle Error}/* Not acceptable */$file = MY_PLUGIN_DIR . '/plugin-file.zip';$to = MY_PLUGIN_DIR; // $to cannot be the 'direct' absolute path to the folder otherwise FTP and SSH methods are left in the coldunzip_file( $file, $to ); $file = $plugin_path . '/plugin-file.zip'; // If $file isn't the 'direct' absolute path then users not using FTP and SSH methods are left in the cold$to = $plugin_path;unzip_file( $file, $to );
```

## External references

- [Tutorial: Using the WP_Filesystem](http://ottopress.com/2011/tutorial-using-the-wp_filesystem/) by Otto
- [Filesystem Debug Helper Plugin](https://github.com/eventespresso/filesystem-debug-helper)
