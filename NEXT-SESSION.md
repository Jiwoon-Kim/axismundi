# NEXT-SESSION.md — v3.5.16 Entry Handoff

> **Status**: v3.5.15 GitHub repository + Pages publish closed.  
> **Use**: read at the start of the next local Claude/Codex session.

---

## 0) Reading Order

```txt
1. AGENTS.md or CLAUDE.md
2. CURRENT-STATE.md
3. PROJECT-CONTEXT.md
4. docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
5. docs/v3.5.16/MODERNIZATION-AUDIT.md
6. docs/v3.5.16/STALE-STATE-AUDIT.md
7. docs/v3.5.0/MODULE-STATUS-MATRIX.md
8. CHANGELOG.md latest entry (v3.5.15)
9. ROADMAP.md v3.5.16 NEXT entry
10. docs/v3.5.15/GITHUB-PUBLISH-PHASE-1-REPORT.md
11. docs/v3.5.15/GITHUB-PUBLISH-PHASE-2-REPORT.md
```

## 1) Current State

```txt
v3.5.13 — Wave 1 closure cleanup (#32/#33/Records)  ✓ CLOSED
v3.5.14 — Publish prep                               ✓ CLOSED
v3.5.15 — GitHub repository + Pages publish          ✓ CLOSED
Validator                                            1.000 / 1.000 / 1.000 / 1.000 PASS
npm test                                             PASS
GitHub repository                                   LIVE
GitHub Pages                                        LIVE
```

Public URLs:

```txt
Repository:
  https://github.com/Jiwoon-Kim/axismundi

Pages:
  https://jiwoon-kim.github.io/axismundi/
```

Wave 1 progress:

```txt
DONE      9 / 9  Button, Icon button, FAB family, Button group, Card,
                 Text field, Search bar, List, Carousel
PARTIAL   0 / 9
TODO      0 / 9
```

Overall matrix:

```txt
13 DONE / 2 PARTIAL / 16 TODO / 3 RECORD + 3 infrastructure = 37 entries
```

## 2) v3.5.15 Closure Summary

```txt
Local directory rename              COMPLETE
Git initialization                   COMPLETE
Initial commit                       COMPLETE
GitHub repository creation           COMPLETE
Initial push                         COMPLETE
GitHub Pages activation              COMPLETE
Public navigation verification       COMPLETE
```

Details:

```txt
Current path:
  C:\Users\thaum\dev\axismundi

Remote:
  origin https://github.com/Jiwoon-Kim/axismundi.git

Initial commit:
  e22b9e5 Initial Axismundi public release

Pages source:
  main branch root
```

Public navigation QA:

```txt
https://jiwoon-kim.github.io/axismundi/                                                   200
https://jiwoon-kim.github.io/axismundi/styleguide/                                        200
https://jiwoon-kim.github.io/axismundi/README.md                                          200
https://jiwoon-kim.github.io/axismundi/README.ko.md                                       200
https://jiwoon-kim.github.io/axismundi/products/reference-implementations/axismundi-lab/README.md 200
https://jiwoon-kim.github.io/axismundi/products/reference-implementations/axismundi-lab/modules/README.md 200
https://jiwoon-kim.github.io/axismundi/docs/v3.5.14/TEMPLATES-PUBLISH-CATEGORY-NOTE.md    200
https://jiwoon-kim.github.io/axismundi/LICENSE-MATRIX.md                                  200
https://jiwoon-kim.github.io/axismundi/NOTICE.md                                          200
```

## 3) v3.5.16 Next Route — Modernization

Authoritative inputs:

```txt
docs/v3.5.16/MODERNIZATION-AUDIT.md
docs/v3.5.16/STALE-STATE-AUDIT.md
```

Locked direction:

```txt
Use option B:
  v4.0까지 directory restructure 보류
  v3.5.16에서 framing / UX / navigation 정렬
```

Recommended lanes:

```txt
Lane M — Charter §3.3 amendment
  "lab" = module workspace / legacy naming, not experimentation-only.

Lane N — Styleguide -> module navigation UX
  BACKLOG #34, including #11 overlap resolution.

Lane O — Lab pattern HTML validation-specimen banners
  17 pattern HTMLs.

Lane P — BACKLOG hygiene
  #11/#34 overlap, #10/#17 close-check, #13 publish theme.js decision,
  Snackbar/Tooltip legacy marking.

Lane Q — Optional bounded side-fixes
  #1 inline code font size, #2 Avatar size tokens, #3 Floating toolbar selected
  color, #28 Icon button SVG wording cleanup.
```

Do not perform v4.0 directory restructure in v3.5.16.

## 4) Locked Later Route

```txt
v3.5.16  Styleguide modernization + module workspace framing
v3.6.0   Ontology Theme Pilot
v3.7.x   Wave 2/3
v4.0     Public release + directory restructure
```

v4.0 candidate:

```txt
Retire "lab" naming structurally:
  - axismundi-lab/ -> module-first name
  - lab-* prefixes retired
  - publish_styleguide.py redesigned
  - audit doc cross-refs updated
```

## 5) Operational Commands

```powershell
cd C:\Users\thaum\dev\axismundi
python .\tools\validators\validate_theme_pilot.py
npm test
python .\tools\generators\publish_styleguide.py
git status --short
```
