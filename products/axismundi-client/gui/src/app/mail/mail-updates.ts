import type { MailRow } from "./mail-rows.ts";

export type Keyword = "seen" | "flagged";

/**
 * What a write changes about a message. An Email can change in two ways only (RFC 8621 §4): its
 * keywords and its mailboxIds. Each keyword is its own aspect; all mailbox moves share one.
 */
export type Aspect = Keyword | "mailboxes";

/**
 * One write. A move names the mailbox the message is listed in and the one it goes to. `exclusive`
 * also takes it out of every other mailbox this login sees (Rust `cache::move_emails`); which moves
 * ask for that is `exclusiveMove()` in mail-moves.ts.
 */
export type Change =
  | {kind: "keyword"; keyword: Keyword; on: boolean}
  | {kind: "move"; from: string | null; to: string; exclusive: boolean};

export function aspectOf(change: Change): Aspect {
  return change.kind === "keyword" ? change.keyword : "mailboxes";
}

/**
 * The answer of `mail_set_keywords` and `mail_move`, declared in Rust (`UpdateView`). One result per
 * distinct id asked for, in the order first asked.
 */
export type UpdateResult =
  | {outcome: "confirmed"; id: string}
  /** Known not to have applied: refused, failed before the server did anything, or read back unchanged. */
  | {outcome: "notApplied"; id: string; reason: string}
  /** Not known: read the message again before sending anything again. */
  | {outcome: "unknown"; id: string; reason: string};

export interface UpdateView {
  results: UpdateResult[];
  /** False when a confirmed change could not be read back: the views have to sync to show it. */
  readBackComplete: boolean;
}

export interface Settled {
  confirmed: string[];
  notApplied: string[];
  unknown: string[];
  /** Rows that stay selected: everything not confirmed, in result order. */
  keepSelected: string[];
  /** Whether the views should read again: something may have changed on the server. */
  refresh: boolean;
  /** One line for the user, or null when everything was confirmed and read back. */
  notice: string | null;
}

/** What the GUI does with an answer. `action` names the change, e.g. "Mark as read". */
export function settle(view: UpdateView, action: string): Settled {
  const confirmed: string[] = [];
  const notApplied: string[] = [];
  const unknown: string[] = [];
  for (const result of view.results) {
    (result.outcome === "confirmed" ? confirmed : result.outcome === "notApplied" ? notApplied : unknown).push(result.id);
  }
  const keepSelected = view.results.filter(result => result.outcome !== "confirmed").map(result => result.id);
  const refresh = confirmed.length > 0 || unknown.length > 0;
  const total = view.results.length;
  let notice: string | null = null;
  if (notApplied.length || unknown.length) {
    if (total === 1) {
      const only = view.results[0];
      notice = only.outcome === "confirmed" ? null : `${action}: not done. ${only.reason}`;
    } else {
      const parts = [`${action}: ${confirmed.length} of ${total} done`];
      if (notApplied.length) parts.push(`${notApplied.length} not applied`);
      if (unknown.length) parts.push(`${unknown.length} unknown — check before trying again`);
      notice = parts.join(", ") + ".";
    }
  } else if (!view.readBackComplete) {
    notice = `${action}: done, but the messages could not be read back. Refreshing.`;
  }
  return {confirmed, notApplied, unknown, keepSelected, refresh, notice};
}

/** The one IPC function the writer needs; `invoke` in the app, a fake in the spec. */
export type Call = (command: string, args: Record<string, unknown>) => Promise<unknown>;

export interface Target {
  connectionId: number;
  accountId: string;
}

export interface WriteResult {
  /** The write's outcome, or null when every id was held back and nothing was sent. */
  settled: Settled | null;
  /** Ids not written because their last outcome for this aspect is still unknown. */
  held: string[];
  /** Ids whose state for this aspect is still unknown after this call: writes stay blocked. */
  unverified: string[];
  /** Whether the verifying read failed this time. */
  checkFailed: boolean;
  /**
   * Set when the write request itself failed. Whether it reached the server is then unknown (the
   * answer may have been dropped after sending, e.g. on a sign-out), so its ids are treated as
   * unknown outcomes: blocked and checked.
   */
  writeError: unknown;
}

/**
 * Where a message is, as far as the server has confirmed: mailboxes it is known to be in or out of.
 * `complete` when it came from a read of the whole membership, so any other mailbox is out.
 */
interface Membership {
  in: ReadonlySet<string>;
  out: ReadonlySet<string>;
  complete: boolean;
}

/**
 * Sends writes and holds back any message whose last outcome for that aspect is unknown.
 *
 * `unknown` means the server may or may not have applied the change. Writing again before reading
 * would act on a state nobody has seen, so such a message is blocked for that aspect until a read
 * (`mail_check`) returns it with the aspect known — or returns it gone. A click on a blocked message
 * runs that read instead of the write; if the read fails the block stays. Selection plays no part:
 * deselecting and selecting again does not lift it. The block is per connection, Account, message
 * and aspect (each keyword, or "mailboxes" for every move), and lives as long as this object.
 *
 * Lifting a block is not enough on its own: the views may still hold the value from before, and a
 * toggle decides its direction from what is shown. So every value the server confirms — a
 * confirmed write, or what a check read — is kept here, stamped, and `shown()` / `shownIn()` put it
 * in front of a view's value until that view has loaded again after the stamp. The block lifts in
 * the same step the value is kept, so no moment exists where a write is allowed against a stale
 * value.
 */
