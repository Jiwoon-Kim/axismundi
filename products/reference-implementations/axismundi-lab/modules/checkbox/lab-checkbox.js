/*
 * lab-checkbox.js - fixture-only setup for Checkbox indeterminate specimens.
 * This is not a component runtime or provider. Native checkbox behavior
 * remains browser-owned; this file only assigns HTMLInputElement.indeterminate
 * on demo inputs because HTML has no indeterminate attribute.
 */
(function () {
  "use strict";

  function setIndeterminate(root) {
    root.querySelectorAll("[data-checkbox-indeterminate]").forEach((input) => {
      if (input instanceof HTMLInputElement && input.type === "checkbox") {
        input.indeterminate = true;
        input.setAttribute("aria-checked", "mixed");
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => setIndeterminate(document), { once: true });
  } else {
    setIndeterminate(document);
  }

  window.labCheckbox = { init: setIndeterminate };
})();
