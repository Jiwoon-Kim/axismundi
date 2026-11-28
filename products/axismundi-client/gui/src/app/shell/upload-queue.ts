// An upload queue that does not know where files go. Angular-free so `node --test` checks the rules.
// A domain (Files today, ActivityPub media later) turns its save result into one of these states; the
// queue only keeps them apart, so "uploaded" never stands in for "saved" and an unanswered request is
// never shown as a failure that invites a second upload.

/** A file the native picker or window drop registered: the webview holds a handle, never a path. */
export interface QueueFile { handle: string; name: string; size: number; type: string }

export type QueueState =
  | "uploading"
  | "done"
  /** Saved, but the stored copy could not be read back yet. */
  | "unconfirmed"
  /** The name is taken: the person chooses to replace it or keep both. */
  | "conflict"
  | "failed"
  /** The request went out and no answer came back: it may have been saved. Check, do not resend. */
  | "unknown";

export interface QueueItem { key: string; file: QueueFile; state: QueueState; message: string | null }

export function enqueue(items: readonly QueueItem[], files: readonly QueueFile[], key: () => string): QueueItem[] {
  return [...items, ...files.map(file => ({key: key(), file, state: "uploading" as const, message: null}))];
}

export function settle(items: readonly QueueItem[], key: string, state: QueueState, message: string | null = null): QueueItem[] {
  return items.map(item => item.key === key ? {...item, state, message} : item);
}

/** What the queue's header says: how many are still going and how many need the person. */
export function summary(items: readonly QueueItem[]): {active: number; attention: number; done: number} {
  return {
    active: items.filter(item => item.state === "uploading").length,
    attention: items.filter(item => item.state === "conflict" || item.state === "failed" || item.state === "unknown").length,
    done: items.filter(item => item.state === "done" || item.state === "unconfirmed").length,
  };
}

/** Finished items can be cleared; ones that still need a choice or a check stay. */
export function clearFinished(items: readonly QueueItem[]): QueueItem[] {
  return items.filter(item => item.state !== "done");
}