export class MailWriter {
  private readonly blocked = new Set<string>();
  private readonly known = new Map<string, {value: boolean | Membership; at: number}>();
  private tick = 0;
  private readonly call: Call;

  constructor(call: Call) {
    this.call = call;
  }

  isUnverified(target: Target, id: string, aspect: Aspect): boolean {
    return this.blocked.has(key(target, id, aspect));
  }

  /** A stamp to take when a view starts loading; pass it to `shown()` once that load has landed. */
  mark(): number {
    return this.tick;
  }

  /**
   * Whether the message has the keyword, as the screen should show it: the value the server confirmed
   * after the view's last load started, else the view's own value.
   */
  shown(target: Target, id: string, keyword: Keyword, viewHas: boolean | null, loadedFrom: number): boolean | null {
    const confirmed = this.known.get(key(target, id, keyword));
    return confirmed && confirmed.at > loadedFrom && typeof confirmed.value === "boolean" ? confirmed.value : viewHas;
  }

  /** Whether the message is in the mailbox, as the screen should show it; same terms as `shown()`. */
  shownIn(target: Target, id: string, mailboxId: string, viewIn: boolean, loadedFrom: number): boolean {
    const confirmed = this.known.get(key(target, id, "mailboxes"));
    if (!confirmed || confirmed.at <= loadedFrom || typeof confirmed.value === "boolean") return viewIn;
    const membership = confirmed.value;
    if (membership.in.has(mailboxId)) return true;
    if (membership.out.has(mailboxId) || membership.complete) return false;
    return viewIn;
  }

  private keep(target: Target, id: string, aspect: Aspect, value: boolean | Membership): void {
    this.known.set(key(target, id, aspect), {value, at: ++this.tick});
  }

  async write(target: Target, ids: readonly string[], change: Change, action: string): Promise<WriteResult> {
    const aspect = aspectOf(change);
    const held = ids.filter(id => this.isUnverified(target, id, aspect));
    const allowed = ids.filter(id => !this.isUnverified(target, id, aspect));
    let settled: Settled | null = null;
    let writeError: unknown = null;
    let unknown: string[] = [];
    if (allowed.length) {
      try {
        const view = (change.kind === "keyword"
          ? await this.call("mail_set_keywords", {...target, emailIds: allowed, keyword: change.keyword, on: change.on})
          : await this.call("mail_move", {...target, emailIds: allowed, from: change.from, to: change.to, exclusive: change.exclusive})) as UpdateView;
        settled = settle(view, action);
        unknown = settled.unknown;
        for (const id of settled.confirmed) {
          // A confirmed move proves the source left and the destination joined, nothing more: an
          // exclusive one also leaves mailboxes this login may not remove from, or has not cached, so
          // it is not a complete membership. The reload `changed` triggers learns the rest.
          this.keep(target, id, aspect, change.kind === "keyword"
            ? change.on
            : {in: new Set([change.to]), out: new Set(change.from === null ? [] : [change.from]), complete: false});
        }
      } catch (error) {
        writeError = error ?? "failed";
        unknown = allowed;
      }
      for (const id of unknown) this.blocked.add(key(target, id, aspect));
    }
    const toCheck = [...held, ...unknown];
    const checkFailed = toCheck.length ? !(await this.check(target, toCheck, aspect)) : false;
    const unverified = ids.filter(id => this.isUnverified(target, id, aspect));
    return {settled, held, unverified, checkFailed, writeError};
  }

  /** Reads the messages and lifts the block on each one the server shows with this aspect known. */
  async check(target: Target, ids: readonly string[], aspect: Aspect): Promise<boolean> {
    let rows: MailRow[];
    try {
      rows = await this.call("mail_check", {...target, emailIds: ids}) as MailRow[];
    } catch {
      return false;
    }
    for (const row of rows) {
      if (row.kind === "unavailable") {
        this.blocked.delete(key(target, row.id, aspect));
        continue;
      }
      let value: boolean | Membership;
      if (aspect === "mailboxes") {
        if (row.mailboxIds === null) continue;
        value = {in: new Set(row.mailboxIds), out: new Set(), complete: true};
      } else {
        const has = aspect === "seen" ? row.unread : row.flagged;
        if (has === null) continue;
        value = aspect === "seen" ? !has : has;
      }
      // Keep the value first, then lift the block: the screen never allows a write it cannot show.
      this.keep(target, row.id, aspect, value);
      this.blocked.delete(key(target, row.id, aspect));
    }
    return true;
  }
}

function key(target: Target, id: string, aspect: Aspect): string {
  return JSON.stringify([target.connectionId, target.accountId, id, aspect]);
}
