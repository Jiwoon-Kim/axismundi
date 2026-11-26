// What the interface layout setting means for the shell and the mail screen. Angular-free so
// `node --test` can check it. M3 Scaffold terms (docs/BRIEF-MAIL-LAYOUT-MODES.md): the setting picks
// how apps are reached and how a message opens; `appBarScope` only says how far the top bar spans.

import type { InterfaceLayout } from "../settings/appearance";

export interface ScaffoldLayout {
  /** 'pane': each pane brings its own bar (search above the list). 'window': one bar across the window. */
  appBarScope: "pane" | "window";
  /** How apps are switched: the navigation rail, or the app menu in the window bar. */
  navigation: "rail" | "menu";
  /** An opened message reads beside the list, or replaces it. */
  reading: "side" | "replace";
  /** Compose as the list's FAB, or at the top of the mailbox panel. */
  compose: "fab" | "panel";
}

const RAIL: ScaffoldLayout = {appBarScope: "pane", navigation: "rail", reading: "side", compose: "fab"};
const SEARCH_APP_BAR: ScaffoldLayout = {appBarScope: "window", navigation: "menu", reading: "replace", compose: "panel"};

/**
 * Below the expanded size class both settings lay out the same compact screen (Bulwark's mobile
 * header ignores the setting too); compact reading is one pane at a time either way.
 */
export function scaffoldLayout(setting: InterfaceLayout, expanded: boolean): ScaffoldLayout {
  if (!expanded) return {...RAIL, reading: "replace"};
  return setting === "search-app-bar" ? SEARCH_APP_BAR : RAIL;
}
