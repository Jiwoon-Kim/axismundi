// Run: node --test gui/src/app/mail/mail-updates.spec.ts   (Node 24 strips the types itself)
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { test } from "node:test";
import { type Keyword, MailWriter, type UpdateView, settle } from "./mail-updates.ts";

// Written by the Rust test `update_results_serialize_to_the_shape_the_gui_reads`: every core
// outcome folded into what mail_set_keywords and mail_move send over IPC.
const view: UpdateView = JSON.parse(readFileSync(new URL("./mail-updates.fixture.json", import.meta.url), "utf8"));

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
  const one: UpdateView = {results: [view.results[1]], readBackComplete: true};
  const settled = settle(one, "Star");
  assert.match(settled.notice ?? "", /^Star: not done\. The server refused it \(forbidden\)\.$/);
  assert.equal(settled.refresh, false);
});

test("an unknown message refreshes, because the server may have changed", () => {
  const one: UpdateView = {results: [view.results[4]], readBackComplete: true};
  assert.equal(settle(one, "Star").refresh, true);
});

test("all confirmed and read back is silent; not read back says so", () => {
  const done: UpdateView = {results: [view.results[0]], readBackComplete: true};
  assert.equal(settle(done, "Star").notice, null);
  assert.match(settle({...done, readBackComplete: false}, "Star").notice ?? "", /could not be read back/);
});

// --- MailWriter: an unknown outcome blocks writes until a read settles it ---

const unknownView = {results: [{outcome: "unknown", id: "m1", reason: "lost"}], readBackComplete: true};
const target = {connectionId: 1, accountId: "a"};
const kw = (keyword: Keyword, on: boolean) => ({kind: "keyword", keyword, on}) as const;
const row = (flagged: boolean | null) => ({
  kind: "message", id: "m1", threadId: null, senderName: null, senderEmail: null, subject: null,
  preview: null, receivedAt: null, unread: false, flagged, hasAttachment: null, mailboxIds: ["in"],
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
    mail_check: [() => { throw new Error("offline"); }, () => { throw new Error("offline"); }],
  });
  const writer = new MailWriter(call);
  const first = await writer.write(target, ["m1"], kw("flagged", true), "Star");
  assert.deepEqual(first.unverified, ["m1"]);
  assert.equal(first.checkFailed, true);
  const second = await writer.write(target, ["m1"], kw("flagged", true), "Star");
  assert.equal(second.settled, null, "nothing was sent");
  assert.deepEqual(second.held, ["m1"]);
  assert.deepEqual(calls, ["mail_set_keywords", "mail_check", "mail_check"]);
});

test("a check that shows the keyword lifts the block; the next click writes", async () => {
  const {calls, call} = fake({
    mail_set_keywords: [() => unknownView, () => ({results: [{outcome: "confirmed", id: "m1"}], readBackComplete: true})],
    mail_check: [() => [row(true)]],
  });
  const writer = new MailWriter(call);
  const first = await writer.write(target, ["m1"], kw("flagged", true), "Star");
  assert.deepEqual(first.unverified, [], "the immediate check settled it");
  await writer.write(target, ["m1"], kw("flagged", false), "Remove star");
  assert.deepEqual(calls, ["mail_set_keywords", "mail_check", "mail_set_keywords"]);
});

test("a check that still cannot see the keyword keeps the block", async () => {
  const {call} = fake({mail_set_keywords: [() => unknownView], mail_check: [() => [row(null)]]});
  const writer = new MailWriter(call);
  assert.deepEqual((await writer.write(target, ["m1"], kw("flagged", true), "Star")).unverified, ["m1"]);
});

