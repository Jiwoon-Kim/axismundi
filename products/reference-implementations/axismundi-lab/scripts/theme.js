/* ============================================================
 * Axismundi theme.js — runtime behaviors for production pages
 * v0.1.0 — Phase 2B skeleton
 *
 * NOT the same as style-guide.js. style-guide.js boots the
 * component catalog (FAB menu, slider fill, palette swap).
 * theme.js boots BEHAVIORS USED BY ACTUAL PAGES — nav drawer,
 * toolbar toggles, TOC scroll-spy, heading anchor injection.
 *
 * Both files are pure vanilla JS. No framework. No build step.
 * Loaded via <script defer src="scripts/theme.js"></script>.
 * `defer` guarantees DOM is parsed before any code runs, so
 * DOMContentLoaded wrappers are unnecessary.
 *
 * Each section is an IIFE so failures in one don't block others.
 *
 * Sections:
 *   §1 Off-canvas nav drawer (App bar hamburger ↔ Sheet-side modal)
 *   §2 Submenu expand (accordion pattern for depth-2+ nav items)
 *   §3 Toolbar aria-pressed toggle (B/I/U/S formatting groups)
 *   §4 Heading anchor injection (markdown / classic editor output)
 *   §5 TOC scroll-spy (highlight current section in In-this-article)
 *   §6 Radiogroup keyboard nav (single-select chip filter pattern)
 *   §7 Slider — sync --_value for active-fill gradient
 *   §8 Theme switcher (light / dark / auto with localStorage persist)
 *
 * §5 is now implemented. §4 is implemented but optional —
 * server-side render usually injects anchors via PHP/render filter.
 * §1–§3 and §6 are required for Phase 2B static pages to function.
 * ============================================================ */


/* ============================================================
 * §1 Off-canvas nav drawer
 *
 * Pattern: App bar hamburger button toggles a <dialog> element
 * containing the nav rail's content. `<dialog>` provides native:
 *   - focus trap (ESC key + Tab cycle)
 *   - scrim (::backdrop pseudo-element)
 *   - scroll-lock on body (modal mode only)
 *
 * Markup contract:
 *   <button data-toggle-nav aria-controls="nav-drawer" aria-expanded="false">
 *   <dialog id="nav-drawer" class="ax-sheet ax-sheet--side-modal">
 *     <button data-close-modal>…</button>
 *     …nav content…
 *   </dialog>
 *
 * State:
 *   - aria-expanded on the trigger reflects open/closed
 *   - data-toggle-nav buttons swap between Menu / Menu Open icons
 *     via CSS (see app-bar component styling — both icons in
 *     markup, one hidden by aria-expanded state)
 *
 * Delegation: ALL [data-toggle-nav] and [data-close-modal] buttons
 * are wired via a single document-level click listener. Page
 * authors don't need to wire individual buttons.
 * ============================================================ */
