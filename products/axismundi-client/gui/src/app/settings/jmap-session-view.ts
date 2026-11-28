// A JMAP Session (RFC 8620 §2) laid out for the connection detail screen. Angular-free so `node --test`
// checks it against real Session documents. Everything here comes from the one Session the connection
// already received: no request is made for it.


// --- Capability registry: display names, groups and value formats. Presentation only: whether an app
// opens is decided elsewhere (the connection's and Account's capabilities), never here.

export const groups = ["Mail", "Calendars", "Contacts", "Files & storage", "Principals", "Metadata",
  "Core & transport", "Vendor extensions", "Other"] as const;
export type Group = typeof groups[number];

const ietf = "urn:ietf:params:jmap:";

const known: Record<string, {label: string; group: Group}> = {
  "core": {label: "Core", group: "Core & transport"},
  "websocket": {label: "WebSocket", group: "Core & transport"},
  "webpush-vapid": {label: "Web Push (VAPID)", group: "Core & transport"},
  "mail": {label: "Mail", group: "Mail"},
  "mail:share": {label: "Mailbox sharing", group: "Mail"},
  "submission": {label: "Email submission", group: "Mail"},
  "emailpush": {label: "Email push", group: "Mail"},
  "sieve": {label: "Sieve scripts", group: "Mail"},
  "vacationresponse": {label: "Vacation response", group: "Mail"},
  "calendars": {label: "Calendars", group: "Calendars"},
  "calendars:parse": {label: "Calendar parsing", group: "Calendars"},
  "contacts": {label: "Contacts", group: "Contacts"},
  "contacts:parse": {label: "Contact parsing", group: "Contacts"},
  "filenode": {label: "Files (experimental, draft-ietf-jmap-filenode-22)", group: "Files & storage"},
  "blob": {label: "Blob management", group: "Files & storage"},
  "quota": {label: "Quotas", group: "Files & storage"},
  "principals": {label: "Principals", group: "Principals"},
  "principals:availability": {label: "Availability", group: "Principals"},
  "principals:owner": {label: "Principal owner", group: "Principals"},
  "metadata": {label: "Metadata", group: "Metadata"},
};

/**
 * What this app actually uses, checked in the code (2026-11-28): core and blob in every request path,
 * mail and submission (Mail, sending), contacts (Contacts), websocket (live updates), filenode and
 * quota (Files, experimental). Advertised but not here means "not in this app" — calendars, sieve
 * and the rest.
 */
export const clientSupported: ReadonlySet<string> = new Set(
  ["core", "blob", "mail", "submission", "contacts", "websocket", "filenode", "quota"].map(name => ietf + name));

export function capabilityInfo(urn: string): {label: string; group: Group} {
  if (urn.startsWith(ietf)) return known[urn.slice(ietf.length)] ?? {label: urn.slice(ietf.length), group: "Other"};
  return {label: urn, group: "Vendor extensions"};
}

/** The apps the Overview names, and the capability each one needs. */
export const apps = [
  {label: "Mail", icon: "mail", urn: ietf + "mail"},
  {label: "Calendar", icon: "calendar_month", urn: ietf + "calendars"},
  {label: "Contacts", icon: "contacts", urn: ietf + "contacts"},
  {label: "Files", icon: "folder", urn: ietf + "filenode"},
  {label: "Sieve", icon: "filter_alt", urn: ietf + "sieve"},
  {label: "Blob", icon: "storage", urn: ietf + "blob"},
  {label: "Principals", icon: "group", urn: ietf + "principals"},
  {label: "Metadata", icon: "label", urn: ietf + "metadata"},
  {label: "Quota", icon: "data_usage", urn: ietf + "quota"},
] as const;

type Format = "bytes" | "seconds" | "duration" | "datetime" | "uri";

