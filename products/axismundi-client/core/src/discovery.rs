//! Anonymous discovery, separate from the authenticated JMAP client.

use std::time::Duration;

use serde::{Deserialize, Serialize};
use serde_json::Value;
use url::Url;

use crate::CORE_CAPABILITY;

pub const AP_SESSION_ROUTE: &str = "/axismundi/v1/users/me";

#[derive(Debug, Serialize)]
#[serde(tag = "status", content = "detail", rename_all = "snake_case")]
pub enum Probe<T> {
    Skipped,
    Found(T),
    LoginRequired(T),
    NotFound(String),
    Unconfirmed(String),
}

#[derive(Debug, Serialize)]
pub struct Discovery {
    pub input: String,
    pub base: String,
    pub wordpress: Probe<WordPress>,
    pub jmap: Probe<Jmap>,
    pub activitypub: Probe<ActivityPub>,
}

#[derive(Debug, Serialize)]
pub struct WordPress {
    pub index_url: String,
    pub name: String,
    pub namespaces: Vec<String>,
    pub ap_session_advertised: bool,
}

#[derive(Debug, Serialize)]
pub struct Jmap {
    pub session_url: String,
    /// The Session is on another origin than the site the user entered: say so and ask again.
    pub origin_changed: bool,
    /// `host:port` from a `_jmap._tcp` SRV record, when that is how the host was found.
    pub srv: Option<String>,
}

#[derive(Debug, Serialize)]
pub struct ActivityPub {
    pub protocols: Vec<String>,
    pub software: Option<String>,
}

#[derive(Clone, Copy, Deserialize)]
pub struct DiscoveryOptions {
    pub wordpress: bool,
    pub jmap: bool,
    pub activitypub: bool,
}

impl Default for DiscoveryOptions {
    fn default() -> Self { Self {wordpress:true,jmap:true,activitypub:true} }
}

pub fn discover(input: &str) -> Result<Discovery, String> {
    discover_selected(input, DiscoveryOptions::default())
}

pub fn discover_selected(input: &str, options: DiscoveryOptions) -> Result<Discovery, String> {
    let input_url = input.trim();
    let mut site = if input_url.contains("://") {
        checked_url(Url::parse(input_url), None)?
    } else {
        checked_url(Url::parse(&format!("https://{input_url}")), None)?
    };
    if site.query().is_some() {
        return Err("enter a site URL without a query string".into());
    }
    if !site.path().ends_with('/') {
        let path = format!("{}/", site.path());
        site.set_path(&path);
    }
    let origin = site.origin().ascii_serialization();
    let agent: ureq::Agent = ureq::Agent::config_builder()
        .http_status_as_error(false)
        .tls_config(crate::platform_tls())
        .max_redirects(0)
        .timeout_global(Some(Duration::from_secs(20)))
        .user_agent("axismundi-discovery/0.1")
        .build()
        .into();
    let wordpress = if options.wordpress { wordpress(&agent, site.clone()) } else { Probe::Skipped };
    let jmap = if options.jmap {
        // RFC 8620 §2.2: a bare domain may name its JMAP host in DNS. A URL the user typed in
        // full already names the host, so it is not looked up.
        let srv = if input_url.contains("://") { Srv::Absent } else { jmap_srv(&site) };
        match srv {
            Srv::Target(url, record) => jmap(&agent, url, &site.origin(), Some(record)),
            Srv::NotOffered(record) => Probe::NotFound(format!("_jmap._tcp SRV {record} says JMAP is not offered")),
            Srv::Absent => jmap(&agent, site.join("/.well-known/jmap").expect("HTTP site URL"), &site.origin(), None),
        }
    } else { Probe::Skipped };
    let activitypub = if options.activitypub { activitypub(
        &agent,
        site.join("/.well-known/nodeinfo").expect("HTTP site URL"),
    ) } else { Probe::Skipped };
    Ok(Discovery {
        input: input.into(),
        base: origin,
        wordpress,
        jmap,
        activitypub,
    })
}

