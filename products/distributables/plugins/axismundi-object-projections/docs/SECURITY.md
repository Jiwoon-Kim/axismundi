# Security notes

- **No network, no DB writes in a transformer.** Transformers are pure and the renderer
  only reads and serializes. A later remote-projection repository may perform bounded
  administrator-initiated fetch/cache work outside transformation; it still owns no
  inbox, delivery, or Activity processing.
- **Output escaping.** `name` is reduced to plain text (`wp_strip_all_tags` +
  `sanitize_text_field`); `content` / `summary` pass a dedicated FEP-b2b8-derived positive
  allowlist. It deliberately does not inherit `wp_kses_allowed_html( 'post' )` or the
  global `wp_kses_allowed_html` filter, so another plugin cannot widen federated HTML.
  Interactive/embed elements and their contents are removed before KSES. A future JSON-LD
  emit path must still `wp_json_encode` with `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`
  and set the correct content type — never echo raw.
- **`@context` cannot be injected** by a transformer or a caller; the renderer is the sole
  owner and drops any supplied `@context`.
- **No private leakage.** Public/private is a required-to-honor `visible` gate; the Core
  Post transformer additionally gates on `is_post_publicly_viewable()`,
  `post_status = publish`, password protection, and excludes revisions / autosaves /
  attachments-as-posts. It also requires a public local user or site Actor. Draft,
  private, password, and Actor-less sources never emit an object.
- **Media negotiation is anonymous and cache-safe.** The Media Library adapter accepts
  only effective public/unlisted, ungated attachments with a public Actor. It never calls
  the owner/editor-aware single-view permission helper, so login state cannot widen a
  negotiated representation.
- **Relation-indexed media never widens visibility.** Article `image`/`attachment` members
  include only public/unlisted, ungated Attachment projections. The `usedIn` reverse
  collection resolves relations as an anonymous viewer and lists only freshly projectable
  public Articles; drafts/private/password posts and private/locked media remain absent.
- **Callback isolation.** A transformer that throws yields a `WP_Error`, never a fatal, so
  one bad plugin cannot break negotiation for the rest.
- **Id integrity.** The emitted `id` is forced to equal the declared stable object URI, so
  a transformer cannot point federated identity at an arbitrary URL.
- **Typed sources by default; exact canonical reverse lookup at interaction boundaries.**
  Transformers receive typed objects and never reverse-resolve their own output. Public
  interaction routes may use `url_to_postid()` only as a candidate lookup, then require the
  source's freshly projected canonical `id` to exactly equal the requested URI.
- **Narrow negotiation.** Bare `application/json`, unprofiled `application/ld+json`,
  non-GET/HEAD requests, REST, AJAX, feeds, and wp-admin never enter the router. The
  read-only `?activitypub` selector may bypass only the Accept check, not these surface,
  method, visibility, or single-negotiator gates.
- **Remote rows are observations, not trust.** Their URI remains remote authority;
  normalized text is sanitized and raw payload JSON is never rendered directly. Invalid
  refresh input preserves the last good snapshot. Repository writes perform no network.
- **Fetch is a separate SSRF boundary.** The administrator fetcher validates public HTTPS
  targets, forwards no cookies/auth, caps responses at 1 MiB, validates content type, and
  disables redirects. Front-end rendering never fetches synchronously. Signed fetching is
  explicitly unavailable until Federation supplies it.
- **Metadata-only means no passive remote media request.** Admin previews remove all
  image/video/audio/embed elements from cached HTML. Source links require an explicit
  click. Binary preview/display/original policies remain deferred.
- **Single negotiator.** `ACTIVITYPUB_PLUGIN_VERSION` disables the standalone router;
  registry and renderer remain available for an adapter, but two plugins never answer
  the same canonical URL in one request.