(function () {
  'use strict';

  // --- Cached DOM lookups ----
  // Find the dialog by aria-controls on the first toggle button.
  // Multiple toggles can share one drawer (e.g. desktop + mobile
  // hamburger both opening the same drawer), but only one drawer
  // per page is supported in this skeleton — extend if needed.
  const firstToggle = document.querySelector('[data-toggle-nav]');
  if (!firstToggle) return;
  const drawerId = firstToggle.getAttribute('aria-controls');
  const drawer = drawerId ? document.getElementById(drawerId) : null;
  if (!drawer || drawer.tagName !== 'DIALOG') {
    console.warn('[theme.js §1] Nav drawer must be a <dialog> with id matching aria-controls.');
    return;
  }
  const allToggles = document.querySelectorAll('[data-toggle-nav]');

  // --- Open / close primitives ---
  function open() {
    if (drawer.open) return;
    drawer.showModal(); // native focus trap + scroll lock + scrim
    allToggles.forEach(btn => btn.setAttribute('aria-expanded', 'true'));
  }
  function close() {
    if (!drawer.open) return;
    drawer.close();
    allToggles.forEach(btn => btn.setAttribute('aria-expanded', 'false'));
    // Return focus to the toggle that was last clicked. Stored on
    // the dialog's data-last-toggle attribute when opening.
    const lastId = drawer.dataset.lastToggle;
    const target = lastId ? document.getElementById(lastId) : firstToggle;
    if (target) target.focus();
  }
  function toggle(triggerEl) {
    if (drawer.open) {
      close();
    } else {
      // Remember which trigger opened it for focus restoration
      if (triggerEl && triggerEl.id) drawer.dataset.lastToggle = triggerEl.id;
      open();
    }
  }

  // --- Document-level click delegation ---
  document.addEventListener('click', (e) => {
    const toggleBtn = e.target.closest('[data-toggle-nav]');
    if (toggleBtn) {
      e.preventDefault();
      toggle(toggleBtn);
      return;
    }
    const closeBtn = e.target.closest('[data-close-modal]');
    if (closeBtn && drawer.contains(closeBtn)) {
      e.preventDefault();
      close();
      return;
    }
  });

  // --- ESC closes (browser fires `cancel` event on <dialog>) ---
  drawer.addEventListener('cancel', (e) => {
    // Default behavior already closes; just sync aria-expanded.
    allToggles.forEach(btn => btn.setAttribute('aria-expanded', 'false'));
  });

  // --- Backdrop click closes (click on dialog itself, NOT its
  //     children — the dialog::backdrop area registers as the
  //     dialog element when the click is outside any child). ---
  drawer.addEventListener('click', (e) => {
    if (e.target === drawer) close();
  });

  // --- Viewport guard: if user resizes from mobile to desktop
  //     while drawer is open, close it (desktop layout doesn't
  //     show drawer; leaving it open creates phantom focus trap). ---
  const desktopMq = window.matchMedia('(min-width: 1024px)');
  desktopMq.addEventListener('change', (e) => {
    if (e.matches && drawer.open) close();
  });
})();


/* ============================================================
 * §2 Submenu expand — accordion pattern (depth 2+)
 *
 * Markup contract:
 *   <button class="ax-nav-list__expand"
 *           aria-expanded="false"
 *           aria-controls="submenu-id">
 *     Label <svg class="material-icons--chevron">…</svg>
 *   </button>
 *   <ul id="submenu-id" hidden>
 *     <li>…</li>
 *   </ul>
 *
 * Click the button → toggle aria-expanded + add/remove [hidden].
 * CSS rotates chevron via [aria-expanded="true"] selector.
 *
 * Phase 2B+ extensions:
 *   - Cascade push pattern (mobile: child level slides in,
 *     parent slides out, back button restores) — separate IIFE
 *   - Mega menu (desktop hover-open) — separate IIFE
 *
 * This skeleton handles ONLY the accordion (in-place expand)
 * variant. Depth-N support — works recursively because each
 * button toggles only its own aria-controls target.
 * ============================================================ */
(function () {
  'use strict';

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.ax-nav-list__expand');
    if (!btn) return;
    const targetId = btn.getAttribute('aria-controls');
    if (!targetId) return;
    const target = document.getElementById(targetId);
    if (!target) return;

    e.preventDefault();
    const isOpen = btn.getAttribute('aria-expanded') === 'true';
    btn.setAttribute('aria-expanded', String(!isOpen));
    if (isOpen) {
      target.setAttribute('hidden', '');
    } else {
      target.removeAttribute('hidden');
    }
  });
})();


/* ============================================================
 * §3 Toolbar — multi-toggle aria-pressed
 *
 * Markup contract:
 *   <div role="toolbar" aria-label="…">
 *     <button aria-pressed="true|false">…</button>
 *     …
 *   </div>
 *
 * Each button toggles its OWN aria-pressed independently.
 * Use case: text-formatting toolbars (B/I/U/S), filter pills
 * where multiple can be active, layout toggles.
 *
 * Also syncs `.is-selected` class on the same element — some
 * components (icon-button, button) use `.is-selected` as the
 * visual hook for selected styling. Keeping aria-pressed and
 * is-selected in sync prevents visual drift.
 *
 * For mutually-exclusive single-select, use the radio+label
 * pattern documented in components.css §28 instead — no JS
 * needed, native keyboard support.
 *
 * Replaces the inline demo in style-guide.html (which can be
 * removed once theme.js is loaded on that page too).
 * ============================================================ */
