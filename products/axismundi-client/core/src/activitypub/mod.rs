//! ActivityPub: dereferencing Actor and ActivityStreams documents. Where these functions settle
//! (a submodule, and its name) is left open until the client grows Inbox/Outbox and C2S.
use serde_json::Value;

use crate::{
    Error,
    http::{Client, Credentials},
};

pub mod pre_fep;

impl Client {
    pub fn activitypub_document(&self, url: &str) -> Result<Value, Error> {
        let (body, _) = self.get_with_accept(
            url,
            r#"application/ld+json; profile="https://www.w3.org/ns/activitystreams", application/activity+json"#,
        )?;
        serde_json::from_str(&body).map_err(|error| Error::Shape(format!("ActivityPub: {error}")))
    }

    /// Public dereference: never attach the connection's credentials.
    pub fn public_actor_document(url: &str) -> Result<Value, Error> {
        Self::public_as2_document(url)
    }

    pub fn public_as2_document(url: &str) -> Result<Value, Error> {
        let client = Self::without_redirects(Credentials { user: String::new(), secret: String::new() });
        let response = client.agent.get(url)
            .header("Accept", r#"application/ld+json; profile="https://www.w3.org/ns/activitystreams", application/activity+json"#)
            .call().map_err(|error| Error::Transport(error.to_string()))?;
        let body = Self::read(response)?;
        serde_json::from_str(&body).map_err(|error| Error::Shape(format!("AS2 document: {error}")))
    }
}

#[cfg(test)]
mod tests {
    use crate::http::Client;

    #[test]
    fn public_actor_get_sends_as2_accept_without_credentials() {
        use std::{io::{BufRead, BufReader, Write}, net::TcpListener, thread, time::Duration};
        let listener = TcpListener::bind("127.0.0.1:0").unwrap();
        let address = format!("http://{}/actors/fixture", listener.local_addr().unwrap());
        let expected = address.clone();
        let server = thread::spawn(move || {
            let (mut stream, _) = listener.accept().unwrap();
            stream.set_read_timeout(Some(Duration::from_secs(5))).unwrap();
            let mut accept = false;
            for line in BufReader::new(&mut stream).lines() {
                let line = line.unwrap().to_ascii_lowercase();
                if line.is_empty() { break; }
                assert!(!line.starts_with("authorization:") && !line.starts_with("cookie:"));
                if line.starts_with("accept:") { accept = line.contains("application/ld+json"); }
            }
            assert!(accept);
            let body = serde_json::json!({"id": expected, "type": "Person"}).to_string();
            write!(stream, "HTTP/1.1 200 OK\r\nContent-Type: application/activity+json\r\nContent-Length: {}\r\nConnection: close\r\n\r\n{}", body.len(), body).unwrap();
        });
        assert_eq!(Client::public_actor_document(&address).unwrap()["id"], address);
        server.join().unwrap();
    }
}
