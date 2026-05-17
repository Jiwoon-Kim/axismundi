# Button — WordPress Block Mapping (v3.5.1 Phase 1)

> **Bucket**: E (Component module — WordPress binding audit)
> **Status**: Phase 1 audit body — supersedes Phase 1 skeleton.
> **Charter**: `CONSTITUTION.md` Article 2 (Platform ≠ Design system ≠ Federation), Article 7 (Products consume the layers), Article 12 (Publishing surfaces are mirrors); `docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md §3.4` (theme can / plugin should).
> **Strategy reference**: `bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md`
> **Reference template**: `lab/modules/chip/docs/CHIP-WP-MAPPING.md` (v3.4.9 — 209 lines)
> **Companions**: `./BUTTON-SPEC-AUDIT.md`, `./BUTTON-MEASUREMENT-AUDIT.md`

## §1 — Critical framing

This document maps the Axismundi Button primitive to its WordPress / Gutenberg surfaces. Four questions guide each mapping:

1. **Where does a button naturally appear in a WordPress site?** (theme contexts)
2. **Which WordPress core blocks render button-shaped content?** (block contexts — `core/button` + `core/buttons`)
3. **How do we register block style variations that map to `.ax-button.is-*`?** (theme.json / `register_block_style()` paths)
4. **Where does button rendering cross the Charter §3.4 theme / plugin boundary?** (mapping limits — form submission, federation actions)

The audit is descriptive, not prescriptive. It records the mapping as it stands today; it does not register block styles or modify `theme.json` at v3.5.1. Phase 2 may add a minimal `register_block_style()` demonstration in `lab-button-pattern.html` as a code snippet (not as live theme registration).

