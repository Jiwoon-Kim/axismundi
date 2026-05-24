# v3.6.22 Theme Switcher Explicit Auto State - Phase 2 Implementation

## Verdict

Phase 2 implements:

```txt
Route D = JS + CSS + Pilot PHP default
Route E = defer inline head-script / FOUC policy
M2      = edit source + generated styleguide mirror in one reviewed pass
```

BACKLOG #22 is implemented for current Axismundi surfaces and remains pending
Phase 3 / Phase 5 verification before final close.

## Implementation Summary

Changed root-state behavior:

```txt
Before:
  auto -> remove [data-theme]

After:
  auto -> data-theme="auto"
```

Changed dark-auto CSS behavior:

```txt
Before:
  :root:not([data-theme="light"])
  html:not([data-theme="light"])

After:
  :root:not([data-theme]),
  :root[data-theme="auto"]

  html:not([data-theme]),
  html[data-theme="auto"]
```

This preserves current absent-attribute compatibility while preventing future
values such as `sepia`, `dim`, or `high-contrast` from being silently absorbed
into the OS-dark branch.

## Files Changed

JS root-state mutation:

```txt
products/reference-implementations/axismundi-lab/scripts/theme.js
products/reference-implementations/axismundi-lab/scripts/style-guide.js
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
styleguide/scripts/theme.js
styleguide/scripts/style-guide.js
```

CSS cascade:

```txt
products/reference-implementations/axismundi-lab/stylesheets/tokens.sys.dark.css
products/reference-implementations/axismundi-lab/stylesheets/base.css
products/reference-implementations/axismundi-pilot/assets/styles/tokens.sys.dark.css
products/reference-implementations/axismundi-pilot/assets/styles/base.css
styleguide/stylesheets/tokens.sys.dark.css
styleguide/stylesheets/base.css
```

Pilot PHP:

```txt
products/reference-implementations/axismundi-pilot/functions.php
```

Bookkeeping:

```txt
BACKLOG.md
docs/v3.6.22/THEME-SWITCHER-AUTO-STATE-PHASE-2-IMPLEMENTATION.md
```

## JS Changes

All six JS surfaces now write explicit root state:

```js
ROOT.setAttribute("data-theme", mode);
```

or:

```js
html.setAttribute("data-theme", mode);
```

No selector, attribute vocabulary, storage key, or selected-state logic changed.

Preserved contracts:

```txt
.sg-theme          = lab / styleguide / module selector
.ax-theme-switcher = Pilot / future product-facing selector
data-theme-button  = styleguide-local
data-theme-set     = production / module / Pilot
ax-theme / axismundi.theme / axismundi-pilot-theme remain owner-specific
```

Phase 2 amendment note:

```txt
The initial Phase 2 implementation updated the enqueued Pilot copy at
assets/scripts/pilot-block-bridge.js but missed the v3.6.17-declared
authoritative source at bridge/pilot-block-bridge.js.

This amendment restores the v3.6.17 source/copy byte-identical contract.
```

## CSS Changes

The old implicit selector was removed, not commented out.

Reason:

- CSS comments should not preserve obsolete logic as future cleanup debt.
- The audit trail lives in this Phase 2 doc and Git history.
- Removing `:not([data-theme="light"])` is the point of BACKLOG #22: future
  variant values must not fall through into dark mode.

Dark auto selectors now match only:

```txt
absent legacy root state
explicit data-theme="auto"
```

Manual overrides remain:

```txt
data-theme="light" -> light
data-theme="dark"  -> dark
```

## Pilot PHP Default

Added a Pilot-only `language_attributes` filter:

```php
function axismundi_pilot_language_attributes( string $output ) : string {
	if ( is_admin() || false !== strpos( $output, 'data-theme=' ) ) {
		return $output;
	}

	return trim( $output . ' data-theme="auto"' );
}
add_filter( 'language_attributes', 'axismundi_pilot_language_attributes', 20 );
```

Three-layer boundary enforcement:

1. Code comment:

   ```txt
   Pilot-only BACKLOG #22 evidence. Do not copy this filter into distributable
   themes without an explicit distributable skeleton bootstrap decision.
   ```

2. This Phase 2 doc records the filter as Pilot-only evidence.

3. The promoted Pilot/distributable memory remains the guardrail: Pilot is not a
   distributable, and future skeleton root defaults require their own product
   decision.

Editor/admin scope:

```txt
is_admin() returns the original language attributes.
```

Therefore this implementation targets the Pilot front-end root default. It does
not try to control editor preview state and does not create editor UI.

## Inline Head Script Deferred

The older BACKLOG #22 inline head-script note is not implemented.

Reason:

```txt
FOUC budget
CSP / inline-script policy
future distributable first-paint contract
BACKLOG #21 theme-mode runtime boundary
```

