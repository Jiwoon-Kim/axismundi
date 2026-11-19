//! Explicit, idempotent local-server GUI fixtures. Never addresses external recipients.
use axismundi_jmap_core::{CORE_CAPABILITY, Client, Credentials, Session, mail};
use serde_json::{Value, json};
use std::{
    collections::{BTreeMap, BTreeSet},
    error::Error,
    thread,
    time::Duration,
};

type Result<T> = std::result::Result<T, Box<dyn Error>>;
const SUBMISSION: &str = "urn:ietf:params:jmap:submission";
const PREFIX: &str = "[Axismundi probe v1]";
const PROPERTIES: &[&str] = &[
    "id",
    "threadId",
    "messageId",
    "inReplyTo",
    "references",
    "subject",
    "mailboxIds",
    "keywords",
];

struct Account {
    client: Client,
    session: Session,
    id: String,
    address: String,
    identity: String,
    boxes: BTreeMap<String, String>,
}

impl Account {
    fn connect(url: &str, user_env: &str, secret_env: &str, address: &str) -> Result<Self> {
        let source = url::Url::parse(url)?;
        if !matches!(source.host_str(), Some("localhost" | "127.0.0.1" | "[::1]")) {
            return Err("probe seeding requires an explicit loopback server".into());
        }
        let client = Client::new(Credentials {
            user: std::env::var(user_env)?,
            secret: std::env::var(secret_env)?,
        });
        let session = client.session(url)?;
        if url::Url::parse(&session.api_url)?.origin() != source.origin() {
            return Err("probe API must remain on the approved origin".into());
        }
        let id = mail::choose_account(&session, None)?;
        if session.accounts[&id].is_read_only
            || !session.accounts[&id]
                .account_capabilities
                .contains_key(SUBMISSION)
        {
            return Err("probe requires a writable Mail and Submission account".into());
        }
        let mut account = Self {
            client,
            session,
            id,
            address: address.into(),
            identity: String::new(),
            boxes: BTreeMap::new(),
        };
        let identities = account.call("Identity/get", json!({}))?;
        account.identity = identities["list"]
            .as_array()
            .ok_or("Identity/get missing list")?
            .iter()
            .find(|v| v["email"] == address)
            .and_then(|v| v["id"].as_str())
            .ok_or("expected local sender Identity not found")?
            .to_owned();
        let boxes = mail::mailboxes(&account.client, &account.session, &account.id)?;
        for role in ["inbox", "sent", "drafts"] {
            let ids: Vec<_> = boxes["list"]
                .as_array()
                .ok_or("Mailbox/get missing list")?
                .iter()
                .filter(|v| v["role"] == role)
                .collect();
            let [item] = ids.as_slice() else {
                return Err(format!("no unambiguous {role} mailbox").into());
            };
            account.boxes.insert(
                role.into(),
                item["id"].as_str().ok_or("mailbox missing id")?.into(),
            );
        }
        Ok(account)
    }

    fn call(&self, method: &str, mut args: Value) -> Result<Value> {
        args["accountId"] = json!(self.id);
        let using = [CORE_CAPABILITY, mail::CAPABILITY, SUBMISSION];
        let core = self
            .session
            .capabilities
            .get(CORE_CAPABILITY)
            .ok_or("missing Core capability")?;
        if core["maxCallsInRequest"].as_u64().unwrap_or(0) < 1 {
            return Err("invalid call limit".into());
        }
        let payload = json!({"using":using,"methodCalls":[[method,args,"probe"]]});
        if payload.to_string().len() as u64 > core["maxSizeRequest"].as_u64().unwrap_or(0) {
            return Err("probe request exceeds server byte limit".into());
        }
        if let Some(ids) = args.get("ids").and_then(Value::as_array) {
            if ids.len() as u64 > core["maxObjectsInGet"].as_u64().unwrap_or(0) {
                return Err("probe get exceeds server object limit".into());
            }
        }
        let batch = self
            .client
            .request(&self.session, &using, vec![(method, args, "probe")])?;
        let response = batch.response("probe", method)?;
        if response.arguments["accountId"] != self.id {
            return Err("probe response account mismatch".into());
        }
        if method.ends_with("/set") && !response.set_outcome()?.fully_applied() {
            return Err(format!("{method} partially rejected: {}", response.arguments).into());
        }
        Ok(response.arguments.clone())
    }

