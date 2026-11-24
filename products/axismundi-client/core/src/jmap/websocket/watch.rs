use super::{Event, PushChannel};
use crate::Error;
use std::{collections::BTreeMap, time::Duration};

/// A mail listing may need to be refreshed; the state values are not cached here.
pub enum Wake {
    Initial,
    CatchUp,
    Push(BTreeMap<String, Vec<String>>),
}

pub enum WatchStatus<'a> {
    ConnectFailed(&'a str, Duration),
    Connected,
    Dropped(&'a str),
}

pub trait Push {
    fn next(&mut self) -> Result<Event, Error>;
    fn close(self);
}

impl Push for PushChannel {
    fn next(&mut self) -> Result<Event, Error> {
        PushChannel::next(self)
    }
    fn close(self) {
        PushChannel::close(self)
    }
}

/// Subscribe before catching up, on the first connection and on every reconnect.
/// The initial callback is only for callers that want a listing before connecting.
/// Cancellation is checked after blocking operations and before invoking callbacks.
pub fn watch_loop<C: Push>(
    accounts: &[&str],
    limit: Option<usize>,
    mut connect: impl FnMut(Option<&str>) -> Result<C, Error>,
    mut sync: impl FnMut(Wake) -> Result<(), String>,
    mut wait: impl FnMut(Duration),
    cancelled: impl Fn() -> bool,
    mut status: impl FnMut(WatchStatus<'_>),
    explain: impl Fn(Error) -> String,
) -> Result<(), String> {
    if cancelled() { return Ok(()); }
    sync(Wake::Initial)?;
    let mut push_state: Option<String> = None;
    let mut synced = 0usize;
    let mut backoff = Duration::from_secs(1);
    while !cancelled() && !limit.is_some_and(|n| synced >= n) {
        let mut channel = match connect(push_state.as_deref()) {
            Ok(channel) => channel,
            Err(Error::Transport(error)) => {
                if !cancelled() {
                    status(WatchStatus::ConnectFailed(&error, backoff));
                    wait(backoff);
                }
                backoff = (backoff * 2).min(Duration::from_secs(60));
                continue;
            }
            Err(error) => return Err(explain(error)),
        };
        if cancelled() { channel.close(); return Ok(()); }
        backoff = Duration::from_secs(1);
        status(WatchStatus::Connected);
        sync(Wake::CatchUp)?;
        while !cancelled() {
            let event = channel.next();
            if cancelled() { break; }
            match event {
                Ok(Event::Idle) => continue,
                Ok(Event::StateChange { changed, push_state: state }) => {
                    if state.is_some() { push_state = state; }
                    let changed: BTreeMap<_, Vec<_>> = changed.into_iter().filter_map(|(account, types)| {
                        if !accounts.contains(&account.as_str()) { return None; }
                        let mail: Vec<_> = types.into_keys().filter(|t| matches!(t.as_str(), "Email" | "Mailbox")).collect();
                        (!mail.is_empty()).then_some((account, mail))
                    }).collect();
                    if changed.is_empty() { continue; }
                    sync(Wake::Push(changed))?;
                    synced += 1;
                    if limit.is_some_and(|n| synced >= n) { break; }
                }
                Err(Error::Transport(error)) => {
                    status(WatchStatus::Dropped(&error));
                    break;
                }
                Err(error) => return Err(explain(error)),
            }
        }
        channel.close();
    }
    Ok(())
}

#[cfg(test)]
mod watch_tests {
    use super::*;
    use std::{cell::RefCell, collections::{BTreeMap, VecDeque}, rc::Rc};

    type Log = Rc<RefCell<Vec<String>>>;

    struct Script {
        events: VecDeque<Result<Event, Error>>,
    }

    impl Push for Script {
        fn next(&mut self) -> Result<Event, Error> {
            self.events.pop_front().unwrap_or(Err(Error::Transport("script ended".into())))
        }
        fn close(self) {}
    }

    fn change(state: &str) -> Result<Event, Error> {
        let mut changed = BTreeMap::new();
        changed.insert("b".to_owned(), BTreeMap::from([("Email".to_owned(), state.to_owned())]));
        Ok(Event::StateChange { changed, push_state: Some(state.to_owned()) })
    }

    /// Mail that arrives while the connection is down — here, during two failed attempts and a
    /// drop — reaches the listing through a sync that runs after the next subscription is open.
    #[test]
    fn every_connection_is_opened_before_the_sync_that_catches_up() {
        let log: Log = Rc::default();
        let mut attempts: VecDeque<Result<Script, Error>> = VecDeque::from([
            Err(Error::Transport("refused".into())),
            Err(Error::Transport("refused".into())),
            Ok(Script { events: VecDeque::from([Ok(Event::Idle), change("p1"), Err(Error::Transport("dropped".into()))]) }),
            Ok(Script { events: VecDeque::from([change("p2")]) }),
        ]);
        let offered: Rc<RefCell<Vec<Option<String>>>> = Rc::default();
        let (l1, l2, l3, o) = (log.clone(), log.clone(), log.clone(), offered.clone());
        watch_loop(
            &["b"],
            Some(2),
            move |state| {
                o.borrow_mut().push(state.map(str::to_owned));
                let attempt = attempts.pop_front().unwrap();
                l1.borrow_mut().push(if attempt.is_ok() { "connect" } else { "connect failed" }.into());
                attempt
            },
            move |why| {
                l2.borrow_mut().push(format!("sync {}", match why { Wake::Initial => "initial", Wake::CatchUp => "catch-up", Wake::Push(_) => "push Email" }));
                Ok(())
            },
            move |_| l3.borrow_mut().push("wait".into()),
            || false,
            |_| {},
            |error| error.to_string(),
        )
        .unwrap();
        assert_eq!(
            *log.borrow(),
            [
                "sync initial",
                "connect failed", "wait",
                "connect failed", "wait",
                "connect", "sync catch-up", "sync push Email",
                "connect", "sync catch-up", "sync push Email",
            ]
        );
        // The reconnect offers the pushState the dropped connection last delivered.
        assert_eq!(*offered.borrow(), [None, None, None, Some("p1".to_owned())]);
    }

    #[test]
    fn a_refusal_that_will_repeat_ends_the_loop_instead_of_retrying() {
        let result = watch_loop::<Script>(
            &["b"],
            None,
            |_| Err(Error::Shape("WebSocket server did not accept the jmap subprotocol".into())),
            |_| Ok(()),
            |_| panic!("must not wait to retry a refusal"),
            || false,
            |_| {},
            |error| error.to_string(),
        );
        assert!(result.is_err());
    }

    #[test]
    fn only_watched_accounts_wake_the_listing() {
        let other = Ok(Event::StateChange {
            changed: BTreeMap::from([("other".into(), BTreeMap::from([("Email".into(), "s1".into())]))]),
            push_state: None,
        });
        let mut script = Some(Script { events: VecDeque::from([other, change("p1")]) });
        let mut pushes = 0;
        watch_loop(
            &["b"], Some(1),
            |_| Ok(script.take().unwrap()),
            |wake| {
                if let Wake::Push(changed) = wake {
                    assert_eq!(changed, BTreeMap::from([("b".into(), vec!["Email".into()])]));
                    pushes += 1;
                }
                Ok(())
            },
            |_| panic!("unexpected reconnect"),
            || false,
            |_| {},
            |error| error.to_string(),
        ).unwrap();
        assert_eq!(pushes, 1);
    }

    #[test]
    fn cancelling_during_connect_closes_without_catching_up() {
        use std::cell::Cell;
        let cancelled = Cell::new(false);
        let mut wakes = 0;
        watch_loop(
            &["b"],
            None,
            |_| {
                cancelled.set(true);
                Ok(Script { events: VecDeque::new() })
            },
            |wake| {
                assert!(matches!(wake, Wake::Initial));
                wakes += 1;
                Ok(())
            },
            |_| panic!("must not wait after cancellation"),
            || cancelled.get(),
            |_| {},
            |error| error.to_string(),
        ).unwrap();
        assert_eq!(wakes, 1);
    }
}
