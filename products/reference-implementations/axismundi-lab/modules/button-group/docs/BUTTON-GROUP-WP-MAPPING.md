# Button Group — WordPress Mapping (v3.5.10 Phase 5 close)

> **Component**: Button group #6  
> **Status**: Phase 5 close — v3.5.10 DONE. No WordPress baseline registration occurred.  
> **Companions**: `./BUTTON-GROUP-SPEC-AUDIT.md`, `./BUTTON-GROUP-MEASUREMENT-AUDIT.md`

---

## §0 — Mapping Framing

Button group has no direct WordPress core block equivalent.

The closest visual surface is:

```txt
core/buttons + core/button children
```

But the semantic contract is richer:

```txt
Pattern A:
  native radio + label single-select

Pattern B:
  button + aria-pressed multi-toggle
```

Therefore `core/buttons` is a partial visual mapping, not a full semantic
mapping.

---

## §1 — Charter Boundary

Theme can:

```txt
- Render Button group visual surfaces.
- Provide CSS for standard and connected geometry.
- Provide pattern markup examples for radio/label and aria-pressed buttons.
- Register block patterns that output static Button group markup.
- Style core/buttons when it is only a visual action row.
```

Plugin should:

```txt
- Execute filtering / sorting / view switching logic.
- Persist selected preference.
- Implement editor toolbar commands.
- Toggle aria-pressed in production behavior.
- Fetch data, search, paginate, or alter query state.
```

Boundary:

```txt
Theme owns the grouped-button surface.
Plugin/integrator owns the stateful product behavior.
```

---

## §2 — Core Block Inventory

| Core surface | Relationship | Verdict |
|---|---|---|
| `core/buttons` | Visual row of buttons | Partial visual mapping |
| `core/button` | Individual button child | Useful for simple action rows |
| `core/group` | Generic wrapper | Pattern composition only |
| `core/navigation` | Link/navigation items | Not Button group |
| `core/search` | Search submit/control | Search bar owns this |
| `core/query` / filters | Dynamic filtering | Plugin territory |
| editor toolbar | Command controls | Plugin/editor territory |

---

## §3 — Mapping Paths

### Path A — `core/buttons` Visual Approximation

Use when:

```txt
- The author needs a row of action buttons.
- No single-select or multi-toggle state is required.
- Connected geometry is not required.
```

Limit:

```txt
core/buttons does not provide native radio semantics or aria-pressed state.
```

### Path B — Theme Pattern Composition

Use when:

```txt
- The theme provides a predefined view-mode / sort-mode selector.
- Markup can include fieldset/radio/label or toolbar/button contracts.
- The behavior is still wired by an integrator.
```

This is the preferred theme-side mapping for Button group.

### Path C — Plugin / Custom Block

Use when:

```txt
- The selected value changes query results.
- The group controls editor commands.
- The group stores preferences.
- The group needs live update announcements.
```

This belongs outside the theme baseline.

---

## §4 — Recommended WordPress Position

```txt
Do not register Button group as a baseline core/buttons block style in v3.5.10.
```

Reason:

```txt
core/buttons can approximate Button group visually, but it cannot express the
semantic difference between Pattern A radio groups and Pattern B aria-pressed
toolbars without custom markup or plugin support.
```

Phase 2 may include a code specimen showing pattern composition, but should
not modify WordPress registration files.

---

## §5 — Anti-Patterns

Do not:

```txt
- Treat every core/buttons row as a Button group.
- Use Button group to fake Tabs.
- Use Button group to fake Split button.
- Use Button group for Menu or dropdown behavior.
- Use anchors as action toggles without navigation semantics.
- Use div role="button" for segments.
- Hide radio inputs with display:none.
- Use aria-pressed for mutually-exclusive form values without explanation.
- Use radio groups for independent toolbar commands.
- Put product query behavior in theme CSS or functions.php.
- Store selected preference in theme-only markup.
- Make icon-only segments without aria-label.
```

---

## §6 — Accessible Name Contract

Pattern A:

```txt
fieldset + legend labels the group.
label text labels each radio option.
```

Pattern B:

```txt
toolbar container may have aria-label.
each button needs visible text or aria-label.
aria-pressed exposes toggle state.
```

Icon-only:

```txt
aria-label required.
decorative Material Symbol span must be aria-hidden.
```

---

## §7 — Plugin Territory Examples

Plugin territory:

```txt
- Product grid view/list toggle that changes query results.
- Faceted search mode selector.
- Sort order selector connected to WP_Query.
- Rich text editor toolbar.
- Media editor formatting commands.
- User preference persistence.
- Async result announcement.
```

Theme may style these surfaces, but it does not own the behavior.

---

## §8 — Phase 2 Pattern Recommendation

`lab-button-group-pattern.html` should include:

```txt
1. A pure theme-side Pattern A specimen.
2. A pure theme-side Pattern B specimen with static aria-pressed values.
3. A WordPress pattern composition snippet.
4. A warning that real filtering/sorting behavior is plugin territory.
```

Do not include live WordPress registration code as an executed artifact.

---

## §9 — WP Mapping Verdict

```txt
Phase 5 close (v3.5.10) — PASS.

Button group maps weakly to core/buttons as visual row composition.
Full semantics require theme pattern markup or plugin/custom block behavior.
No WordPress baseline registration occurred in v3.5.10.

Phase 2 lab specimen (lab-button-group-pattern.html) demonstrates Pattern A
radio+label and Pattern B button+aria-pressed compositions. WordPress block
registration deferred to a future binding cycle if Button group ever needs
its own custom block.
```