    fn find(&self, message_id: &str) -> Result<Option<Value>> {
        let count = self.session.capabilities[CORE_CAPABILITY]["maxObjectsInGet"]
            .as_u64()
            .ok_or("missing get limit")?
            .min(50);
        if count == 0 {
            return Err("invalid get limit".into());
        }
        let mut found = None;
        let mut query_state = None;
        for page in 0..16 {
            // Use a portable subject query, then compare parsed Message-IDs exactly.
            let query = self.call(
                "Email/query",
                json!({"filter":{"subject":PREFIX},"limit":count,"position":page*count}),
            )?;
            let state = query["queryState"].as_str().ok_or("query missing state")?;
            if query_state
                .as_deref()
                .is_some_and(|previous| previous != state)
            {
                return Err(
                    "probe query changed during scan; inspect or retry without resubmission".into(),
                );
            }
            query_state = Some(state.to_owned());
            let ids = query["ids"].as_array().ok_or("query missing ids")?;
            if !ids.is_empty() {
                let read = self.call("Email/get", json!({"ids":ids,"properties":PROPERTIES}))?;
                let rows = read["list"].as_array().ok_or("get missing list")?;
                let expected: BTreeSet<_> = ids
                    .iter()
                    .map(|id| id.as_str().ok_or("invalid query id"))
                    .collect::<std::result::Result<_, _>>()?;
                let actual: BTreeSet<_> = rows
                    .iter()
                    .map(|row| row["id"].as_str().ok_or("get missing id"))
                    .collect::<std::result::Result<_, _>>()?;
                if actual != expected
                    || actual.len() != rows.len()
                    || !read["notFound"].as_array().is_some_and(Vec::is_empty)
                {
                    return Err(
                        "incomplete probe get; will not infer absent mail and resend".into(),
                    );
                }
                for row in rows {
                    if row["messageId"]
                        .as_array()
                        .is_some_and(|ids| ids.iter().any(|id| id == message_id))
                    {
                        if found.is_some() {
                            return Err(format!(
                                "duplicate probe Message-ID {message_id}; inspect before retry"
                            )
                            .into());
                        }
                        found = Some(row.clone());
                    }
                }
            }
            if (ids.len() as u64) < count {
                return Ok(found);
            }
        }
        Err("probe search exceeded bounded fixture scan; inspect before creating more".into())
    }

    fn create(
        &self,
        message_id: &str,
        subject: &str,
        text: &str,
        to: &str,
        references: &[String],
    ) -> Result<String> {
        let mut doc = json!({"messageId":[message_id],"subject":subject,"from":[{"email":self.address}],"to":[{"email":to}],
            "mailboxIds":{&self.boxes["drafts"]:true},"keywords":{"$draft":true},
            "textBody":[{"partId":"text","type":"text/plain"}],"bodyValues":{"text":{"value":text}}});
        if let Some(parent) = references.last() {
            doc["inReplyTo"] = json!([parent]);
            doc["references"] = json!(references);
        }
        let response = self.call("Email/set", json!({"create":{"message":doc}}))?;
        Ok(response["created"]["message"]["id"]
            .as_str()
            .ok_or("missing created Email id")?
            .into())
    }

