//! HTTP shared by every protocol: credentials, TLS trust, the agent, and the redirect policy that
//! keeps an Authorization header on its own origin.
use std::fmt;

use serde_json::Value;

use crate::Error;

pub(crate) fn platform_tls() -> ureq::tls::TlsConfig {
    ureq::tls::TlsConfig::builder()
        .root_certs(ureq::tls::RootCerts::PlatformVerifier)
        .build()
}

/// Username and secret for HTTP Basic. Never printed.
pub struct Credentials {
    pub user: String,
    pub secret: String,
}

impl fmt::Debug for Credentials {
    fn fmt(&self, f: &mut fmt::Formatter<'_>) -> fmt::Result {
        // The secret must not reach a log, a panic message or an error report.
        f.debug_struct("Credentials")
            .field("user", &self.user)
            .field("secret", &"<redacted>")
            .finish()
    }
}

pub struct Client {
    pub(crate) agent: ureq::Agent,
    pub(crate) credentials: Credentials,
    pub(crate) redirect_limit: u32,
}

impl Client {
    pub fn new(credentials: Credentials) -> Self {
        Self::with_redirect_limit(credentials, None)
    }

    /// Keep a write probe on its explicitly selected development endpoint.
    pub fn without_redirects(credentials: Credentials) -> Self {
        Self::with_redirect_limit(credentials, Some(0))
    }

    fn with_redirect_limit(credentials: Credentials, limit: Option<u32>) -> Self {
        // Status codes carry meaning here: a 4xx may be a JMAP problem document that has
        // to be read, so the agent must not turn a status into an error and drop the body.
        let config = ureq::Agent::config_builder()
            .http_status_as_error(false)
            .max_redirects(0)
            .tls_config(platform_tls())
            .timeout_global(Some(std::time::Duration::from_secs(30)))
            .user_agent("axismundi-jmap-client/0.1");
        let agent = config.build().into();
        Self { agent, credentials, redirect_limit: limit.unwrap_or(8) }
    }

    pub(crate) fn authorization(&self) -> String {
        use base64::Engine as _;
        let raw = format!("{}:{}", self.credentials.user, self.credentials.secret);
        format!(
            "Basic {}",
            base64::engine::general_purpose::STANDARD.encode(raw)
        )
    }

    pub(crate) fn get(&self, url: &str) -> Result<(String, String), Error> {
        self.get_with_accept(url, "application/json")
    }

    pub(crate) fn get_with_accept(&self, url: &str, accept: &str) -> Result<(String, String), Error> {
        let mut current = checked_http_url(url)?;
        for hop in 0..=self.redirect_limit {
            let response = self.agent.get(current.as_str())
                .header("Authorization", self.authorization()).header("Accept", accept)
                .call().map_err(|error| Error::Transport(error.to_string()))?;
            if matches!(response.status().as_u16(), 301 | 302 | 303 | 307 | 308) && self.redirect_limit > 0 {
                if hop == self.redirect_limit { return Err(Error::Shape("authenticated redirect limit exceeded".into())); }
                let location = response.headers().get("location").and_then(|v|v.to_str().ok())
                    .ok_or_else(||Error::Shape("redirect has no valid Location".into()))?;
                current = authenticated_redirect(&current, location)?;
            } else {
                return Ok((Self::read(response)?, current.into()));
            }
        }
        unreachable!()
    }

    pub(crate) fn post(&self, url: &str, payload: String) -> Result<String, Error> {
        let response = self
            .agent
            .post(url)
            .header("Authorization", self.authorization())
            .header("Accept", "application/json")
            .header("Content-Type", "application/json")
            .send(payload)
            .map_err(|error| Error::Transport(error.to_string()))?;
        Self::read(response)
    }

    /// Turn one HTTP response into either a body or the failure it actually represents.
    pub(crate) fn read(mut response: ureq::http::Response<ureq::Body>) -> Result<String, Error> {
        let status = response.status().as_u16();
        let content_type = response
            .headers()
            .get("content-type")
            .and_then(|value| value.to_str().ok())
            .unwrap_or_default()
            .to_string();
        let text = response
            .body_mut()
            .read_to_string()
            .map_err(|error| Error::Transport(format!("reading body: {error}")))?;
        if (200..300).contains(&status) {
            return Ok(text);
        }
        // A problem document is an answer from the protocol, not a transport failure,
        // so it keeps a variant of its own rather than collapsing into a status code.
        if content_type.starts_with("application/problem+json") {
            if let Ok(problem) = serde_json::from_str::<Value>(&text) {
                return Err(Error::Problem {
                    status,
                    kind: problem
                        .get("type")
                        .and_then(Value::as_str)
                        .unwrap_or("about:blank")
                        .to_string(),
                    detail: problem
                        .get("detail")
                        .and_then(Value::as_str)
                        .map(str::to_string),
                });
            }
        }
        Err(Error::Status { status, body: text })
    }
}