fn wordpress(agent: &ureq::Agent, url: Url) -> Probe<WordPress> {
    let (target, mut response) = match get_accept(agent,url,"text/html") {
        Ok(value) => value,
        Err(reason) => return Probe::Unconfirmed(reason),
    };
    if response.status().as_u16() != 200 {
        return Probe::Unconfirmed(format!("{target}: HTTP {}",response.status().as_u16()));
    }
    let html=match response.body_mut().with_config().limit(1024*1024).read_to_string() {
        Ok(body) => scraper::Html::parse_document(&body),
        Err(error) => return Probe::Unconfirmed(format!("{target}: {error}")),
    };
    let selector=scraper::Selector::parse("head link[rel][href]").expect("static selector");
    let advertised=html.select(&selector).find_map(|node| {
        node.value().attr("rel")?.split_whitespace().any(|rel|rel=="https://api.w.org/")
            .then(||node.value().attr("href")).flatten()
    });
    let Some(reference)=advertised else { return Probe::NotFound("Homepage does not advertise a WordPress REST root".into()); };
    let url=match resolve(&target,reference) { Ok(url)=>url, Err(reason)=>return Probe::Unconfirmed(reason) };
    inspect(agent, url, |target, body| {
        let name = body.get("name")?.as_str()?.to_owned();
        let namespaces = strings(body.get("namespaces")?)?;
        let ap_session_advertised = body
            .get("routes")
            .and_then(|routes| routes.get(AP_SESSION_ROUTE))
            .and_then(|route| route.get("methods"))
            .and_then(Value::as_array)
            .is_some_and(|methods| methods.iter().any(|method| method.as_str() == Some("GET")));
        Some(WordPress {
            index_url: target.to_string(),
            name,
            namespaces,
            ap_session_advertised,
        })
    })
}

enum Srv {
    /// The well-known URL on the record's host, and the record as `host:port`.
    Target(Url, String),
    /// RFC 2782: a target of "." means the service is decidedly not available at this domain.
    NotOffered(String),
    /// No record, a host that is not a DNS name, or a lookup that failed: use the domain itself.
    Absent,
}

fn jmap_srv(site: &Url) -> Srv {
    let Some(url::Host::Domain(host)) = site.host() else { return Srv::Absent };
    if host == "localhost" || host.ends_with(".localhost") || !host.contains('.') {
        return Srv::Absent;
    }
    let Ok(runtime) = tokio::runtime::Builder::new_current_thread().enable_all().build() else { return Srv::Absent };
    let name = format!("_jmap._tcp.{host}.");
    let answers = runtime.block_on(async {
        let resolver = hickory_resolver::TokioResolver::builder_tokio().ok()?.build().ok()?;
        tokio::time::timeout(Duration::from_secs(5), resolver.srv_lookup(name.as_str())).await.ok()?.ok()
    });
    let Some(lookup) = answers else { return Srv::Absent };
    let records: Vec<(u16, u16, u16, String)> = lookup
        .answers()
        .iter()
        .filter_map(|record| match &record.data {
            hickory_resolver::proto::rr::RData::SRV(srv) => {
                Some((srv.priority, srv.weight, srv.port, srv.target.to_ascii()))
            }
            _ => None,
        })
        .collect();
    choose_srv(&records)
}

/// `(priority, weight, port, target)` records → the one to try. Lowest priority, then highest
/// weight: RFC 2782 picks among equal priorities by weighted chance, but this client tries a
/// single target, so it takes the heaviest rather than a random one.
fn choose_srv(records: &[(u16, u16, u16, String)]) -> Srv {
    let Some((_, _, port, target)) = records.iter().min_by_key(|(priority, weight, _, _)| (*priority, std::cmp::Reverse(*weight))) else {
        return Srv::Absent;
    };
    let target = target.trim_end_matches('.');
    let record = format!("{target}:{port}");
    if target.is_empty() {
        return Srv::NotOffered(record);
    }
    let address = if *port == 443 { format!("https://{target}/.well-known/jmap") } else { format!("https://{target}:{port}/.well-known/jmap") };
    match checked_url(Url::parse(&address), None) {
        Ok(url) => Srv::Target(url, record),
        Err(_) => Srv::Absent,
    }
}

fn jmap(agent: &ureq::Agent, url: Url, site: &url::Origin, srv: Option<String>) -> Probe<Jmap> {
    match get(agent, url) {
        Ok((target, mut response)) => {
            let info = Jmap {
                origin_changed: target.origin() != *site,
                session_url: target.to_string(),
                srv: srv.clone(),
            };
            match response.status().as_u16() {
                401 | 403 => Probe::LoginRequired(info),
                200 if json(&mut response).is_ok_and(|body| {
                    body.get("apiUrl").and_then(Value::as_str).is_some()
                        && body
                            .get("capabilities")
                            .and_then(|caps| caps.get(CORE_CAPABILITY))
                            .is_some_and(Value::is_object)
                        && body.get("accounts").is_some_and(Value::is_object)
                }) =>
                {
                    Probe::Found(info)
                }
                404 if target.path() == "/.well-known/jmap" && srv.is_none() && target.origin() == *site => {
                    Probe::NotFound(format!("{target}: HTTP 404"))
                }
                200 => Probe::Unconfirmed(format!("{target}: not a JMAP Session document")),
                status => Probe::Unconfirmed(format!("{target}: HTTP {status}")),
            }
        }
        Err(reason) => Probe::Unconfirmed(reason),
    }
}

