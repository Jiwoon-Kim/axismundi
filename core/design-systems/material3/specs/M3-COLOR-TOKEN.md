# M3 Color Tokens — Baseline

> Material Design 3 official baseline color tokens, ref + sys layers.
> Source: m3.material.io/styles/color/static/baseline
> Used as raw input for `tokens.css` Reference and System layers.
>
> **Format:** all values as hex (`#RRGGBB`), enabling `color-mix()` direct usage.
> **Excluded:** Add-on tokens (Fixed colors, Inverse fixed) — not part of core spec.
> **Excluded:** Surface tint — deprecated, use elevation level tokens instead.

---

## 1. Reference layer — Tonal palettes

Six palettes. Each tone is a fixed hex value, not derived from a seed.
Used as `--md-ref-palette-{family}-{tone}`.

### 1.1 Primary

| Tone | Hex |
|---|---|
| 0   | `#000000` |
| 10  | `#21005D` |
| 20  | `#381E72` |
| 30  | `#4F378B` |
| 40  | `#6750A4` |
| 50  | `#7F67BE` |
| 60  | `#9A82DB` |
| 70  | `#B69DF8` |
| 80  | `#D0BCFF` |
| 90  | `#EADDFF` |
| 95  | `#F6EDFF` |
| 98  | `#FEF7FF` |
| 99  | `#FFFBFE` |
| 100 | `#FFFFFF` |

### 1.2 Secondary

| Tone | Hex |
|---|---|
| 0   | `#000000` |
| 10  | `#1D192B` |
| 20  | `#332D41` |
| 30  | `#4A4458` |
| 40  | `#625B71` |
| 50  | `#7A7289` |
| 60  | `#958DA5` |
| 70  | `#B0A7C0` |
| 80  | `#CCC2DC` |
| 90  | `#E8DEF8` |
| 95  | `#F6EDFF` |
| 98  | `#FEF7FF` |
| 99  | `#FFFBFE` |
| 100 | `#FFFFFF` |

### 1.3 Tertiary

| Tone | Hex |
|---|---|
| 0   | `#000000` |
| 10  | `#31111D` |
| 20  | `#492532` |
| 30  | `#633B48` |
| 40  | `#7D5260` |
| 50  | `#986977` |
| 60  | `#B58392` |
| 70  | `#D29DAC` |
| 80  | `#EFB8C8` |
| 90  | `#FFD8E4` |
| 95  | `#FFECF1` |
| 98  | `#FFF8F8` |
| 99  | `#FFFBFA` |
| 100 | `#FFFFFF` |

### 1.4 Error

| Tone | Hex |
|---|---|
| 0   | `#000000` |
| 10  | `#410E0B` |
| 20  | `#601410` |
| 30  | `#8C1D18` |
| 40  | `#B3261E` |
| 50  | `#DC362E` |
| 60  | `#E46962` |
| 70  | `#EC928E` |
| 80  | `#F2B8B5` |
| 90  | `#F9DEDC` |
| 95  | `#FCEEEE` |
| 98  | `#FFF8F7` |
| 99  | `#FFFBF9` |
| 100 | `#FFFFFF` |

### 1.5 Neutral

> M3 Expressive: 25 tones (extra granularity at the dark end for dark-mode surfaces).

| Tone | Hex |
|---|---|
| 0   | `#000000` |
| 4   | `#0F0D13` |
| 6   | `#141218` |
| 10  | `#1D1B20` |
| 12  | `#211F26` |
| 17  | `#2B2930` |
| 20  | `#322F35` |
| 22  | `#36343B` |
| 24  | `#3B383E` |
| 30  | `#48464C` |
| 40  | `#605D64` |
| 50  | `#79767D` |
| 60  | `#938F96` |
| 70  | `#AEA9B1` |
| 80  | `#CAC5CD` |
| 87  | `#DED8E1` |
| 90  | `#E6E0E9` |
| 92  | `#ECE6F0` |
| 94  | `#F3EDF7` |
| 95  | `#F5EFF7` |
| 96  | `#F7F2FA` |
| 98  | `#FEF7FF` |
| 99  | `#FFFBFF` |
| 100 | `#FFFFFF` |

