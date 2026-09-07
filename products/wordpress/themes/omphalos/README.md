# Omphalos

**Omphalos는 Axismundi의 레이아웃 실험실입니다.** 망가져도 되는 쪽이고, Axismundi는
오염되면 안 되는 쪽입니다.

Omphalos is an Axismundi child theme kept as a laboratory for Material Design 3
layout work — [Layout](https://m3.material.io/foundations/layout/layout-overview/overview)
and [Scaffold](https://m3.material.io/foundations/layout/scaffold/overview) —
tried against real WordPress block templates. It is not a general-purpose theme
and is not a design system of its own.

## What it does not carry

Nothing about representation. Colour roles, the type scale, corner shape,
motion, elevation, state layers, the focus ring, every core block style, the
block style variations and the bundled fonts all come from Axismundi. The
parent addresses its own assets through `get_template_directory()` — verified,
23 call sites, and no `get_stylesheet_directory()` anywhere — so they keep
resolving to the parent while this child is active.

That is the whole point of the split. A layout idea can be tried here without
touching the parent, and only what survives moves.

## Where a change belongs

| | |
| --- | --- |
| Composition — shells, rails, panes, scaffold regions, template parts | here |
| Responsive rules that reposition a layout across the theme's viewport bands | the parent |
| Layout tokens — rail width, shell gap, pane sizes | the parent |
| Anything about colour, type, shape, motion, elevation or state | the parent |

The second and third rows are the traps. Building them here means moving them
later, because they are contracts the parent's own templates need.

## Its previous life

Until 0.2.0 this was a Twenty Twenty-Five child theme: the first landing of the
Axismundi design language on a stable native block theme, carrying its own
token layers, block styles and VQA specimens. Axismundi absorbed all of it and
went further, so 0.2.0 empties the theme and re-points it at the parent. The
history is in git; nothing was archived here.

## Local site

```bash
npm start        # wp-env on http://localhost:8894, WordPress 7.1
npm run cli      # wp-cli inside it
```

Both themes and the Theme Switcher plugin are mounted, and `afterStart`
installs the site and activates Omphalos. The repository root also runs an
env on port 8884 with both themes mounted; this one exists so the lab can be
broken without disturbing it.