fn activitypub(agent: &ureq::Agent, url: Url) -> Probe<ActivityPub> {
    let index = inspect(agent, url, |base, body| {
        let links = body.get("links")?.as_array()?;
        ["2.1", "2.0"].into_iter().find_map(|version| {
            let rel = format!("http://nodeinfo.diaspora.software/ns/schema/{version}");
            links.iter().find_map(|link| {
                if link.get("rel")?.as_str()? != rel {
                    return None;
                }
                resolve(base, link.get("href")?.as_str()?).ok()
            })
        })
    });
    let url = match index {
        Probe::Skipped => return Probe::Skipped,
        Probe::Found(url) => url,
        Probe::NotFound(reason) => return Probe::NotFound(reason),
        Probe::Unconfirmed(reason) => return Probe::Unconfirmed(reason),
        Probe::LoginRequired(_) => unreachable!("generic JSON probe never authenticates"),
    };
    match inspect(agent, url, |_, body| {
        let protocols = strings(body.get("protocols")?)?;
        let software =
            body.pointer("/software/name")
                .and_then(Value::as_str)
                .map(
                    |name| match body.pointer("/software/version").and_then(Value::as_str) {
                        Some(version) => format!("{name} {version}"),
                        None => name.into(),
                    },
                );
        Some(ActivityPub {
            protocols,
            software,
        })
    }) {
        Probe::Found(info)
            if !info
                .protocols
                .iter()
                .any(|protocol| protocol == "activitypub") =>
        {
            Probe::NotFound("NodeInfo does not advertise activitypub".into())
        }
        Probe::NotFound(reason) => Probe::Unconfirmed(format!("broken NodeInfo link: {reason}")),
        result => result,
    }
}

fn strings(value: &Value) -> Option<Vec<String>> {
    value
        .as_array()?
        .iter()
        .map(|item| item.as_str().map(str::to_owned))
        .collect()
}

type Response = ureq::http::Response<ureq::Body>;

fn inspect<T>(
    agent: &ureq::Agent,
    url: Url,
    parse: impl FnOnce(&Url, Value) -> Option<T>,
) -> Probe<T> {
    match get(agent, url) {
        Ok((target, mut response)) => match response.status().as_u16() {
            200 => match json(&mut response)
                .ok()
                .and_then(|body| parse(&target, body))
            {
                Some(value) => Probe::Found(value),
                None => Probe::Unconfirmed(format!("{target}: unexpected JSON document")),
            },
            404 => Probe::NotFound(format!("{target}: HTTP 404")),
            status => Probe::Unconfirmed(format!("{target}: HTTP {status}")),
        },
        Err(reason) => Probe::Unconfirmed(reason),
    }
}

fn json(response: &mut Response) -> Result<Value, String> {
    let body = response
        .body_mut()
        .with_config()
        .limit(1024 * 1024)
        .read_to_string()
        .map_err(|error| format!("reading discovery response: {error}"))?;
    serde_json::from_str(&body).map_err(|error| error.to_string())
}

fn get(agent: &ureq::Agent, url: Url) -> Result<(Url, Response), String> {
    get_accept(agent,url,"application/json")
}

fn get_accept(agent: &ureq::Agent, mut url: Url, accept: &str) -> Result<(Url, Response), String> {
    for hop in 0..=5 {
        let response = agent
            .get(url.as_str())
            .header("Accept", accept)
            .call()
            .map_err(|error| format!("{url}: {error}"))?;
        if !matches!(response.status().as_u16(), 301 | 302 | 303 | 307 | 308) {
            return Ok((url, response));
        }
        if hop == 5 {
            return Err(format!("{url}: too many discovery redirects"));
        }
        let location = response
            .headers()
            .get("location")
            .and_then(|value| value.to_str().ok())
            .ok_or_else(|| format!("{url}: redirect without a valid Location"))?;
        url = resolve(&url, location)?;
    }
    unreachable!("bounded loop always returns")
}

fn resolve(base: &Url, location: &str) -> Result<Url, String> {
    if location.trim().is_empty() {
        return Err("empty discovery URL reference".into());
    }
    checked_url(base.join(location), Some(base))
}

