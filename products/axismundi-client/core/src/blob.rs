//! Binary data: the RFC 8620 upload and download endpoints, and the RFC 9404 Blob methods.
//!
//! The endpoints carry the bytes; the methods work on blobs already on the server — composing a new
//! blob from pieces, reading a slice or a digest, and finding which objects use a blob. Both kinds
//! send the login, so both are only used on the apiUrl origin.
use std::collections::BTreeMap;

use serde_json::{Value, json};

use crate::{Batch, CORE_CAPABILITY, Client, Error, Session};

pub const CAPABILITY: &str = "urn:ietf:params:jmap:blob";

/// RFC 6570 level 1: every character outside the unreserved set is percent-encoded, `/` included,
/// so a media type like `text/plain` cannot add a path segment to the download URL.
fn encode(value: &str) -> String {
    let mut out = String::with_capacity(value.len());
    for byte in value.bytes() {
        if byte.is_ascii_alphanumeric() || matches!(byte, b'-' | b'.' | b'_' | b'~') {
            out.push(byte as char);
        } else {
            out.push_str(&format!("%{byte:02X}"));
        }
    }
    out
}

/// Expands a Session URL template. Every variable the specification requires for that template must
/// be present, and nothing may be left unexpanded — a template with a variable this client does not
/// know would otherwise send a literal `{…}` to the server.
pub(crate) fn expand(template: &str, values: &[(&str, &str)]) -> Result<String, Error> {
    let mut out = String::new();
    let mut rest = template;
    let mut seen = Vec::new();
    while let Some(start) = rest.find('{') {
        out.push_str(&rest[..start]);
        let end = rest[start..]
            .find('}')
            .ok_or_else(|| Error::Shape("URL template has an unclosed variable".into()))?;
        let name = &rest[start + 1..start + end];
        let (_, value) = values
            .iter()
            .find(|(key, _)| *key == name)
            .ok_or_else(|| Error::Shape(format!("URL template has an unknown variable {name}")))?;
        out.push_str(&encode(value));
        seen.push(name);
        rest = &rest[start + end + 1..];
    }
    if rest.contains('}') {
        return Err(Error::Shape("URL template has a stray brace".into()));
    }
    out.push_str(rest);
    for (key, _) in values {
        if !seen.contains(key) {
            return Err(Error::Shape(format!("URL template lacks the required variable {key}")));
        }
    }
    Ok(out)
}

/// An endpoint URL that may receive the login: HTTP(S), no embedded credentials, and the apiUrl's
/// origin. The Session is trusted to name its endpoints, but not to send the password elsewhere.
fn on_api_origin(session: &Session, raw: &str) -> Result<url::Url, Error> {
    let api = url::Url::parse(&session.api_url)
        .map_err(|_| Error::Shape("invalid Session apiUrl".into()))?;
    let url = api
        .join(raw)
        .map_err(|_| Error::Shape("invalid endpoint URL".into()))?;
    if !matches!(url.scheme(), "https" | "http") || !url.username().is_empty() || url.password().is_some() {
        return Err(Error::Shape("endpoint must be HTTP(S) without credentials".into()));
    }
    if url.origin() != api.origin() {
        return Err(Error::Shape(
            "endpoint is not on the apiUrl origin; select and authorize it explicitly".into(),
        ));
    }
    Ok(url)
}

fn core_limit(session: &Session, key: &str) -> Option<u64> {
    session.capabilities.get(CORE_CAPABILITY)?.get(key)?.as_u64()
}

/// What the upload endpoint answered (RFC 8620 §6.1).
#[derive(Debug, PartialEq)]
pub struct Uploaded {
    pub blob_id: String,
    pub media_type: String,
    pub size: u64,
}

