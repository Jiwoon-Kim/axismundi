import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";
import { bytes, compareCapability, creationPermissions, duration, isServerLevel, searchCapabilities, sessionView, show } from "./jmap-session-view.ts";

const load = (name: string) => JSON.parse(readFileSync(new URL(`fixtures/${name}`, import.meta.url), "utf8"));
const admin = load("jmap-session-admin.json");
const alice = load("jmap-session-alice.json");

test("one Session laid out: connection, endpoints and core limits come from the document", () => {
  const view = sessionView(admin);
  assert.equal(view.username, "admin@example.org");
  assert.equal(view.origin, "https://localhost:18881");
  assert.deepEqual(view.endpoints.map(e => e.label), ["API", "Upload", "Download", "Event source", "WebSocket"]);
  assert.deepEqual(view.limits.find(l => l.name === "Upload size")?.value, {kind: "text", text: "50 MB"});
});

test("Accounts answer what each Account can do: personal first, shared ones, and what it is primary for", () => {
  const view = sessionView(alice);
  assert.deepEqual(view.accounts.map(a => [a.id, a.personal]), [["c", true], ["b", false], ["d", false]]);
  const personal = view.accounts[0];
  assert.ok(personal.primaryFor.includes("urn:ietf:params:jmap:mail"));
  assert.deepEqual(view.accounts.find(a => a.id === "b")!.primaryFor, []);
  // The admin Account shared with alice carries no submission: alice cannot send as it.
  assert.ok(!view.accounts.find(a => a.id === "b")!.capabilities.some(c => c.urn === "urn:ietf:params:jmap:submission"));
});

test("Capabilities answer where a capability is: server-only, Account-only, primary and client apart", () => {
  const view = sessionView(alice);
  const row = (urn: string) => view.capabilities.find(r => r.urn === urn)!;
  const core = row("urn:ietf:params:jmap:core");
  assert.equal(core.server, true);
  assert.deepEqual(core.accounts, []);
  assert.equal(isServerLevel(core), true);
  assert.equal(isServerLevel(row("urn:ietf:params:jmap:websocket")), false);
  assert.equal(isServerLevel({...core, primary: "c"}), false);
  const share = row("urn:ietf:params:jmap:mail:share");
  assert.equal(share.server, false);
  assert.deepEqual(share.accounts.map(a => a.id), ["c", "b", "d"]);
  assert.equal(row("urn:ietf:params:jmap:submission").accounts.length, 2);
  assert.equal(row("urn:ietf:params:jmap:calendars").client, false);
  assert.equal(row("urn:ietf:params:jmap:contacts").client, true);
  const vendor = row("urn:stalwart:jmap");
  assert.equal(vendor.group, "Vendor extensions");
  assert.equal(vendor.label, "urn:stalwart:jmap");
});

test("a read-only Account and a primary mapping that differs per capability are kept apart", () => {
  const changed = structuredClone(alice);
  changed.accounts.d.isReadOnly = true;
  changed.primaryAccounts["urn:ietf:params:jmap:contacts"] = "d";
  const view = sessionView(changed);
  const team = view.accounts.find(a => a.id === "d")!;
  assert.equal(team.readOnly, true);
  assert.deepEqual(team.primaryFor, ["urn:ietf:params:jmap:contacts"]);
  assert.equal(view.capabilities.find(r => r.urn === "urn:ietf:params:jmap:contacts")!.primary, "d");
  assert.ok(!view.accounts.find(a => a.id === "c")!.primaryFor.includes("urn:ietf:params:jmap:contacts"));
});

test("apps say why: in this app, offered but not in this app, or not offered", () => {
  const view = sessionView(alice);
  const app = (label: string) => view.apps.find(a => a.label === label)!;
  assert.equal(app("Mail").state, "available");
  assert.equal(app("Calendar").state, "not-in-app");
  const without = structuredClone(alice);
  delete without.capabilities["urn:ietf:params:jmap:filenode"];
  for (const account of Object.values(without.accounts) as {accountCapabilities: Record<string, unknown>}[]) {
    delete account.accountCapabilities["urn:ietf:params:jmap:filenode"];
  }
  assert.equal(sessionView(without).apps.find(a => a.label === "Files")!.state, "not-offered");
  // Advertised by the server and built into this app, but no Account carries it: not usable.
  const noAccount = structuredClone(alice);
  for (const account of Object.values(noAccount.accounts) as {accountCapabilities: Record<string, unknown>}[]) {
    delete account.accountCapabilities["urn:ietf:params:jmap:contacts"];
  }
  assert.equal(sessionView(noAccount).apps.find(a => a.label === "Contacts")!.state, "no-account");
});

test("values read as read-only facts", () => {
  assert.deepEqual(show("x", true), {kind: "flag", on: true});
  assert.deepEqual(show("x", {}), {kind: "text", text: "Advertised"});
  assert.deepEqual(show("x", null), {kind: "text", text: "Not provided"});
  assert.deepEqual(show("maxDelayedSend", 2592000), {kind: "text", text: "30 days"});
  assert.equal(bytes(7499488), "7.5 MB");
  assert.equal(bytes(10000000), "10 MB");
  assert.equal(duration("P52W1D"), "52 weeks 1 day");
  assert.equal(duration("nonsense"), "nonsense");
  assert.equal(searchCapabilities(sessionView(alice).capabilities, "CONTACT").length, 2);
});

test("Calendars differs only when the selected accounts include admin", () => {
  const view = sessionView(alice);
  const row = view.capabilities.find(r => r.urn === "urn:ietf:params:jmap:calendars")!;
  const comparison = compareCapability(row, ["c", "b", "d"]);
  assert.equal(comparison.presence, false);
  assert.equal(comparison.differs, true);
  assert.deepEqual(comparison.fields, [{
    key: "mayCreateCalendar",
    cells: [true, false, true].map(on => ({kind: "flag", on})),
  }]);
  assert.equal(compareCapability(row, ["c", "d"]).differs, false);
});

test("comparison ignores nested object key order but not absence or array order", () => {
  const row = sessionView(alice).capabilities.find(r => r.urn.endsWith(":calendars"))!;
  const compare = (a: unknown, b: unknown) => compareCapability({
    ...row, accounts: [{id: "a", name: "A", raw: a, fields: []}, {id: "b", name: "B", raw: b, fields: []}],
  }, ["a", "b"]);
  assert.equal(compare({x: [{a: 1, b: 2}]}, {x: [{b: 2, a: 1}]}).differs, false);
  assert.equal(compare({x: [1, 2]}, {x: [2, 1]}).differs, true);
  assert.equal(compareCapability({...row, accounts: [{id: "a", name: "A", raw: {}, fields: []}]}, ["a", "b"]).presence, true);
});

test("creation permissions distinguish false from missing", () => {
  const view = sessionView(alice);
  const accounts = [...view.accounts, {...view.accounts[0], raw: {}}];
  assert.deepEqual(creationPermissions(accounts)[0].values, ["Yes", "No", "Yes", "Not advertised"]);
});
