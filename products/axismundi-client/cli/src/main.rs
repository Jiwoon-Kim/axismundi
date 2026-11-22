//! A terminal front end over the JMAP core.
//!
//! The split is deliberate: everything that speaks the protocol lives in the core
//! crate, and this file only reads arguments and prints. A desktop front end can
//! then reuse the same core rather than reimplementing the exchange behind a window.

use std::process::ExitCode;

use axismundi_jmap_core::discovery::{Probe, DiscoveryOptions, discover_selected};
use axismundi_jmap_core::{CORE_CAPABILITY as CORE, Client, Credentials, Error, Session};
use serde_json::{Value, json};

mod todo_set;

const TODO: &str = "urn:com.example:jmap:todo";

struct Options {
    session_url: String,
    user: String,
    secret: String,
    account: Option<String>,
    since: Option<String>,
    db: Option<String>,
    profile: Option<String>,
    id: Option<String>,
    mailbox: Option<String>,
    rows: Option<String>,
    events: Option<String>,
    file: Option<String>,
    out: Option<String>,
    media_type: Option<String>,
    name: Option<String>,
    properties: Option<String>,
    offset: Option<String>,
    length: Option<String>,
    types: Option<String>,
    text: Option<String>,
}

/// `--rows` takes a value rather than being a bare switch, because the offline parser reads its
/// arguments strictly in pairs.
fn rows(value: Option<&str>) -> Result<axismundi_jmap_core::mail::Rows, String> {
    use axismundi_jmap_core::mail::Rows;
    match value {
        None | Some("messages") => Ok(Rows::Messages),
        Some("threads") => Ok(Rows::Threads),
        Some(other) => Err(format!("--rows takes messages or threads, not {other}")),
    }
}

fn usage() -> String {
    [
        "axismundi - a JMAP probe that learns everything from the Session resource",
        "",
        "usage:",
        "  axismundi discover  HOST_OR_URL [--jmap] [--wordpress] [--activitypub]  (anonymous)",
        "  axismundi session   --session-url URL [--user NAME] [--secret SECRET]",
        "  axismundi echo      --session-url URL [--user NAME] [--secret SECRET]",
        "  axismundi mailboxes ... [--account ID] (read-only Mailbox/get)",
        "  axismundi mail-inbox ... [--account ID] (first 50 or fewer messages)",
        "  axismundi mail-read ... [--account ID] --id EMAIL_ID (body values on demand)",
        "  axismundi mail-more ... --db PATH --profile ID [--account ID] [--mailbox ID] [--rows messages|threads]",
        "                    (append the next page to a synced listing)",
        "  axismundi mail-watch ... --db PATH --profile ID [--account ID] [--mailbox ID] [--rows messages|threads] [--events N]",
        "                    (sync, then sync again on every WebSocket push; N syncs then exit)",
        "  axismundi blob-upload ... --file PATH [--type MEDIA_TYPE] [--account ID]",
        "  axismundi blob-download ... --id BLOB --out PATH [--type MEDIA_TYPE] [--name NAME]",
        "  axismundi blob-get ... --id BLOB [--properties data:asText,size,digest:sha-256] [--offset N] [--length N]",
        "  axismundi blob-compose ... --text PREFIX --id BLOB [--offset N] [--length N] [--type MEDIA_TYPE]",
        "  axismundi blob-lookup ... --id BLOB --types Email,Thread",
        "  axismundi mail-thread ... [--account ID] --id THREAD_ID [--db PATH --profile ID] (every message, no bodies)",
        "  axismundi mail-sync ... --db PATH --profile ID [--account ID] [--mailbox ID] [--rows messages|threads]",
        "  axismundi mail-offline --db PATH --profile ID --account ID [--mailbox ID [--rows messages|threads] | --id EMAIL_ID]",
        "  axismundi todo      --session-url URL [--user NAME] [--secret SECRET] [--account ID]",
        "  axismundi changes   ... [--account ID] [--since STATE]",
        "  axismundi todo-set  ... [--account ID]  (loopback development server only)",
        "  axismundi contacts  ... --db PATH --profile ID [--account ID] (sync and list)",
        "  axismundi contacts-offline --db PATH --profile ID --account ID (no credentials/network)",
        "",
        "Credentials may come from AXJ_USER and AXJ_SECRET instead of the flags, which",
        "keeps the secret out of the shell history and the process list.",
        "The Session URL is the only address this tool is told; apiUrl, the accounts and",
        "the capabilities all come from what the server advertises.",
    ]
    .join("\n")
}

