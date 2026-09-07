# Emoji fixtures

Captured from live instances on 2026-07-27. These exist so E1 is written against
what the wire actually carries rather than against what the specs imply.

| File | What it pins |
| --- | --- |
| `actor-emoji-tags.json` | Real `tag[].type = Emoji` entries from two cached remote Actors. The `icon.url` host differs from the declaring authority in **both**, which is the evidence behind the identity rule in §2 of the architecture doc. |
| `emoji-document-misskey-restricted.json` | An emoji `id` dereferenced as ActivityPub. Shows that metadata — including a restrictive `_misskey_license.freeText` and `icon.mediaType: image/apng` — is reachable without any vendor API. |
| `license-sample-misskey.json` | A stratified 11-emoji sample: 5 `allowed`, 4 `unknown`, 2 `restricted`, and one Public-Domain-but-NSFW entry. The reason licence is three-state and independent of sensitivity. |
| `reaction-types-misskey.json` | Observed reaction values, including the authority-qualified `:09_bird@hoto.moe:` beside bare Unicode. **REST observation, not an AP payload** — the qualified form is documented at the reaction layer only. |
| `rest-custom-emojis-mastodon.json`<br>`rest-emojis-misskey.json` | Two rows each of the catalogue endpoints, kept only to record their shape and the fields that are **absent** from the list responses. The full captures (119 and 13,092 entries, 3.2 MB) are deliberately not committed. |
| `animated-2frame.apng` | A generated 204-byte, 2-frame APNG. |

## About the APNG

`animated-2frame.apng` is generated, not truncated, because a truncated file is a
broken file: it can prove a magic-number check and nothing else. This one is a
valid PNG container and reproduces every behaviour that matters, verified against
the 1.92 MiB Misskey original:

```text
                        real 1.92 MiB APNG      204-byte fixture
wp_get_image_mime()     image/png               image/png
acTL                    19 frames, loop 0       2 frames, loop 0
wp_get_image_editor()   NoDecodeDelegate…       NoDecodeDelegate…
GD save()               first frame, no acTL    first frame, no acTL
```

The real emoji is **not committed**: it is 1.92 MiB, and its own licence reads
*"exclusive to Misskey.io; usage in other platform is prohibited."* CI has no
reason to carry it.

To run the optional integration checks against a local copy:

```bash
AXISMUNDI_EMOJI_REFERENCE_APNG=/path/to/ai_acid_misskeyio.apng \
  wp eval-file tests/audit-emoji-fixtures.php
```

Without it the harness reports `1 skipped` rather than failing, and nothing in the
plugin may depend on that path existing.