Button is distinct from Chip in WordPress mapping: Chip has no direct core/* block analog (uses `core/tag-cloud` block style); Button has the dedicated `core/button` + `core/buttons` block pair. This makes Button's WordPress mapping the cleanest and most natural of any component in the system.

한글:

```
이 문서는 Axismundi Button primitive를 WordPress / Gutenberg surface에
mapping한다. 네 가지 질문이 mapping을 가이드한다:
  1. WordPress 사이트에서 button이 자연스럽게 어디 나타나는가?
  2. 어떤 core block이 button 모양 콘텐츠를 렌더링하는가? (core/button +
     core/buttons)
  3. .ax-button.is-* 와 mapping되는 block style variation을 어떻게 등록하는가?
  4. Button rendering이 theme / plugin 경계를 어디서 넘는가? (form submit,
     federation action 등)

이 audit는 서술적이지 처방적이지 않다. v3.5.1에서 block style을 등록하거나
theme.json을 수정하지 않는다. Phase 2가 lab-button-pattern.html에 코드 예시로
register_block_style() snippet을 추가할 수 있다(실 등록은 아님).
```

## §2 — Charter §3.4 application

```
Theme can:
  - Render <button class="ax-button is-*"> surfaces via .ax-button + 5 variants
  - Register WordPress block style variations on core/button that map to
    Filled / Tonal / Elevated / Outlined / Text
  - Provide visual states (rest / hover / focus-visible / pressed / disabled)
  - Provide token bridge via theme.json --wp--preset--* (color / typography)

Theme should NOT:
  - Implement form submission, validation, or AJAX (plugin territory)
  - Add custom button blocks for plugin behavior (plugin territory)
  - Encode form / data behavior in functions.php (plugin territory)
  - Implement federation action handling (federation plugin territory)

Plugin should:
  - Handle form submission, validation, error state messaging
  - Manage form state (loading / success / error transitions)
  - Provide accessibility announcements (aria-live regions for async results)
  - Implement federation action behaviors (Follow / Like / Boost button
    semantics for ActivityPub composer)
  - Add custom blocks ONLY if the use case requires plugin-controlled
    save markup (e.g., dynamic faceted filter UI)
```

The Button primitive is firmly on the theme side. What the button *triggers* (form submit, AJAX action, federation action) is plugin / integration territory. This is the cleanest theme / plugin split of any Wave 1 component — Button is purely a surface; behavior crosses the boundary.

## §3 — WordPress core block context inventory

The primary mapping is `core/button` + `core/buttons`. Each row records:

- **Block**: the core block name
- **Current relationship**: how Button rendering relates to the block today
- **Mapping action at v3.5.1**: what (if anything) this audit recommends

| Core block | Current relationship | Mapping action |
|---|---|---|
| `core/button` | Native button block. Renders `<a class="wp-block-button__link …">` or `<button>` depending on link/no-link. Block style variations map cleanly to `.ax-button.is-filled / .is-tonal / .is-elevated / .is-outlined / .is-text`. | **Primary mapping** — declarative recommendation: register 5 block styles (`Filled`, `Tonal`, `Elevated`, `Outlined`, `Text`) via theme.json `styles.blocks` or `register_block_style()`. v3.5.1 records the recommendation; does NOT execute it (no theme.json edits at v3.5.1 per Phase 0 §10). |
| `core/buttons` | Container block for multiple buttons. Provides flex layout. Already provides the row layout that Button needs. | **No mapping action** — `core/buttons` handles layout; individual `core/button` children map to `.ax-button.is-*`. |
| `core/group` | Generic container. Button groups (action row, toolbar) often live inside `core/group` for additional layout control. | **No mapping action** — composition concern; not Button-specific. |
| `core/heading` + button below | Pattern context (call-to-action). Composition of `core/heading` + `core/paragraph` + `core/buttons`. | **Pattern composition only** — handled by block patterns, not Button mapping. |
| `core/form` (if used) | Native form block (limited adoption). Submit button typically uses `core/button` with form action. | **Indirect mapping** — `core/button` inside `core/form` still maps via the same block style variations. Form behavior is plugin territory (§2 above). |
| `core/post-comments-form` | Comment form. Submit button is a `<button>` rendered by core. | **Indirect mapping** — theme CSS may target `.comment-form .submit` to apply `.ax-button.is-filled` styling. Recorded declaratively; no theme.json action at v3.5.1. |
| `core/search` | Search form. Submit affordance is an icon button (handled by Icon button Wave 1 #2, not Button #1). | **Out of Button #1 scope** — handled in Icon button audit. |
| `core/navigation` | Nav menu. Nav items are links, not buttons. | **No Button mapping** — nav surfaces are M3 navigation patterns (top-app-bar, navigation-bar, etc.), not Button. |

## §4 — Theme-side Button rendering paths

Three concrete paths a theme can take to render button-shaped content. Paths A and B are recommended; Path C is plugin territory.

### §4.1 Path A — Block style variation on `core/button` (recommended primary path)

WordPress `register_block_style()` lets a theme register a CSS class to apply to a block. This is the cleanest theme-side Button surface:

```php
/* In functions.php — NOT added at v3.5.1; example only */
register_block_style(
    'core/button',
    array(
        'name'  => 'filled',
        'label' => __( 'Filled', 'axismundi' ),
    )
);
register_block_style(
    'core/button',
    array(
        'name'  => 'tonal',
        'label' => __( 'Tonal', 'axismundi' ),
    )
);
register_block_style(
    'core/button',
    array(
        'name'  => 'elevated',
        'label' => __( 'Elevated', 'axismundi' ),
    )
);
register_block_style(
    'core/button',
    array(
        'name'  => 'outlined',
        'label' => __( 'Outlined', 'axismundi' ),
    )
);
register_block_style(
    'core/button',
    array(
        'name'  => 'text',
        'label' => __( 'Text', 'axismundi' ),
    )
);
```

Then in theme CSS bridge:

```css
.wp-block-button.is-style-filled   .wp-block-button__link { /* apply .ax-button.is-filled */ }
.wp-block-button.is-style-tonal    .wp-block-button__link { /* apply .ax-button.is-tonal  */ }
.wp-block-button.is-style-elevated .wp-block-button__link { /* apply .ax-button.is-elevated */ }
.wp-block-button.is-style-outlined .wp-block-button__link { /* apply .ax-button.is-outlined */ }
.wp-block-button.is-style-text     .wp-block-button__link { /* apply .ax-button.is-text */ }
```

**Default block style**: `Filled` (M3 highest-emphasis default).

**Pros**:

- No save markup change; uses native WordPress block style variation mechanism.
- Block style picker is exposed in the editor inspector.
- Reversible (user can unset / switch via the inspector).
- 5 variants map 1:1 to baseline `.ax-button.is-*` classes.

**Cons**:

- `core/button` renders `<a>` (link button) or `<button>` (form submit) depending on its `linkRel` / `linkTarget` settings. Theme CSS must target both shapes.
- Block style applies to all instances uniformly; per-button overrides require additional block style variations or pattern composition.

**v3.5.1 action**: `lab-button-pattern.html` (Phase 2 deliverable) includes a section demonstrating this pattern as a code snippet. The actual `register_block_style()` calls are NOT added to baseline `functions.php`. Promotion to baseline is a separate CHARTER decision and a separate release.

### §4.2 Path B — Pattern-based composition

WordPress block patterns are pre-composed block arrangements that authors can insert as a starting point. A "Call-to-action row" pattern could compose `core/heading` + `core/paragraph` + `core/buttons` with each `core/button` pre-styled.

**Pros**:

- Author retains full control; buttons are real `core/button` blocks.
- Patterns can encode design intent (e.g., "primary CTA + secondary CTA") via pre-selected block styles.
- Reusable patterns reduce duplication across themes.

**Cons**:

- Each button is a separate block; updating one doesn't update siblings.
- Patterns are theme-distribution-safe but require maintenance when block styles change.

**v3.5.1 action**: Documentation only. Pattern authoring is part of the Pilot Block Theme Probe (separate ROADMAP item — not a Button-specific concern).

### §4.3 Path C — Custom block (plugin territory)

For dynamic button surfaces (live form submission with loading state, federation action buttons, faceted filter clear-all), a custom block is more appropriate. This crosses into plugin territory.

**Pros**:

- Full data sourcing, editor UI, save format under plugin control.
- Plugin can hook into the M3 Interpreter Plugin (BACKLOG #21) for dynamic style binding.

**Cons**:

- Plugin dependency.
- Not theme-distribution-safe (plugin must be installed alongside theme).

**v3.5.1 action**: Out of scope. Recorded in BACKLOG #21 (Interpreter Plugin) scope.

## §5 — Form submission boundary (theme-can / plugin-should)

The most important Charter §3.4 application for Button is form submission. Detailed split:

```
Theme can (Button surface):
  - Render <button type="submit" class="ax-button is-filled">Submit</button>
  - Provide visual states: rest / hover / focus-visible / pressed / disabled
  - Apply disabled state when JS sets aria-disabled="true" (e.g., during
    async submission)

