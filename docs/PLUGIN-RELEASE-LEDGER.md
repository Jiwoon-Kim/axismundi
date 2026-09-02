# Plugin release ledger — SVN vs GitHub

> **Policy** / **정책** — established 2026-09-03.

## The two ledgers are not the same event

**EN** — A WordPress.org release and a GitHub release record different things and
must not be merged into one ledger.

**KO** — WordPress.org 배포와 GitHub 릴리스는 서로 다른 사건을 기록하며, 하나의
원장으로 합치지 않는다.

```
SVN / WordPress.org   =  the official record of a released plugin version
                         배포된 플러그인 버전의 공식 기록

GitHub Release        =  a development or integration milestone of this repository
                         Axismundi 저장소의 개발·통합 이정표
```

A repository milestone may span Contacts, Actors, the theme and several plugins
at once. It has no single plugin version, so it cannot be a plugin changelog
entry. Summarise it in the GitHub release body instead.

저장소 이정표는 여러 제품을 한꺼번에 포함할 수 있어 단일 플러그인 버전에
대응하지 않는다. 플러그인 changelog에 밀어 넣지 말고 GitHub 릴리스 본문에서
요약한다.

## What each plugin file holds

Per plugin, `readme.txt` and `changelog.txt` record **WordPress.org released
versions only**.

| File | Holds |
| --- | --- |
| `readme.txt` `== Changelog ==` | the current WordPress.org release, one entry |
| `changelog.txt` | every earlier WordPress.org release |

This follows the Plugin Handbook, which recommends keeping the current release in
the readme and splitting the rest into a separate file, and warns that a readme
over 10&nbsp;KB may produce errors.

## Release procedure

In one commit, when releasing a plugin:

1. Write the new version as the only `== Changelog ==` entry in `readme.txt`.
2. Move the entry it replaces to the top of `changelog.txt`.
3. Set `Version:` in the main plugin file, `Stable tag` in `readme.txt`, and the
   SVN tag to that same version.
4. If the repository publishes a GitHub release for this plugin, copy that new
   `readme.txt` entry into the release body.

## Why the readme stays short

`readme.txt` is parsed into the GlotPress projects **Stable Readme** and
**Development Readme**; `changelog.txt` is not a readme section. Every changelog
line in the readme therefore becomes a translatable string, and GlotPress marks
changelog strings `Priority: low` while the description and plugin name are
`Priority: high`.

Measured 2026-09-02 on `axismundi-media-library` before the split: Stable Readme
carried 118 Korean strings, 0 translated, 118 waiting — the great majority of
them low-priority release history for a plugin with 74 all-time downloads.

**Unverified** — that entries moved into `changelog.txt` leave the translation
queue is the expected consequence, not a documented one. Confirm it by comparing
the Stable Readme string count after the next release. The directory parses the
readme in the folder named by `Stable tag`, so a trunk edit has no effect until
a new version ships.

## See also

- <https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/>
- `BACKLOG.md`
