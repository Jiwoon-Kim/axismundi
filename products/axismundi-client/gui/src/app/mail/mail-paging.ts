import type { ListView } from "./mail-rows.ts";

export function canRequestPage(listing: Pick<ListView, "hasMore" | "rows">, busy: boolean, exhausted: boolean): boolean {
  if (busy || exhausted) return false;
  // The IPC view has cumulative rows, not the effective per-server page limit.
  // With no total, a nonempty listing can be probed until a request makes no progress.
  return listing.hasMore ?? listing.rows.length > 0;
}

export function reachedPageEnd(previousCount: number, listing: Pick<ListView, "hasMore" | "rows">): boolean {
  return listing.hasMore === false || (listing.hasMore === null && listing.rows.length <= previousCount);
}

export function acceptsPage(currentRequest: object | null, request: object, currentScope: string, requestedScope: string): boolean {
  return currentRequest === request && currentScope === requestedScope;
}
