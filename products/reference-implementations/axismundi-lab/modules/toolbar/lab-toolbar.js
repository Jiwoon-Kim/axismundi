/*
 * v3.6.13 - Toolbar lab runtime
 * Lab-scoped aria-pressed toggle. Not styleguide/theme runtime.
 */
(function () {
  "use strict";

  function updateStatus(root, button, pressed) {
    var status = root.querySelector("[data-toolbar-status]");
    if (status) status.textContent = button.getAttribute("aria-label") + ": " + (pressed ? "on" : "off");
  }

  function init(root) {
    var scope = root || document;
    scope.querySelectorAll(".lab-toolbar-demo [role='toolbar']").forEach(function (toolbar) {
      var demo = toolbar.closest(".lab-toolbar-card") || scope;
      toolbar.querySelectorAll("[aria-pressed]").forEach(function (button) {
        button.addEventListener("click", function () {
          if (button.disabled || button.getAttribute("aria-disabled") === "true") return;
          var next = button.getAttribute("aria-pressed") !== "true";
          button.setAttribute("aria-pressed", String(next));
          button.classList.toggle("is-selected", next);
          updateStatus(demo, button, next);
        });
      });
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    init(document);
  }, { once: true });

  window.labToolbar = { init: init };
})();