fn options(args: &[String]) -> Result<Options, String> {
    let mut session_url = std::env::var("AXJ_SESSION_URL").unwrap_or_default();
    let mut user = std::env::var("AXJ_USER").unwrap_or_default();
    let mut secret = std::env::var("AXJ_SECRET").unwrap_or_default();
    let mut account = None;
    let mut since = None;
    let mut db = None;
    let mut profile = None;
    let mut id = None;
    let mut mailbox = None;
    let mut rows = None;
    let mut events = None;
    let mut file = None;
    let mut out = None;
    let mut media_type = None;
    let mut name = None;
    let mut properties = None;
    let mut offset = None;
    let mut length = None;
    let mut types = None;
    let mut text = None;
    let mut rest = args.iter();
    while let Some(flag) = rest.next() {
        let value = || {
            rest.clone()
                .next()
                .cloned()
                .ok_or(format!("{flag} needs a value"))
        };
        match flag.as_str() {
            "--session-url" => session_url = value()?,
            "--user" => user = value()?,
            "--secret" => secret = value()?,
            "--account" => account = Some(value()?),
            "--since" => since = Some(value()?),
            "--db" => db = Some(value()?),
            "--profile" => profile = Some(value()?),
            "--id" => id = Some(value()?),
            "--mailbox" => mailbox = Some(value()?),
            "--rows" => rows = Some(value()?),
            "--events" => events = Some(value()?),
            "--file" => file = Some(value()?),
            "--out" => out = Some(value()?),
            "--type" => media_type = Some(value()?),
            "--name" => name = Some(value()?),
            "--properties" => properties = Some(value()?),
            "--offset" => offset = Some(value()?),
            "--length" => length = Some(value()?),
            "--types" => types = Some(value()?),
            "--text" => text = Some(value()?),
            other => return Err(format!("unknown argument: {other}")),
        }
        if flag.starts_with("--") {
            rest.next();
        }
    }
    if session_url.is_empty() {
        return Err("a Session URL is required (--session-url or AXJ_SESSION_URL)".into());
    }
    if user.is_empty() || secret.is_empty() {
        return Err("credentials are required (--user/--secret or AXJ_USER/AXJ_SECRET)".into());
    }
    Ok(Options {
        session_url,
        user,
        secret,
        account,
        since,
        db,
        profile,
        id,
        mailbox,
        rows,
        events,
        file,
        out,
        media_type,
        name,
        properties,
        offset,
        length,
        types,
        text,
    })
}

fn describe(session: &Session) {
    if let Some(reason) = session.empty_account_diagnostic() {
        println!("session note  {reason}");
    }
    println!("apiUrl        {}", session.api_url);
    println!("state         {}", session.state);
    println!(
        "capabilities  {}",
        session
            .capabilities
            .keys()
            .cloned()
            .collect::<Vec<_>>()
            .join(", ")
    );
    for (id, account) in &session.accounts {
        let caps: Vec<&str> = account
            .account_capabilities
            .keys()
            .map(String::as_str)
            .collect();
        println!(
            "account {id}   name={:?} personal={} readOnly={} capabilities=[{}]",
            account.name,
            account.is_personal,
            account.is_read_only,
            caps.join(", ")
        );
    }
    for (capability, id) in &session.primary_accounts {
        println!("primary       {capability} -> {id}");
    }
}

/// Choose the account to query, preferring the server's stated primary.
fn choose(session: &Session, capability: &str, requested: Option<&str>) -> Result<String, String> {
    if let Some(reason) = session.empty_account_diagnostic() { return Err(reason.into()); }
    let advertised = session.accounts_with(capability);
    if let Some(wanted) = requested {
        // An account the caller names still has to advertise the capability.
        return if advertised.contains(&wanted) {
            Ok(wanted.to_string())
        } else {
            Err(format!("account {wanted} does not advertise {capability}"))
        };
    }
    if let Some(primary) = session.primary_account(capability) {
        if advertised.contains(&primary) {
            return Ok(primary.to_string());
        }
    }
    match advertised.as_slice() {
        [only] => Ok((*only).to_string()),
        [] => Err(format!("no account advertises {capability}")),
        many => Err(format!(
            "several accounts advertise {capability}: {}. Name one with --account",
            many.join(", ")
        )),
    }
}

