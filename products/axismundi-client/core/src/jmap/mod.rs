//! JMAP (RFC 8620) and the data types built on it. Folders follow what the code does; the RFC
//! each part implements is named in its comments and tests.
//!
//! A JMAP client core that holds no knowledge of any particular server.
//!
//! The one rule this module exists to enforce: every URL comes from the Session
//! resource. The caller supplies a Session URL and credentials, and nothing else
//! about the server's shape is assumed - no path suffix is appended, no account
//! identifier is guessed, and no capability is presumed present. A client that
//! builds `apiUrl` itself cannot be evidence of interoperability, because it would
//! only ever work against the server it was written for.
mod request;
mod session;

pub mod blob;
pub mod contacts;
pub mod mail;
pub mod websocket;

pub use request::{Batch, Invocation, SetError, SetOutcome};
pub use session::{Account, Session};

pub const CORE_CAPABILITY: &str = "urn:ietf:params:jmap:core";
