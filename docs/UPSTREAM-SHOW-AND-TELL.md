# Draft — Gutenberg Discussion, Show and tell

> Status: draft, not posted. Category **Show and tell** ("Show off something
> you've made").
>
> Rule for this one: it shows a thing and says what building it taught. It makes
> no API proposal — the two API-shaped questions already have threads, and this
> links to them rather than restating them.
>
> Facts below were read from the plugin source at 0.1.9, not from memory.

---

## Title

A colour-scheme switcher as a Block Directory plugin, and what composing it out
of five Core blocks' habits taught me

## Body

I have been building a light / dark / auto switcher as a block plugin, and it is
now in the Block Directory with a Playground preview, so it can be opened rather
than described:

- Plugin: <https://wordpress.org/plugins/axismundi-theme-switcher/>
- Live preview: the **Live Preview** button on that page boots a Playground site
  with the companion theme and a demo page already built.

I am posting it here because the interesting part turned out not to be the
feature. It is that almost every decision in it had a Core precedent to copy,
and copying them well is most of what the block is.

### What it does

One block, `axismundi/theme-switcher`. It writes `html[data-theme]`, keeps the
choice in a first-party cookie, and reapplies it before the next page paints so
returning to the site does not flash the wrong scheme. Rendering is server-side;
the front end runs on the Interactivity API; the editor preview stays in step
with the front end through a small bridge.

It renders one of two surfaces:

- a **connected button group** — Auto / Light / Dark, the active one filled
- a **single cycling button** that advances through the three

### The five habits it borrowed

| from | what was borrowed |
| --- | --- |
| `core/navigation` | `overlayMenu`'s three answers — `off` / `mobile` / `always` — as the shape for "how far does this control compress" |
| `core/buttons` | a flex layout with justification, and `align: wide, full` |
| `core/button` | block style variations as the colour axis: Filled, Tonal, Outlined |
| `core/social-links` | an icon-only control whose accessible name is a visually hidden text node rather than an `aria-label` |
| `core/icon` | the question of where a symbol comes from at all |

The `core/navigation` one is the borrowing I am most glad about. My first
version made the cycling button a block *style*, which was wrong: the two
surfaces are different controls — three buttons that select versus one that
advances, with different keyboard models — and a style variation cannot change
which control renders. `overlayMenu` had already answered exactly that, so the
setting became `cycleButtonVisibility: off | mobile | always`, and at `mobile`
both surfaces render with a media query showing one, the way Navigation renders
its overlay alongside the inline menu.

The breakpoint for that query comes from the active theme:
`wp_get_global_settings()['viewport']` through
`WP_Theme_JSON::get_viewport_media_queries()`, new in 7.1. A theme that declares
nothing gets WordPress's own default. The block never names a pixel value.

### The settings, and why there are only five

`cycleButtonVisibility`, `size`, `showLabels`, `showTooltips`,
`cycleButtonStandard`. They sit in one ToolsPanel, and each appears only where it
can do something — with `off` there is no cycling button, so its two settings are
absent; with labels shown there is nothing for a tooltip to say, so that one is
absent too. The list is a prefix of the next as the visibility widens, which
turned out to be a nice property to aim for.

`size` covers Material's five container heights. The two surfaces share only that
height — an icon button and a button with no label are different components with
different padding, icon sizes and corner values — so one setting picks the height
and each surface takes the rest from its own table.

### Tooltips, which were the hardest part

A tooltip only appears where a control has no visible name, which here is the
cycling button and the group's segments when labels are off. The runtime is about
250 lines of vanilla JS, one element per document, moved rather than duplicated.

The accessibility rule I followed is the one the newer Core Tooltip states: the
popup is **visual only**. It carries `aria-hidden`, has no `role="tooltip"`, and
nothing points at it. The button's own accessible name already says what the
tooltip says. Its text is read from the trigger's screen-reader text at open
time, so there is one source for the string and the cycling button's tooltip
follows the live scheme without holding any state.

The behaviour is Material's: 700ms before the first one, none for the next one in
the same switcher, 1.5s of linger after the pointer leaves, and — the part I had
missed — **tap and hold** on touch, with the click that would otherwise follow
swallowed, because a hold is not a tap.

### Three things I got wrong, in case they are useful

**The editor is a different document.** Three separate bugs came from forgetting
it. Switchers on one page disagreed with each other because each held its own
state instead of subscribing to one signal. The block vanished under Mobile
device preview because the canvas iframe really is that width, so the breakpoint
query fired and hid a surface the editor had not rendered. And Justification did
nothing in the canvas because `layout` support hands its class names to the
inner-blocks wrapper, which a block with no inner blocks never collects — the
front end was fine the whole time, because `get_block_wrapper_attributes()` adds
them regardless.

**`data-wp-bind` is processed on the server too**, and a binding the server
cannot resolve *removes* the attribute rather than leaving the authored value.
With no `wp_interactivity_state()` registered, the delivered page carried a
button with no icon, no accessible name and no scheme, all of it appearing only
after hydration.

**Touch feedback is three separate things.** The browser's own tap highlight, a
`:focus` state layer left behind because a tap focuses too, and a hover layer
that sticks to the last thing tapped. I fixed them one at a time, each time
thinking it was the last.

### What it taught me about the boundary

The block ended up with a clean split, and it was not planned — it fell out of
making the thing work under a theme other than its own.

Everything about *behaviour* is the block's: the three states, persistence, the
early restore before paint, the accessible names, which surface renders at which
width, the keyboard and touch contracts. None of that is visual and none of it
varies by theme.

Everything about *representation* is the theme's: the colours, corner sizes and
motion come from `--md-sys-*` custom properties with baseline fallbacks, so the
block survives a foreign theme.

Except the icons. Those are still ligature text against a font the companion
theme loads, which means under any other theme the control reads `light_mode`
instead of showing a sun. That is the one place the split leaks, and it is the
subject of [#82229](https://github.com/WordPress/gutenberg/discussions/82229) —
whether an icon reference can resolve through a provider rather than requiring
SVG markup. The related question of how an icon-only control gets its name is
[#82228](https://github.com/WordPress/gutenberg/discussions/82228). I am not
re-opening either here.

Happy to answer anything about how it is put together, and the preview is one
click if it is easier to poke at than to read about.
