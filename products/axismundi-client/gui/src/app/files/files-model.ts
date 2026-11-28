// Files over JMAP FileNode (draft-ietf-jmap-filenode-22): the shapes the Tauri commands return and how
// they read. Angular-free so `node --test` checks them against files.fixture.json.
import type { QueueState } from "../shell/upload-queue.ts";

export const FILENODE = "urn:ietf:params:jmap:filenode";
/** Shown beside the title: Files follows an Internet-Draft, not an RFC. */
export const FILENODE_REVISION = "draft-ietf-jmap-filenode-22";

export interface FileNode {
  "@type": string; id: string; parentId: string | null; name: string;
  blobId?: string; size?: number; mediaType?: string; modified?: string; role?: string | null;
  created?: string; accessed?: string; executable?: boolean;
  myRights?: Record<string, boolean>;
}
/** `limit` is the page size the page was actually read with (the server may lower it, RFC 8620 §5.5). */
export interface Listing { nodes: FileNode[]; position: number; queryState: string; total: number | null; limit: number }
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
  return fileKind(node).label;
}

/**
 * What a node is, for people: a Material Symbols icon (the placeholder until a thumbnail loads, and the
 * icon in the list) and a short label. Read from the media type; when the server has none or only
 * application/octet-stream, the file name's extension is used, for the icon and label only. The media
 * type itself is shown unchanged in the details.
 */
export interface FileKind { icon: string; label: string }

