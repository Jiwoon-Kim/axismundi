# Draft — reply to the WordPress.org review, T2

> Not part of the plugin. Delete before packaging, or keep it out of the ZIP.
>
> The review asked for a concise reply that shares clarifications and context,
> and explicitly asked NOT to list every change, since the team re-reviews the
> whole plugin anyway. So this says what was wrong, what is deliberate, and
> nothing else.

Review ID: R axismundi-actors/kimjiwoon/3Sep26/T2 6Sep26/4.2.1 (P0TDX362691HGN)

---

```
Hello, and thank you for the review — both findings were correct.

On the nonce issue: the management screen was reading a user id from the query
string and then calling a function that creates an actor record when none
exists, so a link could make an administrator perform that write. The screen no
longer creates anything; it reads, and creation happens in the activation form,
which already carried a nonce and a capability check. I added regression tests
around it.

On the SQL: every custom table name now reaches the database through a prepared
identifier placeholder (%i) instead of being interpolated into the query text.
There were rather more than the 26 you listed — around a hundred call sites —
and while going through them I also found and fixed unsanitized $_POST values in
the profile-links handler that neither of us had flagged.

Three kinds of interpolation remain, and I would rather explain them than hide
them behind suppressions:

- CREATE TABLE statements passed to dbDelta(), which requires the literal table
  name and does not accept a placeholder.
- Generated placeholder runs — strings like "%s, %s, %s" built with array_fill()
  from a count, used to size an IN list. These are neither values nor
  identifiers, so no placeholder can carry them; every value they stand for is
  prepared normally.
- WHERE clauses assembled in the calling function, which carry their own
  placeholders and are filled by prepare(). Nothing in them comes from input.

Happy to change any of those if you would prefer a different approach.

Thank you again for your time.
```

---

## If they ask for specifics

The reply deliberately does not enumerate. If a follow-up asks:

**The CSRF path.** `axismundi_actors_render_admin_page()` called
`axismundi_actors_ensure_for_user()`, which creates on miss. The capability
check (`edit_user`) limited who could reach it but does not address CSRF, which
is about a capable user being made to act. The wizard it renders never used the
actor object — only the user id — so the fix cost no behaviour: the screen calls
`get_for_user()`, and the nonce-checked activation POST creates.

**Why `%i` and not `esc_sql()`.** `%i` is WordPress 6.2+, emits a
backtick-quoted identifier, and keeps the whole statement inside `prepare()`.
Verified it works for the shapes here, including `SHOW COLUMNS`, `SHOW INDEX`,
joins with aliases, and `ALTER TABLE ... DROP COLUMN`.

**Column lists.** One migration selected a variable column list. A single `%i`
would have backtick-quoted the commas along with the names, so it now emits one
`%i` per column from an array.
