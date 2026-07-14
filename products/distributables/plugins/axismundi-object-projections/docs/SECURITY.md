# Security notes

- **No network, no DB writes in a transformer.** Transformers are pure and the renderer
  only reads and serializes. A later remote-projection repository may perform bounded
  administrator-initiated fetch/cache work outside transformation; it still owns no
  inbox, delivery, or Activity processing.
- **Output escaping.** `name` is reduced to plain text (`wp_strip_all_tags` +
  `sanitize_text_field`); `content` / `summary` pass `wp_kses_post`. A future JSON-LD
  emit path must still `wp_json_encode` with `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`
  and set the correct content type — never echo raw.
- **`@context` cannot be injected** by a transformer or a caller; the renderer is the sole
  owner and drops any supplied `@context`.
- **No private leakage.** Public/private is a required-to-honor `visible` gate; the Core
  Post transformer additionally gates on `is_post_publicly_viewable()`,
  `post_status = publish`, password protection, and excludes revisions / autosaves /
  attachments-as-posts. It also requires a public local user or site Actor. Draft,
  private, password, and Actor-less sources never emit an object.
- **Callback isolation.** A transformer that throws yields a `WP_Error`, never a fatal, so
  one bad plugin cannot break negotiation for the rest.
- **Id integrity.** The emitted `id` is forced to equal the declared stable object URI, so
  a transformer cannot point federated identity at an arbitrary URL.
- **URL → id, never the reverse.** The renderer never reverse-resolves a URL string back
  to an attachment/post id; sources are passed in as typed objects.
- **Narrow negotiation.** Bare `application/json`, unprofiled `application/ld+json`,
  non-GET/HEAD requests, REST, AJAX, feeds, and wp-admin never enter the router. The
  read-only `?activitypub` selector may bypass only the Accept check, not these surface,
  method, visibility, or single-negotiator gates.
- **Single negotiator.** `ACTIVITYPUB_PLUGIN_VERSION` disables the standalone router;
  registry and renderer remain available for an adapter, but two plugins never answer
  the same canonical URL in one request.