Plugin should (form behavior):
  - Validate form fields client-side and server-side
  - Submit form data (AJAX or full-page POST)
  - Manage submission state (loading / success / error transitions)
  - Provide accessibility announcements (aria-live="polite" or
    aria-live="assertive" for async result regions)
  - Persist applied filter state for faceted search forms
  - Handle CSRF / nonce / authentication

Boundary: Button is the SURFACE; form is the BEHAVIOR. They compose
          but neither owns the other. Theme provides visual button;
          plugin provides form mechanics.
```

This means:

- A WordPress contact form plugin (Contact Form 7, Gravity Forms, etc.) can use `<button class="ax-button is-filled">` as its submit affordance — theme provides the look, plugin provides the submission.
- WooCommerce can use `.ax-button.is-filled` for "Add to Cart" — same boundary.
- A custom federation action block (ActivityPub plugin) can use `.ax-button.is-tonal` for "Follow" — surface-only theme dependency; plugin owns the federation action.

## §6 — theme.json contract (current state at v3.5.1)

The Axismundi pilot's `theme.json` does NOT currently expose Button-specific tokens to the editor UI. Button styling resolves entirely through CSS class application in `components.css §2`. Specifically:

- No Button-specific palette slugs in `settings.color.palette` (Button uses generic `primary` / `on-primary` / `secondary-container` / `surface-container-low` slugs)
- No Button-specific typography slugs (Button uses `label-large` typescale)
- No Button-specific spacing or shape slugs (Button uses `--space-md` / `--space-sm` and `corner-full` / `corner-small` shape tokens)

This is the **correct** binding posture per `bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md §2`:

```
theme.json = Gutenberg UI contract layer (slug registry)
tokens.css = actual design system runtime
--wp--preset--color--* = bridge between them
```

Button is a downstream consumer of the color / typography / shape / motion token graph, not a token producer. There is nothing to add to `theme.json` for v3.5.1.

**What v3.5.1 records (declaratively, not implemented)**:

- Recommended theme.json `styles.blocks["core/button"].variations` entries (5: filled / tonal / elevated / outlined / text)
- Recommended block style registrations (5 `register_block_style()` calls — see §4.1)

These are RECOMMENDATIONS recorded in this audit. They are NOT executed in v3.5.1. Execution belongs to a future theme integration release or to the M3 Interpreter Plugin (BACKLOG #21).

## §7 — Anti-pattern inventory

These patterns are explicitly NOT recommended:

| Anti-pattern | Why avoid |
|---|---|
| **Hardcoded button literals** (color, height, padding) in block templates or block patterns | MUST use `.ax-button.is-*` + theme tokens. Hardcoded literals bypass the token graph and break theme switching. |
| **Custom button block reinventing variants** | A plugin / theme adding a `my-custom-button` block with its own `.is-cta` / `.is-action` variants creates parallel surfaces. Use the same `.ax-button.is-*` surface; if a new variant is needed, request a Wave addition. |
| **Button as form-submit behavior owner** | Form behavior belongs to plugin / integration. Theme handling form validation, AJAX, or persistence violates Charter §3.4. Use `<button type="submit" class="ax-button is-filled">` with plugin form handler. |
| **`<a>` styled as a button without `role="button"`** | Anti-pattern from pre-Gutenberg WordPress themes. `<a>` is for navigation; `<button>` is for actions. Don't conflate. (`core/button` block correctly produces `<a>` only when a `href` is configured — that's navigation, not a button-as-action.) |
| **`<div role="button">` styled like `.ax-button`** | Accessibility tree violation. Real `<button>` elements get keyboard activation, focus management, and AT semantics for free. Never use `<div>` or `<span>` as a button surface. |
| **Icon-only button using `core/button`** | `core/button` is for label-bearing buttons. For icon-only buttons, use `core/icon-button` (if/when added) or a custom Icon button block. Icon button Wave 1 #2 covers this; do not work around it by using `core/button` with hidden label. |
| **Hardcoded chip rendering inside Button block** | Don't render chips inside button surfaces; they are distinct components. M3 Button and M3 Chip have separate roles. Compose at the pattern level, not within a single block render. |
| **Direct DOM manipulation of `.ax-button`** (e.g., adding `has-state-layer` class via JS at runtime in a block render) | `has-state-layer` should be in template markup, not runtime-applied. JS-applied class changes that affect visual state break Static Visual QA Gate (Phase 3 deliverable) and reduce reproducibility. |
| **Gutenberg color picker exposed without M3 interpreter bridge** | Violates "Visible control must map to real runtime behavior" (BACKLOG #20). Until BACKLOG #21 Interpreter Plugin lands, `settings.color.custom = false` is the honest default for Button-affecting color settings. |
| **Button-rendered `core/post-author` or `core/post-date`** | Author / date are metadata, not actions. Don't button-wrap them. If a button-shaped action is needed adjacent (e.g., "Follow Author"), that's a separate action button. |

## §8 — ActivityPub federation note (project-specific)

Axismundi is a WordPress block theme + ActivityPub microblog. Button surfaces appear in:

- **Editor**: block toolbar (admin-only — buttons within the editor UI are not part of public Button rendering)
- **Front-end (self-hosted view)**: post / page rendering — Button block instances rendered as `.ax-button.is-*`
- **Front-end (federated view)**: posts rendered by other ActivityPub instances (Mastodon, Misskey, Pleroma) — these strip most CSS
- **Reader apps**: Pocket / Instapaper / RSS-style readers strip CSS entirely

Front-end Button MUST render correctly across all four:

| Surface | Rendering quality | Notes |
|---|---|---|
| Self-hosted WP front-end | ✓ Full | All 5 variants render with full visual fidelity |
| ActivityPub-federated views | Graceful degradation | `<button>` semantics survive; visual styling lost; label / text remains accessible |
| Reader apps | Plain `<button>` text | HTML semantics preserved; no visual button rendering |
| Screen reader / AT | Full semantic role | `<button type="…">` always announces as "button" to AT regardless of CSS |

Button's HTML semantics (`<button type="button">` for actions; `<button type="submit">` for form submission; `<a>` for navigation) ensure ATs and federated readers handle it correctly without CSS.

**Federation-specific buttons** (Follow / Like / Boost / Reply):

These belong in a future ActivityPub composer / viewer plugin, not in baseline Button. They are surface-only theme consumers of `.ax-button.is-*` (visual layer), with plugin-side federation action behavior. Out of v3.5.1 scope.

## §9 — Plugin surface index (out of scope for v3.5.1)

Future plugin / integration work that consumes the Button primitive:

| Plugin surface | Description | BACKLOG ref |
|---|---|---|
| Contact form plugin (Submit) | Form submit button surface | BACKLOG #21 (Interpreter) coverage; otherwise existing plugin-side |
| WooCommerce (Add to Cart / Checkout) | Commerce action button surfaces | BACKLOG #21 |
| Comment form (Submit comment) | Native `core/post-comments-form` submit button | BACKLOG #21 (theme CSS bridge already possible) |
| Faceted search filter (Apply / Clear) | Custom block rendering applied filter actions | BACKLOG #21 |
| Pagination (Previous / Next page) | Native nav links — may render as Button per pattern decision | Future |
| ActivityPub composer (Post / Reply / Boost / Like / Follow) | Federation action buttons | Future federation work, not yet on BACKLOG |
| WordPress admin notices (Action buttons) | Admin-side surfaces — NOT public Button territory | Out of theme scope |
| BuddyPress / membership plugins (Join / Leave / etc.) | Community action buttons | BACKLOG #21 |

None of these are v3.5.1 work. The mapping audit records them so that future plugin scope discussions can refer back to a single map.

## §10 — Theme.json declarative recommendation (recorded; not executed)

For future theme integration release, the recommended `theme.json` additions for Button:

```jsonc
{
  "version": 3,
  "styles": {
    "blocks": {
      "core/button": {
        // (no Button-specific token additions; uses global color/typography slugs)
      }
    }
  }
}
```

And `functions.php` (or `setup.php`) for block style registration:

```php
add_action( 'after_setup_theme', 'axismundi_register_button_block_styles' );
function axismundi_register_button_block_styles() {
    $variants = array(
        'filled'   => __( 'Filled',   'axismundi' ),
        'tonal'    => __( 'Tonal',    'axismundi' ),
        'elevated' => __( 'Elevated', 'axismundi' ),
        'outlined' => __( 'Outlined', 'axismundi' ),
        'text'     => __( 'Text',     'axismundi' ),
    );
    foreach ( $variants as $name => $label ) {
        register_block_style(
            'core/button',
            array(
                'name'  => $name,
                'label' => $label,
            )
        );
    }
}
```

And theme CSS bridge (in `theme.css` or equivalent — separate from baseline `components.css`):

```css
.wp-block-button.is-style-filled   > .wp-block-button__link,
.wp-block-button.is-style-tonal    > .wp-block-button__link,
.wp-block-button.is-style-elevated > .wp-block-button__link,
.wp-block-button.is-style-outlined > .wp-block-button__link,
.wp-block-button.is-style-text     > .wp-block-button__link {
  /* Common reset — let .ax-button.is-* take over */
  background: transparent;
  border: 0;
  padding: 0;
  color: inherit;
  font: inherit;
}
.wp-block-button.is-style-filled   > .wp-block-button__link { /* apply .ax-button + .is-filled styles */ }
.wp-block-button.is-style-tonal    > .wp-block-button__link { /* apply .ax-button + .is-tonal */ }
.wp-block-button.is-style-elevated > .wp-block-button__link { /* apply .ax-button + .is-elevated */ }
.wp-block-button.is-style-outlined > .wp-block-button__link { /* apply .ax-button + .is-outlined */ }
.wp-block-button.is-style-text     > .wp-block-button__link { /* apply .ax-button + .is-text */ }
```

**Note**: A cleaner approach is to add the `.ax-button` and `.is-*` classes directly to the block via a filter (`render_block_core/button`) rather than duplicate the styling. That decision belongs to the theme integration release, not Phase 1.

## §11 — Mapping verdict

Phase 1 specific assessment:

| # | Criterion | Status | Notes |
|---:|---|:---:|---|
| 1 | **`core/button` mapping completeness** | ✓ PASS | 5 variants × 5 block style names recorded; Path A primary recommendation documented; theme.json declarative recommendation in §10 |
| 2 | **Three rendering paths documented** | ✓ PASS | Path A (block style) + Path B (pattern) + Path C (custom block / plugin) documented in §4 with pros/cons |
| 3 | **Anti-pattern inventory complete** | ✓ PASS | 10 anti-patterns recorded in §7 with rationale per row |
| 4 | **Theme-can / plugin-should boundary** | ✓ PASS | §2 + §5 clearly separate Button surface (theme) from form behavior (plugin); Charter §3.4 applied |
| 5 | **ActivityPub federation considered** | ✓ PASS | §8 enumerates 4 federation surfaces with rendering quality per surface |
| 6 | **No baseline changes** | ✓ PASS | `components.css §2`, `style-guide.html`, `theme.json`, `functions.php` all unchanged at v3.5.1 |

### Verdict

```
Phase 1 WP-MAPPING audit: PASS on all 6 mapping-specific criteria.