(function () {
  'use strict';

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[role="toolbar"] [aria-pressed]');
    if (!btn) return;
    const v = btn.getAttribute('aria-pressed') === 'true';
    const next = !v;
    btn.setAttribute('aria-pressed', String(next));
    btn.classList.toggle('is-selected', next);
  });
})();


/* ============================================================
 * §4 Heading anchor injection
 *
 * For markdown / classic editor output that doesn't carry
 * `.heading-anchor` markup. Walks h2–h4 inside `.prose`, gives
 * them an `id` derived from their text content, and appends a
 * `<a class="heading-anchor">#</a>` permalink.
 *
 * If WordPress server-side render filter already injects these
 * (Phase 3 PHP wiring), this section becomes redundant but
 * idempotent — won't double up because we check for existing id.
 *
 * Skipped on h1 (page title — not a section anchor) and h5/h6
 * (too deep to deserve a permalink in long-form).
 * ============================================================ */
(function () {
  'use strict';

  const slugify = (text) => text
    .trim()
    .toLowerCase()
    .replace(/[^\p{L}\p{N}\s-]/gu, '') // unicode-aware (Korean ok)
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-')
    .slice(0, 80);

  const usedIds = new Set();
  const uniqueId = (base) => {
    let id = base || 'section';
    let n = 2;
    while (document.getElementById(id) || usedIds.has(id)) {
      id = `${base}-${n++}`;
    }
    usedIds.add(id);
    return id;
  };

  document.querySelectorAll('.prose h2, .prose h3, .prose h4').forEach((h) => {
    // Skip if already has an anchor or empty text
    if (h.querySelector('.heading-anchor')) return;
    const text = h.textContent.trim();
    if (!text) return;

    if (!h.id) h.id = uniqueId(slugify(text));

    const a = document.createElement('a');
    a.className = 'heading-anchor';
    a.href = `#${h.id}`;
    a.setAttribute('aria-label', 'Permalink');
    a.textContent = '#';
    h.appendChild(a);
  });
})();


/* ============================================================
 * §5 TOC scroll-spy
 *
 * Tracks which heading is currently in view via
 * IntersectionObserver and adds `.is-current` to the matching
 * link in `.toc-list a[href="#section-id"]`.
 *
 * Scope: only active when `.toc-list` is present on the page.
 * Pages without a TOC (front-page, 404 etc.) early-return.
 *
 * Uses the link's href as source-of-truth for which headings
 * to observe — no additional class needed on headings, just
 * an `id` matching the TOC anchor target.
 *
 * `rootMargin: -20% 0 -70% 0` = active when heading is in the
 * top 20–30% of viewport (mirrors common doc-site behavior).
 * ============================================================ */
(function () {
  const tocLinks = document.querySelectorAll('.toc-list a');
  if (!tocLinks.length) return;

  const map = new Map();
  tocLinks.forEach((a) => {
    const id = a.getAttribute('href');
    if (id && id.startsWith('#')) map.set(id.slice(1), a);
  });

  const headings = Array.from(map.keys())
    .map((id) => document.getElementById(id))
    .filter(Boolean);
  if (!headings.length) return;

  const io = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        const link = map.get(entry.target.id);
        if (link) link.classList.toggle('is-current', entry.isIntersecting);
      });
    },
    { rootMargin: '-20% 0px -70% 0px' }
  );

  headings.forEach((h) => io.observe(h));
})();


