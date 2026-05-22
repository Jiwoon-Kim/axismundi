/*
 * Axismundi - lab-sheet.js
 * v3.6.11 - Sheet #27 Interaction Runtime module
 *
 * Module-local runtime. Sheet uses custom aside hosts with role="dialog" and
 * aria-modal="true"; this file owns open state, scrim sync, focus containment,
 * Escape / scrim / close-button dismissal, and focus restoration.
 */

(function () {
  "use strict";

  const ROOT_SELECTOR = "[data-sheet-demo]";
  const TRIGGER_SELECTOR = "[data-sheet-trigger]";
  const CLOSE_SELECTOR = "[data-sheet-close]";
  const SCRIM_SELECTOR = "[data-sheet-scrim]";
  const FOCUSABLE_SELECTOR = [
    "a[href]",
    "button:not([disabled])",
    "input:not([disabled])",
    "select:not([disabled])",
    "textarea:not([disabled])",
    "[tabindex]:not([tabindex='-1'])"
  ].join(",");

  let activeSheet = null;
  let activeTrigger = null;

  function onReady(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn, { once: true });
    } else {
      fn();
    }
  }

  function getRoot(el) {
    return el.closest(ROOT_SELECTOR);
  }

  function getScrim(root) {
    return root ? root.querySelector(SCRIM_SELECTOR) : null;
  }

  function setScrim(root, open) {
    const scrim = getScrim(root);
    if (!scrim) return;
    scrim.dataset.open = open ? "true" : "false";
    scrim.classList.toggle("is-open", open);
  }

  function getFocusable(sheet) {
    return Array.from(sheet.querySelectorAll(FOCUSABLE_SELECTOR))
      .filter((item) => item.offsetParent !== null || item === document.activeElement);
  }

  function focusFirst(sheet) {
    const focusable = getFocusable(sheet);
    const target = sheet.querySelector("[data-sheet-initial-focus]") || focusable[0] || sheet;
    if (!sheet.hasAttribute("tabindex")) sheet.tabIndex = -1;
    target.focus({ preventScroll: true });
  }

  function trapFocus(event) {
    if (!activeSheet || event.key !== "Tab") return;
    const focusable = getFocusable(activeSheet);
    if (focusable.length === 0) {
      event.preventDefault();
      activeSheet.focus({ preventScroll: true });
      return;
    }
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus({ preventScroll: true });
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus({ preventScroll: true });
    }
  }

  function restoreFocus() {
    const target = activeTrigger;
    activeTrigger = null;
    if (target && typeof target.focus === "function") {
      target.focus({ preventScroll: true });
    }
  }

  function closeSheet(sheet) {
    const target = sheet || activeSheet;
    if (!target) return;
    const root = getRoot(target);
    target.classList.remove("is-open");
    target.setAttribute("aria-hidden", "true");
    setScrim(root, false);
    if (activeSheet === target) activeSheet = null;
    restoreFocus();
  }

  function openSheet(trigger) {
    const root = getRoot(trigger);
    const id = trigger.getAttribute("aria-controls");
    const sheet = id ? document.getElementById(id) : null;
    if (!root || !sheet) return;

    if (activeSheet && activeSheet !== sheet) closeSheet(activeSheet);

    activeSheet = sheet;
    activeTrigger = trigger;
    sheet.classList.add("is-open");
    sheet.setAttribute("aria-hidden", "false");
    setScrim(root, true);
    requestAnimationFrame(() => focusFirst(sheet));
  }

  function setup(root) {
    root.querySelectorAll(TRIGGER_SELECTOR).forEach((trigger) => {
      trigger.addEventListener("click", () => openSheet(trigger));
    });

    root.querySelectorAll(CLOSE_SELECTOR).forEach((button) => {
      button.addEventListener("click", () => closeSheet(button.closest(".sheet")));
    });

    const scrim = getScrim(root);
    if (scrim) {
      scrim.addEventListener("click", () => {
        if (activeSheet && getRoot(activeSheet) === root) closeSheet(activeSheet);
      });
    }

    root.querySelectorAll(".sheet").forEach((sheet) => {
      sheet.setAttribute("aria-hidden", sheet.classList.contains("is-open") ? "false" : "true");
      sheet.addEventListener("keydown", (event) => {
        if (event.key === "Escape" || event.key === "Esc") {
          event.preventDefault();
          closeSheet(sheet);
        } else {
          trapFocus(event);
        }
      });
    });
  }

  function init(root) {
    const scope = root || document;
    scope.querySelectorAll(ROOT_SELECTOR).forEach(setup);
  }

  window.labSheet = { init, close: closeSheet };

  onReady(() => init());
}());