These belong to a future product-context / Interpreter Plugin-adjacent decision
if still needed.

## M2 Mirror Handling

M2 was selected:

```txt
source + generated styleguide mirror edited in one reviewed pass
```

Post-edit hash evidence:

```txt
theme.js              6C3777A87D20161E383E5C3EF69EA6B099BB9CD600EEA3EBED8B6AF6E50666EB
style-guide.js        35BE01E1F6F9A48AD75B92FD3F8C30D28DD70319836758094A2F8377B0E67B51
pilot-block-bridge.js E18539729212C96C5F912653B04AC8C31D0A5BC7E257CDBF9D614BEC69925713
tokens.sys.dark.css   3035D5F034BB6FDF87BCCE92F6FC8AE131FD8416C102453530E81329FBE3300C
base.css              BE0F9162CCEE1F37CCB075AFB1DB3458BF11B6D121B81EFEDE4EC4E2348DFBB8
```

The hashes show:

```txt
lab theme.js == styleguide theme.js
lab style-guide.js == styleguide style-guide.js
Pilot bridge source == Pilot enqueued asset copy
lab tokens.sys.dark.css == Pilot tokens.sys.dark.css == styleguide tokens.sys.dark.css
lab base.css == Pilot base.css == styleguide base.css
```

No publish tooling was run in Phase 2.

v3.6.17 contract preserved:

```txt
source: products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js
copy:   products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
status: byte-identical after amendment
```

Future note:

```txt
If publish tooling later rewrites these mirror files differently, route that as
a publish/mirror drift follow-on. It is not needed for this bounded M2 change.
```

## BACKLOG #22 Update

`BACKLOG.md` now records:

```txt
Status: Implemented in v3.6.22 Phase 2; pending Phase 3 / Phase 5 close
```

It also records:

```txt
JS writes explicit data-theme="auto"
CSS uses absent + explicit-auto dark-mode selectors
Pilot front-end defaults root data-theme="auto"
old inline head-script note deferred to product-context / BACKLOG #21 territory
```

Final close still waits for Phase 3 / Phase 5.

## Validation Run

Phase 2 validation:

```txt
node --check products/reference-implementations/axismundi-lab/scripts/theme.js                         PASS
node --check products/reference-implementations/axismundi-lab/scripts/style-guide.js                   PASS
node --check products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js           PASS
node --check products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js   PASS
node --check styleguide/scripts/theme.js                                                               PASS
node --check styleguide/scripts/style-guide.js                                                         PASS
php -l products/reference-implementations/axismundi-pilot/functions.php                                PASS
npm test                                                                                               PASS
  Axis A/B/C/D/E/F/G all 1.000
python tools/generators/build_pilot_specimen_wall.py                                                   PASS
npm run validate:specimen-wall                                                                         PASS
npm run validate:computed                                                                              PASS
git diff --check                                                                                       PASS
```

Generated artifact restore:

```txt
bindings/wordpress-material3/binding_legitimacy_audit.json
bindings/wordpress-material3/pilot_validation_report.md
```

`npm test` rewrote both files; they were restored after validation.

## Phase 3 Verification Needs

Phase 3 should verify browser/runtime behavior:

```txt
lab styleguide catalog auto/light/dark click path
lab module pattern auto/light/dark click path
generated styleguide auto/light/dark click path
Pilot front-end initial root data-theme="auto"
Pilot front-end auto/light/dark click path
console/page errors 0
```

Editor canvas:

```txt
PHP filter is front-end only via is_admin().
Editor preview UI and editor persisted state remain out of scope.
Computed validation already passed in Phase 2; Phase 3 can record that editor
runtime was not intentionally changed.
```

## Lock Compliance

Lock 1:

- Preserved. No `wp-custom`, `settings.custom.axismundi.*`, or `theme.json`
  changes.

Lock 2:

- Preserved. Token values and md-sys / md-ref routes are unchanged; only CSS
  selectors changed.

Lock 3:

- Preserved. Core/button semantic route not reopened.

Lock 4:

- Preserved. Explicit auto-state is implemented through the v3.6.21
  theme-switcher contract, not opportunistic edits.

Lock 5:

- Preserved. Phase 1 diagnostic preceded this Phase 2 implementation.
- If Phase 3 / 5 close cleanly, v3.6.22 should count as the 12th clean
  self-application overall and the 7th implementation-cycle application.

## Review Request

Opus review should answer:

```txt
P1: Any blocker in the Route D + E + M2 implementation?
P2: Is the Pilot-only PHP boundary sufficiently enforced?
P3: Is Phase 3 verification scope sufficient to close BACKLOG #22?
```

Phase 3 must wait for Opus Phase 2 verdict and explicit user Phase 3 execution
GO.
