# AGENTS.md — tools

```txt
generators/   write committed files from a declared source
validators/   check that a committed file still agrees with something else
builders/     regenerate core/ output from corpus/ and atlas/ (frozen inputs)
refine/       corpus transformation (frozen)
```

## Generators

A generator writes files that are committed, and takes `--check` to regenerate
in memory and compare. `--check` must fail on any difference, print the first
differing line, and say to edit the source rather than the output. Every output
carries a header naming the generator and the verifier.

`sync_styleguide_assets.py` copies rather than generates where it can: a file
that already exists correctly in a product is copied byte for byte, because a
second authored implementation drifts. It refuses to finish if anything it
wrote is not git-ignored.

## Validators

A validator answers a question the generator cannot. The generator's `--check`
proves the output matches the generator; the validator proves the value matches
the spec, or `theme.json`, or the other place the same fact is written. A buggy
generator satisfies its own check every time.

Every validator must:

- `sys.exit(main())`, and return non-zero on failure. A `main()` returning
  `None` produced a CI job that passed on every input, including four injected
  faults.
- print how many things it checked, and name each failure specifically.
- be tested against a deliberately injected fault before it is trusted.

## builders/ and refine/

These read `corpus/` and `atlas/`, which are frozen research. Running them
reproduces a past result; it does not produce current truth. Do not use their
output to decide a current implementation — see the root `AGENTS.md`.

## Writing Python here

See **This machine** in the root `AGENTS.md`. In short: `newline="\n"` on every
write, `C:/` paths not `/c/`, and never build a script containing a backslash
through a shell heredoc.
