import assert from "node:assert/strict";
import { test } from "node:test";
import { contactFilter } from "./contact-query.ts";

test("whole-account queries omit the address book condition", () => {
  assert.equal(contactFilter("", []), null);
  assert.deepEqual(contactFilter("kim", []), {text: "kim"});
});

test("multiple kinds and UIDs are alternatives while repeated text fields are cumulative", () => {
  assert.deepEqual(contactFilter("", [
    {key: "kind", value: "group"}, {key: "kind", value: "org"},
    {key: "uid", value: "u1"}, {key: "uid", value: "u2"},
    {key: "email", value: "a"}, {key: "email", value: "b"},
  ]), {operator: "AND", conditions: [
    {email: "a"}, {email: "b"},
    {operator: "OR", conditions: [{kind: "group"}, {kind: "org"}]},
    {operator: "OR", conditions: [{uid: "u1"}, {uid: "u2"}]},
  ]});
});

test("selected books are ORed; other conditions are ANDed without rewriting RFC keys", () => {
  assert.deepEqual(contactFilter("kim", [
    {key: "inAddressBook", value: "b1"},
    {key: "inAddressBook", value: "b2"},
    {key: "name/surname", value: "Kim"},
  ]), {operator: "AND", conditions: [
    {text: "kim"}, {"name/surname": "Kim"},
    {operator: "OR", conditions: [{inAddressBook: "b1"}, {inAddressBook: "b2"}]},
  ]});
});