fn checked_url(result: Result<Url, url::ParseError>, from: Option<&Url>) -> Result<Url, String> {
    let url = result.map_err(|error| format!("invalid discovery URL: {error}"))?;
    if !matches!(url.scheme(), "http" | "https")
        || url.host().is_none()
        || !url.username().is_empty()
        || url.password().is_some()
        || url.fragment().is_some()
    {
        return Err(
            "discovery requires HTTP(S) URLs without embedded credentials or fragments".into(),
        );
    }
    if from.is_some_and(|base| base.scheme() == "https") && url.scheme() == "http" {
        return Err("discovery refused an HTTPS-to-HTTP downgrade".into());
    }
    Ok(url)
}

#[cfg(test)]
mod tests {
    use std::io::{BufRead, BufReader, Write};
    use std::net::TcpListener;
    use std::thread::{self, JoinHandle};

    use super::*;

    type Reply = (String, String, String, String);

    fn origin(listener: &TcpListener) -> String {
        format!("http://{}", listener.local_addr().unwrap())
    }

    fn serve(
        listener: TcpListener,
        replies: impl IntoIterator<Item = Reply> + Send + 'static,
    ) -> JoinHandle<()> {
        listener.set_nonblocking(true).unwrap();
        thread::spawn(move || {
            for (path, status, headers, body) in replies {
                let start = std::time::Instant::now();
                let mut stream = loop {
                    match listener.accept() {
                        Ok((stream, _)) => break stream,
                        Err(error)
                            if error.kind() == std::io::ErrorKind::WouldBlock
                                && start.elapsed() < Duration::from_secs(3) =>
                        {
                            thread::sleep(Duration::from_millis(5))
                        }
                        Err(error) => panic!("expected a request to {path}: {error}"),
                    }
                };
                stream.set_nonblocking(false).unwrap();
                stream
                    .set_read_timeout(Some(Duration::from_secs(3)))
                    .unwrap();
                let mut lines = BufReader::new(&mut stream).lines();
                assert_eq!(
                    lines.next().unwrap().unwrap(),
                    format!("GET {path} HTTP/1.1")
                );
                for line in lines {
                    let line = line.unwrap();
                    if line.is_empty() {
                        break;
                    }
                    let header = line.to_ascii_lowercase();
                    assert!(!header.starts_with("authorization:"));
                    assert!(!header.starts_with("cookie:"));
                }
                write!(stream, "HTTP/1.1 {status}\r\nContent-Length: {}\r\nConnection: close\r\n{headers}\r\n{body}", body.len()).unwrap();
            }
        })
    }

