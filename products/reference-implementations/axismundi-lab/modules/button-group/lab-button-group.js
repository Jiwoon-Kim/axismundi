/*
 * Axismundi — lab-button-group.js
 * v3.5.10 — Button group #6 Pattern B demo controller
 *
 * Local catalog script only. Pattern A radio groups stay native and are not
 * touched. No public runtime API is exposed.
 */

(function () {
  "use strict";

  const GROUP_SELECTOR = "[data-button-group-toggle-demo]";
  const BUTTON_SELECTOR = "button[aria-pressed]";

  function onReady(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn, { once: true });
    } else {
      fn();
    }
  }

  function isDisabled(button) {
    return (
      button.disabled ||
      button.getAttribute("aria-disabled") === "true" ||
      button.hasAttribute("disabled")
    );
  }

  function toggle(button) {
    const next = button.getAttribute("aria-pressed") !== "true";
    button.setAttribute("aria-pressed", String(next));
  }

  function setupGroup(group) {
    group.addEventListener("click", (event) => {
      const button = event.target.closest(BUTTON_SELECTOR);
      if (!button || !group.contains(button) || isDisabled(button)) return;
      toggle(button);
    });
  }

  onReady(() => {
    document.querySelectorAll(GROUP_SELECTOR).forEach(setupGroup);
  });
}());