The audit confirms that Button rendering belongs cleanly on the theme
side via block style variations on core/button, with form behavior
belonging to plugin / integration territory. theme.json requires no
Button-specific additions at v3.5.1. Ten anti-patterns are documented
to prevent boundary violations in future work. The mapping audit
establishes the template for future Component module -WP-MAPPING
docs in the button family (Icon button, FAB).
```

한글:

```
Phase 1 WP-MAPPING audit는 6개 mapping 기준에서 모두 PASS다.

Button rendering은 core/button block style variation을 통해 theme side에
깔끔하게 위치하며, form behavior는 plugin / integration territory에 속한다.
theme.json은 v3.5.1에서 Button-specific 추가가 필요 없다. 10개 anti-pattern이
기록되어 향후 작업의 boundary 위반을 방지하며, 이 mapping audit이 button
family의 후속 Component module -WP-MAPPING.md 템플릿이 된다(Icon button, FAB).
```

## §12 — Cross-references

```
Phase 0:    docs/v3.5.1/BUTTON-PHASE-0-REPORT.md §6  (WP block-style mapping candidates)

Companion:  ./BUTTON-SPEC-AUDIT.md §2                (baseline / module split)
                                  §7                (dependencies)
                                  §9                (out of scope — plugin / federation)
            ./BUTTON-MEASUREMENT-AUDIT.md            (M3 §4 dimensions)