/// One piece of a blob composed with Blob/upload (RFC 9404 §4.1).
pub enum Source<'a> {
    Text(&'a str),
    Bytes(&'a [u8]),
    /// A range of a blob already on the server. `None` length runs to the end.
    Blob { id: &'a str, offset: Option<u64>, length: Option<u64> },
}

impl Source<'_> {
    fn to_json(&self) -> Value {
        use base64::Engine;
        match self {
            Source::Text(text) => json!({"data:asText": text}),
            Source::Bytes(bytes) => {
                json!({"data:asBase64": base64::engine::general_purpose::STANDARD.encode(bytes)})
            }
            Source::Blob { id, offset, length } => {
                let mut value = json!({"blobId": id});
                if let Some(offset) = offset {
                    value["offset"] = json!(offset);
                }
                if let Some(length) = length {
                    value["length"] = json!(length);
                }
                value
            }
        }
    }

    fn inline_size(&self) -> u64 {
        match self {
            Source::Text(text) => text.len() as u64,
            Source::Bytes(bytes) => bytes.len() as u64,
            Source::Blob { .. } => 0,
        }
    }
}

/// The capability whose `using` entry a Blob/lookup type name needs.
fn type_capability(name: &str) -> Option<&'static str> {
    Some(match name {
        "Email" | "Thread" | "Mailbox" => "urn:ietf:params:jmap:mail",
        "SieveScript" => "urn:ietf:params:jmap:sieve",
        "ContactCard" | "AddressBook" => "urn:ietf:params:jmap:contacts",
        "CalendarEvent" | "Calendar" => "urn:ietf:params:jmap:calendars",
        _ => return None,
    })
}

fn account_blob(session: &Session, account: &str) -> Result<Value, Error> {
    session
        .accounts
        .get(account)
        .and_then(|a| a.account_capabilities.get(CAPABILITY))
        .cloned()
        .ok_or_else(|| Error::Shape("account does not advertise Blob management".into()))
}

fn arguments(batch: &Batch, name: &str, account: &str) -> Result<Value, Error> {
    let value = &batch.response("blob", name)?.arguments;
    if value.get("accountId").and_then(Value::as_str) != Some(account) {
        return Err(Error::Shape(format!("{name} response accountId mismatch")));
    }
    Ok(value.clone())
}

impl Client {
    /// Uploads bytes to the Session's uploadUrl and returns the blob it created (RFC 8620 §6.1).
    ///
    /// The size is checked against maxSizeUpload before sending, and the answer must name the same
    /// account and the same size: a server that stored something else is not one to reference later.
    pub fn upload(
        &self,
        session: &Session,
        account: &str,
        media_type: &str,
        bytes: &[u8],
    ) -> Result<Uploaded, Error> {
        if core_limit(session, "maxSizeUpload").is_some_and(|max| bytes.len() as u64 > max) {
            return Err(Error::Shape("upload exceeds maxSizeUpload".into()));
        }
        let url = on_api_origin(session, &expand(&session.upload_url, &[("accountId", account)])?)?;
        let response = self
            .agent
            .post(url.as_str())
            .header("Authorization", self.authorization())
            .header("Accept", "application/json")
            .header("Content-Type", media_type)
            .send(bytes)
            .map_err(|e| Error::Transport(e.to_string()))?;
        let body = Self::read(response)?;
        let answer: Value =
            serde_json::from_str(&body).map_err(|e| Error::Shape(format!("upload: {e}")))?;
        if answer.get("accountId").and_then(Value::as_str) != Some(account) {
            return Err(Error::Shape("upload response names another account".into()));
        }
        let uploaded = Uploaded {
            blob_id: answer
                .get("blobId")
                .and_then(Value::as_str)
                .ok_or_else(|| Error::Shape("upload response has no blobId".into()))?
                .to_owned(),
            media_type: answer
                .get("type")
                .and_then(Value::as_str)
                .unwrap_or_default()
                .to_owned(),
            size: answer
                .get("size")
                .and_then(Value::as_u64)
                .ok_or_else(|| Error::Shape("upload response has no size".into()))?,
        };
        if uploaded.size != bytes.len() as u64 {
            return Err(Error::Shape("upload response size differs from the bytes sent".into()));
        }
        Ok(uploaded)
    }

