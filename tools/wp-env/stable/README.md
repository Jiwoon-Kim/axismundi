# stable: the same site without Gutenberg

Two environments, one working tree.

| | port | core | Gutenberg |
| --- | --- | --- | --- |
| integration (`/.wp-env.json`) | 8884 | 7.1 | 23.7.1 |
| stable (this directory) | 8885 | 7.1 | none |

Same themes, same plugins, same core. The only difference is the Gutenberg plugin,
which is the point: anything that renders differently between the two sites is
attributable to Gutenberg and to nothing else.

That distinction is not cosmetic. The Gutenberg plugin replaces the render callback
of **every dynamic core block** — 127 of them on this site, `core/navigation` and
`core/page-list` included. On 8884 a change to `wp-includes/blocks/*.php` has no
effect at all, because core's copy is not what runs. This was discovered the hard
way while preparing a core bug report: a patch applied to core rendered no
difference, and the reason was that the file being patched was never being read.

## Which one to use

**stable** is the release baseline. Use it for anything that answers "what does a
person who installs this actually get": distributable checks, Theme Check and Plugin
Check, block markup the theme depends on, and any bug that might be ours.

**integration** is where the next WordPress is. Use it to see upcoming block markup
before it ships, to reproduce core-block issues against trunk, and to tell a
Gutenberg regression apart from a bug of our own by running the same fixture on both.

## Running it

```
npx wp-env start --config tools/wp-env/stable
```

wp-env keys an environment by its directory, so this one gets its own database and
volumes. Nothing here touches 8884, which holds demo content, the VQA specimens and
accumulated federation fixtures, and is not to be destroyed.

A backup of 8884 (database and uploads) lives outside the repository, under
`dev/wp-env-backups/`.

## Pinning

Gutenberg is pinned to an exact version in the integration declaration rather than
tracked as `gutenberg.zip`. An unpinned plugin would swap the block renderer under
the theme on some later start, which is the failure this split exists to prevent,
arriving silently instead of loudly.
