//! A terminal front end over the JMAP core.
//!
//! The split is deliberate: everything that speaks the protocol lives in the core
//! crate, and this file only reads arguments and prints. A desktop front end can
//! then reuse the same core rather than reimplementing the exchange behind a window.

use std::process::ExitCode;

use axismundi_jmap_core::{Client, Credentials, Error, Session};
use serde_json::{json, Value};

const TODO: &str = "urn:com.example:jmap:todo";
const CORE: &str = "urn:ietf:params:jmap:core";

struct Options {
    session_url: String,
    user: String,
    secret: String,
    account: Option<String>,
    since: Option<String>,
}

fn usage() -> String {
    [
        "axj - a JMAP probe that learns everything from the Session resource",
        "",
        "usage:",
        "  axj session   --session-url URL [--user NAME] [--secret SECRET]",
        "  axj todo      --session-url URL [--user NAME] [--secret SECRET] [--account ID]",
        "  axj changes   ... [--account ID] [--since STATE]",
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
    let mut rest = args.iter();
    while let Some(flag) = rest.next() {
        let value = || rest.clone().next().cloned().ok_or(format!("{flag} needs a value"));
        match flag.as_str() {
            "--session-url" => session_url = value()?,
            "--user" => user = value()?,
            "--secret" => secret = value()?,
            "--account" => account = Some(value()?),
            "--since" => since = Some(value()?),
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
    Ok(Options { session_url, user, secret, account, since })
}

fn describe(session: &Session) {
    println!("apiUrl        {}", session.api_url);
    println!("state         {}", session.state);
    println!("capabilities  {}", session.capabilities.keys().cloned().collect::<Vec<_>>().join(", "));
    for (id, account) in &session.accounts {
        let caps: Vec<&str> = account.account_capabilities.keys().map(String::as_str).collect();
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
    let options = options(rest)?;
    let client = Client::new(Credentials { user: options.user, secret: options.secret });
    let session = client.session(&options.session_url).map_err(explain)?;
    describe(&session);
    if command == "session" {
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

/// Keep the four failure kinds apart in what the operator reads.
fn explain(error: Error) -> String {
    match &error {
        Error::Transport(_) => format!("{error}\n  the server was never reached"),
        Error::Problem { .. } => format!("{error}\n  the server answered with a JMAP problem"),
        Error::Status { status: 401 | 403, .. } => {
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