test("the block is per keyword and per Account; a gone message is no longer blocked", async () => {
  const {calls, call} = fake({
    mail_set_keywords: [() => unknownView, () => ({results: [{outcome: "confirmed", id: "m1"}], readBackComplete: true})],
    mail_check: [() => { throw new Error("offline"); }, () => [{kind: "unavailable", id: "m1", reason: "notFound"}]],
  });
  const writer = new MailWriter(call);
  await writer.write(target, ["m1"], kw("flagged", true), "Star");
  assert.equal(writer.isUnverified(target, "m1", "seen"), false);
  assert.equal(writer.isUnverified({connectionId: 1, accountId: "b"}, "m1", "flagged"), false);
  await writer.write(target, ["m1"], kw("seen", true), "Mark as read");
  assert.equal(await writer.check(target, ["m1"], "flagged"), true);
  assert.equal(writer.isUnverified(target, "m1", "flagged"), false);
  assert.deepEqual(calls, ["mail_set_keywords", "mail_check", "mail_set_keywords", "mail_check"]);
});

test("a failed write request is unknown: blocked and checked, not retried", async () => {
  const {calls, call} = fake({
    mail_set_keywords: [() => { throw new Error("signed out"); }],
    mail_check: [() => { throw new Error("offline"); }, () => { throw new Error("offline"); }],
  });
  const writer = new MailWriter(call);
  const first = await writer.write(target, ["m1"], kw("seen", true), "Mark as read");
  assert.ok(first.writeError);
  assert.deepEqual(first.unverified, ["m1"]);
  await writer.write(target, ["m1"], kw("seen", true), "Mark as read");
  assert.deepEqual(calls, ["mail_set_keywords", "mail_check", "mail_check"]);
});

test("in a batch, only the unverified ids are held back", async () => {
  const sent: unknown[] = [];
  const {call} = fake({
    mail_set_keywords: [() => unknownView, () => ({results: [{outcome: "confirmed", id: "m2"}], readBackComplete: true})],
    mail_check: [() => { throw new Error("offline"); }, () => { throw new Error("offline"); }],
  });
  const writer = new MailWriter(async (command, args) => { if (command === "mail_set_keywords") sent.push(args.emailIds); return call(command); });
  await writer.write(target, ["m1"], kw("flagged", true), "Star");
  const result = await writer.write(target, ["m1", "m2"], kw("flagged", true), "Star");
  assert.deepEqual(sent, [["m1"], ["m2"]]);
  assert.deepEqual(result.held, ["m1"]);
  assert.deepEqual(result.unverified, ["m1"]);
});

test("a check settles it but the view has not reloaded: the confirmed value decides the toggle", async () => {
  const sent: unknown[] = [];
  const {call} = fake({
    mail_set_keywords: [() => unknownView, () => ({results: [{outcome: "confirmed", id: "m1"}], readBackComplete: true})],
    mail_check: [() => [row(true)]],
  });
  const writer = new MailWriter(async (command, args) => { if (command === "mail_set_keywords") sent.push(args.on); return call(command); });
  const loadedFrom = writer.mark();      // the list loaded before any of this, showing no star
  const viewHas = false;
  await writer.write(target, ["m1"], kw("flagged", true), "Star");
  const starred = writer.shown(target, "m1", "flagged", viewHas, loadedFrom);
  assert.equal(starred, true, "the server's star shows although the list has not reloaded");
  await writer.write(target, ["m1"], kw("flagged", !starred), "Remove star");
  assert.deepEqual(sent, [true, false], "the second click removes the star, it does not star again");
});

test("a confirmed write shows at once; a view loaded after it takes over again", async () => {
  const {call} = fake({mail_set_keywords: [() => ({results: [{outcome: "confirmed", id: "m1"}], readBackComplete: true})]});
  const writer = new MailWriter(call);
  const before = writer.mark();
  await writer.write(target, ["m1"], kw("seen", true), "Mark as read");
  assert.equal(writer.shown(target, "m1", "seen", false, before), true);
  const after = writer.mark();
  assert.equal(writer.shown(target, "m1", "seen", false, after), false, "a later load is newer than the kept value");
});

// --- Moves: the same lock, on one aspect for every mailbox change ---

const move = (from: string, to: string, exclusive = false) => ({kind: "move", from, to, exclusive}) as const;
const confirmedView = {results: [{outcome: "confirmed", id: "m1"}], readBackComplete: true};

