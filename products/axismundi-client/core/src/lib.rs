//! The Axismundi client core: one HTTP layer (`http`) and a module per protocol — `jmap`,
//! `activitypub`, `wordpress` — with `discovery` composing their probes. `Error` is shared because
//! its kinds (transport, HTTP status, problem document, unexpected shape) are not protocol-specific.

use std::fmt;

pub mod activitypub;
pub mod discovery;
pub mod http;
pub mod jmap;
pub mod wordpress;

/// Failures an exchange can produce, kept apart because they mean different things.
#[derive(Debug)]
pub enum Error {
    /// The request never produced an HTTP response.
    Transport(String),
    /// A request-level JMAP error, which RFC 8620 serves as a problem document.
    Problem {
        status: u16,
        kind: String,
        detail: Option<String>,
    },
    /// A status this client cannot read as either success or a JMAP problem.
    Status { status: u16, body: String },
    /// The response was not the shape the specification describes.
    Shape(String),
}

impl fmt::Display for Error {
    fn fmt(&self, f: &mut fmt::Formatter<'_>) -> fmt::Result {
        match self {
            Error::Transport(message) => write!(f, "transport: {message}"),
            Error::Problem {
                status,
                kind,
                detail,
            } => match detail {
                Some(text) => write!(f, "jmap problem {status} {kind}: {text}"),
                None => write!(f, "jmap problem {status} {kind}"),
            },
            Error::Status { status, body } => write!(f, "http {status}: {body}"),
            Error::Shape(message) => write!(f, "unexpected response shape: {message}"),
        }
    }
}

impl std::error::Error for Error {}