    fn probe_box(&self) -> Result<String> {
        let response = mail::mailboxes(&self.client, &self.session, &self.id)?;
        let matching: Vec<_> = response["list"]
            .as_array()
            .ok_or("mailboxes missing list")?
            .iter()
            .filter(|v| v["name"] == "Axismundi Probe")
            .collect();
        match matching.as_slice() {
            [mailbox] => Ok(mailbox["id"].as_str().ok_or("missing mailbox id")?.into()),
            [] => {
                let created = self.call(
                    "Mailbox/set",
                    json!({"create":{"probe":{"name":"Axismundi Probe"}}}),
                )?;
                Ok(created["created"]["probe"]["id"]
                    .as_str()
                    .ok_or("missing created mailbox")?
                    .into())
            }
            _ => Err("multiple Axismundi Probe mailboxes; inspect before retry".into()),
        }
    }
}

fn send(
    sender: &Account,
    receiver: &Account,
    message_id: &str,
    subject: &str,
    body: &str,
    references: &[String],
) -> Result<()> {
    let existing_sender = sender.find(message_id)?;
    let existing_receiver = receiver.find(message_id)?;
    if existing_sender.is_some() || existing_receiver.is_some() {
        if existing_sender.is_none() || existing_receiver.is_none() {
            return Err(format!("partial probe {message_id}; will not blindly resubmit").into());
        }
        if let Some(email) = existing_sender.filter(|v| v["keywords"]["$draft"] == true) {
            let id = email["id"]
                .as_str()
                .ok_or("existing sender Email missing id")?;
            sender.call("Email/set",json!({"update":{id:{"mailboxIds":{&sender.boxes["sent"]:true},"keywords/$draft":null,"keywords/$seen":true}}}))?;
        }
        return Ok(());
    }
    let email = sender.create(message_id, subject, body, &receiver.address, references)?;
    // Print the durable ID before submission: an interrupted run must be inspected, not blindly retried.
    eprintln!(
        "Created probe Email {}:{email} Message-ID={message_id}",
        sender.id
    );
    let submission = sender.call(
        "EmailSubmission/set",
        json!({"create":{"send":{"emailId":email,"identityId":sender.identity,
        "envelope":{"mailFrom":{"email":sender.address},"rcptTo":[{"email":receiver.address}]}}}}),
    )?;
    let submission_id = submission["created"]["send"]["id"]
        .as_str()
        .ok_or("missing Submission id")?;
    for _ in 0..40 {
        if receiver.find(message_id)?.is_some() {
            let result = sender.call("EmailSubmission/get", json!({"ids":[submission_id]}))?;
            if result["list"][0]["deliveryStatus"][&receiver.address]["delivered"] != "yes" {
                thread::sleep(Duration::from_millis(500));
                continue;
            }
            sender.call("Email/set",json!({"update":{&email:{"mailboxIds":{&sender.boxes["sent"]:true},"keywords/$draft":null,"keywords/$seen":true}}}))?;
            return Ok(());
        }
        thread::sleep(Duration::from_millis(500));
    }
    Err(format!("probe delivery incomplete: sender={} Email={email} Submission={submission_id}; inspect before retry",sender.id).into())
}