test("a move goes out as mail_move with both mailboxes and leaves keyword locks alone", async () => {
  const sent: unknown[] = [];
  const {call} = fake({mail_move: [() => unknownView], mail_check: [() => { throw new Error("offline"); }]});
  const writer = new MailWriter(async (command, args) => { sent.push([command, args.from, args.to, args.exclusive]); return call(command); });
  await writer.write(target, ["m1"], move("in", "trash", true), "Move to Trash");
  assert.deepEqual(sent[0], ["mail_move", "in", "trash", true]);
  assert.equal(writer.isUnverified(target, "m1", "mailboxes"), true);
  assert.equal(writer.isUnverified(target, "m1", "seen"), false, "a lost move does not block marking read");
});

test("an unknown move blocks every further move of that message until a read shows where it is", async () => {
  const {calls, call} = fake({
    mail_move: [() => unknownView, () => confirmedView],
    mail_check: [() => { throw new Error("offline"); }, () => { throw new Error("offline"); }, () => [row(null)]],
  });
  const writer = new MailWriter(call);
  await writer.write(target, ["m1"], move("in", "trash"), "Move to Trash");
  const again = await writer.write(target, ["m1"], move("in", "archive"), "Move to Archive");
  assert.equal(again.settled, null, "a move elsewhere is held as well");
  // The third read returns the message with its mailboxes known: the block lifts, the next move goes.
  await writer.write(target, ["m1"], move("in", "archive"), "Move to Archive");
  await writer.write(target, ["m1"], move("in", "archive"), "Move to Archive");
  assert.deepEqual(calls, ["mail_move", "mail_check", "mail_check", "mail_check", "mail_move"]);
});

test("a read without mailboxes does not lift a move block", async () => {
  const {call} = fake({
    mail_move: [() => unknownView],
    mail_check: [() => [{...row(true), mailboxIds: null}]],
  });
  const writer = new MailWriter(call);
  const result = await writer.write(target, ["m1"], move("in", "trash"), "Move to Trash");
  assert.deepEqual(result.unverified, ["m1"]);
});

test("a confirmed move takes the row out of its listing at once; the next load decides again", async () => {
  const {call} = fake({mail_move: [() => confirmedView]});
  const writer = new MailWriter(call);
  const before = writer.mark();
  await writer.write(target, ["m1"], move("in", "trash"), "Move to Trash");
  assert.equal(writer.shownIn(target, "m1", "in", true, before), false, "gone from where it was listed");
  assert.equal(writer.shownIn(target, "m1", "trash", false, before), true, "and shown where it went");
  assert.equal(writer.shownIn(target, "m1", "project", true, before), true, "a membership it did not name is the view's");
  const after = writer.mark();
  assert.equal(writer.shownIn(target, "m1", "in", true, after), true, "a later load is newer than the kept value");
});

test("a check that read the whole membership answers for every mailbox", async () => {
  const {call} = fake({mail_move: [() => unknownView], mail_check: [() => [{...row(false), mailboxIds: ["sent"]}]]});
  const writer = new MailWriter(call);
  const before = writer.mark();
  await writer.write(target, ["m1"], move("trash", "sent"), "Move to Sent");
  assert.equal(writer.shownIn(target, "m1", "sent", false, before), true);
  assert.equal(writer.shownIn(target, "m1", "project", true, before), false, "not in anything the read did not list");
});

test("a confirmed exclusive move claims only the source and the destination, as a plain one does", async () => {
  // The server may keep a membership the move could not remove (no right, not cached): the screen
  // must not say it is gone until a load or a check shows it.
  const {call} = fake({mail_move: [() => confirmedView]});
  const writer = new MailWriter(call);
  const before = writer.mark();
  await writer.write(target, ["m1"], move("in", "trash", true), "Move to Trash");
  assert.equal(writer.shownIn(target, "m1", "in", true, before), false);
  assert.equal(writer.shownIn(target, "m1", "trash", false, before), true);
  assert.equal(writer.shownIn(target, "m1", "project", true, before), true, "Project is the view's to say");
});
