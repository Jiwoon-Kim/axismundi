// Files over JMAP FileNode (draft-ietf-jmap-filenode-22): the shapes the Tauri commands return and how
// they read. Angular-free so `node --test` checks them against files.fixture.json.
import type { QueueState } from "../shell/upload-queue.ts";

export const FILENODE = "urn:ietf:params:jmap:filenode";
/** Shown beside the title: Files follows an Internet-Draft, not an RFC. */
export const FILENODE_REVISION = "draft-ietf-jmap-filenode-22";

export interface FileNode {
  "@type": string; id: string; parentId: string | null; name: string;
  blobId?: string; size?: number; mediaType?: string; modified?: string; role?: string | null;
  myRights?: Record<string, boolean>;
}
export interface Listing { nodes: FileNode[]; position: number; queryState: string; total: number | null }
export type UploadResult =
  | {kind: "saved"; node: FileNode}
  | {kind: "rejected"; error: {type?: string; description?: string; existingId?: string}}
  | {kind: "unknown"; parentId: string | null; name: string; error: string}
  | {kind: "needsRefresh"; id: string; error: string};
export type Quotas =
  | {kind: "available"; list: {id: string; resourceType: string; scope: string; used: number; hardLimit: number;
      warnLimit?: number; types: string[]; name?: string}[]}
  | {kind: "notApplicable"}
  | {kind: "failed"; error: string};
export interface Sort { property: string; isAscending: boolean }

export const isFolder = (node: FileNode) => node["@type"] === "Directory";

/**
 * Folders first, then by name, when the server offers both sorts; otherwise the best the server offers.
 * `null` order means the server's own order, which the page says out loud.
 */
export function folderSort(options: readonly string[]): Sort[] {
  if (options.includes("nodeType") && options.includes("name")) {
    return [{property: "nodeType", isAscending: true}, {property: "name", isAscending: true}];
  }
  return options.includes("name") ? [{property: "name", isAscending: true}] : [];
}

/** How one upload ended, as the queue shows it. Saved means read back from the server, not just sent. */
export function uploadOutcome(result: UploadResult): {state: QueueState; message: string | null} {
  switch (result.kind) {
    case "saved": return {state: "done", message: null};
    case "needsRefresh": return {state: "unconfirmed", message: "Saved, but it could not be read back yet. Refresh the folder."};
    case "unknown": return {state: "unknown", message: "No answer from the server. It may have been saved — check the folder before trying again."};
    case "rejected": {
      const type = result.error.type ?? "";
      if (type === "alreadyExists") return {state: "conflict", message: "A file with this name is already here."};
      if (type === "overQuota") return {state: "failed", message: "Not enough storage space: the account's quota is full."};
      return {state: "failed", message: result.error.description ?? `The server did not save it (${type || "unknown reason"}).`};
    }
  }
}

/** An upload that failed before saving: the file is over the per-upload limit, or the name is refused. */
export function uploadError(error: string): string {
  if (/larger than the server accepts/i.test(error)) return "Too large for this server's upload limit. This is a per-file limit, not your storage space.";
  if (/naming limits/i.test(error)) return "This server does not allow that file name.";
  return error;
}

/** The storage line under the folders: only quotas that count files, and a shared one says so. */
export function quotaLines(quotas: Quotas | null): {text: string; used?: number; limit?: number}[] {
  if (!quotas) return [];
  if (quotas.kind === "failed") return [{text: "Storage could not be read."}];
  if (quotas.kind === "notApplicable") return [{text: "This server reports no storage quota for files."}];
  return quotas.list.filter(quota => quota.resourceType === "octets").map(quota => {
    const shared = quota.types.some(type => type !== "FileNode");
    return {text: `${formatBytes(quota.used)} of ${formatBytes(quota.hardLimit)} used${shared ? " — shared with " +
      quota.types.filter(type => type !== "FileNode").join(", ") : ""}`, used: quota.used, limit: quota.hardLimit};
  });
}

/** What a set call did to one id, in a sentence, or null when it worked. */
export function setFailure(response: Record<string, unknown>, key: "notCreated" | "notUpdated" | "notDestroyed", id: string): string | null {
  const failed = (response[key] as Record<string, {type?: string; description?: string}> | undefined)?.[id];
  if (!failed) return null;
  if (failed.type === "alreadyExists") return "An item with this name is already here.";
  if (failed.type === "nodeHasChildren") return "The folder is not empty.";
  if (failed.type === "forbidden") return "The server does not allow this here.";
  return failed.description ?? `The server refused (${failed.type ?? "unknown reason"}).`;
}

/** Decimal units, as servers state their limits. */
export function formatBytes(n: number | undefined): string {
  if (n === undefined) return "";
  const units = ["B", "KB", "MB", "GB", "TB"];
  let value = n, unit = 0;
  while (value >= 1000 && unit < units.length - 1) { value /= 1000; unit++; }
  return `${unit === 0 || value >= 100 ? Math.round(value) : value.toFixed(1)} ${units[unit]}`;
}

export function kindLabel(node: FileNode): string {
  if (isFolder(node)) return "Folder";
  if (node["@type"] === "SymLink") return "Link";
  const type = node.mediaType ?? "";
  return type === "application/octet-stream" || !type ? "File" : type;
}