/* ============================================================
 * §6 Radiogroup keyboard nav + state toggle
 *
 * Generic handler for `role="radiogroup"` containers with
 * `role="radio"` children (single-select chip filter pattern).
 *
 * Provides:
 *   - Click selects (exclusive aria-checked + .is-selected)
 *   - Arrow keys move focus + select (Left/Up = prev, Right/Down = next)
 *   - Home / End jump to first / last
 *   - Space / Enter — native button activation, click handler runs
 *
 * Per WAI-ARIA APG: arrow nav with "selection follows focus" pattern,
 * which keeps single-select state in sync with focus position.
 *
 * Markup contract:
 *   <div role="radiogroup" aria-label="...">
 *     <button role="radio" aria-checked="true">...</button>
 *     <button role="radio" aria-checked="false">...</button>
 *   </div>
 *
 * Application-level listeners (e.g. style-guide.js theme handler,
 * Phase 3 home.html category filter) attach `click` listeners on
 * individual radios. Arrow-key activation triggers `.click()` on
 * the target radio so app listeners auto-run.
 * ============================================================ */
(function () {
  const groups = document.querySelectorAll('[role="radiogroup"]');
  if (!groups.length) return;

  groups.forEach((group) => {
    const radios = Array.from(group.querySelectorAll('[role="radio"]'));
    if (!radios.length) return;

    /* Click handler — exclusive state update on the group. */
    radios.forEach((radio) => {
      radio.addEventListener('click', () => {
        radios.forEach((r) => {
          const active = r === radio;
          r.setAttribute('aria-checked', active ? 'true' : 'false');
          r.classList.toggle('is-selected', active);
        });
      });
    });

    /* Keyboard nav — delegate from group container. */
    group.addEventListener('keydown', (e) => {
      const idx = radios.indexOf(document.activeElement);
      if (idx === -1) return; /* focus not on any radio in this group */

      let target = null;
      switch (e.key) {
        case 'ArrowRight':
        case 'ArrowDown':
          target = radios[(idx + 1) % radios.length];
          break;
        case 'ArrowLeft':
        case 'ArrowUp':
          target = radios[(idx - 1 + radios.length) % radios.length];
          break;
        case 'Home':
          target = radios[0];
          break;
        case 'End':
          target = radios[radios.length - 1];
          break;
      }

      if (target) {
        e.preventDefault();
        target.focus();
        target.click(); /* triggers click handler + any app-level listeners */
      }
    });
  });
})();

/* ============================================================
 * §7 Slider — sync --_value for active-fill gradient
 *
 * .ax-slider__input::-webkit-slider-runnable-track uses a
 * linear-gradient(primary 0% var(--_value), secondary-container
 * var(--_value) 100%) to render the active vs inactive halves.
 * The CSS default --_value: 0% would render the entire track as
 * secondary-container (which can read as "no color" against a
 * light surface). This IIFE writes --_value as a CSS custom
 * property on each slider input on load + on every `input`
 * event, keeping the visual fill in sync with the value.
 *
 * Static markup can also set the initial value inline
 * (`style="--_value: 40%"`) so the fill renders correctly
 * before JS loads — this IIFE will overwrite it on first run.
 * ============================================================ */
(function () {
  var sliders = document.querySelectorAll(".ax-slider__input");
  if (!sliders.length) return;

  function update(input) {
    var min = parseFloat(input.min);
    var max = parseFloat(input.max);
    if (isNaN(min)) min = 0;
    if (isNaN(max)) max = 100;
    var val = parseFloat(input.value);
    if (isNaN(val)) val = min;
    var pct = max === min ? 0 : ((val - min) / (max - min)) * 100;
    input.style.setProperty("--_value", pct + "%");
  }

  sliders.forEach(function (input) {
    update(input);
    input.addEventListener("input", function () { update(input); });
    input.addEventListener("change", function () { update(input); });
  });
})();

