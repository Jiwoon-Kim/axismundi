/*
 * v3.6.13 - FAB menu lab runtime
 * Component-local runtime for validation specimens. Not a provider.
 */
(function () {
  "use strict";

  function setOpen(menu, open) {
    var trigger = menu.querySelector("[data-fab-menu-toggle]");
    var list = trigger ? document.getElementById(trigger.getAttribute("aria-controls")) : null;
    menu.classList.toggle("is-open", open);
    if (trigger) trigger.setAttribute("aria-expanded", String(open));
    if (list) list.setAttribute("aria-hidden", String(!open));
    if (open) {
      var first = menu.querySelector(".ax-fab-menu__item-button:not(:disabled):not([aria-disabled='true'])");
      if (first) first.focus();
    }
  }

  function activateItem(root, button) {
    var status = root.querySelector("[data-fab-menu-status]");
    if (status) status.textContent = "Activated: " + button.textContent.trim();
  }

  function init(root) {
    var scope = root || document;
    scope.querySelectorAll("[data-fab-menu-demo]").forEach(function (menu) {
      var trigger = menu.querySelector("[data-fab-menu-toggle]");
      if (!trigger) return;
      trigger.addEventListener("click", function () {
        setOpen(menu, trigger.getAttribute("aria-expanded") !== "true");
      });
      menu.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
          setOpen(menu, false);
          trigger.focus();
        }
      });
      menu.querySelectorAll(".ax-fab-menu__item-button").forEach(function (button) {
        button.addEventListener("click", function () {
          if (button.disabled || button.getAttribute("aria-disabled") === "true") return;
          activateItem(menu.closest(".lab-fab-menu-card") || scope, button);
          setOpen(menu, false);
          trigger.focus();
        });
      });
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    init(document);
  }, { once: true });

  window.labFabMenu = { init: init };
})();