/// Report a batch, treating a method-level error as a failure even on HTTP 200.
fn report(batch: &axismundi_jmap_core::Batch) -> bool {
    if let Some(state) = &batch.session_state {
        println!("sessionState  {state}");
    }
    for invocation in &batch.method_responses {
        if invocation.is_error() {
            println!(
                "ERROR  {}  type={}",
                invocation.call_id,
                invocation.error_type().unwrap_or("(none)")
            );
            continue;
        }
        let ids = invocation
            .arguments
            .get("list")
            .and_then(Value::as_array)
            .map(|rows| rows.len())
            .unwrap_or(0);
        println!(
            "{}  callId={}  state={}  list={}",
            invocation.name,
            invocation.call_id,
            invocation
                .arguments
                .get("state")
                .or_else(|| invocation.arguments.get("newState"))
                .and_then(Value::as_str)
                .unwrap_or("(none)"),
            ids
        );
        if let Some(rows) = invocation.arguments.get("list").and_then(Value::as_array) {
            for row in rows {
                println!("    {row}");
            }
        }
    }
    batch.errors().is_empty()
}

fn run() -> Result<bool, String> {
    let argv: Vec<String> = std::env::args().skip(1).collect();
    let (command, rest) = argv.split_first().ok_or_else(usage)?;
    if command == "discover" {
        let (input, flags) = rest.split_first().ok_or("usage: axismundi discover HOST_OR_URL [--jmap] [--wordpress] [--activitypub]")?;
        if flags.iter().any(|f| !matches!(f.as_str(),"--jmap"|"--wordpress"|"--activitypub")) { return Err("unknown discovery option".into()); }
        let options=if flags.is_empty() {DiscoveryOptions::default()} else {DiscoveryOptions {
            wordpress:flags.iter().any(|f|f=="--wordpress"), jmap:flags.iter().any(|f|f=="--jmap"), activitypub:flags.iter().any(|f|f=="--activitypub") }};
        let result = discover_selected(input, options)?;
        println!("base          {}", result.base);
        print_probe("wordpress", result.wordpress, |details| {
            format!(
                "name={:?} via={} namespaces={}",
                details.name,
                details.found_by,
                details.namespaces.join(", ")
            )
        });
        print_probe("jmap", result.jmap, |details| {
            let change = if details.origin_changed {
                " (different origin)"
            } else {
                ""
            };
            format!("session={}{change}", details.session_url)
        });
        print_probe("activitypub", result.activitypub, |details| {
            format!(
                "protocols=[{}] software={}",
                details.protocols.join(", "),
                details.software.as_deref().unwrap_or("(not advertised)")
            )
        });
        print_probe("ap-session", result.activitypub_session, |details| {
            format!("session={} (/.well-known/activitypub)", details.session_url)
        });
        return Ok(true);
    }
    if matches!(command.as_str(), "contacts-offline" | "mail-offline") {
        let mut flags = std::collections::BTreeMap::new();
        if rest.len() % 2 != 0 {
            return Err("expected --db PATH --profile ID --account ID".into());
        }
        for pair in rest.chunks(2) {
            if !(matches!(pair[0].as_str(), "--db" | "--profile" | "--account")
                || (command == "mail-offline"
                    && matches!(pair[0].as_str(), "--mailbox" | "--id" | "--rows")))
                || flags.insert(pair[0].as_str(), pair[1].as_str()).is_some()
            {
                return Err("invalid offline argument".into());
            }
        }
        let get = |name| {
            flags
                .get(name)
                .copied()
                .ok_or_else(|| format!("{name} is required"))
        };
        let path = get("--db")?;
        if !std::path::Path::new(path).is_file() {
            return Err("offline cache does not exist".into());
        }
        if command == "mail-offline" {
            if flags.contains_key("--id")
                && (flags.contains_key("--mailbox") || flags.contains_key("--rows"))
            {
                return Err("--id reads one message; it takes no --mailbox or --rows".into());
            }
            let store = axismundi_jmap_core::mail::cache::Store::open(path, get("--profile")?)
                .map_err(|e| e.to_string())?;
            let account = get("--account")?;
            let result = if let Some(id) = flags.get("--id") {
                store.email(account, id)
            } else {
                store.view(account, flags.get("--mailbox").copied(), rows(flags.get("--rows").copied())?)
            }.map_err(|e| e.to_string())?;
            println!("{}", serde_json::to_string_pretty(&result).map_err(|e| e.to_string())?);
            return Ok(true);
        }
        let store = axismundi_jmap_core::contacts::Store::open(path, get("--profile")?)
            .map_err(|e| e.to_string())?;
        let account = get("--account")?;
        println!(
            "{}",
            serde_json::to_string_pretty(&json!({
                "addressBooks":store.list(account,"AddressBook").map_err(|e|e.to_string())?,
                "contactCards":store.list(account,"ContactCard").map_err(|e|e.to_string())?
            }))
            .map_err(|e| e.to_string())?
        );
        return Ok(true);
    }
    let options = options(rest)?;
    let credentials = Credentials {
        user: options.user,
        secret: options.secret,
    };
    let probe_origin = if command == "todo-set" {
        Some(todo_set::loopback_origin(&options.session_url)?)
    } else {
        None
    };
    let client = if probe_origin.is_some() {
        Client::without_redirects(credentials)
    } else {
        Client::new(credentials)
    };
    let (session, session_document) = client
        .session_document(&options.session_url)
        .map_err(explain)?;
    if command.starts_with("blob-") {
        return blob_command(&client, &session, command, BlobFlags {
            account: options.account.as_deref(),
            id: options.id.as_deref(),
            file: options.file.as_deref(),
            out: options.out.as_deref(),
            media_type: options.media_type.as_deref(),
            name: options.name.as_deref(),
            properties: options.properties.as_deref(),
            offset: options.offset.as_deref(),
            length: options.length.as_deref(),
            types: options.types.as_deref(),
            text: options.text.as_deref(),
        })
        .map(|()| true);
    }
    if command == "mail-watch" {
        return watch(&client, &session, Watch {
            account: options.account.as_deref(),
            db: options.db.as_deref(),
            profile: options.profile.as_deref(),
            mailbox: options.mailbox.as_deref(),
            rows: options.rows.as_deref(),
            events: options.events.as_deref(),
        })
        .map(|()| true);
    }
    if command == "mail-sync" || command == "mail-more" || (matches!(command.as_str(), "mail-read" | "mail-thread") && options.db.is_some()) {
        use axismundi_jmap_core::mail::{self, cache};
        let account = mail::choose_account(&session, options.account.as_deref()).map_err(explain)?;
        let path = options.db.as_deref().ok_or("--db is required")?;
        let profile = options.profile.as_deref().ok_or("--profile is required")?;
        let mut store = cache::Store::open(path, profile).map_err(|e| e.to_string())?;
        let result = if command == "mail-sync" {
            cache::sync(&client, &session, &mut store, &account, options.mailbox.as_deref(),
                rows(options.rows.as_deref())?)
        } else if command == "mail-more" {
            cache::more(&client, &session, &mut store, &account, options.mailbox.as_deref(),
                rows(options.rows.as_deref())?)
        } else if command == "mail-thread" {
            cache::thread(&client, &session, &mut store, &account,
                options.id.as_deref().ok_or("--id is required")?)
        } else {
            cache::read(&client, &session, &mut store, &account,
                options.id.as_deref().ok_or("--id is required")?)
        }.map_err(|e| e.to_string())?;
        println!("{}", serde_json::to_string_pretty(&result).map_err(|e| e.to_string())?);
        return Ok(true);
    }
    if matches!(command.as_str(), "mailboxes" | "mail-inbox" | "mail-read" | "mail-thread") {
        use axismundi_jmap_core::mail;
        let account = mail::choose_account(&session, options.account.as_deref()).map_err(explain)?;
        let result = match command.as_str() {
            "mailboxes" => mail::mailboxes(&client, &session, &account),
            "mail-inbox" => mail::inbox(&client, &session, &account),
            "mail-thread" => mail::thread(&client, &session, &account, options.id.as_deref().ok_or("--id is required")?),
            _ => mail::read(&client, &session, &account, options.id.as_deref().ok_or("--id is required")?),
        }.map_err(explain)?;
        println!("{}", serde_json::to_string_pretty(&result).map_err(|e| e.to_string())?);
        return Ok(true);
    }
    if command == "contacts" {
        let db = options.db.as_deref().ok_or("--db is required")?;
        let profile = options.profile.as_deref().ok_or("--profile is required")?;
        let account =
            axismundi_jmap_core::contacts::choose_account(&session, options.account.as_deref())
                .map_err(|e| e.to_string())?;
        let mut store =
            axismundi_jmap_core::contacts::Store::open(db, profile).map_err(|e| e.to_string())?;
        store
            .remember_session(&client, &session, &session_document)
            .map_err(|e| e.to_string())?;
        for kind in ["AddressBook", "ContactCard"] {
            axismundi_jmap_core::contacts::sync_type(&client, &session, &mut store, &account, kind)
                .map_err(|e| e.to_string())?;
        }
        println!(
            "{}",
            serde_json::to_string_pretty(&json!({"accountId":account,
                "addressBooks":store.list(&account,"AddressBook").map_err(|e|e.to_string())?,
                "contactCards":store.list(&account,"ContactCard").map_err(|e|e.to_string())?
            }))
            .map_err(|e| e.to_string())?
        );
        return Ok(true);
    }
    if let Some(origin) = probe_origin {
        if todo_set::loopback_origin(&session.api_url)? != origin {
            return Err("Todo write probe requires a same-origin apiUrl".into());
        }
        let account = choose(&session, TODO, options.account.as_deref())?;
        todo_set::run(&client, &session, &account)?;
        return Ok(true);
    }
    describe(&session);
    if command == "session" {
        return Ok(true);
    }
    if command == "echo" {
        let nonce = std::time::SystemTime::now()
            .duration_since(std::time::UNIX_EPOCH)
            .map_err(|error| error.to_string())?
            .as_nanos();
        let call_id = format!("echo-{}-{nonce}", std::process::id());
        client
            .echo(
                &session,
                json!({"nested": ["\u{d55c}\u{ae00}", 42, null, {"ok": true}]}),
                &call_id,
            )
            .map_err(explain)?;
        println!("PASS Core/echo callId={call_id}: arguments match exactly");
        return Ok(true);
    }
    if command != "todo" && command != "changes" {
        return Err(usage());
    }
    let account = choose(&session, TODO, options.account.as_deref())?;
    println!("querying      account={account} capability={TODO}");
    let batch = client
        .request(
            &session,
            &[CORE, TODO],
            vec![
                ("TodoList/get", json!({ "accountId": account }), "l"),
                ("Todo/get", json!({ "accountId": account }), "t"),
            ],
        )
        .map_err(explain)?;
    let healthy = report(&batch);
    if command == "todo" {
        return Ok(healthy);
    }
    // A sync asks what changed since a state the client already holds. Taking that state
    // from the Get above keeps the client from inventing one, and an unchanged store is
    // expected to answer with no ids rather than with an error.
    let since = match options.since {
        Some(state) => state,
        None => batch
            .first_named("Todo/get")
            .and_then(|i| i.arguments.get("state"))
            .and_then(Value::as_str)
            .ok_or("Todo/get returned no state to sync from")?
            .to_string(),
    };
    println!("sinceState    {since}");
    let changes = client
        .request(
            &session,
            &[CORE, TODO],
            vec![(
                "Todo/changes",
                json!({ "accountId": account, "sinceState": since }),
                "c",
            )],
        )
        .map_err(explain)?;
    // cannotCalculateChanges is a legitimate answer, not a transport failure: the server
    // is telling the client to refetch the type. It still counts as the call failing.
    for invocation in changes.errors() {
        if invocation.error_type() == Some("cannotCalculateChanges") {
            println!("REFETCH  the server cannot compute this delta; re-read the type in full");
        }
    }
    Ok(healthy && report(&changes))
}

