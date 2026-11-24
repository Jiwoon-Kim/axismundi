//! NON-STANDARD: the multi-Actor C2S Session our Pre-FEP proposes ("ActivityPub C2S Session
//! Discovery"). Discovering its URL (`/.well-known/activitypub`) stays in `discovery`, with the
//! other probes.
use serde::{Deserialize, Serialize};
use serde_json::Value;

use crate::{Error, http::Client};

#[derive(Clone, Debug, Deserialize, Serialize)]
#[serde(rename_all = "camelCase")]
pub struct ActingActor {
    pub id: String,
}

impl Client {
    /// Axismundi's authenticated AP discovery projection.
    pub fn acting_actors(&self, session_url: &str) -> Result<Vec<ActingActor>, Error> {
        self.acting_session(session_url).map(|(actors, _)| actors)
    }

    pub fn acting_session(&self, session_url: &str) -> Result<(Vec<ActingActor>, Value), Error> {
        #[derive(Deserialize)]
        struct ActingSession {
            actors: Vec<ActingActor>,
        }
        let document = self.activitypub_document(session_url)?;
        let session: ActingSession = serde_json::from_value(document.clone())
            .map_err(|error| Error::Shape(format!("AP Session: {error}")))?;
        Ok((session.actors, document))
    }
}
