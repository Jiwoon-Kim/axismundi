//! Account-wide search. Query arguments, state, and snippets never enter the profile Store.
use std::collections::{BTreeMap, BTreeSet};

use serde_json::{Value, json};

use super::{CAPABILITY, CORE_CAPABILITY, Client, Error, LIST_PROPERTIES, SORT_PROPERTY, Session,
    arguments, arguments_for, call, choose_account, limit, mailboxes, query_ids, validate_get, window_size};

pub struct Cursor<'a> {
    pub position: usize,
    pub state: &'a str,
}

struct Page {
    state: String,
    total: Option<u64>,
    rows: Vec<Value>,
    snippets: BTreeMap<String, Value>,
}

fn filter(text: &str, boxes: &[Value]) -> Value {
    let ids: Vec<_> = boxes.iter()
        .filter(|box_| matches!(box_["role"].as_str(), Some("trash" | "junk")))
        .filter_map(|box_| box_["id"].as_str()).collect();
    if ids.is_empty() {
        json!({"text":text})
    } else {
        json!({"operator":"AND","conditions":[{"text":text},{"inMailboxOtherThan":ids}]})
    }
}

fn page(client: &Client, session: &Session, account: &str, filter: &Value, position: usize, count: usize, expected: Option<&str>) -> Result<Page, Error> {
    let args = json!({"accountId":account,"filter":filter,"position":position,"limit":count,
        "calculateTotal":true,"sort":[{"property":SORT_PROPERTY,"isAscending":false}],"collapseThreads":false});
    let snippet_args = json!({"accountId":account,"filter":filter,
        "#emailIds":{"resultOf":"query","name":"Email/query","path":"/ids"}});
    let batch_size = json!({"using":[CORE_CAPABILITY,CAPABILITY],"methodCalls":[
        ["Email/query",args,"query"],["SearchSnippet/get",snippet_args,"snippet"]]}).to_string().len();
    let (query_batch, query_tag, bundled) = if limit(session, "maxCallsInRequest")? >= 2
        && batch_size <= limit(session, "maxSizeRequest")?
    {
        (client.request(session, &[CORE_CAPABILITY,CAPABILITY],
            vec![("Email/query",args,"query"),("SearchSnippet/get",snippet_args,"snippet")])?, "query", true)
    } else {
        (call(client, session, "Email/query", args)?, "mail", false)
    };
    if query_batch.errors().iter().any(|error| error.call_id == query_tag && error.error_type() == Some("unsupportedFilter")) {
        return Err(Error::Shape("unsupportedFilter".into()));
    }
    let query = arguments_for(&query_batch, "Email/query", account, query_tag)?;
    let state = query["queryState"].as_str().ok_or_else(|| Error::Shape("Missing search state".into()))?.to_owned();
    if expected.is_some_and(|expected| expected != state) {
        return Ok(Page { state, total: None, rows: vec![], snippets: BTreeMap::new() });
    }
    let ids = query_ids(&query, count)?;
    if query["position"].as_u64() != Some(position as u64) {
        return Err(Error::Shape("Search returned a different position".into()));
    }
    let mut rows = Vec::new();
    let mut snippets = BTreeMap::new();
    if !ids.is_empty() {
        let get = call(client, session, "Email/get", json!({"accountId":account,"ids":ids,"properties":LIST_PROPERTIES}))?;
        let get = arguments(&get, "Email/get", account)?;
        validate_get(&get, &ids)?;
        let by_id: BTreeMap<_, _> = get["list"].as_array().unwrap().iter()
            .filter_map(|row| Some((row["id"].as_str()?, row))).collect();
        for id in &ids {
            rows.push(match by_id.get(id.as_str()) {
                Some(row) => json!({"id":id,"available":true,"document":row}),
                None => json!({"id":id,"available":false,"reason":"notFound"}),
            });
        }
        // A server may reject snippets without rejecting the query. Plain results still work.
        let snippet = if bundled {
            arguments_for(&query_batch, "SearchSnippet/get", account, "snippet").ok()
        } else {
            call(client, session, "SearchSnippet/get", json!({"accountId":account,"filter":filter,"emailIds":ids}))
                .and_then(|batch| arguments(&batch, "SearchSnippet/get", account)).ok()
        };
        if let Some(list) = snippet.as_ref().and_then(|v| v["list"].as_array()) {
            let allowed: BTreeSet<_> = ids.iter().map(String::as_str).collect();
            for entry in list {
                if let Some(id) = entry["emailId"].as_str().filter(|id| allowed.contains(id)) {
                    snippets.insert(id.to_owned(), json!({"subject":entry["subject"].as_str(),"preview":entry["preview"].as_str()}));
                }
            }
        }
    }
    Ok(Page { state, total: query["total"].as_u64(), rows, snippets })
}

/// Returns an append at the requested position only when its query state matches. Otherwise it
/// rebuilds the visible prefix once, with no mixture of rows from different states.
pub fn search(client: &Client, session: &Session, account: &str, text: &str, retained: usize, cursor: Option<Cursor<'_>>) -> Result<Value, Error> {
    choose_account(session, Some(account))?;
    let text = text.trim();
    if text.is_empty() { return Err(Error::Shape("Empty search".into())); }
    let boxes = mailboxes(client, session, account)?;
    let boxes = boxes["list"].as_array().ok_or_else(|| Error::Shape("Missing mailboxes".into()))?;
    let filter = filter(text, boxes);
    let window = window_size(session)?;
    let mut wanted = retained.max(window);
    if let Some(cursor) = cursor {
        let page = page(client, session, account, &filter, cursor.position, window, Some(cursor.state))?;
        if page.state == cursor.state {
            return Ok(result(account, boxes, cursor.position, page, window));
        }
        wanted = wanted.saturating_add(window);
    }
    let mut prefix = page(client, session, account, &filter, 0, window, None)?;
    let mut seen: BTreeSet<String> = prefix.rows.iter().filter_map(|r| r["id"].as_str().map(str::to_owned)).collect();
    let mut last_limit = window;
    let mut short = prefix.rows.len() < window;
    while !short && prefix.rows.len() < wanted && prefix.total.is_none_or(|n| (prefix.rows.len() as u64) < n) {
        let next_limit = window.min(wanted - prefix.rows.len());
        let next = page(client, session, account, &filter, prefix.rows.len(), next_limit, Some(&prefix.state))?;
        if next.state != prefix.state || next.rows.iter().any(|r| !seen.insert(r["id"].as_str().unwrap().to_owned())) {
            return Err(Error::Shape("Search changed while paging".into()));
        }
        short = next.rows.len() < next_limit;
        last_limit = next_limit;
        prefix.total = next.total.or(prefix.total);
        prefix.rows.extend(next.rows);
        prefix.snippets.extend(next.snippets);
    }
    // With unknown totals only a short last window proves the end.
    let mut response = result(account, boxes, 0, prefix, last_limit);
    if response["total"].is_null() { response["hasMore"] = json!(!short); }
    Ok(response)
}

fn result(account: &str, boxes: &[Value], position: usize, page: Page, window: usize) -> Value {
    let has_more = page.total.map_or(page.rows.len() == window, |n| ((position + page.rows.len()) as u64) < n);
    json!({"accountId":account,"mailboxId":"","mailboxes":boxes,"emails":page.rows,"total":page.total,
        "hasMore":has_more,"queryStale":false,"partialCache":false,
        "search":{"state":page.state,"position":position,"snippets":page.snippets}})
}
