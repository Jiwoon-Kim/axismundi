/*
 * Axismundi - lab-dialog.js
 * v3.6.11 - Dialog #26 Interaction Runtime module
 *
 * Module-local runtime. Native <dialog>.showModal() owns modal semantics and
 * focus containment; this file owns trigger wiring, scrim sync, initial focus,
 * close paths, and focus restoration for the lab pattern page.
 */

(function () {
  "use strict";

  const ROOT_SELECTOR = "[data-dialog-demo]";
  const TRIGGER_SELECTOR = "[data-dialog-trigger]";
  const CLOSE_SELECTOR = "[data-dialog-close]";
  const SCRIM_SELECTOR = "[data-dialog-scrim]";
  const INITIAL_FOCUS_SELECTOR = "[data-dialog-initial-focus], button, [href], input, select, textarea, [tabindex]:not([tabindex='-1'])";

  let activeDialog = null;
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

  function focusInitial(dialog) {
    const target = dialog.querySelector(INITIAL_FOCUS_SELECTOR);
    if (target && typeof target.focus === "function") {
      target.focus({ preventScroll: true });
    }
  }

  function restoreFocus() {
    const target = activeTrigger;
    activeTrigger = null;
    if (target && typeof target.focus === "function") {
      target.focus({ preventScroll: true });
    }
  }

  function closeDialog(dialog) {
    const target = dialog || activeDialog;
    if (!target) return;
    const root = getRoot(target);
    if (target.open) target.close();
    setScrim(root, false);
    activeDialog = null;
    restoreFocus();
  }

  function openDialog(trigger) {
    const root = getRoot(trigger);
    const id = trigger.getAttribute("aria-controls");
    const dialog = id ? document.getElementById(id) : null;
    if (!root || !(dialog instanceof HTMLDialogElement)) return;

    if (activeDialog && activeDialog !== dialog) closeDialog(activeDialog);

    activeDialog = dialog;
    activeTrigger = trigger;
    setScrim(root, true);
    if (!dialog.open) dialog.showModal();
    requestAnimationFrame(() => focusInitial(dialog));
  }

  function setup(root) {
    root.querySelectorAll(TRIGGER_SELECTOR).forEach((trigger) => {
      trigger.addEventListener("click", () => openDialog(trigger));
    });

    root.querySelectorAll(CLOSE_SELECTOR).forEach((button) => {
      button.addEventListener("click", () => closeDialog(button.closest("dialog")));
    });

    const scrim = getScrim(root);
    if (scrim) {
      scrim.addEventListener("click", () => {
        if (activeDialog && getRoot(activeDialog) === root) closeDialog(activeDialog);
      });
    }

    root.querySelectorAll("dialog").forEach((dialog) => {
      dialog.addEventListener("click", (event) => {
        if (event.target === dialog && dialog.classList.contains("dialog--basic")) {
          closeDialog(dialog);
        }
      });
      dialog.addEventListener("cancel", (event) => {
        event.preventDefault();
        closeDialog(dialog);
      });
      dialog.addEventListener("close", () => {
        if (activeDialog === dialog) {
          setScrim(root, false);
          activeDialog = null;
          restoreFocus();
        }
      });
    });
  }

  function init(root) {
    const scope = root || document;
    scope.querySelectorAll(ROOT_SELECTOR).forEach(setup);
  }

  window.labDialog = { init, close: closeDialog };

  onReady(() => init());
}());
