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
        "  axismundi mail-sync ... --db PATH --profile ID [--account ID] [--mailbox ID]",
        "  axismundi mail-offline --db PATH --profile ID --account ID [--mailbox ID | --id EMAIL_ID]",
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
                "name={:?} namespaces={}",
                details.name,
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
                    && matches!(pair[0].as_str(), "--mailbox" | "--id")))
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
            if flags.contains_key("--id") && flags.contains_key("--mailbox") {
                return Err("choose --id or --mailbox, not both".into());
            }
            let store = axismundi_jmap_core::mail::cache::Store::open(path, get("--profile")?)
                .map_err(|e| e.to_string())?;
            let account = get("--account")?;
            let result = if let Some(id) = flags.get("--id") {
                store.email(account, id)
            } else {
                store.view(account, flags.get("--mailbox").copied())
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
    if command == "mail-sync" || (command == "mail-read" && options.db.is_some()) {
        use axismundi_jmap_core::mail::{self, cache};
        let account = mail::choose_account(&session, options.account.as_deref()).map_err(explain)?;
        let path = options.db.as_deref().ok_or("--db is required")?;
        let profile = options.profile.as_deref().ok_or("--profile is required")?;
        let mut store = cache::Store::open(path, profile).map_err(|e| e.to_string())?;
        let result = if command == "mail-sync" {
            cache::sync(&client, &session, &mut store, &account, options.mailbox.as_deref())
        } else {
            cache::read(&client, &session, &mut store, &account,
                options.id.as_deref().ok_or("--id is required")?)
        }.map_err(|e| e.to_string())?;
        println!("{}", serde_json::to_string_pretty(&result).map_err(|e| e.to_string())?);
        return Ok(true);
    }
    if matches!(command.as_str(), "mailboxes" | "mail-inbox" | "mail-read") {
        use axismundi_jmap_core::mail;
        let account = mail::choose_account(&session, options.account.as_deref()).map_err(explain)?;
        let result = match command.as_str() {
            "mailboxes" => mail::mailboxes(&client, &session, &account),
            "mail-inbox" => mail::inbox(&client, &session, &account),
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
