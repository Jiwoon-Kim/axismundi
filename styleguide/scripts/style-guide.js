/* ============================================================
 * Axismundi style guide — interactive scripts
 *
 * Extracted from inline <script> blocks in style-guide.html (Phase 1B).
 * Loaded via <script defer src="scripts/style-guide.js"></script>.
 *
 * Sections:
 *   1. FAB menu toggle (data-fab-menu-toggle)
 *   2. Text field counter (data-tf-counter)
 *   3. Checkbox indeterminate (sg-cb-indet element)
 *   4. Slider active-fill (--_value custom property)
 *   5. Main runtime: snackbar / dialog / sheet / theme / palette
 *
 * `defer` ensures DOM is parsed before this script runs, so DOMContentLoaded
 * wrappers are unnecessary. Each section is its own IIFE — original code
 * preserved verbatim except outer indentation.
 * ============================================================ */

/* ----- FAB menu toggle (data-fab-menu-toggle) ----- */
(function () {
            document.querySelectorAll('[data-fab-menu-toggle]').forEach(function (btn) {
              btn.addEventListener('click', function () {
                const menu = btn.closest('.ax-fab-menu');
                if (!menu) return;
                const isOpen = menu.classList.toggle('is-open');
                btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
              });
            });
          })();

/* ----- Text field counter (data-tf-counter) ----- */
(function () {
            document.querySelectorAll('.text-field').forEach(function (tf) {
              const input   = tf.querySelector('.text-field__input');
              const counter = tf.querySelector('[data-tf-counter]');
              if (!input || !counter) return;
              const max = input.getAttribute('maxlength');
              function update() {
                counter.textContent = (input.value.length) + ' / ' + (max || '∞');
              }
              update();
              input.addEventListener('input', update);
            });
          })();

/* ----- Checkbox indeterminate (sg-cb-indet) ----- */
(function () {
  const indeterminateCheckbox = document.getElementById('sg-cb-indet');
  if (indeterminateCheckbox) {
    indeterminateCheckbox.indeterminate = true;
  }
})();

/* ----- Slider active-fill (--_value custom property) ----- */
(function () {
            const sliders = document.querySelectorAll('.ax-slider__input');
            function update(input) {
              const min = Number(input.min) || 0;
              const max = Number(input.max) || 100;
              const val = Number(input.value);
              const pct = ((val - min) / (max - min)) * 100;
              input.style.setProperty('--_value', pct + '%');
            }
            sliders.forEach(function (input) {
              update(input);
              input.addEventListener('input', function () { update(input); });
            });
          })();

/* ----- Styleguide-local drawer nav clone ----- */
(function () {
  const guideSlot = document.querySelector('[data-drawer-guide-nav]');
  const sectionSlot = document.querySelector('[data-drawer-section-nav]');
  const desktopGuide = document.querySelector('.sg-sidebar .sg-guide-nav');
  const desktopSection = document.querySelector('.sg-sidebar .sg-nav');
  const drawer = document.getElementById('sg-drawer');

  if (guideSlot && desktopGuide) {
    guideSlot.replaceChildren(desktopGuide.cloneNode(true));
  }
  if (sectionSlot && desktopSection) {
    sectionSlot.replaceChildren(desktopSection.cloneNode(true));
  }

  if (!drawer) return;

  drawer.addEventListener('click', (event) => {
    const anchor = event.target.closest('a[href^="#"]');
    if (!anchor || !drawer.contains(anchor)) return;
    drawer.close();
    document.querySelectorAll('[data-toggle-nav]').forEach((button) => {
      button.setAttribute('aria-expanded', 'false');
    });
  });
})();