    /// Downloads a blob from the Session's downloadUrl (RFC 8620 §6.2).
    ///
    /// Redirects are followed only within the same origin, as for every authenticated request.
    /// `max_bytes` bounds what is read into memory; a larger blob is an error, not a silent cut.
    pub fn download(
        &self,
        session: &Session,
        account: &str,
        blob: &str,
        media_type: &str,
        name: &str,
        max_bytes: u64,
    ) -> Result<Vec<u8>, Error> {
        let raw = expand(
            &session.download_url,
            &[("accountId", account), ("blobId", blob), ("type", media_type), ("name", name)],
        )?;
        let mut current = on_api_origin(session, &raw)?;
        for _ in 0..=self.redirect_limit {
            let mut response = self
                .agent
                .get(current.as_str())
                .header("Authorization", self.authorization())
                .call()
                .map_err(|e| Error::Transport(e.to_string()))?;
            let status = response.status().as_u16();
            if matches!(status, 301 | 302 | 303 | 307 | 308) {
                let location = response
                    .headers()
                    .get("location")
                    .and_then(|v| v.to_str().ok())
                    .ok_or_else(|| Error::Shape("redirect has no valid Location".into()))?;
                current = crate::authenticated_redirect(&current, location)?;
                continue;
            }
            if !(200..300).contains(&status) {
                return Err(Self::read(response).err().unwrap_or(Error::Status {
                    status,
                    body: String::new(),
                }));
            }
            return response
                .body_mut()
                .with_config()
                .limit(max_bytes)
                .read_to_vec()
                .map_err(|e| Error::Transport(e.to_string()));
        }
        Err(Error::Shape("authenticated redirect limit exceeded".into()))
    }

    fn blob_call(
        &self,
        session: &Session,
        extra: &[&str],
        name: &str,
        args: Value,
    ) -> Result<Batch, Error> {
        let mut using = vec![CORE_CAPABILITY, CAPABILITY];
        using.extend(extra.iter().copied());
        self.request(session, &using, vec![(name, args, "blob")])
    }