fn print_probe<T>(label: &str, result: Probe<T>, details: impl FnOnce(T) -> String) {
    let (state, reason) = match result {
        Probe::Skipped => ("not requested",String::new()),
        Probe::Found(info) => ("yes", details(info)),
        Probe::LoginRequired(info) => ("login required", details(info)),
        Probe::NotFound(reason) => ("not advertised", reason),
        Probe::Unconfirmed(reason) => ("unconfirmed", reason),
    };
    println!("{label:14}{state:16}{reason}");
}

/// Keep the four failure kinds apart in what the operator reads.
fn explain(error: Error) -> String {
    match &error {
        Error::Transport(_) => format!("{error}\n  the server was never reached"),
        Error::Problem { .. } => format!("{error}\n  the server answered with a JMAP problem"),
        Error::Status {
            status: 401 | 403, ..
        } => {
            format!("{error}\n  authentication was refused; check the user and the secret")
        }
        _ => error.to_string(),
    }
}

fn main() -> ExitCode {
    match run() {
        Ok(true) => ExitCode::SUCCESS,
        Ok(false) => ExitCode::from(2),
        Err(message) => {
            eprintln!("{message}");
            ExitCode::FAILURE
        }
    }
}

/// One line per sync, so a person watching sees each push land without reading JSON.
fn summary(view: &serde_json::Value) -> String {
    format!(
        "mailbox={} rows={} queryState={} stale={}",
        view["mailboxId"].as_str().unwrap_or("?"),
        view["emails"].as_array().map_or(0, Vec::len),
        view["query"]["queryState"].as_str().unwrap_or("-"),
        view["queryStale"]
    )
}