/* ----- Main runtime: snackbar / dialog / sheet / theme switcher / palette painter ----- */
/* ---------------- Theme switcher ---------------- */
    (function () {
      const KEY = "axismundi.theme";
      const html = document.documentElement;
      const buttons = document.querySelectorAll("[data-theme-button]");

      function apply(mode) {
        html.setAttribute("data-theme", mode);
        buttons.forEach((b) => {
          const active = b.dataset.themeButton === mode;
          /* 3-marker pattern (per components.css §27.5):
           * - aria-checked: true ARIA radio state (radiogroup pattern)
           * - aria-pressed: legacy toggle marker (kept for backward compat
           *   if external tooling reads it)
           * - .is-selected: CSS-only marker (works without JS)
           */
          b.setAttribute("aria-checked", active ? "true" : "false");
          b.setAttribute("aria-pressed", active ? "true" : "false");
          b.classList.toggle("is-selected", active);
        });
      }

      const saved = localStorage.getItem(KEY) || "auto";
      apply(saved);

      buttons.forEach((b) => {
        b.addEventListener("click", () => {
          const mode = b.dataset.themeButton;
          localStorage.setItem(KEY, mode);
          apply(mode);
        });
      });
    })();

    /* ---------------- Generic toggle (.sg-toggle) ----------------
     * Click flips aria-pressed so toggle variants demo selected state. */
    document.querySelectorAll(".sg-toggle").forEach((el) => {
      el.addEventListener("click", () => {
        const next = el.getAttribute("aria-pressed") === "true" ? "false" : "true";
        el.setAttribute("aria-pressed", next);
        el.classList.toggle("is-selected", next === "true");
      });
    });

    /* ---------------- Tab group (group-exclusive) ---------------- */
    document.querySelectorAll(".sg-tab-group").forEach((group) => {
      const tabs = group.querySelectorAll(".tabs__tab");
      tabs.forEach((tab) => {
        tab.addEventListener("click", () => {
          tabs.forEach((t) => t.classList.remove("is-active"));
          tab.classList.add("is-active");
        });
      });
    });

    /* ---------------- Nav rail item (group-exclusive) ---------------- */
    (function () {
      const rails = document.querySelectorAll(".nav-rail");
      rails.forEach((rail) => {
        const items = rail.querySelectorAll(".nav-rail__item");
        items.forEach((item) => {
          item.addEventListener("click", () => {
            items.forEach((i) => i.classList.remove("is-active"));
            item.classList.add("is-active");
          });
        });
      });
    })();

    /* ---------------- Live modals (dialog + sheet) ---------------- */
    (function () {
      const portal = document.getElementById("sg-portal");
      if (!portal) return;
      const scrim = portal.querySelector("[data-portal-scrim]");
      const modals = {
        "dialog:basic": document.getElementById("sg-modal-basic"),
        "dialog:full":  document.getElementById("sg-modal-full"),
        "sheet:bottom": document.getElementById("sg-sheet-bottom"),
        "sheet:side":   document.getElementById("sg-sheet-side"),
      };
      let active = null;

      function setScrim(open) {
        if (scrim) {
          scrim.dataset.open = open ? "true" : "false";
        }
      }

      function open(key) {
        const el = modals[key];
        if (!el) return;
        close(); // close any other
        active = el;
        setScrim(true);
        document.documentElement.classList.add("has-modal-open");
        if (el instanceof HTMLDialogElement) {
          if (!el.open) {
            el.showModal();
          }
        } else {
          el.classList.add("is-open");
        }
      }
      function close() {
        document.documentElement.classList.remove("has-modal-open");
        if (!active) {
          setScrim(false);
          return;
        }
        if (active instanceof HTMLDialogElement) {
          if (active.open) {
            active.close();
          }
        } else {
          active.classList.remove("is-open");
        }
        active = null;
        setScrim(false);
      }

      document.querySelectorAll("[data-open-dialog]").forEach((btn) => {
        btn.addEventListener("click", () => open("dialog:" + btn.dataset.openDialog));
      });
      document.querySelectorAll("[data-open-sheet]").forEach((btn) => {
        btn.addEventListener("click", () => open("sheet:" + btn.dataset.openSheet));
      });
      portal.querySelectorAll("[data-close-modal]").forEach((btn) => {
        btn.addEventListener("click", close);
      });
      if (scrim) {
        scrim.addEventListener("click", close);
      }
      Object.values(modals).forEach((el) => {
        if (!(el instanceof HTMLDialogElement)) return;
        el.addEventListener("click", (event) => {
          if (event.target === el && el.classList.contains("dialog--basic")) {
            close();
          }
        });
        el.addEventListener("cancel", () => {
          active = null;
          setScrim(false);
          document.documentElement.classList.remove("has-modal-open");
        });
        el.addEventListener("close", () => {
          if (active === el) {
            active = null;
            setScrim(false);
            document.documentElement.classList.remove("has-modal-open");
          }
        });
      });
      document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") close();
      });
    })();

    /* ---------------- Swatch + type rendering ---------------- */
    (function () {
      // Group definitions — token name only. Hex resolved at runtime.
      const groups = {
        brand: [
          ["primary",                "on-primary"],
          ["primary-container",      "on-primary-container"],
          ["secondary",              "on-secondary"],
          ["secondary-container",    "on-secondary-container"],
          ["tertiary",               "on-tertiary"],
          ["tertiary-container",     "on-tertiary-container"],
        ],
        status: [
          ["error",           "on-error"],
          ["error-container", "on-error-container"],
        ],
        surface: [
          ["background",                "on-background"],
          ["surface",                   "on-surface"],
          ["surface-variant",           "on-surface-variant"],
          ["surface-bright",            null],
          ["surface-dim",               null],
          ["surface-container-lowest",  null],
          ["surface-container-low",     null],
          ["surface-container",         null],
          ["surface-container-high",    null],
          ["surface-container-highest", null],
        ],
        inverse: [
          ["inverse-surface", "inverse-on-surface"],
          ["inverse-primary", null],
        ],
        other: [
          ["outline",         null],
          ["outline-variant", null],
          ["shadow",          null],
          ["scrim",           null],
        ],
      };

      function hex(name, scope) {
        const v = getComputedStyle(scope || document.documentElement)
          .getPropertyValue("--md-sys-color-" + name)
          .trim();
        return v.toUpperCase();
      }

      function swatchEl(name, onName, scope) {
        const wrap = document.createElement("div");
        wrap.className = "sg-swatch";

        const chip = document.createElement("div");
        chip.className = "sg-swatch__chip";
        chip.style.backgroundColor = "var(--md-sys-color-" + name + ")";
        if (onName) {
          chip.style.color = "var(--md-sys-color-" + onName + ")";
          chip.textContent = "Aa 가";
          wrap.classList.add("sg-swatch--pair");
        }

        const nm = document.createElement("div");
        nm.className = "t-label-medium sg-swatch__name";
        nm.textContent = "--md-sys-color-" + name;

        const hx = document.createElement("div");
        hx.className = "t-label-small sg-swatch__hex";
        hx.textContent = hex(name, scope);

        wrap.appendChild(chip);
        wrap.appendChild(nm);
        wrap.appendChild(hx);
        return wrap;
      }

      function paint(target, list, scope) {
        const grid = document.getElementById(target);
        if (!grid) return;
        grid.innerHTML = "";
        list.forEach(([n, on]) => grid.appendChild(swatchEl(n, on, scope)));
      }

      function paintAll() {
        paint("swatches-brand",   groups.brand);
        paint("swatches-status",  groups.status);
        paint("swatches-surface", groups.surface);
        paint("swatches-inverse", groups.inverse);
        paint("swatches-other",   groups.other);

        // Pair grid — uses current root theme (light/dark/auto)
        const pairList = [
          ["primary",            "on-primary"],
          ["secondary",          "on-secondary"],
          ["tertiary",           "on-tertiary"],
          ["error",              "on-error"],
          ["surface",            "on-surface"],
          ["surface-container",  null],
          ["inverse-surface",    "inverse-on-surface"],
          ["outline",            null],
        ];
        paint("swatches-pair-light", pairList);
      }
      paintAll();

      // Re-paint when theme switches (for hex display under root)
      const observer = new MutationObserver(paintAll);
      observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ["data-theme"],
      });

      /* ---------------- Type specimen ---------------- */
      const roles = [
        ["display-large",   "57 / 64 / 400 / brand"],
        ["display-medium",  "45 / 52 / 400 / brand"],
        ["display-small",   "36 / 44 / 400 / brand"],
        ["headline-large",  "32 / 40 / 400 / brand"],
        ["headline-medium", "28 / 36 / 400 / brand"],
        ["headline-small",  "24 / 32 / 400 / brand"],
        ["title-large",     "22 / 28 / 400 / brand"],
        ["title-medium",    "16 / 24 / 500 / plain"],
        ["title-small",     "14 / 20 / 500 / plain"],
        ["body-large",      "16 / 24 / 400 / plain"],
        ["body-medium",     "14 / 20 / 400 / plain"],
        ["body-small",      "12 / 16 / 400 / plain"],
        ["label-large",     "14 / 20 / 500 / plain"],
        ["label-medium",    "12 / 16 / 500 / plain"],
        ["label-small",     "11 / 16 / 500 / plain"],
      ];
      const root = document.getElementById("type-specimen");
      if (root) {
        roles.forEach(([role, spec]) => {
          const row = document.createElement("div");
          row.className = "sg-type-row";

          const meta = document.createElement("div");
          meta.className = "sg-type-row__meta";
          const r = document.createElement("div");
          r.className = "t-label-large sg-type-row__role";
          r.textContent = "." + "t-" + role;
          const s = document.createElement("div");
          s.className = "t-label-small sg-type-row__spec";
          s.textContent = spec;
          meta.appendChild(r);
          meta.appendChild(s);

          const sample = document.createElement("div");
          sample.className = "sg-type-row__sample t-" + role;
          const en = document.createElement("div");
          en.textContent = "The quick brown fox jumps over the lazy dog";
          const ko = document.createElement("div");
          ko.textContent = "다람쥐 헌 쳇바퀴에 타고파";
          sample.appendChild(en);
          sample.appendChild(ko);

          row.appendChild(meta);
          row.appendChild(sample);
          root.appendChild(row);
        });
      }
    })();