Strategy:   bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md  (theme.json binding posture)

Framework:  docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md §3.4  (theme can / plugin should)
            docs/v3.5.0/PROMOTION-CRITERIA.md §4.5      (Plugin-territory category)
            docs/v3.5.0/MODULE-STATUS-MATRIX.md row #1  (Button)

Constitution: CONSTITUTION.md Article 2  (Platform ≠ Design system ≠ Federation)
              CONSTITUTION.md Article 7  (Products consume the layers)
              CONSTITUTION.md Article 12 (Publishing surfaces are mirrors)

Baseline:   components.css §2 Button                  L122–L234
            style-guide.html #components-button       L624–L693
            theme.json                                 (untouched at v3.5.1)

Template:   lab/modules/chip/docs/CHIP-WP-MAPPING.md  (v3.4.9 — 209 lines)

BACKLOG:    #18  Snackbar naming sweep (v3.5.x)
            #20  Theme-only color customization policy (v3.5.x)
            #21  M3 Interpreter Plugin (future plugin scope)
            #22  data-theme=auto 3-state (v3.5.x)
            #24  Matrix consumer-state column (v3.5.x)
            #25  Ripple v2 contract (v3.5.x)

WP docs:    https://developer.wordpress.org/themes/getting-started/glossary/#block-style
            https://developer.wordpress.org/reference/functions/register_block_style/
            https://developer.wordpress.org/themes/global-settings-and-styles/styles/blocks/
