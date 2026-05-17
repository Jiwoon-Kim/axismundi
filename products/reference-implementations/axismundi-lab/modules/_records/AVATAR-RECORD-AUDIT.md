# Avatar Record Audit

> **Status**: v3.5.13 Phase 5 closed record-only audit.  
> **Category**: Baseline-only Record.  
> **Matrix row**: #32 Avatar.  
> **Verdict**: RECORD remains RECORD; no lab module required.

---

## §0 — Record Status

Avatar is a Baseline-only Record, not a Component Full-Spec module.

```txt
No lab-avatar.css
No lab-avatar.js
No lab-avatar-pattern.html
No SPEC / MEASUREMENT / WP-MAPPING trio
```

This record exists to keep the matrix promise honest: Avatar is inventoried,
mapped, and bounded, but its baseline surface is already small enough that a
full module would add ceremony without new behavior.

---

## §1 — Baseline Inventory

Baseline source:

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css §1
```

Selectors:

```txt
.ax-avatar
.ax-avatar.is-size-xs
.ax-avatar.is-size-sm
.ax-avatar.is-size-lg
.ax-avatar > img
```

Current baseline:

```txt
Default size: 40px via --comp-avatar-size
XS size:      24px
SM size:      32px
LG size:      56px
Shape:        --comp-avatar-radius = corner-full
Container:    primary-container
Label/icon:   on-primary-container
Typography:   title-medium tokens
Image mode:   object-fit cover, clipped by avatar radius
```

Avatar supports two content modes:

```txt
Initials / glyph content:
  inline text or icon centered in the circular container.

Image content:
  direct child img fills the container and is clipped by overflow hidden.
```

---

## §2 — M3 / WordPress Mapping

M3 role:

```txt
Avatar is a leading visual identity primitive. It commonly appears in List,
chat, profile, account switcher, and navigation contexts.
```

WordPress mapping:

```txt
Core blocks:
  - core/avatar may map visually to .ax-avatar in theme surfaces.
  - core/image can be composed into an avatar-like surface only when the theme
    controls the wrapper and crop.

Plugin territory:
  - user identity lookup,
  - fallback initials generation,
  - remote image privacy/caching,
  - ActivityPub actor avatar resolution.
```

Theme territory:

```txt
The theme may provide the visual primitive, size classes, border radius, color,
and image clipping behavior.
```

---

## §3 — Dependency / Composition Notes

Avatar is composition-owned in List:

```txt
List #33 may place Avatar in the leading slot.
List does not own Avatar's size, color, image clipping, or fallback initials.
```

Other likely consumers:

```txt
App bar account slot
Nav rail account slot
Chat / comments UI
Profile card
Author byline
```

Dependencies:

```txt
icon-system/  NONE by default; conditional only if an icon is used as content.
ripple/       NONE. Avatar is visual identity, not an action surface.
popover/      NONE. Account menu behavior belongs to consumer components.
```

BACKLOG cross-reference:

```txt
BACKLOG #2 Avatar size token consistency remains open unless separately closed.
This record does not close #2; it only documents current baseline behavior.
```

---

## §4 — Accessibility / Reduced Motion

Avatar itself has no interactive semantics.

Accessibility contract:

```txt
Decorative avatar:
  aria-hidden="true" or empty alt on inner image, depending on context.

Informative avatar:
  accessible name belongs to the surrounding user/profile link or image alt.

Action avatar:
  compose inside a real button/anchor owned by the consumer.
```

Reduced motion:

```txt
N/A. Avatar has no animation or runtime.
```

---

## §5 — Why No Lab Module Is Needed

Avatar does not need a module because:

```txt
- It has no runtime.
- It has no state-layer contract.
- It has no WordPress block registration requirement at baseline tier.
- It is already represented by a concise baseline primitive.
- Its complexity is composition/context data, not component mechanics.
```

If future work needs account menus, chat presence, upload/crop, or identity
resolution, that belongs to the consuming component or plugin layer.

---

## §6 — Record Verdict

```txt
Baseline inventory      PASS
Composition boundary    PASS
WordPress mapping       PASS
Accessibility framing   PASS
No-module decision      PASS
```

Avatar remains `RECORD` in the module matrix.

---

## §7 — Cross-References

- `docs/v3.5.0/MODULE-STATUS-MATRIX.md` row #32
- `BACKLOG.md` #2
- `products/reference-implementations/axismundi-lab/stylesheets/components.css` §1
- `../list/docs/LIST-SPEC-AUDIT.md`
- `docs/v3.5.13/WAVE-1-CLEANUP-PHASE-0-REPORT.md`

---

## §8 — Non-Goals

This record does not:

- create a lab module,
- create avatar pattern HTML,
- close BACKLOG #2,
- edit `components.css`,
- edit `tokens.css`,
- edit `style-guide.html`,
- fold Avatar into List,
- define ActivityPub actor avatar behavior,
- define media upload/crop behavior.