    #[test]
    fn discovery_reports_the_session_target_after_anonymous_redirects() {
        let site = TcpListener::bind("127.0.0.1:0").unwrap();
        let session = TcpListener::bind("127.0.0.1:0").unwrap();
        let site_url = origin(&site);
        let target = format!("{}/session", origin(&session));
        let site = serve(site, [
            ("/blog/".into(), "200 OK".into(), String::new(), r#"<html><head><link rel="https://api.w.org/" href="/blog/wp-json/"></head></html>"#.into()),
            ("/blog/wp-json/".into(), "200 OK".into(), String::new(),
                r#"{"name":"Fixture","namespaces":["wp/v2"],"routes":{"/axismundi/v1/users/me":{"methods":["GET"]}}}"#.into()),
            ("/.well-known/jmap".into(), "302 Found".into(), "Location: /jmap-entry\r\n".into(), String::new()),
            ("/jmap-entry".into(), "307 Temporary Redirect".into(), format!("Location: {target}\r\n"), String::new()),
            ("/.well-known/nodeinfo".into(), "200 OK".into(), String::new(),
                r#"{"links":[{"rel":"http://nodeinfo.diaspora.software/ns/schema/2.1","href":"/nodeinfo/2.1"}]}"#.into()),
            ("/nodeinfo/2.1".into(), "200 OK".into(), String::new(),
                r#"{"protocols":["activitypub"],"software":{"name":"fixture"}}"#.into()),
        ]);
        let session = serve(
            session,
            [(
                "/session".into(),
                "403 Forbidden".into(),
                String::new(),
                String::new(),
            )],
        );
        let discovered = discover(&format!("{site_url}/blog")).unwrap();
        assert!(
            matches!(discovered.jmap, Probe::LoginRequired(Jmap { session_url, origin_changed: true, srv: None }) if session_url == target)
        );
        assert!(
            matches!(discovered.wordpress, Probe::Found(info) if info.namespaces == ["wp/v2"] && info.ap_session_advertised)
        );
        assert!(
            matches!(discovered.activitypub, Probe::Found(info) if info.protocols == ["activitypub"])
        );
        site.join().unwrap();
        session.join().unwrap();
    }

    #[test]
    fn absence_unexpected_status_and_transport_failure_are_distinguishable() {
        let site = TcpListener::bind("127.0.0.1:0").unwrap();
        let site_url = origin(&site);
        let handle = serve(
            site,
            [
                (
                    "/".into(),
                    "503 Service Unavailable".into(),
                    String::new(),
                    String::new(),
                ),
                (
                    "/.well-known/jmap".into(),
                    "404 Not Found".into(),
                    String::new(),
                    String::new(),
                ),
            ],
        );
        let result = discover(&site_url).unwrap();
        assert!(
            matches!(result.wordpress, Probe::Unconfirmed(reason) if reason.contains("HTTP 503"))
        );
        assert!(matches!(result.jmap, Probe::NotFound(reason) if reason.contains("HTTP 404")));
        assert!(
            matches!(result.activitypub, Probe::Unconfirmed(reason) if !reason.contains("HTTP 404"))
        );
        handle.join().unwrap();
    }

    #[test]
    fn discovered_links_reject_secrets_and_downgrades() {
        let base = Url::parse("https://example.test/.well-known/jmap").unwrap();
        assert!(resolve(&base, "http://example.test/session").is_err());
        assert!(resolve(&base, "https://user:secret@example.test/session").is_err());
        assert!(resolve(&base, "file:///etc/passwd").is_err());
        assert!(resolve(&base, " ").is_err());
    }

    #[test]
    fn jmap_only_never_probes_other_services() {
        let listener=TcpListener::bind("127.0.0.1:0").unwrap();
        let address=origin(&listener);
        let server=serve(listener,[("/.well-known/jmap".into(),"401 Unauthorized".into(),String::new(),String::new())]);
        let result=discover_selected(&address,DiscoveryOptions{wordpress:false,jmap:true,activitypub:false}).unwrap();
        assert!(matches!(result.wordpress,Probe::Skipped));
        assert!(matches!(result.activitypub,Probe::Skipped));
        assert!(matches!(result.jmap,Probe::LoginRequired(_)));
        server.join().unwrap();
    }

    #[test]
    fn wordpress_requires_a_homepage_advertisement() {
        let listener=TcpListener::bind("127.0.0.1:0").unwrap();
        let address=origin(&listener);
        let server=serve(listener,[("/".into(),"200 OK".into(),String::new(),"<html><head></head><body>Not WordPress</body></html>".into())]);
        let result=discover_selected(&address,DiscoveryOptions{wordpress:true,jmap:false,activitypub:false}).unwrap();
        assert!(matches!(result.wordpress,Probe::NotFound(_)));
        server.join().unwrap();
    }

    #[test]
    fn srv_choice_takes_lowest_priority_then_heaviest_weight() {
        let records = [
            (10, 100, 443, "backup.example.".to_owned()),
            (0, 1, 443, "light.example.".to_owned()),
            (0, 5, 8443, "heavy.example.".to_owned()),
        ];
        match choose_srv(&records) {
            Srv::Target(url, record) => {
                assert_eq!(url.as_str(), "https://heavy.example:8443/.well-known/jmap");
                assert_eq!(record, "heavy.example:8443");
            }
            _ => panic!("expected a target"),
        }
        match choose_srv(&[(0, 0, 443, "api.example.".into())]) {
            Srv::Target(url, _) => assert_eq!(url.as_str(), "https://api.example/.well-known/jmap"),
            _ => panic!("expected a target"),
        }
    }

    #[test]
    fn srv_dot_target_means_not_offered_and_no_record_means_absent() {
        assert!(matches!(choose_srv(&[(0, 0, 0, ".".into())]), Srv::NotOffered(_)));
        assert!(matches!(choose_srv(&[]), Srv::Absent));
    }

    /// Real DNS: fastmail.com publishes `_jmap._tcp` → api.fastmail.com:443 (checked 2026-11-22).
    #[test]
    #[ignore = "needs public DNS"]
    fn fastmail_srv_names_its_api_host() {
        let site = Url::parse("https://fastmail.com/").unwrap();
        match jmap_srv(&site) {
            Srv::Target(url, record) => {
                assert_eq!(record, "api.fastmail.com:443");
                assert_eq!(url.as_str(), "https://api.fastmail.com/.well-known/jmap");
            }
            _ => panic!("expected fastmail.com to publish a _jmap._tcp record"),
        }
        assert!(matches!(jmap_srv(&Url::parse("http://localhost:8884/").unwrap()), Srv::Absent));
    }
}
