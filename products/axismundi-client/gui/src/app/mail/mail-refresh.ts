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