/** Fields whose number or string means something more than itself. Everything else reads as is. */
const formats: Record<string, Format> = {
  maxSizeRequest: "bytes", maxSizeUpload: "bytes", maxSizeAttachmentsPerEmail: "bytes", maxSizeBlobSet: "bytes",
  maxSizeScript: "bytes", maxDelayedSend: "seconds", maxExpandedQueryDuration: "duration",
  maxAvailabilityDuration: "duration", minDateTime: "datetime", maxDateTime: "datetime", url: "uri",
  webUrlTemplate: "uri", webWriteUrlTemplate: "uri", webTrashUrl: "uri",
};

export type Shown =
  | {kind: "text"; text: string; mono?: boolean}
  | {kind: "flag"; on: boolean}
  | {kind: "list"; items: string[]}
  | {kind: "fields"; fields: Field[]};
export interface Field { name: string; value: Shown }

/** Read-only display of one Session value: never a control, so a boolean is a mark and a word. */
export function show(name: string, value: unknown): Shown {
  if (value === null || value === undefined) return {kind: "text", text: "Not provided"};
  if (typeof value === "boolean") return {kind: "flag", on: value};
  if (Array.isArray(value)) return value.length ? {kind: "list", items: value.map(String)} : {kind: "text", text: "None"};
  if (typeof value === "object") {
    const entries = Object.entries(value);
    return entries.length ? {kind: "fields", fields: entries.map(([key, child]) => ({name: key, value: show(key, child)}))}
      : {kind: "text", text: "Advertised"};
  }
  const format = formats[name];
  if (typeof value === "number") {
    if (format === "bytes") return {kind: "text", text: bytes(value)};
    if (format === "seconds") return {kind: "text", text: seconds(value)};
    return {kind: "text", text: value.toLocaleString("en")};
  }
  const text = String(value);
  if (format === "duration") return {kind: "text", text: duration(text)};
  return {kind: "text", text, mono: format === "uri" || format === "datetime"};
}

/** Decimal units, as servers state these limits. */
export function bytes(n: number): string {
  const units = ["B", "KB", "MB", "GB", "TB"];
  let value = n, unit = 0;
  while (value >= 1000 && unit < units.length - 1) { value /= 1000; unit++; }
  return `${Number.isInteger(value) ? value : value.toFixed(2).replace(/\.?0+$/, "")} ${units[unit]}`;
}

function seconds(n: number): string {
  if (n % 86400 === 0) return plural(n / 86400, "day");
  if (n % 3600 === 0) return plural(n / 3600, "hour");
  return plural(n, "second");
}

