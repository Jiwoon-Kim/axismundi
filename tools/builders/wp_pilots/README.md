# WordPress Ontology Pilots (P1–P4)

Scripts that build WP ontology pilots from upstream repos.

**Required environment variables** (point to local clones):

```bash
export WORDPRESS_ROOT=/path/to/wordpress-develop  # 6.9.4 @ 97b7f62a
export GUTENBERG_ROOT=/path/to/gutenberg          # v23.1.1 @ 12c6c76e
```

See `corpus/source/MANIFEST.md` for the exact pinned commits and clone instructions.

**Note (v3.0.0)**: scripts currently use literal `${WORDPRESS_ROOT}` / `${GUTENBERG_ROOT}` in Path() strings — placeholder substitution needs implementing before re-running (e.g., via `os.environ.get`). The pilots have already run against pinned repos and their output sits in `core/wordpress/pilots/`. These scripts are preserved for reproducibility but require minor refactoring (env-var → Path) before re-execution.