WCAG:       (WCAG references live in BUTTON-MEASUREMENT-AUDIT.md §4 + §9)
ActivityPub: https://www.w3.org/TR/activitypub/  (federation reference)
```

## §13 — What this mapping audit does NOT do

- Does not modify `theme.json`.
- Does not call `register_block_style()` in baseline `functions.php`.
- Does not author block patterns (Pilot Block Theme Probe scope, separate ROADMAP item).
- Does not implement any plugin code (BACKLOG #21).
- Does not pre-decide v3.5.x+ Interpreter Plugin architecture (BACKLOG #21).
- Does not decide between Gutenberg color customization modes (BACKLOG #20).
- Does not implement form submission behavior (plugin territory per Charter §3.4 + §5 above).
- Does not address Button rendering in ActivityPub composer or federation viewer (out of v3.5.1 scope; future federation work).
- Does not address admin-side button surfaces (admin UI is out of theme public scope).
- Does not address Icon button mapping (Icon button Wave 1 #2 covers it).
- Does not address FAB / Extended FAB mapping (separate Wave 1+ items).
- Does not generate M3 tonal palette colors (BACKLOG #21 Interpreter Plugin scope).
- Does not modify `components.css §2 Button` baseline.
- Does not modify `style-guide.html #components-button` specimens.