const byType: [RegExp, FileKind][] = [
  [/^application\/pdf$/, {icon: "picture_as_pdf", label: "PDF"}],
  [/^image\//, {icon: "image", label: "Image"}],
  [/^video\//, {icon: "video_file", label: "Video"}],
  [/^audio\//, {icon: "audio_file", label: "Audio"}],
  [/^font\/|^application\/(font-|x-font-)/, {icon: "font_download", label: "Font"}],
  [/^model\//, {icon: "deployed_code", label: "3D model"}],
  [/^message\/rfc822$/, {icon: "mail", label: "Email"}],
  [/^text\/calendar$/, {icon: "calendar_month", label: "Calendar"}],
  [/^text\/(vcard|x-vcard)$|^application\/jscontact\+json$/, {icon: "contact_page", label: "Contact card"}],
  [/^application\/epub\+zip$/, {icon: "menu_book", label: "E-book"}],
  [/^text\/csv$|spreadsheet|ms-excel|opendocument\.spreadsheet/, {icon: "table", label: "Spreadsheet"}],
  [/presentation|ms-powerpoint/, {icon: "slideshow", label: "Presentation"}],
  [/msword|wordprocessing|opendocument\.text|^application\/rtf$/, {icon: "article", label: "Document"}],
  [/^application\/(zip|gzip|x-gzip|x-tar|x-7z-compressed|vnd\.rar|x-rar-compressed|x-bzip2|x-xz|zstd)$/, {icon: "folder_zip", label: "Archive"}],
  [/^application\/(json|ld\+json|activity\+json|jscalendar\+json)$/, {icon: "data_object", label: "Data"}],
  [/^text\/html$|^application\/xhtml\+xml$/, {icon: "html", label: "Web page"}],
  [/^text\/css$/, {icon: "css", label: "Stylesheet"}],
  [/javascript|typescript/, {icon: "javascript", label: "Script"}],
  [/^application\/(xml|x-sh|x-python|x-httpd-php|sql|wasm)$|^text\/(xml|x-)/, {icon: "code", label: "Code"}],
  [/^text\/markdown$/, {icon: "markdown", label: "Markdown"}],
  [/^text\//, {icon: "description", label: "Text"}],
];

const byExtension: Record<string, string> = {
  pdf: "application/pdf", png: "image/png", jpg: "image/jpeg", jpeg: "image/jpeg", gif: "image/gif", webp: "image/webp",
  avif: "image/avif", heic: "image/heic", svg: "image/svg+xml", bmp: "image/bmp", tif: "image/tiff", tiff: "image/tiff",
  mp4: "video/mp4", m4v: "video/mp4", mov: "video/quicktime", webm: "video/webm", mkv: "video/x-matroska", avi: "video/x-msvideo",
  mp3: "audio/mpeg", m4a: "audio/mp4", ogg: "audio/ogg", oga: "audio/ogg", opus: "audio/opus", flac: "audio/flac", wav: "audio/wav",
  ttf: "font/ttf", otf: "font/otf", woff: "font/woff", woff2: "font/woff2", glb: "model/gltf-binary", gltf: "model/gltf+json",
  eml: "message/rfc822", ics: "text/calendar", vcf: "text/vcard", epub: "application/epub+zip",
  csv: "text/csv", xlsx: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", xls: "application/vnd.ms-excel",
  ods: "application/vnd.oasis.opendocument.spreadsheet",
  pptx: "application/vnd.openxmlformats-officedocument.presentationml.presentation", ppt: "application/vnd.ms-powerpoint",
  odp: "application/vnd.oasis.opendocument.presentation",
  docx: "application/vnd.openxmlformats-officedocument.wordprocessingml.document", doc: "application/msword",
  odt: "application/vnd.oasis.opendocument.text", rtf: "application/rtf",
  zip: "application/zip", gz: "application/gzip", tgz: "application/gzip", tar: "application/x-tar", "7z": "application/x-7z-compressed",
  rar: "application/vnd.rar", bz2: "application/x-bzip2", xz: "application/x-xz", zst: "application/zstd",
  json: "application/json", jsonld: "application/ld+json", html: "text/html", htm: "text/html", css: "text/css",
  js: "text/javascript", mjs: "text/javascript", ts: "text/typescript", xml: "application/xml", sh: "application/x-sh",
  py: "application/x-python", php: "application/x-httpd-php", sql: "application/sql", rs: "text/x-rust", md: "text/markdown",
  txt: "text/plain", log: "text/plain",
};

export function fileKind(node: FileNode): FileKind {
  if (isFolder(node)) return {icon: "folder", label: "Folder"};
  if (node["@type"] === "SymLink") return {icon: "shortcut", label: "Link"};
  let type = (node.mediaType ?? "").toLowerCase().split(";")[0].trim();
  if (!type || type === "application/octet-stream") {
    const dot = node.name.lastIndexOf(".");
    type = dot > 0 ? byExtension[node.name.slice(dot + 1).toLowerCase()] ?? "" : "";
  }
  return byType.find(([pattern]) => pattern.test(type))?.[1] ?? {icon: "draft", label: "File"};
}

/** draft-ietf-jmap-filenode-22 FileNodeRights, in the order a person reads them. */
const rights: [string, string][] = [
  ["mayRead", "View"], ["mayAddChildren", "Add items"], ["mayModifyContent", "Edit contents"], ["mayRename", "Rename"],
  ["mayDelete", "Delete"], ["maySetMetadata", "Edit details"], ["mayShare", "Share"],
];

/**
 * What the user may do in a folder, from its myRights. Every known right is "Full access" and View
 * alone is "View only"; otherwise the granted rights are listed. Absent myRights says nothing.
 */
export function accessLabel(myRights: Record<string, boolean> | undefined): string | null {
  if (!myRights) return null;
  const granted = rights.filter(([key]) => myRights[key]).map(([, label]) => label);
  if (granted.length === rights.length) return "Full access";
  if (granted.length === 1 && myRights["mayRead"]) return "View only";
  return granted.length ? granted.join(", ") : "No access";
}

// --- folder tree -------------------------------------------------------------------------------------
/** The tree's key for the Account's top level, whose parentId is null. */
export const ROOT = "";
/**
 * One row of the navigation tree; children is null until that folder has been listed. A `more` row
 * stands after the subfolders of a folder the tree stopped reading early: it opens that folder.
 */
export interface FolderNode { id: string; node: FileNode | null; children: FolderNode[] | null; more?: string }

/**
 * The navigation tree from the folders listed so far, keyed by parent id (ROOT for the top level). A
 * folder that is listed again under its own descendant is not followed, so a server answer with a
 * cycle cannot recurse forever.
 */
export function folderTree(listed: ReadonlyMap<string, readonly FileNode[]>, partial: ReadonlySet<string> = new Set()): FolderNode {
  const build = (node: FileNode | null, seen: ReadonlySet<string>): FolderNode => {
    const id = node?.id ?? ROOT, inside = new Set(seen).add(id), children = listed.get(id);
    if (!children) return {id, node, children: null};
    const rows = children.filter(child => isFolder(child) && !inside.has(child.id)).map(child => build(child, inside));
    if (partial.has(id)) rows.push({id: `more:${id}`, node: null, children: [], more: id});
    return {id, node, children: rows};
  };
  return build(null, new Set());
}

/**
 * Whether a page ended the folder. The server need not count (`total` is only there when asked for,
 * RFC 8620 §5.5), so a page shorter than the limit it was read with is the end too. That limit is the
 * one the server used, not the one the screen asked for: a server that caps pages at 100 would
 * otherwise look finished after its first full page.
 */
export function endsFolder(page: {nodes: readonly unknown[]; position: number; total: number | null; limit: number}): boolean {
  return page.nodes.length < page.limit || (page.total !== null && page.position + page.nodes.length >= page.total);
}

/**
 * Whether these items, read from the start of a folder, include every folder in it: either the whole
 * folder was read, or the server sorts folders first (nodeType) and a file has already appeared.
 */
export function holdsAllFolders(items: readonly FileNode[], ended: boolean, sort: readonly {property: string}[]): boolean {
  return ended || (sort[0]?.property === "nodeType" && items.some(item => !isFolder(item)));
}

// --- where a file is used (RFC 9404 Blob/lookup) ------------------------------------------------------
/** files_usage: the data types the server was asked about, and the ids of each that reference the blob. */
export interface Usage { blobId: string; types: string[]; references: Record<string, string[]> }

const usageNames: Record<string, string> = {FileNode: "Files", Email: "Mail"};

/**
 * What Blob/lookup said, as lines. Only the types that were checked are reported, and the screen says
 * which they were: "no emails" means none in Mail, not none anywhere. The file itself is not counted
 * among the files that use its bytes.
 */
export function usageSummary(usage: Usage, self: string): {lines: string[]; checked: string} {
  const lines = usage.types.map(type => {
    const ids = (usage.references[type] ?? []).filter(id => type !== "FileNode" || id !== self);
    const n = ids.length;
    if (type === "Email") return n ? `Attached to ${n} ${n === 1 ? "email" : "emails"}` : "Not attached to any email";
    if (type === "FileNode") return n ? `Also stored as ${n} other ${n === 1 ? "file" : "files"}` : "No other file shares its stored content";
    return n ? `${n} ${type}` : `No ${type}`;
  });
  return {lines, checked: `Checked: ${usage.types.map(type => usageNames[type] ?? type).join(", ")}`};
}

/** A files_usage error as a sentence: a server without the lookup is not a failure. */
export function usageError(error: string): string {
  return /unavailable|No supported file usage lookup types/i.test(error)
    ? "This server does not say where files are used."
    : "Could not check where this file is used.";
}
