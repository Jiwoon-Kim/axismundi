/* ============================================================
 * lab-date-time.js — Axismundi Date/Time Picker lab module
 * v3.4.7
 *
 * Bucket D — theme interaction runtime, lab module only.
 * Scope: lab/modules/date-time/lab-date-time-pattern.html only.
 *
 * Provenance:
 *   This module is NOT a Beer-CSS-derived extraction.
 *   The visual primitives are already Axismundi-native baseline
 *   components (components.css §33 Date picker + §34 Time picker).
 *   v3.4.7 extracts and audits the GPT Codex-generated benchmark
 *   interaction layer into a bounded lab module per
 *   docs/DATE-TIME-AUDIT.md.
 *
 *   The previous five lab modules (carousel v3.3.2, ripple v3.3.3,
 *   search-expansion v3.3.4, popover v3.4.5, tooltip v3.4.6) were
 *   extracted under docs/BEER-CSS-INTAKE.md contract. The Beer CSS
 *   interaction-module family closed at v3.4.6 tooltip.
 *
 *   This module is NOT covered by docs/BEER-CSS-INTAKE.md.
 *
 * Carry-over policy (audit doc §4):
 *   v3.4.7 preserves the benchmark's existing partial accessibility
 *   level exactly. The WAI-ARIA Date Picker grid navigation pattern
 *   (role="grid"/"gridcell", ArrowKey nav, Home/End, PageUp/PageDown,
 *   aria-current="date", roving tabindex, month/year announcement) is
 *   NOT wired here. See BACKLOG #19 for the deferred a11y phase.
 *
 * Minimum safety fixes vs. benchmark originals (v3.4.7):
 *   1. Module-scoped IIFE — no global helpers leaked.
 *   2. Forbidden-ancestor bail-out — pickers inside .prose,
 *      .wp-block-post-content, .entry-content, or [contenteditable]
 *      are skipped at init time. (Charter §5, expanded selector list
 *      per Phase 2 decision.)
 *   3. Public init API — window.labDateTime.init() for explicit
 *      bootstrapping inside the pattern page (lab-internal only).
 *   4. EXTRACTED markers in benchmark-interactions.{js,css}
 *      (Charter EXTRACTED policy — originals retained verbatim).
 *
 * Out of scope at v3.4.7 (per audit doc §5):
 *   - WAI-ARIA Date Picker full keyboard navigation pattern (BACKLOG #19)
 *   - Time picker WAI-ARIA refinements
 *   - Timezone normalization
 *   - Locale calendar systems (lunar / Hijri / Korean Sexagenary)
 *   - Recurring event date logic
 *   - WordPress editor sidebar date control binding
 *   - Post meta date binding
 *   - Range selection persistence beyond UI preview
 *   - Mobile full-screen picker variant
 *   - Date picker baseline promotion (separate Charter §1 decision)
 * ============================================================ */
