/**
 * Card proprietăți: număr fix de puncte (segmente pe galerie); prev/next în buclă.
 * Delegare evenimente + MutationObserver pentru grid injectat prin AJAX.
 */
(function () {
  "use strict";

  /** Mereu același număr de puncte pe card, indiferent câte imagini sunt. */
  var FIXED_DOT_COUNT = 12;
  var stateMap = new WeakMap();

  function parseSlides(root) {
    try {
      var raw = root.getAttribute("data-lh-slide-urls");
      if (!raw) return [];
      var u = JSON.parse(raw);
      return Array.isArray(u) ? u.filter(Boolean) : [];
    } catch (e) {
      return [];
    }
  }

  /** Punct activ [0 .. FIXED_DOT_COUNT-1] pentru index slide și n imagini. */
  function slideIndexToDot(i, n) {
    if (n < 2) return 0;
    return Math.round((i * (FIXED_DOT_COUNT - 1)) / (n - 1));
  }

  /** Salt la slide-ul din segmentul punctului d. */
  function dotIndexToSlideIndex(d, n) {
    if (n < 2) return 0;
    var t = Math.round((d * (n - 1)) / (FIXED_DOT_COUNT - 1));
    if (t < 0) return 0;
    if (t >= n) return n - 1;
    return t;
  }

  function getState(root, slides) {
    var st = stateMap.get(root);
    if (st) return st;
    st = { slides: slides, index: 0 };
    stateMap.set(root, st);
    return st;
  }

  function buildToolbar(root) {
    var bar = document.createElement("div");
    /* px-5 = același inset orizontal ca zona p-5 cu butonul „Vezi proprietatea” */
    bar.className =
      "lh-property-card-toolbar pointer-events-auto absolute inset-x-0 bottom-0 z-[15] flex items-center gap-3 px-5 pb-5 pt-10 bg-gradient-to-t from-black/55 via-black/25 to-transparent";

    var dots = document.createElement("div");
    dots.className =
      "lh-property-card-dots grid min-w-0 flex-1 w-full items-center justify-items-center gap-3";
    dots.setAttribute("role", "tablist");

    var nav = document.createElement("div");
    nav.className = "flex shrink-0 items-center gap-3";
    nav.innerHTML =
      '<button type="button" class="lh-property-card-prev flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-white/40 bg-white/95 text-ink shadow-md shadow-black/15 transition hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white" aria-label="' + (typeof lhT === "function" ? lhT("slider.prev") : "Previous") + '">' +
      '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>' +
      '<button type="button" class="lh-property-card-next flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-white/40 bg-white/95 text-ink shadow-md shadow-black/15 transition hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white" aria-label="' + (typeof lhT === "function" ? lhT("slider.next") : "Next") + '">' +
      '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></button>';

    bar.appendChild(dots);
    bar.appendChild(nav);
    root.appendChild(bar);

    return { bar: bar, dots: dots };
  }

  function renderDots(dotsContainer, st) {
    var n = st.slides.length;
    var activeDot = slideIndexToDot(st.index, n);

    dotsContainer.innerHTML = "";
    for (var d = 0; d < FIXED_DOT_COUNT; d++) {
      var dot = document.createElement("button");
      dot.type = "button";
      var isActive = d === activeDot;
      dot.className =
        "lh-property-card-dot h-2 w-2 shrink-0 rounded-full transition-[background-color,box-shadow] duration-200 " +
        (isActive
          ? "bg-white/75 ring-2 ring-white/90 shadow-[0_2px_12px_rgb(var(--color-cta)_/_0.45)]"
          : "bg-white/15 ring-1 ring-cta/45 shadow-[0_2px_12px_rgb(var(--color-cta)_/_0.55),0_0_0_3px_rgb(var(--color-cta)_/_0.28)] hover:shadow-[0_2px_14px_rgb(var(--color-cta)_/_0.65),0_0_0_3px_rgb(var(--color-cta)_/_0.38)]");
      dot.setAttribute("data-dot-index", String(d));
      dot.setAttribute("role", "tab");
      dot.setAttribute(
        "aria-label",
        typeof lhT === "function"
          ? lhT("slider.dot_aria", {
              segment: d + 1,
              segments: FIXED_DOT_COUNT,
              images: n,
            })
          : "Segment " + (d + 1) + " / " + FIXED_DOT_COUNT,
      );
      dot.setAttribute("aria-selected", isActive ? "true" : "false");
      dotsContainer.appendChild(dot);
    }
    dotsContainer.style.gridTemplateColumns =
      "repeat(" + FIXED_DOT_COUNT + ", minmax(0, 1fr))";
  }

  function updateView(root, ui) {
    var st = stateMap.get(root);
    if (!st) return;
    var img = root.querySelector(".lh-property-card-slide-img");
    if (img && st.slides[st.index] !== undefined) {
      img.src = st.slides[st.index];
    }
    renderDots(ui.dots, st);
  }

  function go(root, delta, ui) {
    var st = stateMap.get(root);
    if (!st || st.slides.length < 2) return;
    var len = st.slides.length;
    st.index = (st.index + delta + len) % len;
    updateView(root, ui);
  }

  function goIndex(root, idx, ui) {
    var st = stateMap.get(root);
    if (!st || st.slides.length < 2) return;
    if (idx < 0 || idx >= st.slides.length) return;
    st.index = idx;
    updateView(root, ui);
  }

  function initCard(root) {
    if (root.getAttribute("data-lh-slider-init")) return;
    var slides = parseSlides(root);
    if (slides.length < 2) return;

    root.setAttribute("data-lh-slider-init", "1");
    getState(root, slides);
    var ui = buildToolbar(root);

    updateView(root, ui);

    root._lhPropertySliderUi = ui;
  }

  function findMediaRoot(node) {
    return node && node.closest
      ? node.closest(".lh-property-card-media")
      : null;
  }

  document.addEventListener("click", function (e) {
    var prevBtn = e.target.closest(".lh-property-card-prev");
    var nextBtn = e.target.closest(".lh-property-card-next");
    var dotBtn = e.target.closest(".lh-property-card-dot");
    if (!prevBtn && !nextBtn && !dotBtn) return;

    var root = findMediaRoot(prevBtn || nextBtn || dotBtn);
    if (!root || !root.getAttribute("data-lh-slider-init")) return;

    var ui = root._lhPropertySliderUi;
    if (!ui) return;

    e.preventDefault();
    e.stopPropagation();

    if (prevBtn) go(root, -1, ui);
    else if (nextBtn) go(root, 1, ui);
    else if (dotBtn) {
      var di = dotBtn.getAttribute("data-dot-index");
      if (di === null) return;
      var stDot = stateMap.get(root);
      if (!stDot || !stDot.slides.length) return;
      var slideIdx = dotIndexToSlideIndex(parseInt(di, 10), stDot.slides.length);
      goIndex(root, slideIdx, ui);
    }
  });

  var touch = { x: 0, root: null };
  document.addEventListener(
    "touchstart",
    function (e) {
      var r = findMediaRoot(e.target);
      if (!r || !r.getAttribute("data-lh-slider-init")) return;
      touch.root = r;
      touch.x = e.changedTouches[0].clientX;
    },
    { passive: true },
  );
  document.addEventListener(
    "touchend",
    function (e) {
      if (!touch.root) return;
      var r = touch.root;
      touch.root = null;
      var endRoot = findMediaRoot(e.target);
      if (endRoot !== r) return;
      var ui = r._lhPropertySliderUi;
      if (!ui) return;
      var dx = e.changedTouches[0].clientX - touch.x;
      if (Math.abs(dx) < 40) return;
      go(r, dx < 0 ? 1 : -1, ui);
    },
    { passive: true },
  );

  function scan() {
    document.querySelectorAll(".lh-property-card-media").forEach(initCard);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", scan);
  } else {
    scan();
  }

  var moTimer = null;
  function debouncedScan() {
    if (moTimer) clearTimeout(moTimer);
    moTimer = setTimeout(function () {
      moTimer = null;
      scan();
    }, 50);
  }

  var obs = new MutationObserver(debouncedScan);
  obs.observe(document.body, { childList: true, subtree: true });
})();