/** ISO 8601 durations as words: P52W1D → "52 weeks 1 day". An unreadable one is shown as sent. */
export function duration(text: string): string {
  const match = /^P(?:(\d+)Y)?(?:(\d+)M)?(?:(\d+)W)?(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?$/.exec(text);
  if (!match || text === "P" || text.endsWith("T")) return text;
  const names = ["year", "month", "week", "day", "hour", "minute", "second"];
  const parts = match.slice(1).map((n, i) => n ? plural(Number(n), names[i]) : "").filter(Boolean);
  return parts.length ? parts.join(" ") : text;
}

function plural(n: number, word: string): string { return `${n.toLocaleString("en")} ${word}${n === 1 ? "" : "s"}`; }

type Json = Record<string, unknown>;
const object = (value: unknown): Json => value !== null && typeof value === "object" && !Array.isArray(value) ? value as Json : {};
const text = (value: unknown): string => typeof value === "string" ? value : "";

const CORE = "urn:ietf:params:jmap:core";

export interface AccountView {
  raw: Json;
  id: string;
  name: string;
  personal: boolean;
  readOnly: boolean;
  /** Capabilities whose primaryAccounts entry names this Account. */
  primaryFor: string[];
  capabilities: {urn: string; label: string; group: Group; fields: Field[]}[];
}

export interface CapabilityRow {
  urn: string;
  label: string;
  group: Group;
  /** In the Session's capabilities object (server-wide). */
  server: boolean;
  serverFields: Field[];
  /** Accounts whose accountCapabilities carry it, with their values. */
  accounts: {id: string; name: string; fields: Field[]; raw: unknown}[];
  /** The primaryAccounts entry, if any. */
  primary: string | null;
  /** Used by this app. */
  client: boolean;
}

export function isServerLevel(row: CapabilityRow): boolean {
  return row.server && !row.primary && row.accounts.length === 0;
}

function sameJson(a: unknown, b: unknown): boolean {
  if (a === b) return true;
  if (Array.isArray(a) || Array.isArray(b)) {
    return Array.isArray(a) && Array.isArray(b) && a.length === b.length
      && a.every((value, i) => sameJson(value, b[i]));
  }
  if (!a || !b || typeof a !== "object" || typeof b !== "object") return false;
  const left = object(a), right = object(b);
  const keys = Object.keys(left);
  return keys.length === Object.keys(right).length
    && keys.every(key => Object.hasOwn(right, key) && sameJson(left[key], right[key]));
}

export function compareCapability(row: CapabilityRow, ids: readonly string[]) {
  const holders = ids.map(id => row.accounts.find(account => account.id === id));
  const present = holders.filter(holder => holder !== undefined);
  const presence = present.length > 0 && present.length < holders.length;
  const values = present.map(holder => holder.raw);
  const differs = values.some(value => !sameJson(values[0], value));
  const properties = values.map(object);
  const keys = [...new Set(properties.flatMap(value => Object.keys(value)))].sort();
  const fields = keys.filter(key => properties.some(value => !sameJson(properties[0]?.[key], value[key])))
    .map(key => ({
      key,
      cells: holders.map(holder => {
        if (!holder) return {kind: "text", text: "Not advertised"} as const;
        const raw = object(holder.raw);
        return Object.hasOwn(raw, key) ? show(key, raw[key]) : {kind: "text", text: "Not provided"} as const;
      }),
    }));
  return {holders, presence, differs, fields};
}

const creationFields = [
  ["calendars", "mayCreateCalendar", "Calendar"],
  ["contacts", "mayCreateAddressBook", "Address book"],
  ["filenode", "mayCreateTopLevelFileNode", "Top-level file or folder"],
  ["mail", "mayCreateTopLevelMailbox", "Top-level mailbox"],
] as const;

export function creationPermissions(accounts: readonly AccountView[]) {
  return creationFields.map(([capability, key, label]) => ({
    key, label,
    values: accounts.map(account => {
      const value = object(object(account.raw["accountCapabilities"])[`urn:ietf:params:jmap:${capability}`])[key];
      return value === true ? "Yes" : value === false ? "No" : "Not advertised";
    }),
  }));
}

/** In this app with an Account that has it · offered, but no Account here has it · not in this app yet · not offered. */
export type AppState = "available" | "no-account" | "not-in-app" | "not-offered";

export interface SessionView {
  username: string;
  origin: string;
  state: string;
  endpoints: {label: string; url: string}[];
  limits: Field[];
  transport: {label: string; on: boolean; detail: string}[];
  apps: {label: string; icon: string; urn: string; state: AppState; accounts: string[]}[];
  accounts: AccountView[];
  capabilities: CapabilityRow[];
  groups: {group: Group; rows: CapabilityRow[]}[];
}

function fields(value: unknown): Field[] {
  const shown = show("", value);
  return shown.kind === "fields" ? shown.fields : [];
}

const limitNames: [string, string][] = [
  ["maxCallsInRequest", "Calls per request"], ["maxConcurrentRequests", "Concurrent requests"],
  ["maxConcurrentUpload", "Concurrent uploads"], ["maxObjectsInGet", "Objects per get"],
  ["maxObjectsInSet", "Objects per set"], ["maxSizeRequest", "Request size"], ["maxSizeUpload", "Upload size"],
];

export function sessionView(session: unknown): SessionView {
  const root = object(session);
  const capabilities = object(root["capabilities"]);
  const accountsJson = object(root["accounts"]);
  const primary = object(root["primaryAccounts"]);
  const apiUrl = text(root["apiUrl"]);
  let origin = apiUrl;
  try { origin = new URL(apiUrl).origin; } catch { /* Shown as sent. */ }

  const accounts: AccountView[] = Object.entries(accountsJson).map(([id, raw]) => {
    const account = object(raw);
    const own = object(account["accountCapabilities"]);
    return {
      id, name: text(account["name"]) || id,
      personal: account["isPersonal"] === true, readOnly: account["isReadOnly"] === true,
      primaryFor: Object.entries(primary).filter(([, value]) => value === id).map(([urn]) => urn).sort(),
      raw: account,
      capabilities: Object.entries(own).map(([urn, value]) => ({urn, ...capabilityInfo(urn), fields: fields(value)}))
        .sort((a, b) => groups.indexOf(a.group) - groups.indexOf(b.group) || a.urn.localeCompare(b.urn)),
    };
  }).sort((a, b) => Number(b.personal) - Number(a.personal) || a.name.localeCompare(b.name));

  const urns = new Set([...Object.keys(capabilities), ...accounts.flatMap(a => a.capabilities.map(c => c.urn)), ...Object.keys(primary)]);
  const rows: CapabilityRow[] = [...urns].map(urn => ({
    urn, ...capabilityInfo(urn),
    server: urn in capabilities,
    serverFields: fields(capabilities[urn]),
    accounts: accounts.flatMap(account => {
      const found = account.capabilities.find(c => c.urn === urn);
      return found ? [{id: account.id, name: account.name, fields: found.fields,
        raw: object(account.raw["accountCapabilities"])[urn]}] : [];
    }),
    primary: text(primary[urn]) || null,
    client: clientSupported.has(urn),
  })).sort((a, b) => groups.indexOf(a.group) - groups.indexOf(b.group) || a.urn.localeCompare(b.urn));

  const core = object(capabilities[CORE]);
  const websocket = object(capabilities["urn:ietf:params:jmap:websocket"]);
  const endpoints = [
    ["API", apiUrl], ["Upload", text(root["uploadUrl"])], ["Download", text(root["downloadUrl"])],
    ["Event source", text(root["eventSourceUrl"])], ["WebSocket", text(websocket["url"])],
  ].filter(([, url]) => url).map(([label, url]) => ({label, url}));

  return {
    username: text(root["username"]), origin, state: text(root["state"]), endpoints,
    limits: limitNames.filter(([key]) => key in core).map(([key, label]) => ({name: label, value: show(key, core[key])})),
    transport: [
      {label: "WebSocket", on: "urn:ietf:params:jmap:websocket" in capabilities,
        detail: websocket["supportsPush"] === true ? "Push over WebSocket" : "Requests only"},
      {label: "Event source", on: !!text(root["eventSourceUrl"]), detail: "Push over HTTP"},
      {label: "Web Push", on: "urn:ietf:params:jmap:webpush-vapid" in capabilities, detail: "VAPID key advertised"},
    ],
    apps: apps.map(app => {
      const holders = accounts.filter(a => a.capabilities.some(c => c.urn === app.urn)).map(a => a.name);
      const offered = app.urn in capabilities || holders.length > 0;
      const state: AppState = !offered ? "not-offered" : !clientSupported.has(app.urn) ? "not-in-app"
        : holders.length ? "available" : "no-account";
      return {...app, state, accounts: holders};
    }),
    accounts,
    capabilities: rows,
    groups: groups.map(group => ({group, rows: rows.filter(row => row.group === group)})).filter(g => g.rows.length),
  };
}

/** Rows whose URN or display name contains the query, case-insensitively. */
export function searchCapabilities(rows: readonly CapabilityRow[], query: string): CapabilityRow[] {
  const q = query.trim().toLowerCase();
  return q ? rows.filter(row => row.urn.toLowerCase().includes(q) || row.label.toLowerCase().includes(q)) : [...rows];
}