/* ============================================================
 * §8 Theme switcher (light / dark / auto)
 *
 * Reads stored preference from localStorage(`ax-theme`) and
 * applies it via `[data-theme]` on <html> (or removes it for
 * `auto`, falling back to `prefers-color-scheme`).
 *
 * Markup contract — anywhere you want a theme switcher, drop
 * the radiogroup pattern (matches §6 keyboard nav).
 *
 * Canonical (style-guide.html + module pattern pages):
 *
 *   <div class="sg-theme" role="radiogroup" aria-label="Theme">
 *     <button data-theme-set="light" role="radio" aria-checked="false">Light</button>
 *     <button data-theme-set="dark"  role="radio" aria-checked="false">Dark</button>
 *     <button data-theme-set="auto"  role="radio" aria-checked="true">Auto</button>
 *   </div>
 *
 * Legacy (archive/axismundi-prototype only — `.ax-theme-switcher`):
 *
 *   <fieldset class="ax-theme-switcher" role="radiogroup"
 *             aria-label="Theme">…</fieldset>
 *
 * Both class names are accepted by `syncSwitchers()` at v3.4.5.1+
 * for backward compatibility with the archive.
 *
 * §6 (radiogroup keyboard nav) handles arrow-key + Home/End.
 * This IIFE adds: click → set theme, persist, sync aria-checked,
 * react to OS prefers-color-scheme changes when in `auto` mode.
 *
 * Why localStorage(`ax-theme`):
 *   - Survives reloads and per-page navigation in the static
 *     prototype.
 *   - Phase 3 PHP integration can replace with WP user meta
 *     for logged-in users (filter `ax_theme_preference`) while
 *     keeping localStorage for visitors.
 * ============================================================ */
(function () {
  var STORAGE_KEY = "ax-theme";
  var ROOT = document.documentElement;
  var hasProductionSwitcher = !!document.querySelector("[data-theme-set]");
  var hasStyleGuideSwitcher = !!document.querySelector("[data-theme-button]");

  /* style-guide.js owns the catalog theme switcher. Without this guard,
   * theme.js can run after style-guide.js and overwrite the selected
   * light/dark mode with the production storage key, making the sidebar
   * look like it has the opposite theme. */
  if (!hasProductionSwitcher && hasStyleGuideSwitcher) return;

  function getStored() {
    try {
      var v = localStorage.getItem(STORAGE_KEY);
      return v === "light" || v === "dark" || v === "auto" ? v : "auto";
    } catch (e) {
      return "auto"; /* localStorage blocked (e.g. private mode) */
    }
  }

  function setStored(v) {
    try { localStorage.setItem(STORAGE_KEY, v); } catch (e) {}
  }

  function apply(mode) {
    if (mode === "auto") {
      ROOT.removeAttribute("data-theme");
    } else {
      ROOT.setAttribute("data-theme", mode);
    }
  }

  function syncSwitchers(mode) {
    /* v3.4.5.1: selector accepts both the canonical .sg-theme used in
     * style-guide.html and the 5 module pattern HTMLs, and the legacy
     * .ax-theme-switcher used in archive/axismundi-prototype. Keeping
     * both maintains backward compatibility while making module-pattern
     * theme switchers sync correctly on reload. */
    var groups = document.querySelectorAll(".sg-theme, .ax-theme-switcher");
    groups.forEach(function (group) {
      var btns = group.querySelectorAll("[data-theme-set]");
      btns.forEach(function (btn) {
        var active = btn.getAttribute("data-theme-set") === mode;
        btn.setAttribute("aria-checked", active ? "true" : "false");
        btn.classList.toggle("is-selected", active);
      });
    });
  }

  /* Initialize: apply stored preference + sync any switchers
   * that already exist in the page. Runs at script defer time
   * (DOM ready). */
  var initial = getStored();
  apply(initial);
  syncSwitchers(initial);

  /* Click handler — single delegated listener on document */
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-theme-set]");
    if (!btn) return;
    var mode = btn.getAttribute("data-theme-set");
    if (mode !== "light" && mode !== "dark" && mode !== "auto") return;
    apply(mode);
    setStored(mode);
    syncSwitchers(mode);
  });

  /* Re-sync on storage event (cross-tab consistency) */
  window.addEventListener("storage", function (e) {
    if (e.key !== STORAGE_KEY) return;
    var mode = getStored();
    apply(mode);
    syncSwitchers(mode);
  });
})();
