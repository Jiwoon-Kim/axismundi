// A draft's ordinary attachments while it is being written. Angular-free so `node --test` can check
// the rules: which attachments a save may reference, and when sending must wait.

/** A file the native picker or drop registered; the webview holds only this handle, never a path. */
export interface PickedFile { handle: string; name: string; size: number; type: string }
/** What an upload returned. */
export interface UploadedFile { blobId: string; name: string; size: number; type: string }
/** What a draft save references. */
export interface DraftAttachment { blobId: string; name: string; type: string; size: number | null }

export interface ComposeAttachment {
  key: string;
  /** Null for an attachment restored from a saved draft: there is no local file to upload again. */
  handle: string | null;
  name: string;
  size: number | null;
  type: string;
  state: "uploading" | "uploaded" | "failed";
  blobId: string | null;
  error: string | null;
}

export function picked(file: PickedFile, key: string): ComposeAttachment {
  return {key, handle: file.handle, name: file.name, size: file.size, type: file.type,
    state: "uploading", blobId: null, error: null};
}

export function restored(attachment: DraftAttachment, key: string): ComposeAttachment {
  return {key, handle: null, name: attachment.name, size: attachment.size, type: attachment.type,
    state: "uploaded", blobId: attachment.blobId, error: null};
}

/** Only finished uploads go into a save; one still uploading joins a later save. */
export function draftAttachments(items: readonly ComposeAttachment[]): DraftAttachment[] {
  return items.filter(item => item.state === "uploaded" && item.blobId)
    .map(item => ({blobId: item.blobId!, name: item.name, type: item.type, size: item.size}));
}

/** Why sending must wait, or null. A message never leaves without an attachment the person added. */
export function sendBlocker(items: readonly ComposeAttachment[]): string | null {
  const uploading = items.filter(item => item.state === "uploading").length;
  if (uploading) return uploading === 1 ? "1 attachment is still uploading." : `${uploading} attachments are still uploading.`;
  if (items.some(item => item.state === "failed")) return "An attachment failed. Retry or remove it before sending.";
  return null;
}

/** The server no longer has these blobs (blobNotFound): mark them so they are uploaded again or removed. */
export function markMissing(items: readonly ComposeAttachment[], missing: readonly string[]): ComposeAttachment[] {
  return items.map(item => item.blobId && missing.includes(item.blobId)
    ? {...item, state: "failed", blobId: null,
      error: item.handle ? "The server no longer has this file. Upload it again." : "The server no longer has this file. Remove it."}
    : item);
}

/**
 * Whether closing would lose an attachment the person added: one still uploading or failed, or one
 * uploaded but not in the last saved draft. A save snapshot cannot tell this, since it holds only
 * finished uploads.
 */
export function unsavedAttachments(items: readonly ComposeAttachment[], saved: ReadonlySet<string>): boolean {
  return items.some(item => item.state !== "uploaded" || !item.blobId || !saved.has(item.blobId));
}

/**
 * Which new files fit under the server's per-message total (maxSizeAttachmentsPerEmail), counting
 * attachments already uploaded or uploading. Files are taken in order; one that would pass the
 * total is refused before any byte is sent.
 */
export function fitTotal(items: readonly ComposeAttachment[], files: readonly PickedFile[], total: number | null):
  {accepted: PickedFile[]; refused: PickedFile[]} {
  if (total === null) return {accepted: [...files], refused: []};
  let used = items.filter(item => item.state !== "failed").reduce((sum, item) => sum + (item.size ?? 0), 0);
  const accepted: PickedFile[] = [], refused: PickedFile[] = [];
  for (const file of files) {
    if (used + file.size > total) { refused.push(file); continue; }
    used += file.size;
    accepted.push(file);
  }
  return {accepted, refused};
}

export function formatSize(size: number | null): string {
  if (size === null) return "";
  if (size < 1024) return `${size} B`;
  if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
  return `${(size / 1024 / 1024).toFixed(1)} MB`;
}
