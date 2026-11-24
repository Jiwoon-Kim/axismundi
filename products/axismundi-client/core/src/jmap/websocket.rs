//! Push over the JMAP WebSocket subprotocol (RFC 8887). Requests keep going over HTTP.
//!
//! The socket only tells the client that something changed; it never carries the change. A
//! StateChange names, per account, the data types whose state moved, and the caller answers it by
//! running the same `/changes` and `/queryChanges` flow it would run on a timer. Push wakes that flow
//! up sooner; it does not replace it.
use std::{
    collections::BTreeMap,
    net::{TcpStream, ToSocketAddrs},
    sync::Arc,
    time::Duration,
};

use serde_json::{Value, json};
use tungstenite::{
    ClientRequestBuilder, Connector, Message, WebSocket, client_tls_with_config,
    stream::MaybeTlsStream,
};

use crate::{Error, http::Client, jmap::Session};

pub const CAPABILITY: &str = "urn:ietf:params:jmap:websocket";
const SUBPROTOCOL: &str = "jmap";

/// The WebSocket URL the Session advertises for push, checked before any credential is sent.
///
/// The URL must come from the Session, carry no credentials or fragment, and sit on the same origin
/// as apiUrl — the same host and port, with wss where apiUrl is https and ws where it is http. The
/// Authorization header goes on the upgrade request, so a socket on another origin would hand the
/// login to a host the user never chose; that is refused here exactly as an authenticated redirect to
/// another origin is.
pub fn endpoint(session: &Session) -> Result<url::Url, Error> {
    let capability = session
        .capabilities
        .get(CAPABILITY)
        .ok_or_else(|| Error::Shape("Session does not advertise JMAP over WebSocket".into()))?;
    if capability.get("supportsPush").and_then(Value::as_bool) != Some(true) {
        return Err(Error::Shape("the WebSocket endpoint does not offer push".into()));
    }
    let raw = capability
        .get("url")
        .and_then(Value::as_str)
        .ok_or_else(|| Error::Shape("WebSocket capability has no url".into()))?;
    let url = url::Url::parse(raw).map_err(|_| Error::Shape("invalid WebSocket url".into()))?;
    if !url.username().is_empty() || url.password().is_some() || url.fragment().is_some() {
        return Err(Error::Shape("WebSocket url must not contain credentials or a fragment".into()));
    }
    let api = url::Url::parse(&session.api_url)
        .map_err(|_| Error::Shape("invalid Session apiUrl".into()))?;
    let expected = match api.scheme() {
        "https" => "wss",
        "http" => "ws",
        _ => return Err(Error::Shape("apiUrl is not HTTP(S)".into())),
    };
    if url.scheme() != expected
        || url.host_str() != api.host_str()
        || url.port_or_known_default() != api.port_or_known_default()
    {
        return Err(Error::Shape(
            "WebSocket url is not on the apiUrl origin; select and authorize it explicitly".into(),
        ));
    }
    Ok(url)
}

/// Something the push channel delivered, or that nothing did before the read timeout.
#[derive(Debug, PartialEq)]
pub enum Event {
    /// RFC 8620 §7.1: account id → data type → new state. Only what moved is named.
    StateChange {
        changed: BTreeMap<String, BTreeMap<String, String>>,
        push_state: Option<String>,
    },
    /// The read timeout passed with nothing to read. The connection is still open; this lets a
    /// caller check whether it should stop without a second thread.
    Idle,
}

pub struct PushChannel {
    socket: WebSocket<MaybeTlsStream<TcpStream>>,
}