/// The flags `mail-watch` reads. The credentials are already in the Client by then.
struct Watch<'a> {
    account: Option<&'a str>,
    db: Option<&'a str>,
    profile: Option<&'a str>,
    mailbox: Option<&'a str>,
    rows: Option<&'a str>,
    events: Option<&'a str>,
}

/// Keeps one mailbox listing current: sync, then sync again whenever the server pushes a change to
/// Email or Mailbox in this account.
fn watch(client: &Client, session: &Session, options: Watch<'_>) -> Result<(), String> {
    use axismundi_jmap_core::mail::{self, cache};
    use std::time::Duration;
    let account = mail::choose_account(session, options.account).map_err(explain)?;
    let path = options.db.ok_or("--db is required")?;
    let profile = options.profile.ok_or("--profile is required")?;
    let limit: Option<usize> = options
        .events
        .map(|n| n.parse().map_err(|_| "--events takes a number".to_string()))
        .transpose()?;
    let listing = rows(options.rows)?;
    let mut store = cache::Store::open(path, profile).map_err(|e| e.to_string())?;
    watch_loop(
        &account,
        limit,
        |push_state| {
            client.push_channel(session, Some(&["Email", "Mailbox"]), push_state, Duration::from_secs(30))
        },
        |why| {
            let view = cache::sync(client, session, &mut store, &account, options.mailbox, listing)
                .map_err(|e| e.to_string())?;
            println!("{why}: {}", summary(&view));
            Ok(())
        },
        std::thread::sleep,
    )
}

