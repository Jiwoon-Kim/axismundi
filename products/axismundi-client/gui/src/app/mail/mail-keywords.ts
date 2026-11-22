import type { MailRow } from "./mail-rows.ts";

export type Keyword = "seen" | "flagged";

/**
 * The answer of `mail_set_keywords`, declared in Rust (`KeywordsView`). One result per distinct id
 * asked for, in the order first asked.
 */
export type KeywordResult =
  | {outcome: "confirmed"; id: string}
  /** Known not to have applied: refused, failed before the server did anything, or read back unchanged. */
  | {outcome: "notApplied"; id: string; reason: string}
  /** Not known: read the message again before sending anything again. */
  | {outcome: "unknown"; id: string; reason: string};

export interface KeywordsView {
  results: KeywordResult[];
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

/** What the GUI does with an answer. `action` names the change, e.g. "Marked as read". */
export function settle(view: KeywordsView, action: string): Settled {
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
  /** Ids not written because their last outcome for this keyword is still unknown. */
  held: string[];
  /** Ids whose state for this keyword is still unknown after this call: writes stay blocked. */
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
 * Sends keyword writes and holds back any message whose last outcome for that keyword is unknown.
 *
 * `unknown` means the server may or may not have applied the change. Writing again before reading
 * would act on a state nobody has seen, so such a message is blocked for that keyword until a read
 * (`mail_check_keywords`) returns it with the keyword known — or returns it gone. A click on a
 * blocked message runs that read instead of the write; if the read fails the block stays. Selection
 * plays no part: deselecting and selecting again does not lift it. The block is per connection,
 * Account, message and keyword, and lives as long as this object.
 *
 * Lifting a block is not enough on its own: the views may still hold the value from before, and a
 * toggle decides its direction from what is shown. So every value the server confirms — a
 * confirmed write, or what a check read — is kept here, stamped, and `shown()` puts it in front of
 * a view's value until that view has loaded again after the stamp. The block lifts in the same
 * step the value is kept, so no moment exists where a write is allowed against a stale value.
 */
export class KeywordWriter {
  private readonly blocked = new Set<string>();
  private readonly known = new Map<string, {has: boolean; at: number}>();
  private tick = 0;
  private readonly call: Call;

  constructor(call: Call) {
    this.call = call;
  }

  isUnverified(target: Target, id: string, keyword: Keyword): boolean {
    return this.blocked.has(key(target, id, keyword));
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
    return confirmed && confirmed.at > loadedFrom ? confirmed.has : viewHas;
  }

  private keep(target: Target, id: string, keyword: Keyword, has: boolean): void {
    this.known.set(key(target, id, keyword), {has, at: ++this.tick});
  }

  async write(target: Target, ids: readonly string[], keyword: Keyword, on: boolean, action: string): Promise<WriteResult> {
    const held = ids.filter(id => this.isUnverified(target, id, keyword));
    const allowed = ids.filter(id => !this.isUnverified(target, id, keyword));
    let settled: Settled | null = null;
    let writeError: unknown = null;
    let unknown: string[] = [];
    if (allowed.length) {
      try {
        const view = await this.call("mail_set_keywords", {...target, emailIds: allowed, keyword, on}) as KeywordsView;
        settled = settle(view, action);
        unknown = settled.unknown;
        for (const id of settled.confirmed) this.keep(target, id, keyword, on);
      } catch (error) {
        writeError = error ?? "failed";
        unknown = allowed;
      }
      for (const id of unknown) this.blocked.add(key(target, id, keyword));
    }
    const toCheck = [...held, ...unknown];
    const checkFailed = toCheck.length ? !(await this.check(target, toCheck, keyword)) : false;
    const unverified = ids.filter(id => this.isUnverified(target, id, keyword));
    return {settled, held, unverified, checkFailed, writeError};
  }

  /** Reads the messages and lifts the block on each one the server shows with this keyword known. */
  async check(target: Target, ids: readonly string[], keyword: Keyword): Promise<boolean> {
    let rows: MailRow[];
    try {
      rows = await this.call("mail_check_keywords", {...target, emailIds: ids}) as MailRow[];
    } catch {
      return false;
    }
    for (const row of rows) {
      if (row.kind === "unavailable") {
        this.blocked.delete(key(target, row.id, keyword));
        continue;
      }
      const value = keyword === "seen" ? row.unread : row.flagged;
      if (value === null) continue;
      // Keep the value first, then lift the block: the screen never allows a write it cannot show.
      this.keep(target, row.id, keyword, keyword === "seen" ? !value : value);
      this.blocked.delete(key(target, row.id, keyword));
    }
    return true;
  }
}

function key(target: Target, id: string, keyword: Keyword): string {
  return JSON.stringify([target.connectionId, target.accountId, id, keyword]);
}
