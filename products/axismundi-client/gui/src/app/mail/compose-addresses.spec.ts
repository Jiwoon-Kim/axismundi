import assert from "node:assert/strict";
import { test } from "node:test";
import { recipientInput, replyAllRecipients } from "./compose-addresses.ts";

test("recipient input rejects an invalid entry without dropping it", () => {
  assert.deepEqual(recipientInput("a@example.org, b@example.org; c@example.org\n"), [
    {email: "a@example.org"}, {email: "b@example.org"}, {email: "c@example.org"},
  ]);
  assert.equal(recipientInput("a@example.org, unfinished"), null);
});

test("reply all preserves Reply-To first and excludes the selected identity and duplicates", () => {
  const a = (email: string) => ({email});
  const reply = a("reply@example.org"), other = a("other@example.org");
  assert.deepEqual(replyAllRecipients([reply], [a("me@example.org"), a("Reply@example.org")],
    [a("ME@example.org"), reply, other, other], "Me@example.org"), {to: [reply], cc: [other]});
});
