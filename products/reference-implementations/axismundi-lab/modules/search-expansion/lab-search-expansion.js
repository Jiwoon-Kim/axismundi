/*
 * Axismundi — lab-search-expansion.js
 * v3.3.4 — Search Expansion Module Extraction
 *
 * EXTRACTED with refinement from benchmark-interactions.js L247-288
 * (the original enableSearchBar()). Second Beer-CSS-derived module
 * under the ../../docs/BEER-CSS-INTAKE.md contract.
 *
 * Refinement summary (against benchmark original):
 *
 *   - Explicit .prose / federation bail-out per intake §1:
 *     enableSearchBar() now skips any .search-bar that has a
 *     forbidden ancestor (.prose, .wp-block-post-content,
 *     .entry-content, [contenteditable]). A search bar inside
 *     long-form content should remain a plain input — no
 *     expansion, no suggestions popup.
 *
 *   - Escape key policy made explicit (intake §1 semantic risk
 *     surfacing — the GPT consultation specifically flagged
 *     "검색어 입력 중 collapse 금지"):
 *       • Empty input + Escape  → blur + collapse (original behavior)
 *       • Non-empty + Escape #1 → clear text, keep open (new)
 *       • Non-empty + Escape #2 → blur + collapse
 *     This matches macOS / iOS search-field convention and
 *     prevents accidental loss of typed query state.
 *
 *   - aria-controls + aria-expanded wiring (intake §1 a11y
 *     improvement): the popup listbox gets a stable id, and the
 *     input is wired to it via aria-controls. aria-expanded
 *     toggles in sync with the .is-search-active class.
 *
 *   - Suggestion items get aria-selected support and a tabindex
 *     management policy so keyboard users can ArrowDown into the
 *     list and ArrowUp back into the input.
 *
 *   - { passive: true } on pointerdown (intake §6).
 *
 *   - Idempotent — re-running enableSearchBar() (e.g. after a
 *     dynamic HTMX-style content swap) does not duplicate
 *     suggestion panels. The original already had this guard,
 *     retained here.
 *
 * Loaded by: lab-search-expansion-pattern.html ONLY.
 * NOT loaded by main style-guide.html — lab-internal isolation.
 *
 * Allowlist (intake §1): only .search-bar containers.
 * Forbidden ancestors (intake §1):
 *   .prose, .wp-block-post-content, .entry-content,
 *   [contenteditable]
 *
 * Theme territory (per docs/SEARCH-EXPANSION-AUDIT.md):
 *   - Visual expansion on focus/active
 *   - Suggestions popup visual chrome
 *   - Clear-button affordance
 *   - Keyboard navigation within the popup
 *
 * Plugin territory (NOT in this module):
 *   - Live search query against a data source
 *   - Remote search / API calls
 *   - Federated search across ActivityPub feeds
 *   - Suggestion data source (the demo data here is static)
 *   - Query analytics / telemetry
 *
 * Audit: docs/SEARCH-EXPANSION-AUDIT.md
 * Cross-module contract: ../../docs/BEER-CSS-INTAKE.md
 */

