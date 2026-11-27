export const filterFields = {
  inAddressBook: "Address book", uid: "UID", kind: "Kind",
  createdBefore: "Created before", createdAfter: "Created on or after",
  updatedBefore: "Updated before", updatedAfter: "Updated on or after",
  name: "Name", "name/given": "Given name", "name/surname": "Surname",
  "name/surname2": "Second surname", nickname: "Nickname", organization: "Organization",
  email: "Email", phone: "Phone", onlineService: "Online service", address: "Address", note: "Note",
};

export const sortFields = {
  created: "Created", updated: "Updated", "name/given": "Given name",
  "name/surname": "Surname", "name/surname2": "Second surname",
};
export type FilterKey = keyof typeof filterFields;
export type SortKey = keyof typeof sortFields;
export type Chip = { key: FilterKey; value: string };
type Filter = Record<string, string> | {operator: "AND" | "OR"; conditions: Filter[]};

export function contactFilter(text: string, chips: Chip[]): Filter | null {
  const alternatives = new Map<string, Filter[]>();
  const conditions: Filter[] = text ? [{text}] : [];
  for (const {key, value} of chips) {
    if (key === "inAddressBook" || key === "kind" || key === "uid") {
      const group = alternatives.get(key) ?? [];
      group.push({[key]: value});
      alternatives.set(key, group);
    } else conditions.push({[key]: value});
  }
  for (const group of alternatives.values()) {
    conditions.push(group.length === 1 ? group[0] : {operator: "OR", conditions: group});
  }
  return conditions.length > 1 ? {operator: "AND", conditions} : conditions[0] ?? null;
}
