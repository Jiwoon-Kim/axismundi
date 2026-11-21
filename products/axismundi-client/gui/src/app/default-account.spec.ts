// Run: node --test "gui/src/**/*.spec.ts"
import assert from "node:assert/strict";
import { test } from "node:test";
import { defaultAccount } from "./default-account.ts";

// The local Stalwart fixture's two Accounts: alice's own (c) and the team group (d).
const accounts = [{id: "c", isPersonal: true}, {id: "d", isPersonal: false}];

test("the server's primary Account wins even when it is not personal", () => {
  assert.equal(defaultAccount(accounts, "d"), "d");
});

test("without a primary, a personal Account is chosen", () => {
  assert.equal(defaultAccount(accounts, undefined), "c");
});

test("a primary this login cannot see is ignored", () => {
  assert.equal(defaultAccount(accounts, "zz"), "c");
});

test("no Accounts, no choice", () => {
  assert.equal(defaultAccount([], "c"), null);
});
