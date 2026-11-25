export interface ComposeAddress { email: string }

export function sameAddress(a: string, b: string): boolean {
  return a.toLowerCase() === b.toLowerCase();
}

export function recipientInput(value: string): ComposeAddress[] | null {
  const addresses = value.split(/[,;\n]/).map(email => email.trim()).filter(Boolean);
  return addresses.every(email => /^[^\s@<>]+@[^\s@<>]+$/.test(email))
    ? addresses.map(email => ({email})) : null;
}

export function replyAllRecipients(reply: ComposeAddress[], to: ComposeAddress[], cc: ComposeAddress[], from: string) {
  const unique = (values: ComposeAddress[], exclude: ComposeAddress[]) => values.filter((a, index) =>
    !sameAddress(a.email, from) && !exclude.some(b => sameAddress(a.email, b.email))
    && !values.slice(0, index).some(b => sameAddress(a.email, b.email)));
  const targets = unique([...reply, ...to], []);
  return {to: targets, cc: unique(cc, targets)};
}
