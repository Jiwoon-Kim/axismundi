---
source_url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-url/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: package-reference
slug: url
parent_order: 3
sub_order: 9
page_order: 112
title: "@wordpress/url"
code_quality: degraded
code_issue: pre_newline_loss
---

# @wordpress/url

A collection of utilities to manipulate URLs.

## Installation

Install the module

```bash
npm install @wordpress/url --save
```

*This package assumes that your code will run in an **ES2015+** environment. If you’re using an environment that has limited or no support for such language features and APIs, you should include [the polyfill shipped in `@wordpress/babel-preset-default`](https://github.com/WordPress/gutenberg/tree/HEAD/packages/babel-preset-default#polyfill) in your code.*

## Usage

### addQueryArgs

Appends arguments as querystring to the provided URL. If the URL already includes query arguments, the arguments are merged with (and take precedent over) the existing set.

*Usage*

```js
const newURL = addQueryArgs( 'https://google.com', { q: 'test' } ); // https://google.com/?q=test
```

*Parameters*

- *url* `string`: URL to which arguments should be appended. If omitted, only the resulting querystring is returned.
- *args* `Record< string, unknown >`: Query arguments to apply to URL.

*Returns*

- `string`: URL with arguments applied.

### buildQueryString

Generates URL-encoded query string using input query data.

It is intended to behave equivalent as PHP’s `http_build_query`, configured with encoding type PHP\_QUERY\_RFC3986 (spaces as `%20`).

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
const queryString = buildQueryString( { simple: 'is ok', arrays: [ 'are', 'fine', 'too' ], objects: { evenNested: { ok: 'yes', }, },} );// "simple=is%20ok&arrays%5B0%5D=are&arrays%5B1%5D=fine&arrays%5B2%5D=too&objects%5BevenNested%5D%5Bok%5D=yes"
```

*Parameters*

- *data* `Record< string, unknown >`: Data to encode.

*Returns*

- `string`: Query string.

### cleanForSlug

Performs some basic cleanup of a string for use as a post slug.

This replicates some of what `sanitize_title_with_dashes()` does in WordPress core, but is only designed to approximate what the slug will be.

Converts Latin-1 Supplement and Latin Extended-A letters to basic Latin letters. Removes combining diacritical marks. Converts whitespace, periods, and forward slashes to hyphens. Removes any remaining non-word characters except hyphens. Converts remaining string to lowercase. It does not account for octets, HTML entities, or other encoded characters.

*Parameters*

- *string* `string`: Title or slug to be processed.

*Returns*

- `string`: Processed string.

### filterURLForDisplay

Returns a URL for display.

*Usage*

```js
const displayUrl = filterURLForDisplay( 'https://www.wordpress.org/gutenberg/'); // wordpress.org/gutenbergconst imageUrl = filterURLForDisplay( 'https://www.wordpress.org/wp-content/uploads/img.png', 20); // …ent/uploads/img.png
```

*Parameters*

- *url* `string`: Original URL.
- *maxLength* `number | null`: URL length.

*Returns*

- `string`: Displayed URL.

### getAuthority

Returns the authority part of the URL.

*Usage*

```js
const authority1 = getAuthority( 'https://wordpress.org/help/' ); // 'wordpress.org'const authority2 = getAuthority( 'https://localhost:8080/test/' ); // 'localhost:8080'
```

*Parameters*

- *url* `string`: The full URL.

*Returns*

- `string | void`: The authority part of the URL.

### getFilename

Returns the filename part of the URL.

*Usage*

```js
const filename1 = getFilename( 'http://localhost:8080/this/is/a/test.jpg' ); // 'test.jpg'const filename2 = getFilename( '/this/is/a/test.png' ); // 'test.png'
```

*Parameters*

- *url* `string`: The full URL.

*Returns*

- `string | void`: The filename part of the URL.

### getFragment

Returns the fragment part of the URL.

*Usage*

```js
const fragment1 = getFragment( 'http://localhost:8080/this/is/a/test?query=true#fragment'); // '#fragment'const fragment2 = getFragment( 'https://wordpress.org#another-fragment?query=true'); // '#another-fragment'
```

*Parameters*

- *url* `string`: The full URL

*Returns*

- `string | void`: The fragment part of the URL.

### getPath

Returns the path part of the URL.

*Usage*

```js
const path1 = getPath( 'http://localhost:8080/this/is/a/test?query=true' ); // 'this/is/a/test'const path2 = getPath( 'https://wordpress.org/help/faq/' ); // 'help/faq'
```

*Parameters*

- *url* `string`: The full URL.

*Returns*

- `string | void`: The path part of the URL.

### getPathAndQueryString

Returns the path part and query string part of the URL.

*Usage*

```js
const pathAndQueryString1 = getPathAndQueryString( 'http://localhost:8080/this/is/a/test?query=true'); // '/this/is/a/test?query=true'const pathAndQueryString2 = getPathAndQueryString( 'https://wordpress.org/help/faq/'); // '/help/faq'
```

*Parameters*

- *url* `string`: The full URL.

*Returns*

- `string`: The path part and query string part of the URL.

### getProtocol

Returns the protocol part of the URL.

*Usage*

```js
const protocol1 = getProtocol( 'tel:012345678' ); // 'tel:'const protocol2 = getProtocol( 'https://wordpress.org' ); // 'https:'
```

*Parameters*

- *url* `string`: The full URL.

*Returns*

- `string | void`: The protocol part of the URL.

### getQueryArg

Returns a single query argument of the url

*Usage*

```js
const foo = getQueryArg( 'https://wordpress.org?foo=bar&bar=baz', 'foo' ); // bar
```

*Parameters*

- *url* `string`: URL.
- *arg* `string`: Query arg name.

*Returns*

- `QueryArgParsed | undefined`: Query arg value.

### getQueryArgs

Returns an object of query arguments of the given URL. If the given URL is invalid or has no querystring, an empty object is returned.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
const foo = getQueryArgs( 'https://wordpress.org?foo=bar&bar=baz' );// { "foo": "bar", "bar": "baz" }
```

*Parameters*

- *url* `string`: URL.

*Returns*

- `QueryArgs`: Query args object.

### getQueryString

Returns the query string part of the URL.

*Usage*

```js
const queryString = getQueryString( 'http://localhost:8080/this/is/a/test?query=true#fragment'); // 'query=true'
```

*Parameters*

- *url* `string`: The full URL.

*Returns*

- `string | void`: The query string part of the URL.

### hasQueryArg

Determines whether the URL contains a given query arg.

*Usage*

```js
const hasBar = hasQueryArg( 'https://wordpress.org?foo=bar&bar=baz', 'bar' ); // true
```

*Parameters*

- *url* `string`: URL.
- *arg* `string`: Query arg name.

*Returns*

- `boolean`: Whether or not the URL contains the query arg.

### isEmail

Determines whether the given string looks like an email.

*Usage*

```js
const isEmail = isEmail( 'hello@wordpress.org' ); // true
```

*Parameters*

- *email* `string`: The string to scrutinise.

*Returns*

- `boolean`: Whether or not it looks like an email.

### isPhoneNumber

Determines whether the given string looks like a phone number.

*Usage*

```js
const isPhoneNumber = isPhoneNumber( '+1 (555) 123-4567' ); // true
```

*Parameters*

- *phoneNumber* `string`: The string to scrutinize.

*Returns*

- `boolean`: Whether or not it looks like a phone number.

### isURL

Determines whether the given string looks like a URL.

*Related*

- [https://url.spec.whatwg.org/](https://url.spec.whatwg.org/)
- [https://url.spec.whatwg.org/#valid-url-string](https://url.spec.whatwg.org/#valid-url-string)

*Usage*

```js
const isURL = isURL( 'https://wordpress.org' ); // true
```

*Parameters*

- *url* `string`: The string to scrutinise.

*Returns*

- `boolean`: Whether or not it looks like a URL.

### isValidAuthority

Checks for invalid characters within the provided authority.

*Usage*

```js
const isValid = isValidAuthority( 'wordpress.org' ); // trueconst isNotValid = isValidAuthority( 'wordpress#org' ); // false
```

*Parameters*

- *authority* `string`: A string containing the URL authority.

*Returns*

- `boolean`: True if the argument contains a valid authority.

### isValidFragment

Checks for invalid characters within the provided fragment.

*Usage*

```js
const isValid = isValidFragment( '#valid-fragment' ); // trueconst isNotValid = isValidFragment( '#invalid-#fragment' ); // false
```

*Parameters*

- *fragment* `string`: The url fragment.

*Returns*

- `boolean`: True if the argument contains a valid fragment.

### isValidPath

Checks for invalid characters within the provided path.

*Usage*

```js
const isValid = isValidPath( 'test/path/' ); // trueconst isNotValid = isValidPath( '/invalid?test/path/' ); // false
```

*Parameters*

- *path* `string`: The URL path.

*Returns*

- `boolean`: True if the argument contains a valid path

### isValidProtocol

Tests if a url protocol is valid.

*Usage*

```js
const isValid = isValidProtocol( 'https:' ); // trueconst isNotValid = isValidProtocol( 'https :' ); // false
```

*Parameters*

- *protocol* `string`: The url protocol.

*Returns*

- `boolean`: True if the argument is a valid protocol (e.g. http:, tel:).

### isValidQueryString

Checks for invalid characters within the provided query string.

*Usage*

```js
const isValid = isValidQueryString( 'query=true&another=false' ); // trueconst isNotValid = isValidQueryString( 'query=true?another=false' ); // false
```

*Parameters*

- *queryString* `string`: The query string.

*Returns*

- `boolean`: True if the argument contains a valid query string.

### normalizePath

Given a path, returns a normalized path where equal query parameter values will be treated as identical, regardless of order they appear in the original text.

*Parameters*

- *path* `string`: Original path.

*Returns*

- `string`: Normalized path.

### prependHTTP

Prepends “http://” to a url, if it looks like something that is meant to be a TLD.

*Usage*

```js
const actualURL = prependHTTP( 'wordpress.org' ); // http://wordpress.org
```

*Parameters*

- *url* `string`: The URL to test.

*Returns*

- `string`: The updated URL.

### prependHTTPS

Prepends “https://” to a url, if it looks like something that is meant to be a TLD.

Note: this will not replace “http://” with “&lt;https://”&gt;.

*Usage*

```js
const actualURL = prependHTTPS( 'wordpress.org' ); // https://wordpress.org
```

*Parameters*

- *url* `string`: The URL to test.

*Returns*

- `string`: The updated URL.

### removeQueryArgs

Removes arguments from the query string of the url

*Usage*

```js
const newUrl = removeQueryArgs( 'https://wordpress.org?foo=bar&bar=baz&baz=foobar', 'foo', 'bar'); // https://wordpress.org?baz=foobar
```

*Parameters*

- *url* `string`: URL.
- *args* `string[]`: Query Args.

*Returns*

- `string`: Updated URL.

### safeDecodeURI

Safely decodes a URI with `decodeURI`. Returns the URI unmodified if `decodeURI` throws an error.

*Usage*

```js
const badUri = safeDecodeURI( '%z' ); // does not throw an Error, simply returns '%z'
```

*Parameters*

- *uri* `string`: URI to decode.

*Returns*

- `string`: Decoded URI if possible.

### safeDecodeURIComponent

Safely decodes a URI component with `decodeURIComponent`. Returns the URI component unmodified if `decodeURIComponent` throws an error.

*Parameters*

- *uriComponent* `string`: URI component to decode.

*Returns*

- `string`: Decoded URI component if possible.

## Contributing to this package

This is an individual package that’s part of the Gutenberg project. The project is organized as a monorepo. It’s made up of multiple self-contained software packages, each with a specific purpose. The packages in this monorepo are published to [npm](https://www.npmjs.com/) and used by [WordPress](https://make.wordpress.org/core/) as well as other software projects.

To find out more about contributing to this package or Gutenberg as a whole, please read the project’s main [contributor guide](https://github.com/WordPress/gutenberg/tree/HEAD/CONTRIBUTING.md).