/// What `watch_loop` needs from a push connection; the real one is the WebSocket channel.
trait Push {
    fn next(&mut self) -> Result<axismundi_jmap_core::websocket::Event, Error>;
    fn close(self);
}

impl Push for axismundi_jmap_core::websocket::PushChannel {
    fn next(&mut self) -> Result<axismundi_jmap_core::websocket::Event, Error> {
        axismundi_jmap_core::websocket::PushChannel::next(self)
    }
    fn close(self) {
        axismundi_jmap_core::websocket::PushChannel::close(self)
    }
}

/// The order that keeps a push-driven listing from missing a change: subscribe first, then catch up.
///
/// A push only reports changes made after the subscription exists. So every connection — the first
/// and every reconnect — is opened before the sync that catches up, never after it: a change that
/// lands between the two is either already in the catch-up or arrives as a push. Syncing first and
/// subscribing second leaves a gap that only the next unrelated push would close, and a run of failed
/// connection attempts makes that gap as long as the outage. The sync before the first connection is
/// only there to show the cached listing at once; it is not relied on.
///
/// `push_state` from the last connection is offered on reconnect, but the catch-up sync does not
/// depend on the server honouring it. Only transport failures are retried; a server that refuses the
/// upgrade, the subprotocol or the push request will refuse it again, and that ends the loop.
fn watch_loop<C: Push>(
    account: &str,
    limit: Option<usize>,
    mut connect: impl FnMut(Option<&str>) -> Result<C, Error>,
    mut sync: impl FnMut(&str) -> Result<(), String>,
    mut wait: impl FnMut(std::time::Duration),
) -> Result<(), String> {
    use axismundi_jmap_core::websocket::Event;
    use std::time::Duration;
    sync("initial")?;
    let mut push_state: Option<String> = None;
    let mut synced = 0usize;
    let mut backoff = Duration::from_secs(1);
    loop {
        if limit.is_some_and(|n| synced >= n) {
            return Ok(());
        }
        let mut channel = match connect(push_state.as_deref()) {
            Ok(channel) => channel,
            Err(Error::Transport(e)) => {
                eprintln!("push connect failed ({e}); retrying in {}s", backoff.as_secs());
                wait(backoff);
                backoff = (backoff * 2).min(Duration::from_secs(60));
                continue;
            }
            Err(e) => return Err(explain(e)),
        };
        eprintln!("push connected");
        backoff = Duration::from_secs(1);
        sync("catch-up")?;
        loop {
            match channel.next() {
                Ok(Event::Idle) => continue,
                Ok(Event::StateChange { changed, push_state: state }) => {
                    if state.is_some() {
                        push_state = state;
                    }
                    let Some(types) = changed.get(account) else { continue };
                    let named: Vec<&str> = types
                        .keys()
                        .map(String::as_str)
                        .filter(|t| matches!(*t, "Email" | "Mailbox"))
                        .collect();
                    if named.is_empty() {
                        continue;
                    }
                    sync(&format!("push {}", named.join("+")))?;
                    synced += 1;
                    if limit.is_some_and(|n| synced >= n) {
                        channel.close();
                        return Ok(());
                    }
                }
                Err(Error::Transport(e)) => {
                    eprintln!("push dropped ({e}); reconnecting");
                    break;
                }
                Err(e) => return Err(explain(e)),
            }
        }
    }
}