(function () {
  "use strict";

  // --- Shared utilities (intake §8 — copy only what's needed) ---
  const qs  = (selector, root = document) => root.querySelector(selector);
  const qsa = (selector, root = document) => Array.from(root.querySelectorAll(selector));

  function onReady(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn, { once: true });
    } else {
      fn();
    }
  }

  // --- Constants ----------------------------------------------

  const FORBIDDEN_ANCESTORS = ".prose, .wp-block-post-content, .entry-content, [contenteditable]";

  // Demo suggestions data. This is STATIC by design — anything that
  // queries a real data source belongs in plugin territory (see
  // module header). For the lab pattern page, these labels
  // demonstrate the visual + interaction layer only.
  const DEMO_SUGGESTIONS = [
    "최근 검색어",
    "컴포넌트 토큰",
    "상태 레이어",
    "메뉴 인터랙션"
  ];

  // Stable id counter for aria-controls wiring. Module-scoped so
  // multiple search-bars on one page each get a unique listbox id.
  let listboxIdCounter = 0;

  // --- Helpers -------------------------------------------------

  function makeSuggestions(listboxId) {
    const panel = document.createElement("div");
    panel.className = "ax-search-suggestions";
    panel.setAttribute("role", "listbox");
    panel.id = listboxId;
    panel.innerHTML = DEMO_SUGGESTIONS.map((label) => (
      `<button type="button" class="ax-search-suggestions__item"`
      + ` role="option" aria-selected="false" tabindex="-1">${label}</button>`
    )).join("");
    return panel;
  }

  // --- Per-instance setup -------------------------------------

  function setupSearchBar(bar) {
    const input = qs(".search-bar__input", bar);
    if (!input) return;

    // Idempotent — bail if we already attached.
    if (qs(".ax-search-suggestions", bar)) return;

    // Build + insert the suggestions popup with a stable id.
    listboxIdCounter += 1;
    const listboxId = "ax-search-suggestions-" + listboxIdCounter;
    const panel = makeSuggestions(listboxId);
    bar.appendChild(panel);

    // ARIA wiring on the input.
    input.setAttribute("role", "combobox");
    input.setAttribute("aria-controls", listboxId);
    input.setAttribute("aria-expanded", "false");
    input.setAttribute("aria-autocomplete", "list");

    // State helper — toggles the .is-search-active class and
    // keeps aria-expanded in sync.
    const setActive = (active) => {
      bar.classList.toggle("is-search-active", active);
      input.setAttribute("aria-expanded", active ? "true" : "false");
    };

    // --- Listeners ---

    input.addEventListener("focus", () => setActive(true));
    input.addEventListener("input", () => setActive(true));

    // Escape policy — see module header.
    input.addEventListener("keydown", (event) => {
      if (event.key !== "Escape") return;
      if (input.value.length > 0) {
        // First press with text: clear, stay open.
        input.value = "";
        // Fire an input event so any listening UI (e.g. suggestion
        // filtering, if a future version adds it) updates. The
        // 'input' handler above will keep setActive(true).
        input.dispatchEvent(new Event("input", { bubbles: true }));
      } else {
        // Second press (now empty) or first press on empty: collapse.
        setActive(false);
        input.blur();
      }
    });

    // Pointerdown anywhere on the bar opens (covers cases where the
    // user clicks a non-input area of the bar to focus the input).
    // { passive: true } — handler never calls preventDefault().
    bar.addEventListener("pointerdown", () => setActive(true), { passive: true });

    // Focusout with delay — gives suggestion item clicks time to
    // land before the bar collapses. 80ms is enough for the click
    // event to fire after focusout. Original benchmark policy.
    bar.addEventListener("focusout", () => {
      window.setTimeout(() => {
        if (!bar.contains(document.activeElement)) setActive(false);
      }, 80);
    });

    // Suggestion items: click → fill input + collapse.
    qsa(".ax-search-suggestions__item", panel).forEach((item) => {
      item.addEventListener("click", () => {
        input.value = item.textContent.trim();
        input.focus();
        setActive(false);
      });
    });

    // Arrow-key navigation between input and suggestion items.
    // input.ArrowDown → first option; option.ArrowDown → next;
    // option.ArrowUp at first → input.
    input.addEventListener("keydown", (event) => {
      if (event.key !== "ArrowDown") return;
      event.preventDefault();
      setActive(true);
      const firstOption = qs(".ax-search-suggestions__item", panel);
      if (firstOption) firstOption.focus();
    });

    panel.addEventListener("keydown", (event) => {
      const focused = document.activeElement;
      if (!panel.contains(focused)) return;
      const options = qsa(".ax-search-suggestions__item", panel);
      const idx = options.indexOf(focused);

      if (event.key === "ArrowDown") {
        event.preventDefault();
        const next = options[Math.min(idx + 1, options.length - 1)];
        if (next) next.focus();
      } else if (event.key === "ArrowUp") {
        event.preventDefault();
        if (idx <= 0) {
          input.focus();
        } else {
          options[idx - 1].focus();
        }
      } else if (event.key === "Home") {
        event.preventDefault();
        if (options[0]) options[0].focus();
      } else if (event.key === "End") {
        event.preventDefault();
        if (options.length) options[options.length - 1].focus();
      }
    });
  }

  // --- Bootstrap ----------------------------------------------

  function enableSearchBar() {
    qsa(".search-bar").forEach((bar) => {
      // Intake contract §1 — bail out if inside forbidden ancestor.
      // Skip THIS .search-bar entirely (no expansion attached) so
      // a search input inside long-form prose remains a plain
      // input field with no popup.
      if (bar.closest(FORBIDDEN_ANCESTORS)) return;

      setupSearchBar(bar);
    });
  }

  onReady(enableSearchBar);

  // Module diagnostic — surfaces in console when the page is
  // loaded with this module. Helps confirm load order during QA.
  if (typeof window !== "undefined" && window.console && window.console.debug) {
    window.console.debug(
      "[lab-search-expansion v3.3.4] ready"
    );
  }
})();
