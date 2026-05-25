/*
 * Axismundi — lab-search-bar.js
 * v3.5.8 — Search bar #17 Full-Spec + Runtime module
 *
 * Derived from search-expansion/ v3.3.4 evidence, but owned by the
 * v3.5.8 Search bar component lane. No public window API, no remote data,
 * no popover/ dependency, and no attachment inside prose/editor surfaces.
 */

(function () {
  "use strict";

  const HOST_SELECTOR = ".search-bar";
  const INPUT_SELECTOR = ".search-bar__input";
  const PANEL_SELECTOR = ".ax-search-suggestions";
  const OPTION_SELECTOR = ".ax-search-suggestions__item";
  const FORBIDDEN_ANCESTORS = ".prose, .wp-block-post-content, .entry-content, [contenteditable]";
  const READY_ATTR = "data-ax-search-ready";

  const DEFAULT_SUGGESTIONS = [
    { icon: "history", label: "최근 검색어" },
    { icon: "widgets", label: "컴포넌트 토큰" },
    { icon: "layers", label: "상태 레이어" },
    { icon: "search", label: "검색 패턴" }
  ];

  let listboxIdCounter = 0;

  const qs = (selector, root = document) => root.querySelector(selector);
  const qsa = (selector, root = document) => Array.from(root.querySelectorAll(selector));

  function onReady(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn, { once: true });
    } else {
      fn();
    }
  }

  function parseSuggestions(bar) {
    const raw = bar.getAttribute("data-search-suggestions");
    const iconOverride = bar.getAttribute("data-search-suggestion-icon");
    if (!raw) return DEFAULT_SUGGESTIONS;

    const labels = raw.split("|").map((item) => item.trim()).filter(Boolean);
    if (!labels.length) return DEFAULT_SUGGESTIONS;

    return labels.map((label, index) => ({
      icon: iconOverride || (index === 0 ? "history" : "search"),
      label
    }));
  }

  function makeSuggestions(listboxId, suggestions) {
    const panel = document.createElement("div");
    panel.className = "ax-search-suggestions";
    panel.id = listboxId;
    panel.setAttribute("role", "listbox");

    panel.innerHTML = suggestions.map((item) => (
      `<button type="button" class="ax-search-suggestions__item" role="option" aria-selected="false" tabindex="-1" data-search-value="${item.label}">`
      + `<span class="material-symbols-rounded notranslate" translate="no" aria-hidden="true" draggable="false">${item.icon}</span>`
      + `<span>${item.label}</span>`
      + `</button>`
    )).join("");

    return panel;
  }

  function setupSearchBar(bar) {
    if (bar.closest(FORBIDDEN_ANCESTORS)) return;
    if (bar.hasAttribute(READY_ATTR)) return;

    const input = qs(INPUT_SELECTOR, bar);
    if (!input) return;

    listboxIdCounter += 1;
    const listboxId = "ax-search-suggestions-" + listboxIdCounter;
    const panel = makeSuggestions(listboxId, parseSuggestions(bar));
    bar.appendChild(panel);

    bar.setAttribute(READY_ATTR, "true");
    input.setAttribute("role", "combobox");
    input.setAttribute("aria-controls", listboxId);
    input.setAttribute("aria-expanded", "false");
    input.setAttribute("aria-autocomplete", "list");

    const options = () => qsa(OPTION_SELECTOR, panel);

    const setActive = (active) => {
      if (bar.matches('[aria-disabled="true"], .is-search-disabled')) return;
      bar.classList.toggle("is-search-active", active);
      input.setAttribute("aria-expanded", active ? "true" : "false");
    };

    const collapse = () => setActive(false);
    const expand = () => setActive(true);

    input.addEventListener("focus", expand);
    input.addEventListener("input", expand);

    input.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        if (input.value.length > 0) {
          input.value = "";
          input.dispatchEvent(new Event("input", { bubbles: true }));
        } else {
          collapse();
          input.blur();
        }
        return;
      }

      if (event.key === "ArrowDown") {
        event.preventDefault();
        expand();
        const firstOption = options()[0];
        if (firstOption) firstOption.focus();
      }
    });

    bar.addEventListener("pointerdown", () => {
      if (!bar.matches('[aria-disabled="true"], .is-search-disabled')) expand();
    }, { passive: true });

    bar.addEventListener("focusout", () => {
      window.setTimeout(() => {
        if (!bar.contains(document.activeElement)) collapse();
      }, 80);
    });

    panel.addEventListener("click", (event) => {
      const item = event.target.closest(OPTION_SELECTOR);
      if (!item) return;
      input.value = item.getAttribute("data-search-value") || item.textContent.trim();
      input.focus();
      collapse();
    });

    panel.addEventListener("keydown", (event) => {
      const items = options();
      const focused = document.activeElement;
      const index = items.indexOf(focused);
      if (index < 0) return;

      if (event.key === "ArrowDown") {
        event.preventDefault();
        const next = items[Math.min(index + 1, items.length - 1)];
        if (next) next.focus();
      } else if (event.key === "ArrowUp") {
        event.preventDefault();
        if (index <= 0) {
          input.focus();
        } else {
          items[index - 1].focus();
        }
      } else if (event.key === "Home") {
        event.preventDefault();
        if (items[0]) items[0].focus();
      } else if (event.key === "End") {
        event.preventDefault();
        if (items.length) items[items.length - 1].focus();
      } else if (event.key === "Escape") {
        event.preventDefault();
        collapse();
        input.focus();
      }
    });

    qsa('[data-ax-search-action="clear"]', bar).forEach((button) => {
      button.addEventListener("click", () => {
        input.value = "";
        input.dispatchEvent(new Event("input", { bubbles: true }));
        input.focus();
        expand();
      });
    });
  }

  function enableSearchBars() {
    qsa(HOST_SELECTOR).forEach(setupSearchBar);
  }

  onReady(enableSearchBars);

  if (typeof window !== "undefined" && window.console && window.console.debug) {
    window.console.debug("[lab-search-bar v3.5.8] ready");
  }
})();