### 1.6 Neutral variant

| Tone | Hex |
|---|---|
| 0   | `#000000` |
| 10  | `#1D1A22` |
| 20  | `#322F37` |
| 30  | `#49454F` |
| 40  | `#605D66` |
| 50  | `#79747E` |
| 60  | `#938F99` |
| 70  | `#AEA9B4` |
| 80  | `#CAC4D0` |
| 90  | `#E7E0EC` |
| 95  | `#F5EEFA` |
| 98  | `#FDF7FF` |
| 99  | `#FFFBFE` |
| 100 | `#FFFFFF` |

---

## 2. System layer — Color schemes

Sys tokens consume ref palette tones. Each sys role maps to a different ref tone in light vs dark.

### 2.1 Light scheme

#### Brand colors

| Sys token | Hex | Ref source |
|---|---|---|
| `primary`              | `#6750A4` | primary 40 |
| `on-primary`           | `#FFFFFF` | primary 100 |
| `primary-container`    | `#EADDFF` | primary 90 |
| `on-primary-container` | `#4F378B` | primary 30 |
| `secondary`              | `#625B71` | secondary 40 |
| `on-secondary`           | `#FFFFFF` | secondary 100 |
| `secondary-container`    | `#E8DEF8` | secondary 90 |
| `on-secondary-container` | `#4A4458` | secondary 30 |
| `tertiary`              | `#7D5260` | tertiary 40 |
| `on-tertiary`           | `#FFFFFF` | tertiary 100 |
| `tertiary-container`    | `#FFD8E4` | tertiary 90 |
| `on-tertiary-container` | `#633B48` | tertiary 30 |

#### Status colors

| Sys token | Hex | Ref source |
|---|---|---|
| `error`              | `#B3261E` | error 40 |
| `on-error`           | `#FFFFFF` | error 100 |
| `error-container`    | `#F9DEDC` | error 90 |
| `on-error-container` | `#8C1D18` | error 30 |

#### Surface

| Sys token | Hex | Ref source |
|---|---|---|
| `background`           | `#FEF7FF` | neutral 98 |
| `on-background`        | `#1D1B20` | neutral 10 |
| `surface`              | `#FEF7FF` | neutral 98 |
| `on-surface`           | `#1D1B20` | neutral 10 |
| `surface-variant`      | `#E7E0EC` | neutral-variant 90 |
| `on-surface-variant`   | `#49454F` | neutral-variant 30 |
| `surface-bright`       | `#FEF7FF` | neutral 98 |
| `surface-dim`          | `#DED8E1` | neutral 87 |
| `surface-container-lowest`  | `#FFFFFF` | neutral 100 |
| `surface-container-low`     | `#F7F2FA` | neutral 96 |
| `surface-container`         | `#F3EDF7` | neutral 94 |
| `surface-container-high`    | `#ECE6F0` | neutral 92 |
| `surface-container-highest` | `#E6E0E9` | neutral 90 |

#### Inverse

| Sys token | Hex | Ref source |
|---|---|---|
| `inverse-surface`    | `#322F35` | neutral 20 |
| `inverse-on-surface` | `#F5EFF7` | neutral 95 |
| `inverse-primary`    | `#D0BCFF` | primary 80 |

#### Outline

| Sys token | Hex | Ref source |
|---|---|---|
| `outline`         | `#79747E` | neutral-variant 50 |
| `outline-variant` | `#CAC4D0` | neutral-variant 80 |

#### Other

| Sys token | Hex |
|---|---|
| `shadow` | `#000000` |
| `scrim`  | `#000000` |

---

### 2.2 Dark scheme

#### Brand colors