impl Client {
    /// Opens the Session's WebSocket and asks it to push state changes.
    ///
    /// `data_types` limits the push to those types (`None` is every type the account has).
    /// `push_state` is the last pushState a previous connection received: a server that can resume
    /// sends whatever changed while the client was away, so a reconnect does not lose a change.
    pub fn push_channel(
        &self,
        session: &Session,
        data_types: Option<&[&str]>,
        push_state: Option<&str>,
        idle: Duration,
    ) -> Result<PushChannel, Error> {
        let url = endpoint(session)?;
        let host = url
            .host_str()
            .ok_or_else(|| Error::Shape("WebSocket url has no host".into()))?;
        let port = url
            .port_or_known_default()
            .ok_or_else(|| Error::Shape("WebSocket url has no port".into()))?;
        let stream = connect(host, port)?;
        stream
            .set_read_timeout(Some(idle))
            .map_err(|e| Error::Transport(e.to_string()))?;
        let uri = url
            .as_str()
            .parse()
            .map_err(|_| Error::Shape("WebSocket url is not a valid URI".into()))?;
        let request = ClientRequestBuilder::new(uri)
            .with_header("Authorization", self.authorization())
            .with_sub_protocol(SUBPROTOCOL);
        let connector = Connector::Rustls(Arc::new(platform_tls()?));
        let (socket, response) =
            match client_tls_with_config(request, stream, None, Some(connector)) {
                Ok(done) => done,
                Err(tungstenite::HandshakeError::Failure(tungstenite::Error::Http(response))) => {
                    let body = response
                        .body()
                        .as_deref()
                        .map(|b| String::from_utf8_lossy(b).into_owned())
                        .unwrap_or_default();
                    return Err(Error::Status {
                        status: response.status().as_u16(),
                        body,
                    });
                }
                // Not a network failure: the server answered and will answer the same way again, so
                // a reconnect loop must not retry it.
                Err(tungstenite::HandshakeError::Failure(tungstenite::Error::Protocol(
                    tungstenite::error::ProtocolError::SecWebSocketSubProtocolError(_),
                ))) => {
                    return Err(Error::Shape(
                        "WebSocket server did not accept the jmap subprotocol".into(),
                    ));
                }
                Err(e) => return Err(Error::Transport(e.to_string())),
            };
        // RFC 8887 §4.2: the server must agree to the jmap subprotocol. One that upgrades without it
        // is speaking something else on that socket.
        let agreed = response
            .headers()
            .get("sec-websocket-protocol")
            .and_then(|v| v.to_str().ok());
        if agreed != Some(SUBPROTOCOL) {
            return Err(Error::Shape("WebSocket server did not accept the jmap subprotocol".into()));
        }
        let mut channel = PushChannel { socket };
        let mut enable = json!({"@type":"WebSocketPushEnable","dataTypes":data_types});
        if let Some(state) = push_state {
            enable["pushState"] = json!(state);
        }
        channel
            .socket
            .send(Message::text(enable.to_string()))
            .map_err(|e| Error::Transport(e.to_string()))?;
        Ok(channel)
    }
}

/// Tries every address the host resolves to, in order, as the HTTP client does.
///
/// Taking only the first one breaks on an ordinary setup: `localhost` resolves to `::1` before
/// `127.0.0.1`, and a port Docker publishes only on IPv4 refuses the IPv6 attempt — measured against
/// the local Stalwart, where HTTP worked and the socket did not.
fn connect(host: &str, port: u16) -> Result<TcpStream, Error> {
    // url keeps an IPv6 literal in brackets; the resolver wants it bare.
    let host = host.trim_start_matches('[').trim_end_matches(']');
    let mut last = None;
    for address in (host, port)
        .to_socket_addrs()
        .map_err(|e| Error::Transport(e.to_string()))?
    {
        match TcpStream::connect_timeout(&address, Duration::from_secs(10)) {
            Ok(stream) => return Ok(stream),
            Err(e) => last = Some(format!("{address}: {e}")),
        }
    }
    Err(Error::Transport(
        last.unwrap_or_else(|| "WebSocket host did not resolve".into()),
    ))
}

/// The same trust the HTTP client uses: the platform's roots and verifier, ring as the provider,
/// verification never disabled.
fn platform_tls() -> Result<rustls::ClientConfig, Error> {
    use rustls_platform_verifier::BuilderVerifierExt;
    let provider = Arc::new(rustls::crypto::ring::default_provider());
    Ok(rustls::ClientConfig::builder_with_provider(provider)
        .with_safe_default_protocol_versions()
        .map_err(|e| Error::Transport(e.to_string()))?
        .with_platform_verifier()
        .map_err(|e| Error::Transport(e.to_string()))?
        .with_no_client_auth())
}

fn state_change(message: &Value) -> Result<Event, Error> {
    let changed = message
        .get("changed")
        .and_then(Value::as_object)
        .ok_or_else(|| Error::Shape("StateChange has no changed object".into()))?;
    let mut accounts = BTreeMap::new();
    for (account, types) in changed {
        let types = types
            .as_object()
            .ok_or_else(|| Error::Shape("StateChange account entry is not an object".into()))?;
        let mut states = BTreeMap::new();
        for (kind, state) in types {
            let state = state
                .as_str()
                .ok_or_else(|| Error::Shape("StateChange state is not a string".into()))?;
            states.insert(kind.clone(), state.to_owned());
        }
        accounts.insert(account.clone(), states);
    }
    let push_state = match message.get("pushState") {
        None | Some(Value::Null) => None,
        Some(Value::String(s)) => Some(s.clone()),
        Some(_) => return Err(Error::Shape("pushState is not a string".into())),
    };
    Ok(Event::StateChange {
        changed: accounts,
        push_state,
    })
}

