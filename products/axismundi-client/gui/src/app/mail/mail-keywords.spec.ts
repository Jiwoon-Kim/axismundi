// Run: node --test gui/src/app/mail/mail-keywords.spec.ts   (Node 24 strips the types itself)
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { test } from "node:test";
import { KeywordWriter, type KeywordsView, settle } from "./mail-keywords.ts";

// Written by the Rust test `keyword_results_serialize_to_the_shape_the_gui_reads`: every core
// outcome folded into what mail_set_keywords sends over IPC.
const view: KeywordsView = JSON.parse(readFileSync(new URL("./mail-keywords.fixture.json", import.meta.url), "utf8"));

test("each id lands in exactly one group, in result order", () => {
  const settled = settle(view, "Star");
  assert.deepEqual(settled.confirmed, ["m1", "m7"]);
  assert.deepEqual(settled.notApplied, ["m2", "m3", "m4", "m8"]);
  assert.deepEqual(settled.unknown, ["m5", "m6"]);
  assert.deepEqual(settled.keepSelected, ["m2", "m3", "m4", "m5", "m6", "m8"]);
});

test("a mixed answer says how many of each, and that unknown ones need a check", () => {
  const settled = settle(view, "Star");
  assert.equal(settled.notice, "Star: 2 of 8 done, 4 not applied, 2 unknown — check before trying again.");
  assert.equal(settled.refresh, true);
});

test("one refused message gives its reason and does not refresh", () => {
  const one: KeywordsView = {results: [view.results[1]], readBackComplete: true};
  const settled = settle(one, "Star");
  assert.match(settled.notice ?? "", /^Star: not done\. The server refused it \(forbidden\)\.$/);
  assert.equal(settled.refresh, false);
});

test("an unknown message refreshes, because the server may have changed", () => {
  const one: KeywordsView = {results: [view.results[4]], readBackComplete: true};
  assert.equal(settle(one, "Star").refresh, true);
});

test("all confirmed and read back is silent; not read back says so", () => {
  const done: KeywordsView = {results: [view.results[0]], readBackComplete: true};
  assert.equal(settle(done, "Star").notice, null);
  assert.match(settle({...done, readBackComplete: false}, "Star").notice ?? "", /could not be read back/);
});

// --- KeywordWriter: an unknown outcome blocks writes until a read settles it ---

const unknownView = {results: [{outcome: "unknown", id: "m1", reason: "lost"}], readBackComplete: true};
const target = {connectionId: 1, accountId: "a"};
const row = (flagged: boolean | null) => ({
  kind: "message", id: "m1", threadId: null, senderName: null, senderEmail: null, subject: null,
  preview: null, receivedAt: null, unread: false, flagged, hasAttachment: null,
});

function fake(script: Record<string, Array<() => unknown>>) {
  const calls: string[] = [];
  const call = async (command: string) => {
    calls.push(command);
    const next = script[command]?.shift();
    if (!next) throw new Error(`unexpected ${command}`);
    return next();
  };
  return {calls, call};
}

test("unknown, then a failed check: clicking again reads, and never writes", async () => {
  const {calls, call} = fake({
    mail_set_keywords: [() => unknownView],
    mail_check_keywords: [() => { throw new Error("offline"); }, () => { throw new Error("offline"); }],
  });
  const writer = new KeywordWriter(call);
  const first = await writer.write(target, ["m1"], "flagged", true, "Star");
  assert.deepEqual(first.unverified, ["m1"]);
  assert.equal(first.checkFailed, true);
  const second = await writer.write(target, ["m1"], "flagged", true, "Star");
  assert.equal(second.settled, null, "nothing was sent");
  assert.deepEqual(second.held, ["m1"]);
  assert.deepEqual(calls, ["mail_set_keywords", "mail_check_keywords", "mail_check_keywords"]);
});

test("a check that shows the keyword lifts the block; the next click writes", async () => {
  const {calls, call} = fake({
    mail_set_keywords: [() => unknownView, () => ({results: [{outcome: "confirmed", id: "m1"}], readBackComplete: true})],
    mail_check_keywords: [() => [row(true)]],
  });
  const writer = new KeywordWriter(call);
  const first = await writer.write(target, ["m1"], "flagged", true, "Star");
  assert.deepEqual(first.unverified, [], "the immediate check settled it");
  await writer.write(target, ["m1"], "flagged", false, "Remove star");
  assert.deepEqual(calls, ["mail_set_keywords", "mail_check_keywords", "mail_set_keywords"]);
});

