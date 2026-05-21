/*
 * Axismundi — lab-tabs.js
 * v3.6.8 Wave 2A-1 Navigation Core
 *
 * Local catalog script only. No public runtime API is exposed.
 */

(function () {
  "use strict";

  const ROOT_SELECTOR = "[data-tabs-demo]";
  const TAB_SELECTOR = '[role="tab"]';

  function onReady(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn, { once: true });
    } else {
      fn();
    }
  }

  function getTabs(root) {
    return Array.from(root.querySelectorAll(TAB_SELECTOR));
  }

  function getPanel(tab) {
    const id = tab.getAttribute("aria-controls");
    return id ? document.getElementById(id) : null;
  }

  function isDisabled(tab) {
    return tab.disabled || tab.getAttribute("aria-disabled") === "true";
  }

  function activate(root, tab, options = {}) {
    if (!tab || isDisabled(tab)) return;
    getTabs(root).forEach((item) => {
      const selected = item === tab;
      item.classList.toggle("is-active", selected);
      item.setAttribute("aria-selected", String(selected));
      item.tabIndex = selected ? 0 : -1;
      const panel = getPanel(item);
      if (panel) panel.hidden = !selected;
    });
    if (options.focus) tab.focus();
  }

  function move(root, current, direction) {
    const enabled = getTabs(root).filter((tab) => !isDisabled(tab));
    const index = enabled.indexOf(current);
    if (index < 0 || enabled.length === 0) return;
    const next = enabled[(index + direction + enabled.length) % enabled.length];
    activate(root, next, { focus: true });
  }

  function setup(root) {
    root.addEventListener("click", (event) => {
      const tab = event.target.closest(TAB_SELECTOR);
      if (!tab || !root.contains(tab)) return;
      activate(root, tab);
    });

    root.addEventListener("keydown", (event) => {
      const tab = event.target.closest(TAB_SELECTOR);
      if (!tab || !root.contains(tab)) return;
      if (event.key === "ArrowRight") {
        event.preventDefault();
        move(root, tab, 1);
      } else if (event.key === "ArrowLeft") {
        event.preventDefault();
        move(root, tab, -1);
      } else if (event.key === "Home") {
        event.preventDefault();
        const first = getTabs(root).find((item) => !isDisabled(item));
        activate(root, first, { focus: true });
      } else if (event.key === "End") {
        event.preventDefault();
        const enabled = getTabs(root).filter((item) => !isDisabled(item));
        activate(root, enabled[enabled.length - 1], { focus: true });
      }
    });
  }

  onReady(() => {
    document.querySelectorAll(ROOT_SELECTOR).forEach(setup);
  });
}());