impl PushChannel {
    /// Waits for the next state change, or for the idle timeout to pass.
    ///
    /// Pings are answered by the library and are not events. A message of a type this client does
    /// not know is skipped, as JSON objects of an unknown `@type` should be. A RequestError is the
    /// server refusing WebSocketPushEnable, and a close ends the channel; both are errors, and the
    /// caller decides whether to reconnect.
    pub fn next(&mut self) -> Result<Event, Error> {
        loop {
            match self.socket.read() {
                Ok(Message::Text(text)) => {
                    let message: Value = serde_json::from_str(text.as_str())
                        .map_err(|e| Error::Shape(format!("WebSocket message: {e}")))?;
                    match message.get("@type").and_then(Value::as_str) {
                        Some("StateChange") => return state_change(&message),
                        Some("RequestError") => {
                            return Err(Error::Problem {
                                status: message.get("status").and_then(Value::as_u64).unwrap_or(0)
                                    as u16,
                                kind: message
                                    .get("type")
                                    .and_then(Value::as_str)
                                    .unwrap_or("unknown")
                                    .to_owned(),
                                detail: message
                                    .get("detail")
                                    .and_then(Value::as_str)
                                    .map(str::to_owned),
                            });
                        }
                        _ => continue,
                    }
                }
                Ok(Message::Close(frame)) => {
                    return Err(Error::Transport(format!(
                        "WebSocket closed{}",
                        frame.map(|f| format!(": {} {}", f.code, f.reason)).unwrap_or_default()
                    )));
                }
                Ok(Message::Binary(_)) => {
                    return Err(Error::Shape("JMAP WebSocket messages are text".into()));
                }
                Ok(_) => continue,
                Err(tungstenite::Error::Io(e))
                    if matches!(
                        e.kind(),
                        std::io::ErrorKind::WouldBlock | std::io::ErrorKind::TimedOut
                    ) =>
                {
                    return Ok(Event::Idle);
                }
                Err(e) => return Err(Error::Transport(e.to_string())),
            }
        }
    }