pub(crate) fn checked_http_url(value: &str) -> Result<url::Url, Error> {
    let url = url::Url::parse(value).map_err(|_|Error::Shape("invalid HTTP URL".into()))?;
    if !matches!(url.scheme(), "https" | "http") || !url.username().is_empty() || url.password().is_some() || url.fragment().is_some() {
        return Err(Error::Shape("HTTP URL must not contain credentials or a fragment".into()));
    }
    Ok(url)
}

pub(crate) fn authenticated_redirect(from: &url::Url, location: &str) -> Result<url::Url, Error> {
    let target = from.join(location).map_err(|_|Error::Shape("invalid redirect URL".into()))?;
    let target = checked_http_url(target.as_str())?;
    if target.origin() != from.origin() {
        return Err(Error::Shape("authenticated redirect changes origin; select and authorize the new endpoint explicitly".into()));
    }
    Ok(target)
}

#[cfg(test)]
mod tests {
    use super::*;
    use serde_json::json;

    #[test]
    fn credentials_never_print_the_secret() {
        let rendered = format!(
            "{:?}",
            Credentials {
                user: "admin".into(),
                secret: "abcd efgh ijkl".into(),
            }
        );
        assert!(rendered.contains("admin"));
        assert!(!rendered.contains("abcd"));
    }

    #[test]
    fn authenticated_redirects_require_the_same_origin() {
        let from = url::Url::parse("https://example.test:8443/start").unwrap();
        assert!(authenticated_redirect(&from,"/session").is_ok());
        for location in ["http://example.test:8443/session", "https://example.test/session",
            "https://elsewhere.test:8443/session", "https://user:secret@example.test:8443/session", "/session#fragment"] {
            assert!(authenticated_redirect(&from, location).is_err());
        }
        let canonical = url::Url::parse("https://example.test/start").unwrap();
        assert!(authenticated_redirect(&canonical,"https://example.test:443/session").is_ok());
    }

    #[test]
    fn desktop_tls_uses_platform_trust_without_disabling_verification() {
        let client=Client::new(Credentials{user:"u".into(),secret:"test-only".into()});
        let tls=client.agent.config().tls_config();
        assert!(matches!(tls.root_certs(),ureq::tls::RootCerts::PlatformVerifier));
        assert!(!tls.disable_verification());
        assert!(!platform_tls().disable_verification());
    }

    #[test]
    fn same_origin_redirect_keeps_authorization() {
        use std::{io::{BufRead,BufReader,Write},net::TcpListener,thread,time::Duration};
        let listener=TcpListener::bind("127.0.0.1:0").unwrap();
        let endpoint=format!("http://{}",listener.local_addr().unwrap());
        let server=thread::spawn(move || {
            for hop in 0..2 {
                let (mut stream,_)=listener.accept().unwrap();
                stream.set_read_timeout(Some(Duration::from_secs(5))).unwrap();
                let mut authorized=false;
                for line in BufReader::new(&mut stream).lines() {
                    let line=line.unwrap();
                    if line.is_empty() { break; }
                    if line.to_ascii_lowercase().starts_with("authorization: basic ") { authorized=true; }
                }
                assert!(authorized,"credentials missing on hop {hop}");
                if hop==0 {
                    write!(stream,"HTTP/1.1 307 Temporary Redirect\r\nLocation: /session\r\nContent-Length: 0\r\nConnection: close\r\n\r\n").unwrap();
                } else {
                    let body=json!({"apiUrl":"/api","username":"u","accounts":{}}).to_string();
                    write!(stream,"HTTP/1.1 200 OK\r\nContent-Length: {}\r\nConnection: close\r\n\r\n{}",body.len(),body).unwrap();
                }
            }
        });
        let client=Client::new(Credentials{user:"u".into(),secret:"test-only".into()});
        let session=client.session(&format!("{endpoint}/.well-known/jmap")).unwrap();
        assert_eq!(session.username,"u");
        assert_eq!(session.api_url,format!("{endpoint}/api"));
        server.join().unwrap();
    }

    #[test]
    fn cross_port_redirect_is_not_requested() {
        use std::{io::{BufRead,BufReader,Write},net::TcpListener,thread};
        let source=TcpListener::bind("127.0.0.1:0").unwrap();
        let target=TcpListener::bind("127.0.0.1:0").unwrap();
        target.set_nonblocking(true).unwrap();
        let endpoint=format!("http://{}/start",source.local_addr().unwrap());
        let location=format!("http://{}/target",target.local_addr().unwrap());
        let server=thread::spawn(move || {
            let (mut stream,_)=source.accept().unwrap();
            for line in BufReader::new(&mut stream).lines() { if line.unwrap().is_empty() {break;} }
            write!(stream,"HTTP/1.1 307 Temporary Redirect\r\nLocation: {location}\r\nContent-Length: 0\r\nConnection: close\r\n\r\n").unwrap();
        });
        let client=Client::new(Credentials{user:"u".into(),secret:"test-only".into()});
        assert!(client.session(&endpoint).unwrap_err().to_string().contains("changes origin"));
        server.join().unwrap();
        assert_eq!(target.accept().unwrap_err().kind(),std::io::ErrorKind::WouldBlock);
    }
}