fn run() -> Result<()> {
    if std::env::var("AXJ_SEED_MAIL_PROBE").as_deref() != Ok("1") {
        return Err("set AXJ_SEED_MAIL_PROBE=1 to persist GUI probe mail".into());
    }
    let url = std::env::var("AXJ_SESSION_URL")?;
    let admin = Account::connect(&url, "AXJ_USER", "AXJ_SECRET", "admin@example.org")?;
    let alice = Account::connect(
        &url,
        "AXJ_ALICE_USER",
        "AXJ_ALICE_SECRET",
        "alice@example.org",
    )?;
    if admin.id == alice.id {
        return Err("probe requires separate accounts".into());
    }
    let message_ids: Vec<String> = (1..=4)
        .map(|n| format!("axismundi-probe-v1-thread-{n}@example.org"))
        .collect();
    for (index, id) in message_ids.iter().enumerate() {
        let (sender, receiver) = if index % 2 == 0 {
            (&admin, &alice)
        } else {
            (&alice, &admin)
        };
        let subject = format!(
            "{}{PREFIX} Conversation",
            if index == 0 { "" } else { "Re: " }
        );
        let body = format!(
            "Probe conversation, message {} of 4.\n\nThis is local test mail between admin and Alice.\nUse it to inspect Inbox/Sent threading and reply references.",
            index + 1
        );
        send(sender, receiver, id, &subject, &body, &message_ids[..index])?;
    }
    send(
        &alice,
        &admin,
        "axismundi-probe-v1-independent@example.org",
        &format!("{PREFIX} Independent message"),
        "Independent conversation. It must not merge into the four-message reply chain.",
        &[],
    )?;
    let mut summary = Vec::new();
    for account in [&admin, &alice] {
        let probe_box = account.probe_box()?;
        let mut emails = Vec::new();
        for id in &message_ids {
            emails.push(account.find(id)?.ok_or("probe mail vanished")?);
        }
        let thread = emails[0]["threadId"].as_str().ok_or("missing threadId")?;
        if emails.iter().any(|v| v["threadId"] != thread) {
            return Err(format!("server did not group reply chain for {}", account.address).into());
        }
        let independent = account
            .find("axismundi-probe-v1-independent@example.org")?
            .ok_or("independent probe vanished")?;
        if independent["threadId"] == thread {
            return Err("independent probe incorrectly merged with reply chain".into());
        }
        let thread_doc = account.call("Thread/get", json!({"ids":[thread]}))?;
        let members = thread_doc["list"][0]["emailIds"]
            .as_array()
            .ok_or("Thread/get missing emailIds")?;
        if emails.iter().any(|v| !members.contains(&v["id"])) {
            return Err("Thread/get does not include every probe message".into());
        }
        let collapsed = account.call("Email/query",json!({"filter":{"subject":format!("{PREFIX} Conversation")},"collapseThreads":true,"limit":10}))?;
        if collapsed["ids"]
            .as_array()
            .ok_or("query missing ids")?
            .len()
            != 1
        {
            return Err("collapsed probe query is not one thread".into());
        }
        let root = emails[0]["id"].as_str().ok_or("root missing id")?;
        account.call(
            "Email/set",
            json!({"update":{root:{format!("mailboxIds/{probe_box}"):true}}}),
        )?;
        let draft_id = format!(
            "axismundi-probe-v1-draft-{}@example.org",
            if account.address == "admin@example.org" {
                "admin"
            } else {
                "alice"
            }
        );
        if account.find(&draft_id)?.is_none() {
            account.create(
                &draft_id,
                &format!("{PREFIX} Server draft - not sent"),
                "Unsent server Draft fixture. This is not a local client draft.",
                if account.address == admin.address {
                    &alice.address
                } else {
                    &admin.address
                },
                &[],
            )?;
        }
        let draft = account.find(&draft_id)?.ok_or("probe draft vanished")?;
        if draft["keywords"]["$draft"] != true
            || draft["mailboxIds"][&account.boxes["drafts"]] != true
        {
            return Err("existing probe draft was edited; inspect rather than overwrite".into());
        }
        summary.push(json!({"address":account.address,"accountId":account.id,"conversationThreadId":thread,
            "conversationEmailIds":emails.iter().map(|v|v["id"].clone()).collect::<Vec<_>>(),"serverThreadEmailIds":members,
            "independentThreadId":independent["threadId"],"collapsedRows":1,"probeMailboxId":probe_box,"serverDraftMessageId":draft_id}));
    }
    println!(
        "{}",
        serde_json::to_string_pretty(
            &json!({"fixture":"axismundi-probe-v1","accounts":summary,"retained":true})
        )?
    );
    Ok(())
}

fn main() -> std::process::ExitCode {
    match run() {
        Ok(()) => std::process::ExitCode::SUCCESS,
        Err(error) => {
            eprintln!("Mail probe: {error}");
            std::process::ExitCode::FAILURE
        }
    }
}