    /// Composes new blobs from inline data and ranges of existing blobs (RFC 9404 Blob/upload).
    ///
    /// Each entry is (creation id, media type, sources). Returns the created blob ids by creation id
    /// and the server's notCreated errors, also by creation id; one failed blob does not fail the rest.
    pub fn blob_upload(
        &self,
        session: &Session,
        account: &str,
        blobs: &[(&str, Option<&str>, Vec<Source<'_>>)],
    ) -> Result<(BTreeMap<String, Value>, BTreeMap<String, Value>), Error> {
        let limits = account_blob(session, account)?;
        let max_sources = limits.get("maxDataSources").and_then(Value::as_u64);
        let max_set = limits.get("maxSizeBlobSet").and_then(Value::as_u64);
        let mut create = serde_json::Map::new();
        let mut inline = 0u64;
        for (id, media_type, sources) in blobs {
            if max_sources.is_some_and(|max| sources.len() as u64 > max) {
                return Err(Error::Shape("Blob/upload exceeds maxDataSources".into()));
            }
            inline += sources.iter().map(Source::inline_size).sum::<u64>();
            let mut entry = json!({"data": sources.iter().map(Source::to_json).collect::<Vec<_>>()});
            if let Some(media_type) = media_type {
                entry["type"] = json!(media_type);
            }
            create.insert((*id).to_owned(), entry);
        }
        // maxSizeBlobSet bounds the data a Blob/upload request carries; inline data is all of it
        // that this client can count before sending.
        if max_set.is_some_and(|max| inline > max) {
            return Err(Error::Shape("Blob/upload exceeds maxSizeBlobSet".into()));
        }
        let batch = self.blob_call(
            session,
            &[],
            "Blob/upload",
            json!({"accountId":account,"create":create}),
        )?;
        let result = arguments(&batch, "Blob/upload", account)?;
        let section = |key: &str| -> Result<BTreeMap<String, Value>, Error> {
            match result.get(key) {
                None | Some(Value::Null) => Ok(BTreeMap::new()),
                Some(Value::Object(map)) => Ok(map.clone().into_iter().collect()),
                Some(_) => Err(Error::Shape(format!("Blob/upload {key} is not an object"))),
            }
        };
        let created = section("created")?;
        let failed = section("notCreated")?;
        for (id, _, _) in blobs {
            if created.contains_key(*id) == failed.contains_key(*id) {
                return Err(Error::Shape(format!(
                    "Blob/upload must answer {id} exactly once, as created or notCreated"
                )));
            }
        }
        Ok((created, failed))
    }

    /// Reads blobs, or a byte range of them, as text, base64 or digests (RFC 9404 Blob/get).
    ///
    /// `offset` and `length` apply to the digests as well as the data: with a range, `digest:sha-256`
    /// is the digest of that range, not of the blob. Checked against Stalwart — a digest compared
    /// against the whole file after a ranged read looks like corruption and is not.
    ///
    /// Every id asked for must come back once, in `list` or `notFound`. Text that is not valid
    /// UTF-8 comes back with `isEncodingProblem` and a range past the end with `isTruncated`; both
    /// are returned as the server sent them, for the caller to see rather than for this to hide.
    pub fn blob_get(
        &self,
        session: &Session,
        account: &str,
        ids: &[&str],
        properties: &[&str],
        offset: Option<u64>,
        length: Option<u64>,
    ) -> Result<Value, Error> {
        account_blob(session, account)?;
        let mut args = json!({"accountId":account,"ids":ids,"properties":properties});
        if let Some(offset) = offset {
            args["offset"] = json!(offset);
        }
        if let Some(length) = length {
            args["length"] = json!(length);
        }
        let batch = self.blob_call(session, &[], "Blob/get", args)?;
        let result = arguments(&batch, "Blob/get", account)?;
        let ids: Vec<String> = ids.iter().map(|s| (*s).to_owned()).collect();
        crate::mail::validate_get(&result, &ids)?;
        Ok(result)
    }

    /// Which objects of the given types reference each blob (RFC 9404 Blob/lookup).
    ///
    /// Returns blob id → type name → object ids. Only types the account advertises as supported are
    /// sent; asking for another is refused here rather than left to the server.
    pub fn blob_lookup(
        &self,
        session: &Session,
        account: &str,
        type_names: &[&str],
        ids: &[&str],
    ) -> Result<BTreeMap<String, BTreeMap<String, Vec<String>>>, Error> {
        let limits = account_blob(session, account)?;
        let supported: Vec<&str> = limits
            .get("supportedTypeNames")
            .and_then(Value::as_array)
            .map(|names| names.iter().filter_map(Value::as_str).collect())
            .unwrap_or_default();
        let mut extra = Vec::new();
        for name in type_names {
            if !supported.contains(name) {
                return Err(Error::Shape(format!("Blob/lookup does not support {name} here")));
            }
            let capability = type_capability(name)
                .ok_or_else(|| Error::Shape(format!("no known capability for {name}")))?;
            if !extra.contains(&capability) {
                extra.push(capability);
            }
        }
        let batch = self.blob_call(
            session,
            &extra,
            "Blob/lookup",
            json!({"accountId":account,"typeNames":type_names,"ids":ids}),
        )?;
        let result = arguments(&batch, "Blob/lookup", account)?;
        let list = result
            .get("list")
            .and_then(Value::as_array)
            .ok_or_else(|| Error::Shape("Blob/lookup has no list".into()))?;
        let missing = result
            .get("notFound")
            .and_then(Value::as_array)
            .ok_or_else(|| Error::Shape("Blob/lookup has no notFound".into()))?;
        let mut found = BTreeMap::new();
        for entry in list {
            let id = entry
                .get("id")
                .and_then(Value::as_str)
                .ok_or_else(|| Error::Shape("Blob/lookup entry has no id".into()))?;
            let matched = entry
                .get("matchedIds")
                .and_then(Value::as_object)
                .ok_or_else(|| Error::Shape("Blob/lookup entry has no matchedIds".into()))?;
            let mut by_type = BTreeMap::new();
            for (name, ids) in matched {
                if !type_names.contains(&name.as_str()) {
                    return Err(Error::Shape(format!("Blob/lookup answered for {name}, not asked")));
                }
                let ids = ids
                    .as_array()
                    .ok_or_else(|| Error::Shape("matchedIds entry is not a list".into()))?
                    .iter()
                    .map(|v| v.as_str().map(str::to_owned))
                    .collect::<Option<Vec<_>>>()
                    .ok_or_else(|| Error::Shape("matchedIds holds a non-string id".into()))?;
                by_type.insert(name.clone(), ids);
            }
            found.insert(id.to_owned(), by_type);
        }
        let answered = found.len() + missing.len();
        if answered != ids.len()
            || missing.iter().any(|m| m.as_str().is_none_or(|m| !ids.contains(&m) || found.contains_key(m)))
            || found.keys().any(|k| !ids.contains(&k.as_str()))
        {
            return Err(Error::Shape("Blob/lookup did not answer each id exactly once".into()));
        }
        Ok(found)
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    type Answer = (u16, Vec<(&'static str, String)>, Vec<u8>);

    /// Answers one canned HTTP response per connection and keeps each request's head and body.
    fn serve(answers: Vec<Answer>) -> (String, std::thread::JoinHandle<Vec<(String, Vec<u8>)>>) {
        use std::io::{BufRead, BufReader, Read, Write};
        let listener = std::net::TcpListener::bind("127.0.0.1:0").unwrap();
        let base = format!("http://{}", listener.local_addr().unwrap());
        let server = std::thread::spawn(move || {
            let mut seen = Vec::new();
            for (status, headers, body) in answers {
                let (mut stream, _) = listener.accept().unwrap();
                stream.set_read_timeout(Some(std::time::Duration::from_secs(5))).unwrap();
                let mut reader = BufReader::new(&mut stream);
                let mut head = String::new();
                let mut length = 0;
                loop {
                    let mut line = String::new();
                    reader.read_line(&mut line).unwrap();
                    if line == "\r\n" || line.is_empty() {
                        break;
                    }
                    if let Some(v) = line.to_ascii_lowercase().strip_prefix("content-length:") {
                        length = v.trim().parse().unwrap();
                    }
                    head.push_str(&line);
                }
                let mut sent = vec![0; length];
                reader.read_exact(&mut sent).unwrap();
                let mut reply = format!(
                    "HTTP/1.1 {status} X\r\nContent-Length: {}\r\nConnection: close\r\n",
                    body.len()
                );
                for (name, value) in headers {
                    reply.push_str(&format!("{name}: {value}\r\n"));
                }
                reply.push_str("\r\n");
                stream.write_all(reply.as_bytes()).unwrap();
                stream.write_all(&body).unwrap();
                seen.push((head, sent));
            }
            seen
        });
        (base, server)
    }

    fn session_at(base: &str, blob: Value) -> Session {
        serde_json::from_value(json!({
            "apiUrl": format!("{base}/jmap"),
            "uploadUrl": format!("{base}/upload/{{accountId}}/"),
            "downloadUrl": format!("{base}/download/{{accountId}}/{{blobId}}/{{name}}?accept={{type}}"),
            "capabilities": {CORE_CAPABILITY: {"maxSizeUpload": 100, "maxCallsInRequest": 4}, CAPABILITY: {}},
            "accounts": {"a": {"accountCapabilities": {CAPABILITY: blob}}}
        }))
        .unwrap()
    }

    fn client() -> Client {
        Client::new(crate::Credentials { user: "u".into(), secret: "test-only".into() })
    }

    fn json_reply(value: Value) -> Answer {
        (200, vec![("Content-Type", "application/json".into())], value.to_string().into_bytes())
    }

    #[test]
    fn an_upload_must_come_back_as_the_same_bytes_in_the_same_account() {
        let (base, server) = serve(vec![
            json_reply(json!({"accountId":"a","blobId":"B1","type":"image/png","size":3})),
            json_reply(json!({"accountId":"other","blobId":"B2","type":"image/png","size":3})),
            json_reply(json!({"accountId":"a","blobId":"B3","type":"image/png","size":2})),
        ]);
        let session = session_at(&base, json!({}));
        let c = client();
        let ok = c.upload(&session, "a", "image/png", &[0, 255, 7]).unwrap();
        assert_eq!(ok, Uploaded { blob_id: "B1".into(), media_type: "image/png".into(), size: 3 });
        assert!(c.upload(&session, "a", "image/png", &[0, 255, 7]).is_err(), "another account");
        assert!(c.upload(&session, "a", "image/png", &[0, 255, 7]).is_err(), "another size");
        let seen = server.join().unwrap();
        assert!(seen[0].0.starts_with("POST /upload/a/ "), "{}", seen[0].0);
        assert!(seen[0].0.to_ascii_lowercase().contains("content-type: image/png"));
        assert_eq!(seen[0].1, [0, 255, 7], "bytes sent as they are");
        // Over maxSizeUpload: refused before any connection (the server above is gone).
        assert!(matches!(c.upload(&session, "a", "x/y", &[0; 101]), Err(Error::Shape(_))));
    }

    #[test]
    fn a_download_is_read_whole_or_not_at_all_and_never_leaves_the_origin() {
        // Another origin that would answer: a redirect there must fail because of the rule, not
        // because the host is unreachable. Its thread is never joined — it must never be reached.
        let (elsewhere, _unreached) = serve(vec![(200, vec![], vec![6; 4])]);
        let (base, server) = serve(vec![
            (200, vec![], vec![1, 2, 3, 4]),
            (302, vec![("Location", format!("{elsewhere}/blob"))], vec![]),
            (200, vec![], vec![9; 32]),
        ]);
        let session = session_at(&base, json!({}));
        let c = client();
        assert_eq!(c.download(&session, "a", "B", "text/plain", "n.txt", 16).unwrap(), [1, 2, 3, 4]);
        assert!(c.download(&session, "a", "B", "text/plain", "n.txt", 16).is_err(), "cross-origin redirect");
        assert!(c.download(&session, "a", "B", "text/plain", "n.txt", 16).is_err(), "over max_bytes");
        let seen = server.join().unwrap();
        assert!(seen[0].0.starts_with("GET /download/a/B/n.txt?accept=text%2Fplain "), "{}", seen[0].0);
    }

    #[test]
    fn every_composed_blob_is_answered_once_and_limits_hold_before_sending() {
        let (base, server) = serve(vec![
            json_reply(json!({"methodResponses":[["Blob/upload",{"accountId":"a","created":{"x":{"id":"N"}},"notCreated":{"y":{"type":"invalidProperties"}}},"blob"]]})),
            json_reply(json!({"methodResponses":[["Blob/upload",{"accountId":"a","created":{"x":{"id":"N"}}},"blob"]]})),
        ]);
        let session = session_at(&base, json!({"maxDataSources":2,"maxSizeBlobSet":10}));
        let c = client();
        let two = || {
            vec![
                ("x", Some("text/plain"), vec![Source::Text("ab")]),
                ("y", None, vec![Source::Blob { id: "Z", offset: Some(1), length: None }]),
            ]
        };
        let (created, failed) = c.blob_upload(&session, "a", &two()).unwrap();
        assert_eq!(created["x"]["id"], "N");
        assert_eq!(failed["y"]["type"], "invalidProperties");
        assert!(c.blob_upload(&session, "a", &two()).is_err(), "y left unanswered");
        let seen = server.join().unwrap();
        let sent: Value = serde_json::from_slice(&seen[0].1).unwrap();
        assert_eq!(sent["methodCalls"][0][1]["create"]["y"]["data"], json!([{"blobId":"Z","offset":1}]));
        let three = vec![("x", None, vec![Source::Text("a"), Source::Text("b"), Source::Text("c")])];
        assert!(c.blob_upload(&session, "a", &three).is_err(), "maxDataSources");
        let big = vec![("x", None, vec![Source::Bytes(&[0; 11])])];
        assert!(c.blob_upload(&session, "a", &big).is_err(), "maxSizeBlobSet");
    }

    #[test]
    fn a_lookup_answers_only_what_was_asked_once_each() {
        let reply = |list: Value, missing: Value| {
            json_reply(json!({"methodResponses":[["Blob/lookup",{"accountId":"a","list":list,"notFound":missing},"blob"]]}))
        };
        let (base, server) = serve(vec![
            reply(json!([{"id":"B","matchedIds":{"Email":["e1"]}}]), json!(["C"])),
            reply(json!([{"id":"B","matchedIds":{"Thread":["t"]}}]), json!(["C"])),
            reply(json!([{"id":"B","matchedIds":{"Email":[]}}]), json!(["B"])),
        ]);
        let session = session_at(&base, json!({"supportedTypeNames":["Email","Thread"]}));
        let c = client();
        let found = c.blob_lookup(&session, "a", &["Email"], &["B", "C"]).unwrap();
        assert_eq!(found["B"]["Email"], ["e1"]);
        assert!(!found.contains_key("C"));
        assert!(c.blob_lookup(&session, "a", &["Email"], &["B", "C"]).is_err(), "Thread was not asked");
        assert!(c.blob_lookup(&session, "a", &["Email"], &["B", "C"]).is_err(), "B twice, C never");
        let seen = server.join().unwrap();
        let sent: Value = serde_json::from_slice(&seen[0].1).unwrap();
        assert!(sent["using"].as_array().unwrap().contains(&json!("urn:ietf:params:jmap:mail")));
        assert!(c.blob_lookup(&session, "a", &["Mailbox"], &["B"]).is_err(), "not supported, not sent");
    }

    #[test]
    fn templates_encode_every_reserved_character_and_require_their_variables() {
        let download = "https://m.test/download/{accountId}/{blobId}/{name}?accept={type}";
        assert_eq!(
            expand(download, &[("accountId", "b"), ("blobId", "x y"), ("type", "text/plain"), ("name", "a/b.txt")]).unwrap(),
            "https://m.test/download/b/x%20y/a%2Fb.txt?accept=text%2Fplain"
        );
        assert_eq!(encode("한"), "%ED%95%9C");
        // A template that leaves out a required variable, or has one this client does not know.
        assert!(expand("https://m.test/{accountId}/{blobId}", &[("accountId", "b"), ("blobId", "c"), ("type", "t"), ("name", "n")]).is_err());
        assert!(expand("https://m.test/{accountId}/{other}", &[("accountId", "b")]).is_err());
        assert!(expand("https://m.test/{accountId", &[("accountId", "b")]).is_err());
        assert!(expand("https://m.test/accountId}", &[("accountId", "b")]).is_err());
    }

    #[test]
    fn endpoints_must_share_the_api_origin() {
        let session: Session = serde_json::from_value(json!({"apiUrl":"https://m.test/jmap/","capabilities":{}})).unwrap();
        assert!(on_api_origin(&session, "https://m.test/upload/b/").is_ok());
        assert!(on_api_origin(&session, "/upload/b/").is_ok(), "relative to apiUrl");
        assert!(on_api_origin(&session, "https://cdn.m.test/upload/b/").is_err());
        assert!(on_api_origin(&session, "http://m.test/upload/b/").is_err(), "downgrade is another origin");
        assert!(on_api_origin(&session, "https://u:p@m.test/upload/b/").is_err());
    }

    #[test]
    fn lookup_types_map_to_the_capability_their_using_needs() {
        assert_eq!(type_capability("Email"), Some("urn:ietf:params:jmap:mail"));
        assert_eq!(type_capability("ContactCard"), Some("urn:ietf:params:jmap:contacts"));
        assert_eq!(type_capability("Unknown"), None);
    }
}