(function () {
  "use strict";

  // ---------- Shared helpers (mirrored from benchmark-interactions.js) ----------
  const qs = (selector, root = document) => root.querySelector(selector);
  const qsa = (selector, root = document) => Array.from(root.querySelectorAll(selector));

  function onReady(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn, { once: true });
    } else {
      fn();
    }
  }

  function isDisabled(el) {
    return Boolean(
      el.closest("[disabled], [aria-disabled='true']") ||
      el.matches(":disabled, [aria-disabled='true']")
    );
  }

  // ---------- Forbidden-ancestor bail-out (Charter §5, expanded) ----------
  // Pickers inside any of these surfaces are skipped at init time.
  // This list is broader than the popover/tooltip list because date pickers
  // may live in WordPress block editor content surfaces that need explicit
  // isolation per v3.4.7 Phase 2 decision.
  const FORBIDDEN_ANCESTOR_SELECTOR =
    '.prose, .wp-block-post-content, .entry-content, ' +
    '[contenteditable=""], [contenteditable="true"]';

  function isInForbiddenAncestor(element) {
    if (!element || !element.closest) return false;
    return !!element.closest(FORBIDDEN_ANCESTOR_SELECTOR);
  }

  // ---------- Extracted: Date Picker benchmark interaction ----------
  // Verbatim from benchmark-interactions.js L921-L1283 (363 lines).
  // Closure structure intentionally preserved — see audit doc §6
  // "Extraction strategy" decision 1.
  //
  // Single Phase-2 modification vs. original: the roots query is
  // filtered through isInForbiddenAncestor() before per-root setup.

  function enableDateBenchmarks() {
    const roots = qsa("[data-date-benchmark]")
      .filter((r) => !isInForbiddenAncestor(r));
    if (!roots.length) return;

    const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    const shortMonths = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    const weekdays = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
    const demoToday = new Date(2025, 7, 5);

    const pad = (value) => String(value).padStart(2, "0");
    const makeDate = (year, month, day) => new Date(year, month, day);
    const cloneDate = (date) => makeDate(date.getFullYear(), date.getMonth(), date.getDate());
    const addDays = (date, days) => makeDate(date.getFullYear(), date.getMonth(), date.getDate() + days);
    const sameDay = (a, b) => !!a && !!b && a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
    const toKey = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    const formatInputDate = (date) => `${pad(date.getMonth() + 1)}/${pad(date.getDate())}/${date.getFullYear()}`;
    const formatShortDate = (date) => `${shortMonths[date.getMonth()]} ${date.getDate()}`;
    const formatHeadlineDate = (date) => `${date.toLocaleDateString("en-US", { weekday: "short" })}, ${formatShortDate(date)}`;

    function parseInputDate(value) {
      const match = String(value).trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
      if (!match) return null;
      const month = Number(match[1]) - 1;
      const day = Number(match[2]);
      const year = Number(match[3]);
      const parsed = makeDate(year, month, day);
      if (parsed.getFullYear() !== year || parsed.getMonth() !== month || parsed.getDate() !== day) return null;
      return parsed;
    }

    roots.forEach((root) => {
      const surface = qs("[data-date-surface]", root);
      const headline = qs("[data-date-headline]", root);
      const dockedInput = qs("[data-date-docked-input]", root);
      const grid = qs("[data-date-grid]", root);
      const monthList = qs("[data-date-month-list]", root);
      const yearGrid = qs("[data-date-year-grid]", root);
      const input = qs("[data-date-input]", root);
      const endInput = qs("[data-date-end-input]", root);
      const endField = qs("[data-date-end-field]", root);
      const inputField = input?.closest(".text-field");
      const inputSupporting = qs("[data-date-input-supporting]", root);
      const modeButtons = qsa("[data-date-mode]", root);
      const views = qsa("[data-date-view]", root);
      if (!surface || !headline || !dockedInput || !grid || !monthList || !yearGrid || !input) return;

      const committed = {
        selected: makeDate(2025, 7, 17),
        rangeStart: makeDate(2025, 7, 17),
        rangeEnd: makeDate(2025, 7, 23),
      };
      const state = {
        mode: "single",
        view: "calendar",
        viewYear: 2025,
        viewMonth: 7,
        selected: cloneDate(committed.selected),
        rangeStart: cloneDate(committed.rangeStart),
        rangeEnd: cloneDate(committed.rangeEnd),
      };

      function updateModeControls() {
        modeButtons.forEach((button) => {
          const selected = button.dataset.dateMode === state.mode;
          button.classList.toggle("is-selected", selected);
          button.setAttribute("aria-pressed", selected ? "true" : "false");
        });
        if (endField) endField.hidden = state.mode !== "range";
      }

      function selectedForView() {
        return state.mode === "range" ? state.rangeStart : state.selected;
      }

      function updateText() {
        if (state.mode === "range") {
          const start = state.rangeStart;
          const end = state.rangeEnd;
          headline.textContent = end ? `${formatShortDate(start)} - ${formatShortDate(end)}` : `${formatShortDate(start)} -`;
          dockedInput.value = end ? `${formatInputDate(start)} - ${formatInputDate(end)}` : formatInputDate(start);
          input.value = formatInputDate(start);
          if (endInput) endInput.value = end ? formatInputDate(end) : "";
        } else {
          headline.textContent = formatHeadlineDate(state.selected);
          dockedInput.value = formatInputDate(state.selected);
          input.value = formatInputDate(state.selected);
        }
      }

      function updateSwitchLabels() {
        qsa("[data-date-view-button='month']", root).forEach((button) => {
          button.textContent = `${monthNames[state.viewMonth]} ${state.viewYear}`;
        });
        qsa("[data-date-view-button='calendar']", root).forEach((button) => {
          button.textContent = button.closest("[data-date-view='year']") ? `${monthNames[state.viewMonth]} ${state.viewYear}` : monthNames[state.viewMonth];
        });
        qsa(".ax-date-benchmark__year-button", root).forEach((button) => {
          button.textContent = String(state.viewYear);
        });
      }

      function setView(view) {
        state.view = view;
        views.forEach((panel) => {
          panel.hidden = panel.dataset.dateView !== view;
        });
        if (view === "input") {
          clearInputError();
          input.focus();
        }
      }

      function dateInRange(date, start, end) {
        if (!start || !end) return false;
        const time = date.getTime();
        return time >= start.getTime() && time <= end.getTime();
      }

      function renderCalendar() {
        const first = makeDate(state.viewYear, state.viewMonth, 1);
        const start = addDays(first, -first.getDay());
        const preferred = selectedForView();
        grid.innerHTML = "";

        for (let index = 0; index < 42; index += 1) {
          const date = addDays(start, index);
          const button = document.createElement("button");
          const isOutside = date.getMonth() !== state.viewMonth;
          const isToday = sameDay(date, demoToday);
          const isSingleSelected = state.mode === "single" && sameDay(date, state.selected);
          const isRangeStart = state.mode === "range" && sameDay(date, state.rangeStart);
          const isRangeEnd = state.mode === "range" && sameDay(date, state.rangeEnd);
          const inRange = state.mode === "range" && dateInRange(date, state.rangeStart, state.rangeEnd);

          button.type = "button";
          button.className = "ax-date-picker__cell has-state-layer";
          button.textContent = String(date.getDate());
          button.dataset.date = toKey(date);
          button.setAttribute("role", "gridcell");
          button.setAttribute("aria-label", `${weekdays[date.getDay()]}, ${monthNames[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`);
          button.setAttribute("aria-selected", isSingleSelected || isRangeStart || isRangeEnd ? "true" : "false");
          button.tabIndex = sameDay(date, preferred) || (!preferred && !isOutside && date.getDate() === 1) ? 0 : -1;

          button.classList.toggle("is-outside", isOutside);
          button.classList.toggle("is-today", isToday);
          button.classList.toggle("is-selected", isSingleSelected || (state.mode === "range" && (isRangeStart || isRangeEnd)));
          button.classList.toggle("is-in-range", inRange);
          button.classList.toggle("is-range-start", isRangeStart);
          button.classList.toggle("is-range-end", isRangeEnd);
          button.classList.toggle("is-range-preview", inRange && !isRangeStart && !isRangeEnd);

          button.addEventListener("click", () => {
            chooseDate(date);
          });
          grid.append(button);
        }
      }

      function renderMonths() {
        monthList.innerHTML = "";
        monthNames.forEach((name, month) => {
          const button = document.createElement("button");
          button.type = "button";
          button.className = "ax-date-benchmark__list-item";
          button.classList.toggle("is-selected", month === state.viewMonth);
          button.innerHTML = `<span class="ax-date-benchmark__check">${month === state.viewMonth ? "✓" : ""}</span><span>${name}</span>`;
          button.addEventListener("click", () => {
            state.viewMonth = month;
            setView("calendar");
            render();
          });
          monthList.append(button);
        });
      }

      function renderYears() {
        const selected = selectedForView();
        const startYear = Math.max(2021, state.viewYear - 4);
        yearGrid.innerHTML = "";
        for (let year = startYear; year < startYear + 15; year += 1) {
          const button = document.createElement("button");
          button.type = "button";
          button.className = "ax-date-benchmark__year";
          button.textContent = String(year);
          button.classList.toggle("is-selected", selected && year === selected.getFullYear());
          button.addEventListener("click", () => {
            state.viewYear = year;
            setView("calendar");
            render();
          });
          yearGrid.append(button);
        }
      }

      function render() {
        updateModeControls();
        updateSwitchLabels();
        updateText();
        renderCalendar();
        renderMonths();
        renderYears();
      }

      function chooseDate(date) {
        const next = cloneDate(date);
        state.viewYear = next.getFullYear();
        state.viewMonth = next.getMonth();
        if (state.mode === "single") {
          state.selected = next;
        } else if (!state.rangeStart || state.rangeEnd || next < state.rangeStart) {
          state.rangeStart = next;
          state.rangeEnd = null;
        } else {
          state.rangeEnd = next;
        }
        render();
      }

      function copyCommittedToState() {
        state.selected = cloneDate(committed.selected);
        state.rangeStart = cloneDate(committed.rangeStart);
        state.rangeEnd = committed.rangeEnd ? cloneDate(committed.rangeEnd) : null;
        const active = selectedForView();
        state.viewYear = active.getFullYear();
        state.viewMonth = active.getMonth();
      }

      function commitState() {
        committed.selected = cloneDate(state.selected);
        committed.rangeStart = cloneDate(state.rangeStart);
        committed.rangeEnd = state.rangeEnd ? cloneDate(state.rangeEnd) : null;
      }

      function openPicker(view = "calendar") {
        copyCommittedToState();
        surface.hidden = false;
        setView(view);
        render();
        window.requestAnimationFrame(() => {
          const target = view === "calendar" ? qs("[data-date-grid] [tabindex='0']", root) : qs(`[data-date-view='${view}'] button, [data-date-view='${view}'] input`, root);
          target?.focus();
        });
      }

      function closePicker() {
        surface.hidden = true;
        clearInputError();
      }

      function clearInputError() {
        inputField?.classList.remove("is-error");
        endInput?.closest(".text-field")?.classList.remove("is-error");
        if (inputSupporting) inputSupporting.textContent = "MM/DD/YYYY";
      }

      function showInputError(message, target = input) {
        target.closest(".text-field")?.classList.add("is-error");
        if (inputSupporting) inputSupporting.textContent = message;
        target.focus();
      }

      function applyInputValues() {
        clearInputError();
        const start = parseInputDate(input.value);
        if (!start) {
          showInputError("Use MM/DD/YYYY", input);
          return false;
        }
        if (state.mode === "range") {
          const end = parseInputDate(endInput?.value || "");
          if (!end) {
            showInputError("Use MM/DD/YYYY", endInput || input);
            return false;
          }
          if (end < start) {
            showInputError("End date must be later", endInput || input);
            return false;
          }
          state.rangeStart = start;
          state.rangeEnd = end;
        } else {
          state.selected = start;
        }
        state.viewYear = start.getFullYear();
        state.viewMonth = start.getMonth();
        return true;
      }

      qsa("[data-date-open]", root).forEach((button) => {
        button.addEventListener("click", () => {
          openPicker(button.dataset.dateOpen === "input" ? "input" : "calendar");
        });
      });

      modeButtons.forEach((button) => {
        button.addEventListener("click", () => {
          state.mode = button.dataset.dateMode || "single";
          updateModeControls();
          updateText();
          if (!surface.hidden) render();
        });
      });

      qsa("[data-date-view-button]", root).forEach((button) => {
        button.addEventListener("click", () => {
          setView(button.dataset.dateViewButton || "calendar");
          render();
        });
      });

      qs("[data-date-prev]", root)?.addEventListener("click", () => {
        state.viewMonth -= 1;
        if (state.viewMonth < 0) {
          state.viewMonth = 11;
          state.viewYear -= 1;
        }
        render();
      });

      qs("[data-date-next]", root)?.addEventListener("click", () => {
        state.viewMonth += 1;
        if (state.viewMonth > 11) {
          state.viewMonth = 0;
          state.viewYear += 1;
        }
        render();
      });

      qs("[data-date-cancel]", root)?.addEventListener("click", () => {
        copyCommittedToState();
        render();
        closePicker();
      });

      qs("[data-date-ok]", root)?.addEventListener("click", () => {
        if (state.view === "input" && !applyInputValues()) return;
        commitState();
        render();
        closePicker();
      });

      root.addEventListener("keydown", (event) => {
        if (surface.hidden) return;
        if (event.key === "Escape") {
          event.preventDefault();
          closePicker();
          return;
        }
        const focused = document.activeElement;
        if (!focused?.matches("[data-date-grid] .ax-date-picker__cell")) return;
        const offsets = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
        if (!(event.key in offsets)) return;
        event.preventDefault();
        const cells = qsa("[data-date-grid] .ax-date-picker__cell", root);
        const index = cells.indexOf(focused);
        const next = cells[Math.max(0, Math.min(cells.length - 1, index + offsets[event.key]))];
        next?.focus();
      });

      render();
    });
  }


  // ---------- Extracted: Time Picker benchmark interaction ----------
  // Verbatim from benchmark-interactions.js L1284-L1604 (321 lines).
  // Closure structure intentionally preserved.
  // Single Phase-2 modification vs. original: forbidden-ancestor filter
  // on the roots query.

  function enableTimeBenchmarks() {
    const roots = qsa("[data-time-benchmark]")
      .filter((r) => !isInForbiddenAncestor(r));
    if (!roots.length) return;

    const pad = (value) => String(value).padStart(2, "0");
    const clampNumber = (value, min, max) => Math.max(min, Math.min(max, value));

    roots.forEach((root) => {
      const surface = qs("[data-time-surface]", root);
      const panel = qs(".ax-time-benchmark__panel", root);
      const dockedInput = qs("[data-time-docked-input]", root);
      const dial = qs("[data-time-dial]", root);
      const input = qs("[data-time-input]", root);
      const inputField = qs("[data-time-input-field]", root);
      const inputSupporting = qs("[data-time-input-supporting]", root);
      const formatButtons = qsa("[data-time-format]", root);
      const partButtons = qsa("[data-time-part]", root);
      const periodButtons = qsa("[data-time-period-value]", root);
      const views = qsa("[data-time-view]", root);
      if (!surface || !panel || !dockedInput || !dial || !input) return;

      const committed = { hour: 7, minute: 0, period: "AM", format: "12" };
      const state = {
        hour: committed.hour,
        minute: committed.minute,
        period: committed.period,
        format: committed.format,
        activePart: "hour",
        view: "dial",
      };

      function displayHour24() {
        if (state.format === "24") return state.hour;
        const hour = state.hour % 12;
        return hour === 0 ? 12 : hour;
      }

      function displayHourText() {
        return pad(displayHour24());
      }

      function displayTimeText() {
        if (state.format === "24") return `${pad(state.hour)}:${pad(state.minute)}`;
        return `${displayHourText()}:${pad(state.minute)} ${state.period}`;
      }

      function syncCommittedToState() {
        state.hour = committed.hour;
        state.minute = committed.minute;
        state.period = committed.period;
        state.format = committed.format;
      }

      function commitState() {
        committed.hour = state.hour;
        committed.minute = state.minute;
        committed.period = state.period;
        committed.format = state.format;
      }

      function setView(view) {
        state.view = view;
        views.forEach((panelView) => {
          panelView.hidden = panelView.dataset.timeView !== view;
        });
        if (view === "input") {
          clearInputError();
          input.value = state.format === "24" ? `${pad(state.hour)}:${pad(state.minute)}` : `${displayHourText()}:${pad(state.minute)}`;
          input.focus();
        }
      }

      function setActivePart(part) {
        state.activePart = part;
        partButtons.forEach((button) => {
          const active = button.dataset.timePart === part;
          button.classList.toggle("is-selected", active);
          button.classList.toggle("is-active", active);
          button.setAttribute("aria-pressed", active ? "true" : "false");
        });
        renderDial();
      }

      function updateFormatControls() {
        formatButtons.forEach((button) => {
          const selected = button.dataset.timeFormat === state.format;
          button.classList.toggle("is-selected", selected);
          button.setAttribute("aria-pressed", selected ? "true" : "false");
        });
        panel.classList.toggle("is-24h", state.format === "24");
        panel.classList.toggle("is-wide", state.view === "dial");
      }

      function updatePeriodControls() {
        periodButtons.forEach((button) => {
          const selected = button.dataset.timePeriodValue === state.period;
          button.classList.toggle("is-selected", selected);
          button.setAttribute("aria-pressed", selected ? "true" : "false");
        });
      }

      function updateText() {
        qs("[data-time-part='hour']", root).textContent = displayHourText();
        qs("[data-time-part='minute']", root).textContent = pad(state.minute);
        dockedInput.value = displayTimeText();
      }

      function dialValue() {
        return state.activePart === "minute" ? state.minute : displayHour24();
      }

      function renderDial() {
        dial.innerHTML = "";
        const value = dialValue();
        const isMinute = state.activePart === "minute";
        const radius = 104;
        const innerRadius = 74;
        const values = isMinute
          ? Array.from({ length: 12 }, (_, index) => index * 5)
          : Array.from({ length: 12 }, (_, index) => index + 1);

        const selectedAngle = isMinute ? value / 60 * 360 : (displayHour24() % 12) / 12 * 360;
        const hand = document.createElement("span");
        hand.className = "ax-time-benchmark__hand";
        hand.style.transform = `rotate(${selectedAngle + 180}deg)`;
        dial.append(hand);

        values.forEach((item) => {
          const angle = isMinute ? item / 60 * 360 : item / 12 * 360;
          const x = Math.sin(angle * Math.PI / 180) * radius;
          const y = -Math.cos(angle * Math.PI / 180) * radius;
          const button = document.createElement("button");
          button.type = "button";
          button.className = "ax-time-benchmark__dial-option has-state-layer";
          button.textContent = isMinute ? pad(item) : String(item);
          button.style.transform = `translate(${x}px, ${y}px)`;
          button.setAttribute("role", "option");
          button.setAttribute("aria-selected", item === value ? "true" : "false");
          button.classList.toggle("is-selected", item === value);
          button.addEventListener("click", () => chooseDialValue(item));
          dial.append(button);
        });

        if (!isMinute && state.format === "24") {
          Array.from({ length: 12 }, (_, index) => index + 12).forEach((item) => {
            const label = item === 24 ? 0 : item;
            const angle = (label % 12) / 12 * 360;
            const x = Math.sin(angle * Math.PI / 180) * innerRadius;
            const y = -Math.cos(angle * Math.PI / 180) * innerRadius;
            const button = document.createElement("button");
            button.type = "button";
            button.className = "ax-time-benchmark__dial-option is-inner has-state-layer";
            button.textContent = pad(label);
            button.style.transform = `translate(${x}px, ${y}px)`;
            button.setAttribute("role", "option");
            button.setAttribute("aria-selected", state.hour === label ? "true" : "false");
            button.classList.toggle("is-selected", state.hour === label);
            button.addEventListener("click", () => chooseDialValue(label));
            dial.append(button);
          });
        }
      }

      function chooseDialValue(value) {
        if (state.activePart === "minute") {
          state.minute = value;
        } else if (state.format === "24") {
          state.hour = value;
        } else {
          const normalized = value === 12 ? 0 : value;
          state.hour = state.period === "PM" ? normalized + 12 : normalized;
        }
        updateText();
        renderDial();
      }

      function setFormat(format) {
        state.format = format;
        if (format === "12") {
          state.period = state.hour >= 12 ? "PM" : "AM";
        }
        render();
      }

      function setPeriod(period) {
        if (state.period === period) return;
        state.period = period;
        if (period === "PM" && state.hour < 12) state.hour += 12;
        if (period === "AM" && state.hour >= 12) state.hour -= 12;
        render();
      }

      function clearInputError() {
        inputField?.classList.remove("is-error");
        if (inputSupporting) inputSupporting.textContent = "HH:MM";
      }

      function showInputError(message) {
        inputField?.classList.add("is-error");
        if (inputSupporting) inputSupporting.textContent = message;
        input.focus();
      }

      function applyInputValue() {
        clearInputError();
        const match = input.value.trim().match(/^(\d{1,2}):(\d{2})$/);
        if (!match) {
          showInputError("Use HH:MM");
          return false;
        }
        const hour = Number(match[1]);
        const minute = Number(match[2]);
        if (minute > 59) {
          showInputError("Minute must be 00-59");
          return false;
        }
        if (state.format === "24") {
          if (hour > 23) {
            showInputError("Hour must be 00-23");
            return false;
          }
          state.hour = hour;
        } else {
          if (hour < 1 || hour > 12) {
            showInputError("Hour must be 01-12");
            return false;
          }
          const normalized = hour === 12 ? 0 : hour;
          state.hour = state.period === "PM" ? normalized + 12 : normalized;
        }
        state.minute = minute;
        return true;
      }

      function openPicker(view = "dial") {
        syncCommittedToState();
        surface.hidden = false;
        setView(view);
        render();
      }

      function closePicker() {
        surface.hidden = true;
        clearInputError();
      }

      function render() {
        updateFormatControls();
        updatePeriodControls();
        updateText();
        renderDial();
      }

      qsa("[data-time-open]", root).forEach((button) => {
        button.addEventListener("click", () => {
          const nextView = button.dataset.timeOpen === "input" ? "input" : "dial";
          if (surface.hidden) {
            openPicker(nextView);
            return;
          }
          setView(nextView);
          render();
        });
      });

      formatButtons.forEach((button) => {
        button.addEventListener("click", () => {
          setFormat(button.dataset.timeFormat || "12");
        });
      });

      partButtons.forEach((button) => {
        button.addEventListener("click", () => {
          setActivePart(button.dataset.timePart || "hour");
        });
      });

      periodButtons.forEach((button) => {
        button.addEventListener("click", () => {
          setPeriod(button.dataset.timePeriodValue || "AM");
        });
      });

      qs("[data-time-toggle-view]", root)?.addEventListener("click", () => {
        setView(state.view === "dial" ? "input" : "dial");
        render();
      });

      qs("[data-time-cancel]", root)?.addEventListener("click", () => {
        syncCommittedToState();
        render();
        closePicker();
      });

      qs("[data-time-ok]", root)?.addEventListener("click", () => {
        if (state.view === "input" && !applyInputValue()) return;
        commitState();
        render();
        closePicker();
      });

      input.addEventListener("input", () => {
        input.value = input.value.replace(/[^\d:]/g, "").slice(0, 5);
      });

      input.addEventListener("keydown", (event) => {
        if (event.key !== "Enter") return;
        event.preventDefault();
        qs("[data-time-ok]", root)?.click();
      });

      root.addEventListener("keydown", (event) => {
        if (surface.hidden || event.key !== "Escape") return;
        event.preventDefault();
        closePicker();
      });

      render();
    });
  }


  // ---------- Public API + bootstrap ----------
  function init() {
    enableDateBenchmarks();
    enableTimeBenchmarks();
  }

  window.labDateTime = {
    init,
    // Read-only state inspectors (for audit / debugging) — minimal surface.
    get hasDatePickers() { return qsa("[data-date-benchmark]").length > 0; },
    get hasTimePickers() { return qsa("[data-time-benchmark]").length > 0; },
  };

  onReady(init);
})();