test("a check that still cannot see the keyword keeps the block", async () => {
  const {call} = fake({mail_set_keywords: [() => unknownView], mail_check_keywords: [() => [row(null)]]});
  const writer = new KeywordWriter(call);
  assert.deepEqual((await writer.write(target, ["m1"], "flagged", true, "Star")).unverified, ["m1"]);
});

test("the block is per keyword and per Account; a gone message is no longer blocked", async () => {
  const {calls, call} = fake({
    mail_set_keywords: [() => unknownView, () => ({results: [{outcome: "confirmed", id: "m1"}], readBackComplete: true})],
    mail_check_keywords: [() => { throw new Error("offline"); }, () => [{kind: "unavailable", id: "m1", reason: "notFound"}]],
  });
  const writer = new KeywordWriter(call);
  await writer.write(target, ["m1"], "flagged", true, "Star");
  assert.equal(writer.isUnverified(target, "m1", "seen"), false);
  assert.equal(writer.isUnverified({connectionId: 1, accountId: "b"}, "m1", "flagged"), false);
  await writer.write(target, ["m1"], "seen", true, "Mark as read");
  assert.equal(await writer.check(target, ["m1"], "flagged"), true);
  assert.equal(writer.isUnverified(target, "m1", "flagged"), false);
  assert.deepEqual(calls, ["mail_set_keywords", "mail_check_keywords", "mail_set_keywords", "mail_check_keywords"]);
});

test("a failed write request is unknown: blocked and checked, not retried", async () => {
  const {calls, call} = fake({
    mail_set_keywords: [() => { throw new Error("signed out"); }],
    mail_check_keywords: [() => { throw new Error("offline"); }, () => { throw new Error("offline"); }],
  });
  const writer = new KeywordWriter(call);
  const first = await writer.write(target, ["m1"], "seen", true, "Mark as read");
  assert.ok(first.writeError);
  assert.deepEqual(first.unverified, ["m1"]);
  await writer.write(target, ["m1"], "seen", true, "Mark as read");
  assert.deepEqual(calls, ["mail_set_keywords", "mail_check_keywords", "mail_check_keywords"]);
});

test("in a batch, only the unverified ids are held back", async () => {
  const sent: unknown[] = [];
  const {call} = fake({
    mail_set_keywords: [() => unknownView, () => ({results: [{outcome: "confirmed", id: "m2"}], readBackComplete: true})],
    mail_check_keywords: [() => { throw new Error("offline"); }, () => { throw new Error("offline"); }],
  });
  const writer = new KeywordWriter(async (command, args) => { if (command === "mail_set_keywords") sent.push(args.emailIds); return call(command); });
  await writer.write(target, ["m1"], "flagged", true, "Star");
  const result = await writer.write(target, ["m1", "m2"], "flagged", true, "Star");
  assert.deepEqual(sent, [["m1"], ["m2"]]);
  assert.deepEqual(result.held, ["m1"]);
  assert.deepEqual(result.unverified, ["m1"]);
});

test("a check settles it but the view has not reloaded: the confirmed value decides the toggle", async () => {
  const sent: unknown[] = [];
  const {call} = fake({
    mail_set_keywords: [() => unknownView, () => ({results: [{outcome: "confirmed", id: "m1"}], readBackComplete: true})],
    mail_check_keywords: [() => [row(true)]],
  });
  const writer = new KeywordWriter(async (command, args) => { if (command === "mail_set_keywords") sent.push(args.on); return call(command); });
  const loadedFrom = writer.mark();      // the list loaded before any of this, showing no star
  const viewHas = false;
  await writer.write(target, ["m1"], "flagged", true, "Star");
  const starred = writer.shown(target, "m1", "flagged", viewHas, loadedFrom);
  assert.equal(starred, true, "the server's star shows although the list has not reloaded");
  await writer.write(target, ["m1"], "flagged", !starred, "Remove star");
  assert.deepEqual(sent, [true, false], "the second click removes the star, it does not star again");
});

test("a confirmed write shows at once; a view loaded after it takes over again", async () => {
  const {call} = fake({mail_set_keywords: [() => ({results: [{outcome: "confirmed", id: "m1"}], readBackComplete: true})]});
  const writer = new KeywordWriter(call);
  const before = writer.mark();
  await writer.write(target, ["m1"], "seen", true, "Mark as read");
  assert.equal(writer.shown(target, "m1", "seen", false, before), true);
  const after = writer.mark();
  assert.equal(writer.shown(target, "m1", "seen", false, after), false, "a later load is newer than the kept value");
});