| Sys token | Hex | Ref source |
|---|---|---|
| `primary`              | `#D0BCFF` | primary 80 |
| `on-primary`           | `#381E72` | primary 20 |
| `primary-container`    | `#4F378B` | primary 30 |
| `on-primary-container` | `#EADDFF` | primary 90 |
| `secondary`              | `#CCC2DC` | secondary 80 |
| `on-secondary`           | `#332D41` | secondary 20 |
| `secondary-container`    | `#4A4458` | secondary 30 |
| `on-secondary-container` | `#E8DEF8` | secondary 90 |
| `tertiary`              | `#EFB8C8` | tertiary 80 |
| `on-tertiary`           | `#492532` | tertiary 20 |
| `tertiary-container`    | `#633B48` | tertiary 30 |
| `on-tertiary-container` | `#FFD8E4` | tertiary 90 |

#### Status colors

| Sys token | Hex | Ref source |
|---|---|---|
| `error`              | `#F2B8B5` | error 80 |
| `on-error`           | `#601410` | error 20 |
| `error-container`    | `#8C1D18` | error 30 |
| `on-error-container` | `#F9DEDC` | error 90 |

#### Surface

| Sys token | Hex | Ref source |
|---|---|---|
| `background`           | `#141218` | neutral 6 |
| `on-background`        | `#E6E0E9` | neutral 90 |
| `surface`              | `#141218` | neutral 6 |
| `on-surface`           | `#E6E0E9` | neutral 90 |
| `surface-variant`      | `#49454F` | neutral-variant 30 |
| `on-surface-variant`   | `#CAC4D0` | neutral-variant 80 |
| `surface-bright`       | `#3B383E` | neutral 24 |
| `surface-dim`          | `#141218` | neutral 6 |
| `surface-container-lowest`  | `#0F0D13` | neutral 4 |
| `surface-container-low`     | `#1D1B20` | neutral 10 |
| `surface-container`         | `#211F26` | neutral 12 |
| `surface-container-high`    | `#2B2930` | neutral 17 |
| `surface-container-highest` | `#36343B` | neutral 22 |

#### Inverse

| Sys token | Hex | Ref source |
|---|---|---|
| `inverse-surface`    | `#E6E0E9` | neutral 90 |
| `inverse-on-surface` | `#322F35` | neutral 20 |
| `inverse-primary`    | `#6750A4` | primary 40 |

#### Outline

| Sys token | Hex | Ref source |
|---|---|---|
| `outline`         | `#938F99` | neutral-variant 60 |
| `outline-variant` | `#49454F` | neutral-variant 30 |

#### Other

| Sys token | Hex |
|---|---|
| `shadow` | `#000000` |
| `scrim`  | `#000000` |

---

## 3. Token count

| Layer | Light | Dark | Total |
|---|---|---|---|
| Reference (palette tones) | 88 | 88 | 88 (shared) |
| System (sys-color) | 38 | 38 | 38 (shared names, different values) |

---

## 4. Notes for implementation

### 4.1 Hex format
All values stored as 6-digit hex (`#RRGGBB`). This format works with:
- Direct CSS usage: `color: var(--md-sys-color-primary);`
- `color-mix()`: `color-mix(in srgb, var(--md-sys-color-primary), transparent 92%)`
- Native CSS color functions

### 4.2 Surface tint deprecated
The `surfaceTint` token in older M3 specs is deprecated in M3 Expressive. Don't include it. Elevation is conveyed via:
- Tonal containers (`surface-container-low` → `-highest`)
- Shadow elevation in light mode (per `prompt-v2.md` §6.2)

### 4.3 Add-on tokens not included
Material Theme Builder includes Fixed colors (`primary-fixed`, `primary-fixed-dim`, etc.) that are not part of M3 baseline core spec. Excluded from this file. If a use case arises later (e.g. consistent button color across light/dark for hero CTAs), add as `--md-sys-color-*-fixed` then.

### 4.4 Static palettes
Black/White, Blue, Yellow, Red, Purple, Cyan, Grey, Green, Orange, Pink — these are *static palettes* (seed-independent color libraries) maintained separately. See `M3-STATIC-PALETTES.md` for raw values. Not used in core sys tokens; useful for instance/tag color tagging in v1.5.
