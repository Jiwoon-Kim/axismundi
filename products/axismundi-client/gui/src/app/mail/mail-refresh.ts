export interface MailChanged {
  connectionId: number;
  accountIds: string[];
  reason: "catch-up" | "push";
}

export function matchesMailPush(event: MailChanged, connectionId: number | undefined, accountId: string | null): boolean {
  return event.connectionId === connectionId && accountId !== null && event.accountIds.includes(accountId);
}

/** While busy, keep the last applied revision so all intervening wakes become one reload. */
export function reloadRevision(applied: number, requested: number, busy: boolean): number {
  return busy ? applied : requested;
}

const PULL_THRESHOLD = 64;

export function pullProgress(dx: number, dy: number): number {
  return dy > Math.abs(dx) ? Math.min(1, dy / PULL_THRESHOLD) : 0;
}

export function indicatorDelay(visible: boolean, elapsed: number): number {
  return Math.max(0, (visible ? 500 : 300) - elapsed);
}

export function manualRefreshAction(pending: boolean, loading: boolean): "skip" | "join" | "reload" {
  return pending ? "skip" : loading ? "join" : "reload";
}

export function isRefreshShortcut(event: Pick<KeyboardEvent, "key" | "code" | "altKey" | "metaKey" | "shiftKey" | "ctrlKey">): boolean {
  if (event.altKey || event.metaKey || event.shiftKey) return false;
  return event.ctrlKey ? event.code === "KeyR" : event.key === "F5";
}
