export const CONTACTS = "urn:ietf:params:jmap:contacts";
export type Card = Record<string, unknown>;
export interface Book {
  id: string; name: string | null; description: string | null; sortOrder: number | null;
  isDefault: boolean; isSubscribed: boolean | null;
}
export function sortBooks(books: Book[], collator: Intl.Collator): Book[] {
  return [...books].sort((a, b) => (a.sortOrder ?? 0) - (b.sortOrder ?? 0)
    || collator.compare(a.name ?? "", b.name ?? ""));
}
export interface ContactRow {
  id: string; kind: string; name: string | null; email: string | null; avatarUri: string | null;
  phone: string | null; organization: string | null;
}
export interface Edit { path: string[]; value: unknown }
export interface Field { key: string; label: string; path: string[]; original: string }

export const record = (value: unknown): Card =>
  value !== null && typeof value === "object" && !Array.isArray(value) ? value as Card : {};
const str = (value: unknown) => typeof value === "string" ? value : "";
const groups = [
  ["emails", "address", "Email"], ["phones", "number", "Phone"],
  ["organizations", "name", "Organization"], ["notes", "note", "Note"],
] as const;

export function fields(card: Card): Field[] {
  const name = record(card["name"]);
  return [
    ...[["full", "Display name"], ["given", "Given name"], ["surname", "Surname"]].map(([key, label]) =>
      ({key, label, path: ["name", key], original: key === "full" ? str(name[key]) :
        str((Array.isArray(name["components"]) ? name["components"].map(record) : [])
          .find(part => part["kind"] === key)?.["value"])})),
    ...groups.flatMap(([group, property, label]) => [
      ...Object.entries(record(card[group])).map(([id, value]) =>
        ({key: JSON.stringify([group, id]), label, path: [group, id, property], original: str(record(value)[property])})),
      {key: group, label, path: [group, "", property], original: ""},
    ]),
  ];
}

export function editsFor(card: Card, form: Field[], values: Record<string, string>, newId: () => string): Edit[] {
  const edits: Edit[] = [];
  const name = record(card["name"]);
  const components = (Array.isArray(name["components"]) ? name["components"].map(record) : []).slice();
  let changedComponents = false;
  const additions = new Map<string, Card>();
  for (const {key, path, original} of form) {
    const value = (values[key] ?? original).trim();
    if (value === original || (values[key] ?? original) === original) continue;
    if (key === "given" || key === "surname") {
      const index = components.findIndex(part => part["kind"] === key);
      if (index < 0 && value) components.push({kind: key, value});
      else if (index >= 0 && value) components[index] = {...components[index], value};
      else if (index >= 0) components.splice(index, 1);
      changedComponents = true;
    } else if (path[1] === "") {
      if (value) additions.set(path[0], {[newId()]: {[path[2]]: value}});
    } else if (path.length === 3 && !value) {
      edits.push({path: path.slice(0, 2), value: null});
    } else {
      edits.push({path, value: value || null});
    }
  }
  if (changedComponents) edits.push({path: ["name", "components"], value: components.length ? components : null});
  for (const [group, entries] of additions) {
    if (!card[group]) edits.push({path: [group], value: entries});
    else for (const [id, value] of Object.entries(entries)) edits.push({path: [group, id], value});
  }
  return edits;
}

export function newCard(edits: Edit[]): Card {
  const result: Card = {};
  for (const {path, value} of edits) {
    if (value === null) continue;
    let object = result;
    for (const part of path.slice(0, -1)) {
      object[part] ??= {};
      object = record(object[part]);
    }
    object[path[path.length - 1]] = value;
  }
  return result;
}

export function detailFields(card: Card): {label: string; values: string[]}[] {
  return [
    ["emails", "Email", ["address"]], ["phones", "Phone", ["number"]],
    ["addresses", "Address", ["full", "components"]], ["organizations", "Organization", ["name"]],
    ["titles", "Title", ["name"]], ["onlineServices", "Online service", ["service", "user", "uri"]],
    ["links", "Link", ["uri"]], ["anniversaries", "Anniversary", ["date"]],
    ["notes", "Note", ["note"]], ["keywords", "Keywords", []],
  ].map(([key, label, properties]) => ({
    label: label as string,
    values: Object.entries(record(card[key as string])).map(([id, item]) => {
      if (key === "keywords") return item === true ? id : "";
      const object = record(item);
      return (properties as string[]).map(prop => {
        const value = object[prop];
        if (Array.isArray(value)) return value.map(record).map(part => str(part["value"])).filter(Boolean).join(", ");
        if (prop === "date" && value && typeof value === "object") {
          const date = record(value);
          return str(date["utc"]) || [date["year"], date["month"], date["day"]].filter(part => typeof part === "number").join("-");
        }
        return str(value);
      }).filter(Boolean).join(" · ");
    }).filter(Boolean),
  })).filter(group => group.values.length);
}