#[cfg(test)]
mod watch_tests {
    use super::*;
    use axismundi_jmap_core::websocket::Event;
    use std::{cell::RefCell, collections::{BTreeMap, VecDeque}, rc::Rc};

    type Log = Rc<RefCell<Vec<String>>>;

    struct Script {
        events: VecDeque<Result<Event, Error>>,
    }

    impl Push for Script {
        fn next(&mut self) -> Result<Event, Error> {
            self.events.pop_front().unwrap_or(Err(Error::Transport("script ended".into())))
        }
        fn close(self) {}
    }

    fn change(state: &str) -> Result<Event, Error> {
        let mut changed = BTreeMap::new();
        changed.insert("b".to_owned(), BTreeMap::from([("Email".to_owned(), state.to_owned())]));
        Ok(Event::StateChange { changed, push_state: Some(state.to_owned()) })
    }

    /// Mail that arrives while the connection is down — here, during two failed attempts and a
    /// drop — reaches the listing through a sync that runs after the next subscription is open.
    #[test]
    fn every_connection_is_opened_before_the_sync_that_catches_up() {
        let log: Log = Rc::default();
        let mut attempts: VecDeque<Result<Script, Error>> = VecDeque::from([
            Err(Error::Transport("refused".into())),
            Err(Error::Transport("refused".into())),
            Ok(Script { events: VecDeque::from([Ok(Event::Idle), change("p1"), Err(Error::Transport("dropped".into()))]) }),
            Ok(Script { events: VecDeque::from([change("p2")]) }),
        ]);
        let offered: Rc<RefCell<Vec<Option<String>>>> = Rc::default();
        let (l1, l2, l3, o) = (log.clone(), log.clone(), log.clone(), offered.clone());
        watch_loop(
            "b",
            Some(2),
            move |state| {
                o.borrow_mut().push(state.map(str::to_owned));
                let attempt = attempts.pop_front().unwrap();
                l1.borrow_mut().push(if attempt.is_ok() { "connect" } else { "connect failed" }.into());
                attempt
            },
            move |why| {
                l2.borrow_mut().push(format!("sync {why}"));
                Ok(())
            },
            move |_| l3.borrow_mut().push("wait".into()),
        )
        .unwrap();
        assert_eq!(
            *log.borrow(),
            [
                "sync initial",
                "connect failed", "wait",
                "connect failed", "wait",
                "connect", "sync catch-up", "sync push Email",
                "connect", "sync catch-up", "sync push Email",
            ]
        );
        // The reconnect offers the pushState the dropped connection last delivered.
        assert_eq!(*offered.borrow(), [None, None, None, Some("p1".to_owned())]);
    }

