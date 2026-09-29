# Axismundi Capstone

Status: working product architecture, written 2026-09-29.

`Reader` is too narrow a name for the application layer now taking shape. The
working plugin may retain that name while it is small, but its architectural role
is **Capstone**: the React application platform that composes Axismundi's engines
into two products people can use.

This document deliberately does not replace `ARCHITECTURE.md`. That document
describes the initial Reader shell. This one records the larger product boundary
that the shell is growing into.

## One plugin, two applications

Axismundi should own one React runtime boundary, one data-client boundary, one
module registry, and shared application primitives. It does not imply one visual
component library: Frontend and Admin own physically separate component layers.
It exposes two separate applications because their users, permissions, layout
systems, and interaction models differ.

```text
Axismundi
|
|-- Frontend application: /social/
|   |-- Reader: feeds, threads, profiles, object detail, search
|   |-- Composer: future social-object authoring mode
|   `-- mobile/PWA-oriented interaction surface
|
`-- Admin application: wp-admin/admin.php?page=axismundi
    |-- Operations: federation, delivery, moderation, diagnostics
    |-- Resource views: actors, activities, objects, relationships
    `-- Design: styles, templates, template parts, patterns, components, preview
```

The applications share domain contracts, data-client infrastructure, registry
contracts, and nonvisual primitives. They do **not** share a page frame,
visual-component layer, or a design-token source:

```text
Frontend: M3 tokens from the active Axismundi theme
Admin:    WPDS tokens and wp-admin integration
```

Design is the intentional exception to the normal Operations navigation. It is
its own Site Editor-like workspace, with a replacement sidebar for `Styles`,
`Templates`, `Template Parts`, `Patterns`, and `Components`. Each design asset
can host an isolated frontend preview inside the Admin application, analogous to
the Site Editor canvas. The preview is an iframe of the real `/social/` document,
so its runtime, M3 tokens, and stylesheet are identical to the public
application. The surrounding Admin application must not import theme M3 tokens.

The Admin application is both an operations console and an
application-composition environment. It is not a dashboard limited to
operations.

Composer is a future Frontend application mode, not a subordinate Reader
widget. It is deliberately deferred from the initial shell milestone.

## Authoring boundaries

Axismundi does not recreate Gutenberg as a general-purpose frontend Object
Editor. Authoring belongs to the surface that matches the writing experience:

```text
Gutenberg Post Editor      Article and other long-form WordPress documents
Axismundi Frontend Composer Note, reply/comment, poll/question, quote, repost,
                           and lightweight media posts
Axismundi Admin            Object inspection, operations, and recovery
```

An `Article` remains a document-writing workflow: blocks, revisions, drafts,
scheduling, and complex media layout are WordPress Post Editor capabilities.
The Frontend Composer is a social interaction workflow, beginning with text,
mentions, emoji, visibility, content warnings, attachments, reply context, and
poll controls. It will be implemented as its own Frontend surface after the
Reader foundation rather than added to the current shell.

The Frontend Composer may use WordPress attachments, including upload and
selection from the Media Library, but it must not embed Gutenberg or the legacy
`wp.media` interface. It consumes the attachment/API capability and presents a
separate M3 `media-picker` component. The picker is an Axismundi-owned frontend
component that composes Material primitives; the WordPress Media Library remains
the storage and media authority.

## Runtime and stylesheet boundaries

The source tree and build outputs enforce the surface boundary before feature
work grows around it:

```text
src/apps/frontend/       frontend entry and M3-only stylesheet
src/apps/admin/          admin entry and WPDS/@wordpress/ui stylesheet
src/shared/              domain, API, registry, and nonvisual runtime code only
```

Frontend and Admin have independent JavaScript and CSS entry points. Neither
may import the other's visual components or design tokens. `axismundi.*` CSS
layers belong only to the Frontend entry; `axismundi-admin.*` layers belong only
to the Admin entry.

Admin consumes `@wordpress/ui` directly. That library emits its component
styles in the `wp-ui` cascade layer, so Admin establishes this order:

```css
@layer wp-ui, axismundi-admin.tokens, axismundi-admin.components,
	axismundi-admin.compositions, axismundi-admin.utilities;
```

Legacy wp-admin document adjustments remain deliberately unlayered in the
Admin host stylesheet. They are limited to the host boundary, such as removing
the legacy menu or sizing the application root; they do not restyle WPDS
components. The Admin sidebar has one separate, scoped unlayered compatibility
stylesheet because it deliberately uses Site Editor's legacy
`@wordpress/components` `Item` composition, whose styles are also unlayered.
That exception is limited to `.ax-admin-layout__sidebar-region`.

## Engines and Capstone

Actor, Activity, Object, and protocol code are engines and extension providers.
They remain responsible for their own data, invariants, and transport concerns.
Capstone is not a second domain model or a replacement for those plugins. It is
the human-facing composition and operations layer above them.

**Capstone owns application composition, not domain truth.**

```text
Actor / Activity / Object / federation extensions
                    |
                    v
             capability registry
              /                \
             v                  v
    Axismundi applications     NodeInfo / discovery
