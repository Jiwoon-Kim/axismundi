# Omphalos

**Omphalos는 Axismundi 디자인 시스템을 Twenty Twenty-Five 위에 처음으로 착지시키는 차일드테마입니다.**

Omphalos is a Twenty Twenty-Five child theme that acts as the first grounding
point of the Axismundi design system for WordPress. It layers Axismundi's
Material Design 3 tokens, typography, and core block styling onto a stable
native block theme as a compatibility pilot.

- **Omphalos** = TT5 호환 기본형 / 브릿지 / 중심석 (this theme)
- **Axismundi** = TT5 호환 갭을 제거한 순수 Material-token 기반 독립 블록테마 (future)

## Scope

Consumes (pilot from `axismundi-pilot`):

- Material 3 `tokens.ref` / `tokens.sys` color + core scale layer
- bundled Roboto / Noto fonts + Material Symbols icon font (Font Library)
- dynamic attachment media object templates
- 3-state (light / dark / auto) theme switcher (Interactivity API)
- a light / dark / auto scheme application layer. The inserter block lives in
  the companion `omphalos-theme-switcher` plugin.

Does **not** implement:

- the full Axismundi component system
- editor toolkit / HCT runtime / ActivityPub UI

## Develop with wp-env

Docker Desktop must be running.

```powershell
npm install
npm run start                      # boots wp-env on http://localhost:8884
npm run cli theme activate omphalos
```

The `.wp-env.json` maps this folder as a theme, maps the local Twenty
Twenty-Five copy as the parent, and installs the Create Block Theme plugin
(dev-only export/inspect helper).

| URL | Purpose |
| --- | --- |
| http://localhost:8884 | front-end |
| http://localhost:8884/wp-admin | admin (admin / password) |

## Parent theme

Requires **Twenty Twenty-Five**. The dev environment maps a local copy; for a
distributed release the parent is the WordPress.org theme `twentytwentyfive`.