    #[test]
    fn a_refusal_that_will_repeat_ends_the_loop_instead_of_retrying() {
        let result = watch_loop::<Script>(
            "b",
            None,
            |_| Err(Error::Shape("WebSocket server did not accept the jmap subprotocol".into())),
            |_| Ok(()),
            |_| panic!("must not wait to retry a refusal"),
        );
        assert!(result.is_err());
    }
}

/// The flags the blob commands read.
struct BlobFlags<'a> {
    account: Option<&'a str>,
    id: Option<&'a str>,
    file: Option<&'a str>,
    out: Option<&'a str>,
    media_type: Option<&'a str>,
    name: Option<&'a str>,
    properties: Option<&'a str>,
    offset: Option<&'a str>,
    length: Option<&'a str>,
    types: Option<&'a str>,
    text: Option<&'a str>,
}

fn number(value: Option<&str>, flag: &str) -> Result<Option<u64>, String> {
    value
        .map(|v| v.parse().map_err(|_| format!("{flag} takes a number")))
        .transpose()
}

/// The blob commands. The account is the Session's Mail account unless one is named, since mail
/// attachments are what these exist for first.
fn blob_command(client: &Client, session: &Session, command: &str, flags: BlobFlags<'_>) -> Result<(), String> {
    use axismundi_jmap_core::{blob::Source, mail};
    let account = mail::choose_account(session, flags.account).map_err(explain)?;
    let id = || flags.id.ok_or("--id is required");
    let media_type = flags.media_type.unwrap_or("application/octet-stream");
    let result = match command {
        "blob-upload" => {
            let bytes = std::fs::read(flags.file.ok_or("--file is required")?).map_err(|e| e.to_string())?;
            let up = client.upload(session, &account, media_type, &bytes).map_err(explain)?;
            json!({"accountId":account,"blobId":up.blob_id,"type":up.media_type,"size":up.size})
        }
        "blob-download" => {
            let out = flags.out.ok_or("--out is required")?;
            let bytes = client
                .download(session, &account, id()?, media_type, flags.name.unwrap_or("blob"), 64 * 1024 * 1024)
                .map_err(explain)?;
            std::fs::write(out, &bytes).map_err(|e| e.to_string())?;
            json!({"accountId":account,"blobId":id()?,"written":out,"size":bytes.len()})
        }
        "blob-get" => {
            let properties: Vec<&str> = flags
                .properties
                .unwrap_or("data:asText,size,digest:sha-256")
                .split(',')
                .collect();
            client
                .blob_get(session, &account, &[id()?], &properties,
                    number(flags.offset, "--offset")?, number(flags.length, "--length")?)
                .map_err(explain)?
        }
        "blob-compose" => {
            let sources = vec![
                Source::Text(flags.text.ok_or("--text is required")?),
                Source::Blob { id: id()?, offset: number(flags.offset, "--offset")?, length: number(flags.length, "--length")? },
            ];
            let (created, failed) = client
                .blob_upload(session, &account, &[("c1", flags.media_type, sources)])
                .map_err(explain)?;
            json!({"accountId":account,"created":created,"notCreated":failed})
        }
        "blob-lookup" => {
            let types: Vec<&str> = flags.types.ok_or("--types is required")?.split(',').collect();
            let found = client.blob_lookup(session, &account, &types, &[id()?]).map_err(explain)?;
            json!({"accountId":account,"matched":found})
        }
        other => return Err(format!("unknown command: {other}")),
    };
    println!("{}", serde_json::to_string_pretty(&result).map_err(|e| e.to_string())?);
    Ok(())
}
