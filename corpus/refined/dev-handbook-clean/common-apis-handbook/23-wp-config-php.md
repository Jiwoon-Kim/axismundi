---
source_url: https://developer.wordpress.org/apis/wp-config-php/
synced: 2026-05-12
handbook: common-apis
chapter: wp-config-php
slug: wp-config-php
parent_order: 23
page_order: 0
title: "wp-config.php"
code_quality: degraded
code_issue: pre_newline_loss
---

# wp-config.php

One of the most important files in your WordPress installation is the `wp-config.php` file. This file is located in the root of your WordPress file directory and contains your website’s base configuration details, such as database connection information.

## Configure Database Settings

**Important:** *Never* use a word processor like Microsoft Word for editing WordPress files!

Locate the file `wp-config-sample.php` in the base directory of your WordPress directory and open in a [text editor](https://wordpress.org/support/article/editing-files/#text-editors).

### Default wp-config-sample.php

Note: This is an example of a default [wp-config-sample.php](https://core.trac.wordpress.org/browser/trunk/wp-config-sample.php). The values here are examples to show you what to do.


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
// ** MySQL settings - You can get this info from your web host ** ///** The name of the database for WordPress */define( 'DB_NAME', 'database_name_here' );/** MySQL database username */define( 'DB_USER', 'username_here' );/** MySQL database password */define( 'DB_PASSWORD', 'password_here' );/** MySQL hostname */define( 'DB_HOST', 'localhost' );
```

**Note:** Text inside /\* \*/ are *[comments](http://www.php.net/manual/en/language.basic-syntax.comments.php)*, for information purposes only.

#### Set Database Name

Replace ‘database\_name\_here’, with the name of your database, e.g. *MyDatabaseName*.


```php
define( 'DB_NAME', 'MyDatabaseName' ); // Example MySQL database name
```

#### Set Database User

Replace ‘username\_here’, with the name of your username e.g. *MyUserName*.


```php
define( 'DB_USER', 'MyUserName' ); // Example MySQL username
```

#### Set Database Password

Replace ‘password\_here’, with the your password, e.g. *MyPassWord*.


```php
define( 'DB_PASSWORD', 'MyPassWord' ); // Example MySQL password
```

#### Set Database Host

If needed, replace ‘*localhost*‘, with the name of your database host (e.g. *MyDatabaseHost*). A port number or Unix socket file path may be needed as well.


```php
define( 'DB_HOST', 'MyDatabaseHost' ); // Example MySQL Database host
```

**Note:** There is a good chance you will **NOT** have to change it. If you are unsure, try installing with the default value of ‘localhost’ and see if it works. If the install fails, contact your web hosting provider.

##### MySQL Alternate Port

If your host uses an alternate port number for your database, you’ll need to change the **DB\_HOST** value in the `wp-config.php` file to reflect the alternate port provided by your host.

For localhost:


```php
define( 'DB_HOST', '127.0.0.1:3307' );ordefine( 'DB_HOST', 'localhost:3307' );
```

For specified server:


```php
define( 'DB_HOST', 'mysql.example.com:3307' );
```

Replace the number **3307** in either of the code examples above with the port number information provided by your host.

##### MySQL Sockets or Pipes

If your host uses Unix sockets or pipes, you’ll need to change the **DB\_HOST** value in the `wp-config.php` file to reflect the socket or pipe information provided by your host.


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
define( 'DB_HOST', '127.0.0.1:/var/run/mysqld/mysqld.sock' );// or define( 'DB_HOST', 'localhost:/var/run/mysqld/mysqld.sock' );// or define( 'DB_HOST', 'example.tld:/var/run/mysqld/mysqld.sock' );
```

Replace the text string `/var/run/mysqld/mysqld.sock` above with the socket or pipe information provided by your host.

### Database character set

**DB\_CHARSET** was made available to allow designation of the database [character set](https://codex.wordpress.org/Glossary#Character_Set) (e.g. tis620 for TIS620 Thai) to be used when defining the MySQL database tables.

The default value of **utf8** ([Unicode](http://en.wikipedia.org/wiki/Unicode) [UTF-8](http://en.wikipedia.org/wiki/UTF-8)) is almost always the best option. UTF-8 supports any language, so you typically want to leave DB\_CHARSET at **utf8** and use the [DB_COLLATE](https://codex.wordpress.org/Editing_wp-config.php#Database_collation) value for your language instead.

This example shows utf8 which is considered the WordPress default value:


```php
define( 'DB_CHARSET', 'utf8' );
```

There usually should be no reason to change the default value of DB\_CHARSET. If your blog needs a different character set, please read [Character Sets and Collations MySQL Supports](http://dev.mysql.com/doc/refman/5.6/en/charset-charsets.html) for valid DB\_CHARSET values. **WARNING:** Those performing upgrades.

If DB\_CHARSET and DB\_COLLATE do not exist in your `wp-config.php` file, **DO NOT** add either definition to your `wp-config.php` file unless you read and understand [Converting Database Character Sets](https://codex.wordpress.org/Converting_Database_Character_Sets). Adding DB\_CHARSET and DB\_COLLATE to the `wp-config.php` file, for an existing blog, can cause major problems.

### Database collation

**DB\_COLLATE** was made available to allow designation of the database [collation](https://codex.wordpress.org/Glossary#Collation) (i.e. the sort order of the character set). In most cases, this value should be left blank (null) so the database collation will be automatically assigned by MySQL based on the database character set specified by DB\_CHARSET. An example of when you may need to set ''’DB\_COLLATE''’ to one of the UTF-8 values defined in [UTF-8 character sets](http://dev.mysql.com/doc/refman/5.6/en/charset-unicode-sets.html) for most Western European languages would be when a different language in which the characters that you entered are not the same as what is being displayed. (See also [Unicode Character Sets](https://dev.mysql.com/doc/refman/8.0/en/charset-unicode-sets.html#charset-unicode-sets-general-versus-unicode) in SQL Manual)

The WordPress default DB\_COLLATE value:


```php
define( 'DB_COLLATE', '' );
```

UTF-8 Unicode General collation


```php
define( 'DB_COLLATE', 'utf8_general_ci' );
```

UTF-8 Unicode Turkish collation


```php
define( 'DB_COLLATE', 'utf8_turkish_ci' );
```

There usually should be no reason to change the default value of DB\_COLLATE. Leaving the value blank (null) will insure the collation is automatically assigned by MySQL when the database tables are created. **WARNING:** Those performing upgrades

If DB\_COLLATE and DB\_CHARSET do not exist in your `wp-config.php` file, **DO NOT** add either definition to your `wp-config.php` file unless you read and understand [Converting Database Character Sets](https://codex.wordpress.org/Converting_Database_Character_Sets). And you may be in need of a WordPress upgrade.

### Security Keys

You don’t have to remember the keys, just make them long, random and complicated — or better yet, use the [online generator](https://api.wordpress.org/secret-key/1.1/salt/). You can change these at any point in time to invalidate all existing cookies. This does mean that all users will have to login again.

Example (don’t use these!):


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
define( 'AUTH_KEY', 't`DK%X:>xy|e-Z(BXb/f(Ur`8#~UzUQG-^_Cs_GHs5U-&Wb?pgn^p8(2@}IcnCa|' );define( 'SECURE_AUTH_KEY', 'D&ovlU#|CvJ##uNq}bel+^MFtT&.b9{UvR]g%ixsXhGlRJ7q!h}XWdEC[BOKXssj' );define( 'LOGGED_IN_KEY', 'MGKi8Br(&{H*~&0s;{k0<S(O:+f#WM+q|npJ-+P;RDKT:~jrmgj#/-,[hOBk!ry^' );define( 'NONCE_KEY', 'FIsAsXJKL5ZlQo)iD-pt??eUbdc{_Cn<4!d~yqz))&B D?AwK%)+)F2aNwI|siOe' );define( 'AUTH_SALT', '7T-!^i!0,w)L#JK@pc2{8XE[DenYI^BVf{L:jvF,hf}zBf883td6D;Vcy8,S)-&G' );define( 'SECURE_AUTH_SALT', 'I6`V|mDZq21-J|ihb u^q0F }F_NUcy`l,=obGtq*p#Ybe4a31R,r=|n#=]@]c #' );define( 'LOGGED_IN_SALT', 'w<$4c$Hmd%/*]`Oom>(hdXW|0M=X={we6;Mpvtg+V.o<$|#_}qG(GaVDEsn,~*4i' );define( 'NONCE_SALT', 'a|#h{c5|P &xWs4IZ20c2&%4!c(/uG}W:mAvy<I44`jAbup]t=]V<`}.py(wTP%%' );
```

A **secret key** makes your site harder to successfully attack by adding random elements to the password.

In simple terms, a secret key is a password with elements that make it harder to generate enough options to break through your security barriers. A password like “password” or “test” is simple and easily broken. A random, long password which uses no dictionary words, such as “88a7da62429ba6ad3cb3c76a09641fc” would take a brute force attacker millions of hours to crack. A ‘*salt* is used to further enhance the security of the generated result.

The four keys are required for the enhanced security. The four salts are recommended, but are not required, because WordPress will generate salts for you if none are provided. They are included in `wp-config.php` by default for inclusiveness.

For more information on the technical background and breakdown of secret keys and secure passwords, see:

- [Wikipedia’s explanation of Password Cracking](http://en.wikipedia.org/wiki/Password_cracking)
- [Lorelle VanFossen – Protect Your Blog With a Solid Password](http://www.blogherald.com/2007/05/08/protect-your-blog-with-a-solid-password/)
- [Instructables – Security Password Tips](http://www.instructables.com/id/How-to-Choose-a-Good-Password-A-few-quick-tips-on/)
- [Huffington Post – 17 Tips You Can Do Today to Protect Your Online Passwords](http://www.huffingtonpost.com/arkady-bukh/17-tips-you-can-do-today-to-protect-your-online-passwords_b_6812478.html/)

## Advanced Options

The following sections may contain advanced information and some changes might result in unforeseen issues. Please make sure you practice [regular backups](https://developer.wordpress.org/support/article/wordpress-backups/) and know how to restore them before modifying these settings.

### table_prefix

The **$table\_prefix** is the value placed in the front of your database tables. Change the value if you want to use something other than **wp\_** for your database prefix. Typically this is changed if you are [installing multiple WordPress blogs](https://developer.wordpress.org/support/article/installing-multiple-blogs/) in the same database, as is done with the multisite feature.

It is possible to have multiple installations in one database if you give each a unique prefix. Keep security in mind if you choose to do this.


```php
$table_prefix = 'r235_'; // Only numbers, letters, and underscores please!
```

### WP_SITEURL

WP\_SITEURL allows the WordPress address (URL) to be defined. The value defined is the address where your WordPress core files reside. It should include the `http://` part too. Do not put a slash “**/**” at the end. Setting this value in `wp-config.php` overrides the [wp_options table](https://codex.wordpress.org/Database_Description#Table:_wp_options) value for **siteurl**. Adding this in can reduce the number of database calls when loading your site. **Note:** This will **not** change the database stored value. The URL will revert to the old database value if this line is ever removed from `wp-config`. [Use the **RELOCATE** constant](https://codex.wordpress.org/Changing_The_Site_URL#Relocate_method) to change the **siteurl **value in the database.

If WordPress is installed into a directory called “wordpress” for the [domain](http://en.wikipedia.org/wiki/Domain_name_system) example.com, define WP\_SITEURL like this:


```php
define( 'WP_SITEURL', 'http://example.com/wordpress' );
```

Dynamically set WP\_SITEURL based on $\_SERVER[‘HTTP\_HOST’]


```php
define( 'WP_SITEURL', 'http://' . $_SERVER['HTTP_HOST'] . '/path/to/wordpress' );
```

**Note:** HTTP\_HOST is created dynamically by PHP based on the value of the HTTP HOST Header in the request, thus possibly allowing for [file inclusion vulnerabilities](https://en.wikipedia.org/wiki/File_inclusion_vulnerability). SERVER\_NAME may also be created dynamically. However, when Apache is configured as UseCanonicalName “on”, SERVER\_NAME is set by the server configuration, instead of dynamically. In that case, it is safer to user SERVER\_NAME than HTTP\_HOST.

Dynamically set WP\_SITEURL based on $\_SERVER[‘SERVER\_NAME’]


```php
define( 'WP_SITEURL', 'http://' . $_SERVER['SERVER_NAME'] . '/path/to/wordpress' );
```

### Blog address (URL)

Similar to WP\_SITEURL, WP\_HOME *overrides the [wp_options table](https://codex.wordpress.org/Database_Description#Table:_wp_options) value for *home* but does not change it in the database.* **home** is the address you want people to type in their browser to reach your WordPress blog. It should include the `http://` part and should not have a slash “**/**” at the end. Adding this in can reduce the number of database calls when loading your site.


```php
define( 'WP_HOME', 'http://example.com/wordpress' );
```

If you are using the technique described in [Giving WordPress Its Own Directory](https://developer.wordpress.org/support/article/giving-wordpress-its-own-directory/) then follow the example below. Remember, you will also be placing an `index.php` in your web-root directory if you use a setting like this.


```php
define( 'WP_HOME', 'http://example.com' );
```

Dynamically set WP\_HOME based on $\_SERVER[‘HTTP\_HOST’]


```php
define( 'WP_HOME', 'http://' . $_SERVER['HTTP_HOST'] . '/path/to/wordpress' );
```

### Moving wp-content folder

You can move the `wp-content` directory, which holds your themes, plugins, and uploads, outside of the WordPress application directory.

Set WP\_CONTENT\_DIR to the full **local path** of this directory (no trailing slash), e.g.


```php
define( 'WP_CONTENT_DIR', dirname(__FILE__) . '/blog/wp-content' );
```

Set WP\_CONTENT\_URL to the full **URL** of this directory (no trailing slash), e.g.


```php
define( 'WP_CONTENT_URL', 'http://example/blog/wp-content' );
```

### Moving plugin folder

Set WP\_PLUGIN\_DIR to the full **local path** of this directory (no trailing slash), e.g.


```php
define( 'WP_PLUGIN_DIR', dirname(__FILE__) . '/blog/wp-content/plugins' );
```

Set WP\_PLUGIN\_URL to the full **URI** of this directory (no trailing slash), e.g.


```php
define( 'WP_PLUGIN_URL', 'http://example/blog/wp-content/plugins' );
```

If you have compability issues with plugins Set PLUGINDIR to the full **local path** of this directory (no trailing slash), e.g.


```php
define( 'PLUGINDIR', dirname(__FILE__) . '/blog/wp-content/plugins' );
```

### Moving themes folder

You cannot move the themes folder because its path is hardcoded relative to the `wp-content` folder:

$theme\_root = WP\_CONTENT\_DIR . ‘/themes’;

However, you can register additional theme directories using [register_theme_directory](https://developer.wordpress.org/reference/functions/register_theme_directory/).

See how to [move the wp-content](https://developer.wordpress.org/support/article/editing-wp-config-php/) folder. For more details how the themes folder is determined, see `wp-includes/theme.php`.

### Moving uploads folder

Set UPLOADS to :


```php
define( 'UPLOADS', 'blog/wp-content/uploads' );
```

This path can not be absolute. It is always relative to ABSPATH, therefore does not require a leading slash.

### Modify AutoSave Interval

When editing a post, WordPress uses Ajax to auto-save revisions to the post as you edit. You may want to increase this setting for longer delays in between auto-saves, or decrease the setting to make sure you never lose changes. The default is 60 seconds.


```php
define( 'AUTOSAVE_INTERVAL', 160 ); // Seconds
```

### Post Revisions

WordPress, by default, will save copies of each edit made to a post or page, allowing the possibility of reverting to a previous version of that post or page. The saving of revisions can be disabled, or a maximum number of revisions per post or page can be specified.

#### Disable Post Revisions

If you do **not** set this value, WordPress defaults WP\_POST\_REVISIONS to *true* (enable post revisions). If you want to disable the awesome revisions feature, use this setting:


```php
define( 'WP_POST_REVISIONS', false );
```

Note: Some users could not get this to function until moving the command to the first line under the initial block comment in `wp-config.php`.

#### Specify the Number of Post Revisions

If you want to specify a maximum number of revisions that WordPress stores, change *false* to an integer/number (*e.g.*, 3 or 12).


```php
define( 'WP_POST_REVISIONS', 3 );
```

Note: Some users could not get this to function until moving the command to the first line under the initial block comment in `wp-config.php`.

### Set Cookie Domain

The domain set in the cookies for WordPress can be specified for those with unusual domain setups. For example, if subdomains are used to serve static content, you can set the cookie domain to only your non-static domain to prevent WordPress cookies from being sent with each request to static content on your subdomain .


```php
define( 'COOKIE_DOMAIN', 'www.example.com' );
```

### Enable Multisite / Network Ability

WP\_ALLOW\_MULTISITE is a feature enable multisite functionality. If this setting is absent from `wp-config.php` it defaults to false.


```php
define( 'WP_ALLOW_MULTISITE', true );
```

### Redirect Nonexistent Blogs

NOBLOGREDIRECT can be used to redirect the browser if the visitor tries to access a nonexistent subdomain or a subfolder.


```php
define( 'NOBLOGREDIRECT', 'http://example.com' );
```

### Fatal Error Handler

WordPress 5.2 introduced [Recovery Mode](https://make.wordpress.org/core/2019/04/16/fatal-error-recovery-mode-in-5-2/) which displays error message instead of white screen when plugins causes fatal error.

*The site is experiencing technical difficulties. Please check your site admin email inbox for instructions.*

White screens and PHP error messages are not displayed to users any more. But in a development environment, if you want to enable WP\_DEBUG\_DISPLAY, you have to disable recovery mode by set true to WP\_DISABLE\_FATAL\_ERROR\_HANDLER.


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true ); // 5.2 and later define( 'WP_DEBUG', true );define( 'WP_DEBUG_DISPLAY', true );
```

### WP_DEBUG

The [WP_DEBUG](https://codex.wordpress.org/Debugging_in_WordPress) option controls the reporting of some errors and warnings and enables use of the WP\_DEBUG\_DISPLAY and WP\_DEBUG\_LOG settings. The default boolean value is false.


```php
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true ); // 5.2 and laterdefine( 'WP_DEBUG', true );
```

[Database errors are printed only if WP_DEBUG is set to true](https://trac.wordpress.org/ticket/5473). Database errors are handled by the [wpdb](https://developer.wordpress.org/reference/classes/wpdb/) class and are not affected by [PHP’s error settings](http://www.php.net/errorfunc).

Setting WP\_DEBUG to true also raises the [error reporting level](http://www.php.net/error-reporting) to E\_ALL and activates warnings when deprecated functions or files are used; otherwise, WordPress sets the error reporting level to E\_ALL ^ E\_NOTICE ^ E\_USER\_NOTICE.

### WP_ENVIRONMENT_TYPE

The WP\_ENVIRONMENT\_TYPE option controls the environment type for a site: `local`, `development`, `staging`, and `production`.

The values of environment types are processed in the following order with each sequential method overriding any previous values: the WP\_ENVIRONMENT\_TYPE [PHP environment variable](https://www.php.net/manual/en/reserved.variables.environment.php) and the WP\_ENVIRONMENT\_TYPE constant.

For both methods, if the value of an environment type provided is not in the list of allowed environment types, the default `production` value will be returned.

The simplest way to set the value is probably through defining the constant:


```php
define( 'WP_ENVIRONMENT_TYPE', 'staging' );
```

Note: When `development` is returned by [wp_get_environment_type()](https://developer.wordpress.org/reference/functions/wp_get_environment_type/), WP\_DEBUG will be set to `true` if it is not defined in the `wp-config.php` file of the site.

### SCRIPT_DEBUG

[SCRIPT_DEBUG](https://developer.wordpress.org/support/article/debugging-in-wordpress/) is a related constant that will force WordPress to use the “dev” versions of scripts and stylesheets in `wp-includes/js`, `wp-includes/css`, `wp-admin/js`, and `wp-admin/css` will be loaded instead of the `.min.css` and `.min.js` versions.. If you are planning on modifying some of WordPress’ built-in JavaScript or Cascading Style Sheets, you should add the following code to your config file:


```php
define( 'SCRIPT_DEBUG', true );
```

### Disable Javascript Concatenation

To result in faster administration screens, all JavaScript files are [concatenated](http://en.wikipedia.org/wiki/Concatenation) into one URL. If JavaScript is failing to work in an administration screen, you can try disabling this feature:


```php
define( 'CONCATENATE_SCRIPTS', false );
```

### Configure Error Logging

Configuring error logging can be a bit tricky. First of all, default PHP error log and display settings are set in the php.ini file, which you may or may not have access to. If you do, they should be set to the desired settings for live PHP pages served to the public. It’s strongly recommended that no error messages are displayed to the public and instead routed to an error log. Further more, error logs should not be located in the publicly accessible portion of your server. Sample recommended php.ini error settings:


```ini
error_reporting = 4339display_errors = Offdisplay_startup_errors = Offlog_errors = Onerror_log = /home/example.com/logs/php_error.loglog_errors_max_len = 1024ignore_repeated_errors = Onignore_repeated_source = Offhtml_errors = Off
```

**About Error Reporting 4339** This is a custom value that only logs issues that affect the functioning of your site, and ignores things like notices that may not even be errors. See [PHP Error Constants](http://php.net/manual/en/errorfunc.constants.php) for the meaning of each binary position for 1000011110011, which is the binary number equal to 4339. The far left 1 means report any E\_RECOVERABLE\_ERROR. The next 0 means do not report E\_STRICT, (which is thrown when sloppy but functional coding is used) and so on. Feel free to determine your own custom error reporting number to use in place of 4339.

Obviously, you will want different settings for your development environment. If your staging copy is on the same server, or you don’t have access to `php.ini`, you will need to override the default settings at run time. It’s a matter of personal preference whether you prefer errors to go to a log file, or you prefer to be notified immediately of any error, or perhaps both. Here’s an example that reports all errors immediately that you could insert into your `wp-config.php` file:


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
@ini_set( 'log_errors', 'Off' );@ini_set( 'display_errors', 'On' );define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true ); // 5.2 and laterdefine( 'WP_DEBUG', true );define( 'WP_DEBUG_LOG', false );define( 'WP_DEBUG_DISPLAY', true );
```

Because `wp-config.php` is loaded for every page view not loaded from a cache file, it is an excellent location to set `php.ini` settings that control your PHP installation. This is useful if you don’t have access to a `php.ini` file, or if you just want to change some settings on the fly. One exception is ‘error\_reporting’. When WP\_DEBUG is defined as true, ‘error\_reporting’ will be set to E\_ALL by WordPress regardless of anything you try to set in wp-config.php. If you really have a need to set ‘error\_reporting’ to something else, it must be done after `wp-settings.php` is loaded, such as in a plugin file.

If you turn on error logging, remember to delete the file afterwards, as it will often be in a publicly accessible location, where anyone could gain access to your log.

Here is an example that turns PHP error\_logging on and logs them to a specific file. If WP\_DEBUG is defined to true, the errors will also be saved to this file. Just place this above any *require\_once* or *include* commands.


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
@ini_set( 'log_errors', 'On' );@ini_set( 'display_errors', 'Off' );@ini_set( 'error_log', '/home/example.com/logs/php_error.log' );/* That's all, stop editing! Happy blogging. */
```

Another example of logging errors, as suggested by Mike Little on the [wp-hackers email list](http://lists.automattic.com/pipermail/wp-hackers/2010-September/034830.html):


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
/** * This will log all errors notices and warnings to a file called debug.log in * wp-content (if Apache does not have write permission, you may need to create * the file first and set the appropriate permissions (i.e. use 666) ) */define( 'WP_DEBUG', true );define( 'WP_DEBUG_LOG', true );define( 'WP_DEBUG_DISPLAY', false );@ini_set( 'display_errors', 0 );
```

A refined version from Mike Little on the [Manchester WordPress User Group](http://groups.google.com/group/manchester-wordpress-user-group/msg/dcab0836cabc7f76):


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
/** * This will log all errors notices and warnings to a file called debug.log in * wp-content only when WP_DEBUG is true. if Apache does not have write permission, * you may need to create the file first and set the appropriate permissions (i.e. use 666). */define( 'WP_DEBUG', true ); // Or falseif ( WP_DEBUG ) { define( 'WP_DEBUG_LOG', true ); define( 'WP_DEBUG_DISPLAY', false ); @ini_set( 'display_errors', 0 );}
```

Confusing the issue is that WordPress has three (3) constants that look like they could do the same thing. First off, remember that if WP\_DEBUG is false, it and the other two WordPress DEBUG constants do not do anything. The PHP directives, whatever they are, will prevail. Except for ‘error\_reporting’, WordPress will set this to 4983 if WP\_DEBUG is defined as false. Second, even if WP\_DEBUG is true, the other constants only do something if they too are set to true. If they are set to false, the PHP directives remain unchanged. For example, if your `php.ini` file has the directive (‘display\_errors’ = ‘On’); but you have the statement define( ‘WP\_DEBUG\_DISPLAY’, false ); in your `wp-config.php` file, errors will still be displayed on screen even though you tried to prevent it by setting WP\_DEBUG\_DISPLAY to false because that is the PHP configured behavior. This is why it’s very important to set the PHP directives to what you need in case any of the related WP constants are set to false. To be safe, explicitly set/define both types. More detailed descriptions of the WP constants is available at [Debugging in WordPress](https://codex.wordpress.org/Debugging_in_WordPress).

For your public, production WordPress installation, you might consider placing the following in your `wp-config.php` file, even though it may be partly redundant:


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
@ini_set( 'log_errors', 'On' );@ini_set( 'display_errors', 'Off' );define( 'WP_DISABLE_FATAL_ERROR_HANDLER', false ); // 5.2 and laterdefine( 'WP_DEBUG', false );define( 'WP_DEBUG_LOG', false );define( 'WP_DEBUG_DISPLAY', false );
```

The default debug log file is`/wp-content/debug.log`. Placing error logs in publicly accessible locations is a security risk. Ideally, your log files should be placed above you site’s public root directory. If you can’t do this, at the very least, set the log file permissions to 600 and add this entry to the `.htaccess` file in the root directory of your WordPress installation:


```html
<Files debug.log> Order allow,deny Deny from all</Files>
```

This prevents anyone from accessing the file via HTTP. You can always view the log file by retrieving it from your server via FTP.

### Increasing memory allocated to PHP

**WP\_MEMORY\_LIMIT** option allows you to specify the maximum amount of memory that can be consumed by PHP. This setting may be necessary in the event you receive a message such as “Allowed memory size of xxxxxx bytes exhausted”.

This setting increases PHP Memory only for WordPress, not other applications. By default, WordPress will attempt to increase memory allocated to PHP to 40MB (code is at the beginning of `/wp-includes/default-constants.php`) for single site and 64MB for multisite, so the setting in `wp-config.php` should reflect something higher than 40MB or 64MB depending on your setup.

WordPress will automatically check if PHP has been allocated less memory than the entered value before utilizing this function. For example, if PHP has been allocated 64MB, there is no need to set this value to 64M as WordPress will automatically use all 64MB if need be.

Note: Some hosts do not allow for increasing the PHP memory limit automatically. In that event, contact your host to increase the PHP memory limit. Also, many hosts set the PHP limit at 8MB.

Adjusting the WordPress memory limit potentially creates problems as well. You might end up hiding the root of the issue for it to happen later down the line as you add in more plugins or functionalities.

If you are facing Out of Memory issues even with an elevated memory limit, you should properly debug your installation. Chances are you have too many memory intensive functions tied to a specific action and should move these functions to a cronjob.

Increase PHP Memory to 64MB


```php
define( 'WP_MEMORY_LIMIT', '64M' );
```

Increase PHP Memory to 96MB


```php
define( 'WP_MEMORY_LIMIT', '96M' );
```

Administration tasks require may require memory than usual operation. When in the administration area, the memory can be increased or decreased from the WP\_MEMORY\_LIMIT by defining WP\_MAX\_MEMORY\_LIMIT. The default value for this is 256MB.


```php
define( 'WP_MAX_MEMORY_LIMIT', '512M' );
```

Note: this has to be put before wp-settings.php inclusion.

### Cache

The **WP\_CACHE** setting, if true, includes the `wp-content/advanced-cache.php` script, when executing `wp-settings.php`.


```php
define( 'WP_CACHE', true );
```

### Custom User and Usermeta Tables

**CUSTOM\_USER\_TABLE** and **CUSTOM\_USER\_META\_TABLE** are used to designate that the user and usermeta tables normally utilized by WordPress are not used, instead these values/tables are used to store your user information.


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
define( 'CUSTOM_USER_TABLE', $table_prefix.'my_users' );define( 'CUSTOM_USER_META_TABLE', $table_prefix.'my_usermeta' );
```

Note: Even if ‘CUSTOM\_USER\_META\_TABLE’ is manually set, a usermeta table is still created for each database with the corresponding permissions for each instance. By default, the WordPress installer will add permissions for the first user (ID #1). You also need to manage permissions to each of the site via a plugin or custom function. If this isn’t setup you will experience permission errors and log-in issues.

CUSTOM\_USER\_TABLE is easiest to adopt during initial Setup your first instance of WordPress. The define statements of the `wp-config.php` on the first instance point to where `wp_users` data will be stored by default. After the first site setup, copying the working `wp-config.php` to your next instance will only require a change the `$table_prefix` variable. Do not use an e-mail address that is already in use by your original install. Once you have finished the setup process log in with the auto generated admin account and password. Next, promote your normal account to the administrator level and Log out of admin. Log back in as yourself, delete the admin account and promote the other user accounts as is needed.

### Language and Language Directory

WordPress [Version 4.0](https://codex.wordpress.org/Version_4.0) allows you to change the language in your WordPress [Administration Screens](https://developer.wordpress.org/support/article/administration-screens/). To change the language in the admin settings screen. Go to [Settings](https://developer.wordpress.org/support/article/administration-screens/#settings-configuration-settings) &gt; [General](https://developer.wordpress.org/support/article/settings-general-screen/) and select Site Language.

#### WordPress v3.9.6 and below

**WPLANG** defines the name of the language translation (.mo) file. **WP\_LANG\_DIR** defines what directory the WPLANG .mo file resides. If WP\_LANG\_DIR is not defined WordPress looks first to wp-content/languages and then `wp-includes/languages` for the .mo defined by WPLANG file.


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
define( 'WPLANG', 'de_DE' );define( 'WP_LANG_DIR', dirname(__FILE__) . 'wordpress/languages' );
```

To find out the WPLANG language code, please [refer here](https://make.wordpress.org/polyglots/teams/). The code in WP Local column is what you need.

### Save queries for analysis

The **SAVEQUERIES** definition saves the database queries to an array and that array can be displayed to help analyze those queries. The information saves each query, what function called it, and how long that query took to execute. **Note:** This will have a performance impact on your site, so make sure to turn this off when you aren’t debugging.

First, add this to the `wp-config.php` file:


```php
define( 'SAVEQUERIES', true );
```

Then in the footer of your theme put this:


```php
if ( current_user_can( 'administrator' ) ) { global $wpdb; echo "<pre>"; print_r( $wpdb->queries ); echo "</pre>";}?>
```

Alternatively, consider using [Query Monitor](https://wordpress.org/plugins/query-monitor/)

### Override of default file permissions

The **FS\_CHMOD\_DIR** and **FS\_CHMOD\_FILE** define statements allow override of default file permissions. These two variables were developed in response to the problem of the core update function failing with hosts running under [suexec](https://en.wikipedia.org/wiki/SuEXEC). If a host uses restrictive file permissions (e.g. 400) for all user files, and refuses to access files which have group or world permissions set, these definitions could solve the problem.


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
define( 'FS_CHMOD_DIR', ( 0755 & ~ umask() ) );define( 'FS_CHMOD_FILE', ( 0644 & ~ umask() ) );
```

Example to provide setgid:


```php
define( 'FS_CHMOD_DIR', ( 02755 & ~umask() ) );
```

Note: ‘**0755′** and ‘**02755**‘ are octal values. Octal values must be prefixed with a 0 and are not delineated with single quotes (‘). See Also: [Changing File Permissions](https://codex.wordpress.org/Changing_File_Permissions)

### WordPress Upgrade Constants

**Note: Define as few of the below constants as needed to correct your update issues.**

The most common causes of needing to define these are:

Host running with a special installation setup involving symlinks. You may need to define the path-related constants (FTP\_BASE, FTP\_CONTENT\_DIR, and FTP\_PLUGIN\_DIR). Often defining simply the base will be enough.

Certain PHP installations shipped with a PHP FTP extension which is incompatible with certain FTP servers. Under these rare situations, you may need to define FS\_METHOD to “ftpsockets”.

The following are valid constants for WordPress updates:

- **FS\_METHOD** forces the filesystem method. It should only be “direct”, “ssh2”, “ftpext”, or “ftpsockets”. Generally, you should only change this if you are experiencing update problems. If you change it and it doesn’t help, **change it back/remove it**. Under most circumstances, setting it to ‘ftpsockets’ will work if the automatically chosen method does not.
```php
- **(Primary Preference) “direct”** forces it to use Direct File I/O requests from within PHP, this is fraught with opening up security issues on poorly configured hosts, This is chosen automatically when appropriate.
- **(Secondary Preference) “ssh2”** is to force the usage of the SSH PHP Extension if installed
- **(3rd Preference) “ftpext”** is to force the usage of the FTP PHP Extension for FTP Access, and finally
- **(4th Preference) “ftpsockets”** utilises the PHP Sockets Class for FTP Access.
```
- **FTP\_BASE** is the full path to the “base”(ABSPATH) folder of the WordPress installation.
- **FTP\_CONTENT\_DIR** is the full path to the wp-content folder of the WordPress installation.
- **FTP\_PLUGIN\_DIR** is the full path to the plugins folder of the WordPress installation.
- **FTP\_PUBKEY** is the full path to your SSH public key.
- **FTP\_PRIKEY** is the full path to your SSH private key.
- **FTP\_USER** is either user FTP or SSH username. Most likely these are the same, but use the appropriate one for the type of update you wish to do.
- **FTP\_PASS** is the password for the username entered for **FTP\_USER**. If you are using SSH public key authentication this can be omitted.
- **FTP\_HOST** is the hostname:port combination for your SSH/FTP server. The default FTP port is 21 and the default SSH port is 22. These do not need to be mentioned.
- **FTP\_SSL** TRUE for SSL-connection *if supported by the underlying transport* (not available on all servers). This is for “Secure FTP” not for SSH SFTP.


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
define( 'FS_METHOD', 'ftpext' );define( 'FTP_BASE', '/path/to/wordpress/' );define( 'FTP_CONTENT_DIR', '/path/to/wordpress/wp-content/' );define( 'FTP_PLUGIN_DIR ', '/path/to/wordpress/wp-content/plugins/' );define( 'FTP_PUBKEY', '/home/username/.ssh/id_rsa.pub' );define( 'FTP_PRIKEY', '/home/username/.ssh/id_rsa' );define( 'FTP_USER', 'username' );define( 'FTP_PASS', 'password' );define( 'FTP_HOST', 'ftp.example.org' );define( 'FTP_SSL', false );
```

Some configurations should set FTP\_HOST to localhost to avoid 503 problems when trying to update plugins or WP itself.

#### Enabling SSH Upgrade Access

There are two ways to upgrade using SSH2.

The first is to use the [SSH SFTP Updater Support plugin](https://wordpress.org/plugins/ssh-sftp-updater-support/). The second is to use the built-in SSH2 upgrader, which requires the pecl SSH2 extension be installed.

To install the pecl SSH2 extension you will need to issue a command similar to the following or talk to your web hosting provider to get this installed:


```bash
pecl install ssh2
```

After installing the pecl ssh2 extension you will need to modify your PHP configuration to automatically load this extension.

pecl is provided by the pear package in most linux distributions. To install pecl in Redhat/Fedora/CentOS:


```text
yum -y install php-pear
```

To install pecl in Debian/Ubuntu:


```text
apt-get install php-pear
```

It is recommended to use a private key that is not pass-phrase protected. There have been numerous reports that pass phrase protected private keys do not work properly. If you decide to try a pass phrase protected private key you will need to enter the pass phrase for the private key as FTP\_PASS, or entering it in the “Password” field in the presented credential field when installing updates.

### Alternative Cron

There might be reason to use an alternative Cron with WP. Most commonly this is done if scheduled posts are not getting published as predicted. This alternative method uses a redirection approach. The users’ browser get a redirect when the cron needs to run, so that they come back to the site immediately while cron continues to run in the connection they just dropped. This method has certain risks, since it depends on a non-native WordPress service.


```php
define( 'ALTERNATE_WP_CRON', true );
```

### Disable Cron and Cron Timeout

Disable cron entirely by setting DISABLE\_WP\_CRON to true.


```php
define( 'DISABLE_WP_CRON', true );
```

Make sure a cron process cannot run more than once every WP\_CRON\_LOCK\_TIMEOUT seconds.


```php
define( 'WP_CRON_LOCK_TIMEOUT', 60 );
```

### Additional Defined Constants

Here are additional constants that can be defined. These probably shouldn’t be set unless other methodologies have been attempted first. The Cookie definitions can be particularly useful if you have an unusual domain setup.


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
define( 'COOKIEPATH', preg_replace( '|https?://[^/]+|i', '', get_option( 'home' ) . '/' ) );define( 'SITECOOKIEPATH', preg_replace( '|https?://[^/]+|i', '', get_option( 'siteurl' ) . '/' ) );define( 'ADMIN_COOKIE_PATH', SITECOOKIEPATH . 'wp-admin' );define( 'PLUGINS_COOKIE_PATH', preg_replace( '|https?://[^/]+|i', '', WP_PLUGIN_URL ) );define( 'TEMPLATEPATH', get_template_directory() );define( 'STYLESHEETPATH', get_stylesheet_directory() );
```

### Empty Trash

This constant controls the number of days before WordPress permanently deletes posts, pages, attachments, and comments, from the trash bin. The default is 30 days:


```php
define( 'EMPTY_TRASH_DAYS', 30 ); // 30 days
```

To disable trash set the number of days to zero.


```php
define( 'EMPTY_TRASH_DAYS', 0 ); // Zero days
```

**Note:** WordPress will not ask for confirmation when someone clicks on “Delete Permanently” using this setting.

### Automatic Database Optimizing

There is automatic database repair support, which you can enable by adding the following define to your `wp-config.php` file.

Note: This should only be enabled if needed and disabled once the issue is solved. When enabled, a user does not need to be logged in to access the functionality, since its main intent is to repair a corrupted database and users can often not login when the database is corrupt.


```php
 define( 'WP_ALLOW_REPAIR', true );
```

The script can be found at `{$your_site}/wp-admin/maint/repair.php`.

### DO_NOT_UPGRADE_GLOBAL_TABLES

A **DO\_NOT\_UPGRADE\_GLOBAL\_TABLES** define prevents [dbDelta()](https://developer.wordpress.org/reference/functions/dbdelta/) and the upgrade functions from doing expensive queries against global tables.

Sites that have large global tables (particularly users and usermeta), as well as sites that share user tables with bbPress and other WordPress installs, can prevent the upgrade from changing those tables during upgrade by defining **DO\_NOT\_UPGRADE\_GLOBAL\_TABLES **to true. Since an ALTER, or an unbounded DELETE or UPDATE, can take a long time to complete, large sites usually want to avoid these being run as part of the upgrade so they can handle it themselves. Further, if installations are sharing user tables between multiple bbPress and WordPress installs you may to want one site to be the upgrade master.


```php
define( 'DO_NOT_UPGRADE_GLOBAL_TABLES', true );
```

### View All Defined Constants

PHP has a function that returns an array of all the currently defined constants with their values.


```php
 print_r( @get_defined_constants() );
```

### Disable the Plugin and Theme File Editor

Occasionally you may wish to disable the plugin or theme file editor to prevent overzealous users from being able to edit sensitive files and potentially crash the site. Disabling these also provides an additional layer of security if a hacker gains access to a well-privileged user account.


```php
define( 'DISALLOW_FILE_EDIT', true );
```

**Note**: The functionality of some plugins may be affected by the use of `current_user_can('edit_plugins')` in their code. Plugin authors should avoid checking for this capability, or at least check if this constant is set and display an appropriate error message. Be aware that if a plugin is not working this may be the cause.

### Disable Plugin and Theme Update and Installation

This will block users being able to use the plugin and theme installation/update functionality from the WordPress admin area. Setting this constant also disables the Plugin and Theme File editor (i.e. you don’t need to set DISALLOW\_FILE\_MODS and DISALLOW\_FILE\_EDIT, as on its own DISALLOW\_FILE\_MODS will have the same effect).


```php
define( 'DISALLOW_FILE_MODS', true );
```

### Require SSL for Admin and Logins

**Note:** WordPress [Version 4.0](https://codex.wordpress.org/Version_4.0) deprecated FORCE\_SSL\_LOGIN. Please use FORCE\_SSL\_ADMIN.

FORCE\_SSL\_ADMIN is for when you want to secure logins and the admin area so that both passwords and cookies are never sent in the clear. See also [Administration_Over_SSL](https://developer.wordpress.org/support/article/administration-over-ssl/) for more details.


```php
define( 'FORCE_SSL_ADMIN', true );
```

### Block External URL Requests

Block external URL requests by defining WP\_HTTP\_BLOCK\_EXTERNAL as true and this will only allow localhost and your blog to make requests. The constant WP\_ACCESSIBLE\_HOSTS will allow additional hosts to go through for requests. The format of the WP\_ACCESSIBLE\_HOSTS constant is a comma separated list of hostnames to allow, wildcard domains are supported, eg \*.wordpress.org will allow for all subdomains of wordpress.org to be contacted.


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
define( 'WP_HTTP_BLOCK_EXTERNAL', true );define( 'WP_ACCESSIBLE_HOSTS', 'api.wordpress.org,*.github.com' );
```

### Disable WordPress Auto Updates

There might be reason for a site to not auto-update, such as customizations or host supplied updates. It can also be done before a major release to allow time for testing on a development or staging environment before allowing the update on a production site.


```php
 define( 'AUTOMATIC_UPDATER_DISABLED', true );
```

### Disable WordPress Core Updates

The easiest way to manipulate core updates is with the WP\_AUTO\_UPDATE\_CORE constant:


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```bash
 # Disable all core updates: define( 'WP_AUTO_UPDATE_CORE', false ); # Enable all core updates, including minor and major: define( 'WP_AUTO_UPDATE_CORE', true ); # Enable core updates for minor releases (default): define( 'WP_AUTO_UPDATE_CORE', 'minor' );
```

Reference: [Disabling Auto Updates in WordPress 3.7](https://make.wordpress.org/core/2013/10/25/the-definitive-guide-to-disabling-auto-updates-in-wordpress-3-7/)

### Cleanup Image Edits

By default, WordPress creates a new set of images every time you edit an image and when you restore the original, it leaves all the edits on the server. Defining IMAGE\_EDIT\_OVERWRITE as true changes this behaviour. Only one set of image edits are ever created and when you restore the original, the edits are removed from the server.


```php
 define( 'IMAGE_EDIT_OVERWRITE', true );
```

## Double Check Before Saving

***Be sure to check for leading and/or trailing spaces around any of the above values you entered, and DON’T delete the single quotes!***

Before you save the file, be sure to **double-check** that you have not accidentally deleted any of the single quotes around the parameter values. Be sure there is nothing after the closing PHP tag in the file. The last thing in the file should be **?&gt;** and nothing else. No spaces.

To save the file, choose **File &gt; Save As &gt; wp-config.php** and save the file in the root of your WordPress install. Upload the file to your web server and you’re ready to install WordPress!