    /// Says goodbye properly, so the server can drop its subscription at once.
    pub fn close(mut self) {
        let _ = self.socket.close(None);
        let _ = self.socket.flush();
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::http::Credentials;
    use std::net::TcpListener;
    use tungstenite::handshake::server::{ErrorResponse, Request, Response};

    fn session(api: &str, ws: &str, push: bool) -> Session {
        serde_json::from_value(json!({"apiUrl":api,"capabilities":{CAPABILITY:{"url":ws,"supportsPush":push}}})).unwrap()
    }

    fn client() -> Client {
        Client::new(Credentials { user: "fixture".into(), secret: "test-only".into() })
    }

    #[test]
    fn the_socket_must_be_advertised_for_push_and_share_the_api_origin() {
        let ok = |api: &str, ws: &str| endpoint(&session(api, ws, true)).is_ok();
        assert!(ok("https://mail.test/jmap/", "wss://mail.test/jmap/ws"));
        assert!(ok("https://mail.test:443/jmap/", "wss://mail.test/ws"));
        assert!(ok("http://127.0.0.1:8080/jmap", "ws://127.0.0.1:8080/ws"));
        assert!(!ok("https://mail.test/jmap/", "ws://mail.test/jmap/ws"), "plaintext under https");
        assert!(!ok("https://mail.test/jmap/", "wss://push.mail.test/ws"), "another host");
        assert!(!ok("https://mail.test/jmap/", "wss://mail.test:8443/ws"), "another port");
        assert!(!ok("https://mail.test/jmap/", "wss://u:p@mail.test/ws"), "credentials");
        assert!(!ok("https://mail.test/jmap/", "https://mail.test/ws"), "not a socket");
        assert!(endpoint(&session("https://mail.test/", "wss://mail.test/ws", false)).is_err());
        let bare: Session = serde_json::from_value(json!({"apiUrl":"https://mail.test/","capabilities":{}})).unwrap();
        assert!(endpoint(&bare).is_err());
    }

    /// One server connection: `handshake` decides the upgrade response, `script` runs after it.
    fn serve(
        handshake: impl FnOnce(&Request, Response) -> Result<Response, ErrorResponse> + Send + 'static,
        script: impl FnOnce(WebSocket<TcpStream>) + Send + 'static,
    ) -> (String, std::thread::JoinHandle<()>) {
        let listener = TcpListener::bind("127.0.0.1:0").unwrap();
        let address = listener.local_addr().unwrap();
        let server = std::thread::spawn(move || {
            let (stream, _) = listener.accept().unwrap();
            stream.set_read_timeout(Some(Duration::from_secs(5))).unwrap();
            if let Ok(socket) = tungstenite::accept_hdr(stream, handshake) {
                script(socket);
            }
        });
        (format!("127.0.0.1:{}", address.port()), server)
    }

    fn accept_jmap(request: &Request, mut response: Response) -> Result<Response, ErrorResponse> {
        assert_eq!(
            request.headers().get("authorization").unwrap(),
            "Basic Zml4dHVyZTp0ZXN0LW9ubHk="
        );
        assert_eq!(request.headers().get("sec-websocket-protocol").unwrap(), "jmap");
        response
            .headers_mut()
            .insert("sec-websocket-protocol", "jmap".parse().unwrap());
        Ok(response)
    }

    #[test]
    fn push_is_enabled_and_only_state_changes_come_through() {
        let (go, wait) = std::sync::mpsc::channel::<()>();
        let (host, server) = serve(accept_jmap, move |mut socket| {
            let enable: Value =
                serde_json::from_str(socket.read().unwrap().to_text().unwrap()).unwrap();
            assert_eq!(
                enable,
                json!({"@type":"WebSocketPushEnable","dataTypes":["Email","Mailbox"],"pushState":"p0"})
            );
            socket.send(Message::Ping(vec![1].into())).unwrap();
            socket.send(Message::text(r#"{"@type":"FutureThing","x":1}"#)).unwrap();
            socket
                .send(Message::text(
                    json!({"@type":"StateChange","changed":{"b":{"Email":"s9","Mailbox":"m2"}},"pushState":"p1"}).to_string(),
                ))
                .unwrap();
            wait.recv().unwrap();
            socket.close(None).unwrap();
            let _ = socket.flush();
            while socket.read().is_ok() {}
        });
        let session = session(&format!("http://{host}/jmap"), &format!("ws://{host}/ws"), true);
        let mut channel = client()
            .push_channel(&session, Some(&["Email", "Mailbox"]), Some("p0"), Duration::from_millis(300))
            .unwrap();
        let mut expected = BTreeMap::new();
        expected.insert(
            "b".to_owned(),
            BTreeMap::from([("Email".to_owned(), "s9".to_owned()), ("Mailbox".to_owned(), "m2".to_owned())]),
        );
        assert_eq!(
            channel.next().unwrap(),
            Event::StateChange { changed: expected, push_state: Some("p1".into()) }
        );
        assert_eq!(channel.next().unwrap(), Event::Idle, "nothing more within the idle timeout");
        go.send(()).unwrap();
        assert!(matches!(channel.next(), Err(Error::Transport(_))), "a close ends the channel");
        server.join().unwrap();
    }

    #[test]
    fn a_server_that_does_not_agree_to_jmap_is_refused() {
        let (host, server) = serve(|_, response| Ok(response), |_| {});
        let session = session(&format!("http://{host}/jmap"), &format!("ws://{host}/ws"), true);
        let result = client().push_channel(&session, None, None, Duration::from_secs(1));
        assert!(
            matches!(&result, Err(Error::Shape(m)) if m.contains("jmap subprotocol")),
            "{:?}",
            result.err()
        );
        server.join().unwrap();
    }

    #[test]
    fn a_refused_upgrade_reports_its_status() {
        let (host, server) = serve(
            |_, _| {
                let mut refusal = ErrorResponse::new(Some("no".into()));
                *refusal.status_mut() = tungstenite::http::StatusCode::UNAUTHORIZED;
                Err(refusal)
            },
            |_| {},
        );
        let session = session(&format!("http://{host}/jmap"), &format!("ws://{host}/ws"), true);
        let result = client().push_channel(&session, None, None, Duration::from_secs(1));
        assert!(matches!(result, Err(Error::Status { status: 401, .. })), "{:?}", result.err());
        server.join().unwrap();
    }

    #[test]
    fn a_refused_push_enable_is_an_error_not_silence() {
        let (host, server) = serve(accept_jmap, |mut socket| {
            socket.read().unwrap();
            socket
                .send(Message::text(json!({"@type":"RequestError","type":"urn:ietf:params:jmap:error:notRequest","status":400}).to_string()))
                .unwrap();
            while socket.read().is_ok() {}
        });
        let session = session(&format!("http://{host}/jmap"), &format!("ws://{host}/ws"), true);
        let mut channel = client().push_channel(&session, None, None, Duration::from_secs(2)).unwrap();
        assert!(matches!(channel.next(), Err(Error::Problem { status: 400, .. })));
        channel.close();
        server.join().unwrap();
    }
}