```

Extensions should register capabilities rather than Capstone probing their
implementation details. A future registration contract can contribute routes,
settings panels, diagnostics, permissions, frontend render capabilities,
templates, template parts, patterns, surface-specific component extensions, and
NodeInfo capability facts. A provider registers Admin UI in the Admin layer and
frontend UI in the Frontend layer; the two surfaces do not import one another's
visual components. The same registry should drive both the operator's view of
an instance and its externally published capability description.

## Two presentation systems

The same local entity can have three representations with different jobs:

```text
Local Actor or wp_post
        |
        |-- Web document: block-theme HTML, canonical and indexable
        |-- App document: /social/ React view, interactive and noindex
        `-- Federation document: ActivityStreams JSON, machine-facing
```

The React application never consumes a block theme's rendered HTML as its
content model. Local and remote Actors and Objects enter it as ActivityStreams
or normalized JSON representations. React then composes the application view.

A local `wp_post` is projected as an `Article` or another object JSON
representation before entering the React application. It does not travel
through the block-theme renderer on its way to the app.

This creates local/remote presentation parity without forcing the block-theme
web document and the social application to be the same renderer.

`/social/*` is the application namespace. It explicitly emits `noindex,follow`;
React rendering is not an indexing policy. Routes with a matching web document
may additionally point to that document as canonical. The block-theme page
remains the public web and SEO surface.

## App composition

The application mirrors Gutenberg's compositional vocabulary, but its inputs are
JSON object representations rather than a block tree.

```text
Template        complete app route
Template part   named persistent region
Pattern         reusable composition
M3 component    reusable interactive or visual unit
```

Initial examples:

```text
Template:       profile, feed, single object, thread
Template part:  top app bar, navigation rail, navigation bar, profile header
Pattern:        object card, action cluster, metadata group, empty state
M3 component:   button, icon button, avatar, chip, card, tabs, dialog
```

M3 is the fixed design system, so its component vocabulary is a first-class part
of the library. Composition role still remains distinct from component type: a
Top App Bar is an M3 component and can be registered as a template part; a Button
is an M3 component and can be registered as a block-like leaf.

Each library item will use colocated `component.json` metadata. That metadata is
for Capstone's registry, Design component catalogue, fixtures, and build checks. It is
not a WordPress `block.json` replacement and does not duplicate runtime props.

## Component provenance

Component authority depends on its provenance:

| Provenance | Scope | Normative source | Axismundi responsibility |
| --- | --- | --- | --- |
| Material 3 | Frontend | Material Design 3 guidance | Implement the component faithfully for the runtime. |
| Axismundi custom | Frontend | Axismundi specification | Define the component and compose Material primitives where appropriate. |
| WPDS | Admin | WordPress Design System | Consume WPDS directly; keep any Admin-specific additions in the Admin app. |

The Frontend keeps Material and Axismundi components physically separate:

```text
src/apps/frontend/components/
|-- material/
|   |-- button/
|   |-- dialog/
|   |-- tabs/
|   `-- ...
`-- axismundi/
    |-- emoji-picker/
    |-- actor-card/
    |-- object-actions/
    `-- ...
```

This is a physical source boundary, not a committed npm package boundary. The
library may remain inside the plugin until independent consumption or a separate
build requires extraction.

Directory placement follows the owner of a component's public contract, not the
primitive it happens to compose. For example, an `actor-card` belongs to
`axismundi/` even when it uses a Material `Card` internally. Dependencies flow
one way: Axismundi frontend components may import Material components; Material
components must not import Axismundi frontend components. Any Admin-specific
components are physically isolated in the Admin application.

The Material 3 specification is authoritative for core frontend components such
as `Button`, `Dialog`, `Tabs`, and navigation components. Axismundi's React
implementation is the runtime source of truth, but it does not redefine the
component contract.

Axismundi owns the specification for product-specific frontend components such
as an emoji picker, actor card, object actions, or federation status. Those
components remain compatible with Material interaction, accessibility, motion,
and token guidance while composing Material primitives where they fit.

Axismundi-owned library items use `component.json` to record provenance and
target surface. Frontend provenance is `material` or `axismundi`.
Admin-specific wrappers around WPDS may use `wpds` provenance when such wrappers
exist; directly consumed WPDS components do not require Axismundi catalogue
metadata. Templates, template parts, and patterns are Axismundi-owned
composition; the primitives inside them retain the authority of their
originating design system.

## Gutenberg relationship

Capstone is a sibling application layer, not a fork of Gutenberg:

```text
Gutenberg                         Axismundi
---------                         ---------
Document authoring / Post Editor  Social authoring / Frontend Composer
Site Editor                       Capstone admin application
Post / block tree                 Actor or Object JSON representation
Block theme template              React app template
Site Editor canvas                Design frontend preview
```

Gutenberg remains the editor for WordPress documents and block-theme web
presentation. Capstone provides social composition, application presentation,
and instance operations; its Frontend Composer complements rather than replaces
the Post Editor.

Axismundi Theme owns Web presentation. The Axismundi plugin owns application
and runtime presentation. Their shared brand denotes a coordinated platform,
not shared rendering code.

## Deferred naming and migration

The plugin is named **Axismundi**. `Capstone` remains the internal architectural
name for its integration and composition layer, while Reader remains a frontend
consumption surface. Future changes to the public plugin slug, activation path,
or user-facing routes remain dedicated migrations.
