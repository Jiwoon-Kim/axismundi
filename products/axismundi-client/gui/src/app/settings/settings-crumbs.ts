// The breadcrumb trail for a Settings URL. Angular-free so `node --test` checks it.

export interface Crumb { label: string; link: string | null }

/** Settings › Connections › alice@example.org, from the path; the last crumb is the page itself (no link). */
export function settingsCrumbs(url: string, connectionName: (id: string) => string | null): Crumb[] {
  const path = url.split(/[?#]/, 1)[0].split("/").filter(Boolean);
  if (path[0] !== "settings") return [];
  const trail: {label: string; link: string}[] = [{label: "Settings", link: "/settings"}];
  if (path[1] === "connections") {
    trail.push({label: "Connections", link: "/settings/connections"});
    if (path[2]) trail.push({label: connectionName(path[2]) ?? "Connection", link: `/settings/connections/${path[2]}`});
    const protocol = {jmap: "JMAP", activitypub: "ActivityPub", wordpress: "WordPress"}[path[3] ?? ""];
    if (path[2] && protocol) trail.push({label: protocol, link: `/settings/connections/${path[2]}/${path[3]}`});
  } else if (path[1] === "appearance") {
    trail.push({label: "Appearance", link: "/settings/appearance"});
  }
  return trail.map((crumb, i) => ({label: crumb.label, link: i === trail.length - 1 ? null : crumb.link}));
}
