# WordPress.org assets — not part of the plugin

Everything here is committed to the plugin's **SVN `assets/` folder**, which is a
sibling of `trunk/` and `tags/` and never reaches the plugin zip. `build-zip.ps1`
excludes this directory for that reason; the zip built from this repo should
never contain it.

```
svn                          this repo
assets/blueprints/           wporg-assets/blueprints/
  blueprint.json               blueprint.json
```

## blueprint.json

Powers the Plugin Directory's Live Preview, which runs the plugin in
[WordPress Playground](https://playground.wordpress.net/). Committing a valid
file shows **Test Preview** to committers only; the public button is a second,
separate switch in the plugin's Advanced view, so the demo can be tried before
anyone else sees it.

Three things about it are easy to get wrong.

**It installs this plugin itself.** The preview environment does not inject it.
The handbook's own example carries an `installPlugin` step for the plugin being
previewed, alongside its dependencies, and this file does the same.

**But not in the handbook's spelling.** That example is older than the schema:
it passes the files as `pluginZipFile` and `themeZipFile`, which are now marked
deprecated in favour of `pluginData` and `themeData` -- and the step definitions
require the new names, so the old ones fail validation outright. Playground's
runtime still accepts either, which is the trap: a blueprint written from the
handbook runs perfectly when you test it by hand and is rejected by
WordPress.org, whose only report is "Missing or invalid blueprint.json file".
Validate against the schema before committing, not just by running it.

**So it previews the released version, not this working tree.** The step pulls
`axismundi-theme-switcher` from wordpress.org, which serves whatever `Stable tag`
points at. A demo written against attributes that only exist here renders as
whatever the published version makes of them — unknown attributes are simply
ignored, so it fails quietly. Release first, then enable the preview.

**The theme is a separate install.** The block is a companion to the Axismundi
theme and shows nothing recognisable under a default theme: its colours, shape
and motion all read `--md-sys-*` tokens the theme publishes. The blueprint
installs `axismundi` from the theme directory before the plugin.

The demo page is built by a `runPHP` step rather than imported, so the preview
needs no WXR, no images and no network beyond the two directory downloads.

## Verifying a change

1. Validate it against the published schema, which catches more than a JSON
   parse and is what WordPress.org checks:

   ```
   pip install jsonschema
   curl -sO https://playground.wordpress.net/blueprint-schema.json
   python -c "import json,jsonschema; s=json.load(open('blueprint-schema.json')); jsonschema.validators.validator_for(s)(s).validate(json.load(open('blueprints/blueprint.json')))"
   ```

   Skip `check_schema`: the published schema does not itself meta-validate (a
   `deprecated` key on the virtualize step holds a string where a boolean is
   expected), so a strict check fails on Playground's file rather than ours.
2. Load it in Playground with the URL **WordPress.org** serves it from:

   ```
   https://playground.wordpress.net/?blueprint-url=https://wordpress.org/plugins/wp-json/plugins/v1/plugin/axismundi-theme-switcher/blueprint.json
   ```

   Not the raw SVN path. `plugins.svn.wordpress.org` answers 200 to curl but
   sends no `Access-Control-Allow-Origin`, so the browser cannot read it and
   Playground reports "Blueprint could not be downloaded" -- a CORS failure that
   says nothing about the file. The REST endpoint above is the one the Directory
   itself uses, and it sends the header; it returns `no_blueprint` until
   WordPress.org has picked the commit up, which takes a few minutes.

   A step that fails leaves the site running with that step skipped, so read the
   console rather than trusting the page.
3. Check the demo itself: the theme is active, the front page is the demo page,
   picking a scheme repaints the site, and narrowing the window past 600px
   collapses the last switcher to one button.
4. Commit to SVN `assets/`, confirm through **Test Preview**, and only then set
   the preview public.
