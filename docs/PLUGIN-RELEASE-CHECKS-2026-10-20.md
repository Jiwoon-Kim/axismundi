# Plugin Release Checks: 2026-10-20

## Artifacts

| Plugin | Version | Destination | ZIP SHA-256 |
| --- | --- | --- | --- |
| Axismundi Actors | 0.1.3 | GitHub and WordPress.org SVN | `884ca958ff25c4c03bd8dbe73c57c5622b4ab3f72d18a07f4648851f99071d53` |
| Axismundi Object Projections | 0.1.3 | GitHub and WordPress.org SVN | `5ac55a3159412f9f44b2678a6f3b63fedab03d2c2dd60887735fd9f5cb61cce1` |
| Axismundi Activities | 0.1.1 | GitHub prerelease; owner submits to WordPress.org | `f3af023cde0938ec38f070ddd6fc9e28106dde2bc142e5e254c0af4875e88049` |

Activities 0.1.0 already has a GitHub prerelease. Its tag and artifact are not
overwritten. This is not a claim that Activities has a WordPress.org release.

Build with each plugin's `scripts/build-zip.ps1`; outputs are under
`products/wordpress/_dist/`. Versioned ZIPs are copies of those build outputs.
Every payload file was SHA-256 compared with the corresponding source file:
Actors 88 files, Object Projections 140 files, Activities 97 files. Development
tests and build scripts are not shipped.

## Plugin Check

Plugin Check 2.1.0, WordPress 7.1, local wp-env CLI:

```sh
wp plugin check axismundi-actors --format=json --exclude-directories=tests,scripts --mode=update
wp plugin check axismundi-object-projections --format=json --exclude-directories=tests,scripts --mode=update
wp plugin check axismundi-activities --format=json --exclude-directories=tests,scripts --mode=new
```

| Plugin | Errors | Warnings |
| --- | ---: | ---: |
| Actors | 0 | 26 |
| Object Projections | 0 | 156 |
| Activities | 0 | 121 |

These are source checks with the same development directories excluded by the
ZIP builder, not a claim of a separate successful ZIP scan. Plugin Check's CLI
in this environment rejects a local ZIP path as an uninstalled plugin. Payload
equality was checked separately. Its CLI exit status alone does not establish
the absence of findings; the reported ERROR and WARNING entries were counted.

The first run reported seven Object Projections errors and six Activities
errors. SQL identifiers now use `%i`, nullable term identifiers use a bound
`NULLIF(%d,0)`, prepared statements are visible at their execution sites, and
transaction completion uses explicit literal COMMIT/ROLLBACK branches.
No blanket error suppression or excluded check category was added.

Warnings remain, predominantly custom-table queries, generated placeholder
lists, schema migrations and direct uncached ledger reads. Other warnings
include custom URI/cursor validation that the static analyzer does not follow,
public read filters without nonces, and WordPress's `DONOTCACHEPAGE` constant.
For example, the remote-follow address is validated with `FILTER_VALIDATE_IP`
before it becomes a rate-limit key. This record does not certify every warning
as a false positive, promise directory approval, or replace a security review.
Full findings are preserved locally as `_dist/*-plugin-check-*.json`.

## Regression Checks

| Audit | Checks | Failed |
| --- | ---: | ---: |
| `tools/wp-env/audit-release-train.php` | 16 | 0 |
| Activities `audit-questions.php` | 15 | 0 |
| Object Projections `audit-tag-references.php` | 34 | 0 |
| Object Projections `audit-term-members.php` | 21 | 0 |
| Object Projections `audit-object-relations.php` | 16 | 0 |
| Object Projections `audit-object-assembly-baseline.php` | 9 | 0 |

The release-train audit now checks the independent tag-reference schema flag
and both new tables. Row preservation compares existing primary keys instead
of requiring an unchanged count: an installer may legitimately add managed
calendar rows, but may not remove existing ones.

The relation migration fixture now supplies its own empty identity, Actor,
Note and post tables, restores the real post-table pointer, and cleans its
additional projection tables. Previously it changed only the prefix while
the installer still scanned the real site's posts and lacked the new registry
dependency. The tag audit's query observers now recognize `%i`-quoted tables,
including its deliberate bad-row injection.

## Publication Boundary

SVN trunk and the new version tag must match the extracted ZIP payload; verify
the committed remote tag with an independent export. SVN assets are unchanged.
Activities has no approved SVN repository and is not submitted automatically.
No external federation test or Note cutoff is part of this release.
