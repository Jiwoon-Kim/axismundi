/*
 * v3.6.14 - Slider lab runtime
 * Lab-scoped value sync for authored --_value examples. Not a provider.
 */
(function () {
  "use strict";

  function percentage(input) {
    var min = Number(input.min || 0);
    var max = Number(input.max || 100);
    var value = Number(input.value || min);
    if (max <= min) return 0;
    return ((value - min) / (max - min)) * 100;
  }

  function sync(input) {
    var field = input.closest(".lab-slider-field") || input.parentElement;
    var output = field ? field.querySelector("[data-slider-output]") : null;
    var value = input.value;
    input.style.setProperty("--_value", percentage(input).toFixed(2) + "%");
    if (output) output.textContent = value;
  }

  function init(root) {
    var scope = root || document;
    scope.querySelectorAll(".lab-slider-demo .ax-slider__input").forEach(function (input) {
      sync(input);
      input.addEventListener("input", function () {
        sync(input);
      });
      input.addEventListener("change", function () {
        sync(input);
      });
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    init(document);
  }, { once: true });

  window.labSlider = { init: init };
})();
