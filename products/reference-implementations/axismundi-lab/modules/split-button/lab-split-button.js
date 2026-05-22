/*
 * v3.6.13 - Split button lab runtime
 * Local status output only. Popover owns trailing menu open/close.
 */
(function () {
  "use strict";

  function writeStatus(root, text) {
    var status = root.querySelector("[data-split-button-status]");
    if (status) status.textContent = text;
  }

  function init(root) {
    var scope = root || document;
    scope.querySelectorAll("[data-split-button-demo]").forEach(function (demo) {
      demo.querySelectorAll("[data-split-primary]").forEach(function (button) {
        button.addEventListener("click", function () {
          writeStatus(demo, "Primary action: " + button.textContent.trim());
        });
      });
      demo.querySelectorAll("[role='menuitem']").forEach(function (item) {
        item.addEventListener("click", function () {
          writeStatus(demo, "Menu action: " + item.textContent.trim());
        });
      });
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    init(document);
  }, { once: true });

  window.labSplitButton = { init: init };
})();
