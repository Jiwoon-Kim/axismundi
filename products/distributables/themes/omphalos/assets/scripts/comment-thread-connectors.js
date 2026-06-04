(() => {
  const TEMPLATE_SELECTOR = ".wp-block-comment-template";
  const ITEM_SELECTOR = ":scope > .omph-comment-item";
  const AVATAR_SELECTOR = ":scope > .wp-block-avatar";
  const SVG_NS = "http://www.w3.org/2000/svg";
  let activeReplyTargetId = "";

  const makeSvg = (width, height) => {
    const svg = document.createElementNS(SVG_NS, "svg");
    svg.setAttribute("class", "omph-comment-connectors");
    svg.setAttribute("aria-hidden", "true");
    svg.setAttribute("focusable", "false");
    svg.setAttribute("width", String(width));
    svg.setAttribute("height", String(height));
    svg.setAttribute("viewBox", `0 0 ${width} ${height}`);
    return svg;
  };

  const ensureId = (element, prefix) => {
    if (!element.id) {
      element.id = `${prefix}-${Math.random().toString(36).slice(2, 10)}`;
    }
    return element.id;
  };

  const makePath = (d, targetId) => {
    const path = document.createElementNS(SVG_NS, "path");
    path.setAttribute("d", d);
    path.setAttribute("class", "omph-comment-connector");
    path.dataset.connectorTarget = targetId;
    return path;
  };

  const roundedConnector = (railX, startY, childX, childY) => {
    const direction = childX >= railX ? 1 : -1;
    const radius = Math.min(10, Math.abs(childX - railX) / 2, Math.abs(childY - startY) / 2);
    const curveStartY = childY - radius;
    const curveEndX = railX + (radius * direction);

    if (radius <= 0) {
      return `M ${railX} ${startY} V ${childY} H ${childX}`;
    }

    return [
      `M ${railX} ${startY}`,
      `V ${curveStartY}`,
      `Q ${railX} ${childY} ${curveEndX} ${childY}`,
      `H ${childX}`,
    ].join(" ");
  };

  const directChildren = (ol) => Array.from(ol.children).filter((child) => child.tagName === "LI");

  const avatarFor = (li) => li.querySelector(ITEM_SELECTOR)?.querySelector(AVATAR_SELECTOR);

  const renderTemplate = (template) => {
    const previous = template.querySelector(":scope > .omph-comment-connectors");
    if (previous) {
      previous.remove();
    }

    const templateRect = template.getBoundingClientRect();
    const width = Math.max(template.scrollWidth, Math.ceil(templateRect.width));
    const height = Math.max(template.scrollHeight, Math.ceil(templateRect.height));
    const svg = makeSvg(width, height);
    let pathCount = 0;

    for (const list of template.querySelectorAll("ol")) {
      const parentLi = list.parentElement?.closest("li");
      const parentAvatar = parentLi ? avatarFor(parentLi) : null;
      if (!parentAvatar) {
        continue;
      }

      const parentRect = parentAvatar.getBoundingClientRect();
      const railX = Math.round(parentRect.left - templateRect.left + (parentRect.width / 2));
      const startY = Math.round(parentRect.bottom - templateRect.top);

      for (const childLi of directChildren(list)) {
        const childAvatar = avatarFor(childLi);
        if (!childAvatar) {
          continue;
        }

        const childRect = childAvatar.getBoundingClientRect();
        const childX = Math.round(childRect.left - templateRect.left);
        const childY = Math.round(childRect.top - templateRect.top + (childRect.height / 2));

        if (childY <= startY) {
          continue;
        }

        const item = childLi.querySelector(ITEM_SELECTOR);
        const targetId = item ? ensureId(item, "omph-comment-item") : "";
        svg.appendChild(makePath(roundedConnector(railX, startY, childX, childY), targetId));
        pathCount += 1;
      }
    }

    if (pathCount > 0) {
      template.classList.add("has-omph-comment-connectors");
      template.prepend(svg);
    } else {
      template.classList.remove("has-omph-comment-connectors");
    }
  };

  const escapeSelectorValue = (value) => {
    if (window.CSS?.escape) {
      return CSS.escape(value);
    }

    return value.replace(/["\\]/g, "\\$&");
  };

  const connectorPathsFor = (targetId) => document.querySelectorAll(
    `.omph-comment-connector[data-connector-target="${escapeSelectorValue(targetId)}"]`
  );

  const closestCommentItem = (target) => {
    if (!(target instanceof Element)) {
      return null;
    }

    return target.closest(".omph-comment-item");
  };

  const chainItemsFor = (item) => {
    const items = [];
    let currentLi = item.closest("li");

    while (currentLi) {
      const currentItem = currentLi.querySelector(ITEM_SELECTOR);
      if (currentItem?.id) {
        items.unshift(currentItem);
      }

      const parentList = currentLi.parentElement?.closest("ol");
      currentLi = parentList?.parentElement?.closest("li") || null;
    }

    return items;
  };

  const activateReplyChain = (item) => {
    chainItemsFor(item).forEach((chainItem) => {
      connectorPathsFor(chainItem.id).forEach((path) => {
        path.classList.add("is-reply-active");
        path.parentElement?.appendChild(path);
      });
    });
  };

  const renderAll = () => {
    document.querySelectorAll(TEMPLATE_SELECTOR).forEach(renderTemplate);

    if (activeReplyTargetId) {
      const item = document.getElementById(activeReplyTargetId);
      if (item) {
        activateReplyChain(item);
      }
    }
  };

  const clearReplyState = () => {
    activeReplyTargetId = "";
    document
      .querySelectorAll(".omph-comment-connector.is-reply-active")
      .forEach((path) => path.classList.remove("is-reply-active"));
    document
      .querySelectorAll(".omph-comment-item.is-reply-target")
      .forEach((item) => item.classList.remove("is-reply-target"));
    document
      .querySelectorAll(".wp-block-comment-reply-link a[aria-pressed='true']")
      .forEach((link) => link.setAttribute("aria-pressed", "false"));
  };

  const setReplyState = (item, replyLink) => {
    clearReplyState();
    activeReplyTargetId = item.id;
    item.classList.add("is-reply-target");
    replyLink.setAttribute("aria-pressed", "true");
    activateReplyChain(item);
  };

  const disableCoreReplyMove = () => {
    document.addEventListener("click", (event) => {
      const replyLink = event.target.closest(".wp-block-comment-reply-link a");
      if (!replyLink) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();

      const item = closestCommentItem(replyLink);
      if (!item) {
        return;
      }

      if (item.classList.contains("is-reply-target")) {
        clearReplyState();
        return;
      }

      setReplyState(item, replyLink);
    }, true);
  };

  const scheduleRender = () => {
    let frame = 0;
    return () => {
      if (frame) {
        cancelAnimationFrame(frame);
      }
      frame = requestAnimationFrame(() => {
        frame = 0;
        renderAll();
      });
    };
  };

  const init = () => {
    if (!document.querySelector(TEMPLATE_SELECTOR)) {
      return;
    }

    const rerender = scheduleRender();
    disableCoreReplyMove();
    rerender();

    window.addEventListener("resize", rerender, { passive: true });
    window.addEventListener("load", rerender, { once: true });

    if ("ResizeObserver" in window) {
      const observer = new ResizeObserver(rerender);
      document.querySelectorAll(TEMPLATE_SELECTOR).forEach((template) => observer.observe(template));
    }
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
  } else {
    init();
  }
})();
