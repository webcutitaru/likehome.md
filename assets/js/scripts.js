/**
 * assets/js/scripts.js
 * Search bar logic: Flatpickr, AJAX, buton dinamic, redirect.
 * Homepage: rezultatele înlocuiesc gridul #home-properties-grid; catalog: #results-container.
 */

(function () {
  "use strict";

  /** Ambele Y-m-d; true dacă intervalul e invalid (aceeași zi sau invers). */
  function lhSearchDatesInvalidRange(checkIn, checkOut) {
    if (!checkIn || !checkOut) return false;
    return checkOut <= checkIn;
  }

  /** blocked_dates: [from, to) half-open — același overlap ca backend. */
  function lhStayOverlapsBlockedRange(checkInYmd, checkOutYmd, from, to) {
    return from < checkOutYmd && to > checkInYmd;
  }

  function lhStayOverlapsAnyBlockedRange(ranges, checkInYmd, checkOutYmd) {
    if (!ranges || !ranges.length) return false;
    for (let i = 0; i < ranges.length; i++) {
      const r = ranges[i];
      const from = r && r.from;
      const to = r && r.to;
      if (
        from &&
        to &&
        lhStayOverlapsBlockedRange(checkInYmd, checkOutYmd, from, to)
      ) {
        return true;
      }
    }
    return false;
  }

  function lhYmdIsBlockedCheckIn(ranges, ymd) {
    if (!ranges || !ranges.length) return false;
    for (let j = 0; j < ranges.length; j++) {
      const b = ranges[j];
      const f = b && b.from;
      const t = b && b.to;
      if (f && t && f <= ymd && ymd < t) return true;
    }
    return false;
  }

  function lhNightsBetweenYmd(checkInYmd, checkOutYmd) {
    const a = String(checkInYmd).split("-");
    const b = String(checkOutYmd).split("-");
    if (a.length !== 3 || b.length !== 3) return 0;
    const d0 = new Date(
      parseInt(a[0], 10),
      parseInt(a[1], 10) - 1,
      parseInt(a[2], 10),
    );
    const d1 = new Date(
      parseInt(b[0], 10),
      parseInt(b[1], 10) - 1,
      parseInt(b[2], 10),
    );
    return Math.round((d1 - d0) / 86400000);
  }

  /** Bara de căutare: min 1 noapte; min_stay per proprietate se verifică pe pagina proprietății. */
  function lhSearchCheckoutInvalid(ranges, checkInYmd, checkOutYmd) {
    if (!checkOutYmd || !checkInYmd || checkOutYmd <= checkInYmd) return true;
    if (lhNightsBetweenYmd(checkInYmd, checkOutYmd) < 1) return true;
    return lhStayOverlapsAnyBlockedRange(ranges, checkInYmd, checkOutYmd);
  }

  /** Flatpickr apelează disable ca d(date); this nu e instanța. */
  function lhLocalDateToYmd(d) {
    if (!d || typeof d.getFullYear !== "function") return "";
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `${y}-${m}-${day}`;
  }

  // ── Elemente DOM ────────────────────────────────────────────────
  const propertySelect = document.getElementById("property-select");
  const guestsSelect = document.getElementById("guests-select");
  const checkInInput = document.getElementById("check-in");
  const checkOutInput = document.getElementById("check-out");
  const searchBtn = document.getElementById("search-btn");
  const btnText = document.getElementById("search-btn-text");
  const btnIconSearch = document.getElementById("btn-icon-search");
  const btnIconReserve = document.getElementById("btn-icon-reserve");
  const searchLoading = document.getElementById("search-loading");
  const resultsContainer = document.getElementById("results-container");
  const searchResultsSection = document.getElementById("search-results-section");
  const resultsHeader = document.getElementById("results-header");
  const featuredSubheading = document.getElementById("featured-subheading");
  const sectorSelect = document.getElementById("lh-sector-select");
  const propertyIdAllInput = document.getElementById("lh-property-id-all");

  const homePropertiesGrid = document.getElementById("home-properties-grid");
  const homePropertiesPreview = document.getElementById("home-properties-preview");
  const homePropertiesHeading = document.getElementById("home-properties-heading");
  const homeCtaWrap = document.getElementById("home-cta-wrap");

  /** Homepage: grid-ul principal; catalog: containerul AJAX de sub hero. */
  function getActiveResultsTarget() {
    return homePropertiesGrid || resultsContainer;
  }

  let lhHomeGridSnapshot = "";
  let lhHomeHeadingSnapshot = "";

  if (homePropertiesGrid) {
    lhHomeGridSnapshot = homePropertiesGrid.innerHTML;
  }
  if (homePropertiesHeading) {
    lhHomeHeadingSnapshot = homePropertiesHeading.textContent.trim();
  }

  function getPropertyId() {
    if (propertySelect) return propertySelect.value;
    if (propertyIdAllInput) return propertyIdAllInput.value;
    return "all";
  }

  function getGuests() {
    return guestsSelect ? guestsSelect.value : "";
  }

  // Bara validă: buton + (select proprietate SAU mod catalog cu sector)
  if (!searchBtn || (!propertySelect && !sectorSelect)) {
    return;
  }

  // ── Stare Flatpickr ──────────────────────────────────────────────
  let fpCheckIn = null;
  let fpCheckOut = null;
  let currentBlockedRanges = []; // intervalele încărcate via AJAX

  function resetResultsUi() {
    if (homePropertiesGrid && lhHomeGridSnapshot !== "") {
      homePropertiesGrid.innerHTML = lhHomeGridSnapshot;
      homePropertiesGrid.classList.remove("opacity-0");
      homePropertiesGrid.classList.add("opacity-100");
      if (homePropertiesHeading) {
        homePropertiesHeading.textContent = "";
        homePropertiesHeading.classList.add("hidden");
      }
      if (homeCtaWrap) {
        homeCtaWrap.classList.remove("hidden");
      }
    }

    if (resultsContainer) {
      resultsContainer.innerHTML = "";
      resultsContainer.classList.add("hidden");
      resultsContainer.classList.remove("opacity-100");
      resultsContainer.classList.add("opacity-0");
    }
    if (resultsHeader) {
      resultsHeader.classList.add("hidden");
    }
    if (featuredSubheading) {
      featuredSubheading.classList.add("hidden");
    }
  }

  function applySearchResults(html) {
    const target = getActiveResultsTarget();
    if (!target) {
      return;
    }

    const onHome = target.id === "home-properties-grid";

    if (onHome) {
      if (homePropertiesHeading) {
        homePropertiesHeading.textContent = typeof lhT === "function" ? lhT("search.available_properties") : "";
        homePropertiesHeading.classList.remove("hidden");
      }
      if (homeCtaWrap) {
        homeCtaWrap.classList.add("hidden");
      }
    }

    target.classList.remove("opacity-100");
    target.classList.add("opacity-0");
    target.innerHTML = html;

    const cardCount = target.querySelectorAll("article").length;
    if (resultsHeader) {
      resultsHeader.classList.toggle("hidden", cardCount === 0);
    }
    if (featuredSubheading) {
      featuredSubheading.classList.toggle("hidden", cardCount === 0);
    }

    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        target.classList.remove("opacity-0");
        target.classList.add("opacity-100");
      });
    });

    const scrollAnchor = searchResultsSection || homePropertiesPreview;
    if (scrollAnchor) {
      scrollAnchor.scrollIntoView({ behavior: "smooth", block: "start" });
    }
  }

  /** Leagă inputul nativ + altInput Flatpickr de eticheta vizuală (vezi aria-labelledby în search_bar.php). */
  function lhFlatpickrBindLegend(fp, legendId) {
    if (!fp || !legendId) return;
    if (fp.input) fp.input.setAttribute("aria-labelledby", legendId);
    if (fp.altInput) fp.altInput.setAttribute("aria-labelledby", legendId);
  }

  // ── Inițializare Flatpickr ───────────────────────────────────────
  function initFlatpickr(blockedRanges) {
    currentBlockedRanges = blockedRanges || [];

    const sharedConfig = {
      locale: window.lhFlatpickrLocale || "ro",
      dateFormat: "Y-m-d", // format trimis în backend
      altInput: true, // câmp vizual separat
      altFormat: "d M Y", // format afișat utilizatorului
      minDate: "today", // fără date în trecut
      disableMobile: true, // folosim flatpickr și pe mobile (nu native picker)
    };

    const checkInDisable = [
      function (date) {
        const ymd = lhLocalDateToYmd(date);
        return ymd ? lhYmdIsBlockedCheckIn(currentBlockedRanges, ymd) : false;
      },
    ];

    // Distruge instanțele anterioare dacă există
    if (fpCheckIn) fpCheckIn.destroy();
    if (fpCheckOut) fpCheckOut.destroy();

    // Check-in picker
    fpCheckIn = flatpickr("#check-in", {
      ...sharedConfig,
      disable: checkInDisable,
      onReady(_dates, _str, instance) {
        lhFlatpickrBindLegend(instance, "lh-search-checkin-label");
      },
      onClose(selectedDates) {
        if (!selectedDates[0]) return;
        // Setăm minDate pentru check-out după check-in
        fpCheckOut.set("minDate", selectedDates[0]);
        // Dacă check-out e deja setat și e înainte de check-in, resetăm
        if (
          fpCheckOut.selectedDates[0] &&
          fpCheckOut.selectedDates[0] <= selectedDates[0]
        ) {
          fpCheckOut.clear();
        }
        // Deschidem automat check-out
        fpCheckOut.open();
      },
    });

    // Check-out picker
    fpCheckOut = flatpickr("#check-out", {
      ...sharedConfig,
      minDate: fpCheckIn?.selectedDates[0] || "today",
      onReady(_dates, _str, instance) {
        lhFlatpickrBindLegend(instance, "lh-search-checkout-label");
      },
      disable: [
        function (date) {
          const ymd = lhLocalDateToYmd(date);
          if (!ymd) return false;
          const cinD = fpCheckIn?.selectedDates?.[0];
          if (!cinD) {
            return lhYmdIsBlockedCheckIn(currentBlockedRanges, ymd);
          }
          const cinYmd = lhLocalDateToYmd(cinD);
          if (!cinYmd) return lhYmdIsBlockedCheckIn(currentBlockedRanges, ymd);
          return lhSearchCheckoutInvalid(currentBlockedRanges, cinYmd, ymd);
        },
      ],
    });
  }

  // Prima inițializare fără date blocate
  initFlatpickr([]);

  // ── Încărcare date blocate via AJAX ──────────────────────────────
  function loadBlockedDates(propertyId) {
    if (!propertyId || propertyId === "all") {
      // Reset: nicio dată blocată
      initFlatpickr([]);
      return;
    }

    fetch(
      "ajax/get_booked_dates.php?property_id=" + encodeURIComponent(propertyId),
    )
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          initFlatpickr(data.blocked_ranges);
        } else {
          initFlatpickr([]);
          console.warn("get_booked_dates:", data.error);
        }
      })
      .catch((err) => {
        initFlatpickr([]);
        console.error("AJAX error (get_booked_dates):", err);
      });
  }

  // ── Actualizare buton ─────────────────────────────────────────────
  function updateButton() {
    if (!propertySelect) {
      btnText.textContent = typeof lhT === "function" ? lhT("search.search") : "Search";
      searchBtn.dataset.mode = "search";
      btnIconSearch.classList.remove("hidden");
      btnIconReserve.classList.add("hidden");
      return;
    }
    const val = propertySelect.value;
    if (val === "all") {
      btnText.textContent = typeof lhT === "function" ? lhT("search.search") : "Search";
      searchBtn.dataset.mode = "search";
      btnIconSearch.classList.remove("hidden");
      btnIconReserve.classList.add("hidden");
    } else {
      btnText.textContent = typeof lhT === "function" ? lhT("booking.book_now") : "Book";
      searchBtn.dataset.mode = "reserve";
      btnIconSearch.classList.add("hidden");
      btnIconReserve.classList.remove("hidden");
    }
  }

  // ── Event: schimbare proprietate ─────────────────────────────────
  if (propertySelect) {
    propertySelect.addEventListener("change", function () {
      updateButton();
      loadBlockedDates(this.value);
      // Resetăm datele la schimbarea proprietății
      if (fpCheckIn) fpCheckIn.clear();
      if (fpCheckOut) fpCheckOut.clear();
      resetResultsUi();
    });
  }

  // Catalog properties.php: schimbare sector → sincronizare URL și grid cu serverul
  if (sectorSelect) {
    sectorSelect.addEventListener("change", function () {
      const u = new URL(window.location.href);
      u.searchParams.delete("city");
      const v = this.value.trim();
      if (v) {
        u.searchParams.set("district", v);
      } else {
        u.searchParams.delete("district");
      }
      window.location.assign(u.toString());
    });
  }

  // ── Click buton ───────────────────────────────────────────────────
  searchBtn.addEventListener("click", function () {
    const mode = this.dataset.mode;
    const propertyId = getPropertyId();
    const checkIn = checkInInput ? checkInInput.value : "";
    const checkOut = checkOutInput ? checkOutInput.value : "";
    const guests = getGuests();
    const dateErr = document.getElementById("lh-search-date-error");
    if (dateErr) {
      dateErr.classList.add("hidden");
      dateErr.textContent = "";
    }

    if (lhSearchDatesInvalidRange(checkIn, checkOut)) {
      if (dateErr) {
        dateErr.textContent = typeof lhT === "function"
          ? lhT("booking.checkout_after_checkin")
          : "Check-out must be after check-in (minimum 1 night).";
        dateErr.classList.remove("hidden");
      }
      return;
    }

    if (mode === "reserve") {
      if (!propertySelect) return;
      // Redirect la pagina proprietății
      const selectedOption =
        propertySelect.options[propertySelect.selectedIndex];
      const slug = selectedOption.dataset.slug;
      const identifier = slug || propertyId;
      const param = slug ? "slug" : "id";

      const params = new URLSearchParams({ [param]: identifier });
      if (checkIn) params.set("check_in", checkIn);
      if (checkOut) params.set("check_out", checkOut);
      if (guests) params.set("guests", guests);

      window.location.href = (typeof lhLocaleUrl === "function" ? lhLocaleUrl("property-details.php") : "property-details.php") + "?" + params.toString();
      return;
    }

    // Mode "search" – AJAX filter
    const target = getActiveResultsTarget();
    if (!target) {
      return;
    }

    // UI feedback
    searchBtn.disabled = true;
    if (searchLoading) searchLoading.classList.remove("hidden");

    if (target.id === "results-container") {
      target.classList.remove("hidden");
      target.classList.remove("opacity-100");
      target.classList.add("opacity-0");
      target.innerHTML = "";
    } else {
      target.classList.remove("opacity-100");
      target.classList.add("opacity-0");
    }

    const formData = new FormData();
    formData.append("property_id", propertyId);
    formData.append("check_in", checkIn);
    formData.append("check_out", checkOut);
    if (window.lhLocale) {
      formData.append("locale", window.lhLocale);
    }
    if (guests !== "") {
      formData.append("guests", guests);
    }

    if (sectorSelect && sectorSelect.value) {
      formData.append("district", sectorSelect.value);
    } else {
      const districtInput = document.getElementById("lh-filter-district");
      const cityInput = document.getElementById("lh-filter-city");
      if (districtInput && districtInput.value) {
        formData.append("district", districtInput.value);
      } else if (cityInput && cityInput.value) {
        formData.append("city", cityInput.value);
      }
    }

    fetch("ajax/filter_properties.php", {
      method: "POST",
      body: formData,
    })
      .then((res) => res.text())
      .then(function (html) {
        applySearchResults(html);
      })
      .catch(function (err) {
        applySearchResults(
          '<p class="col-span-full text-center text-red-500 py-8">' + (typeof lhT === "function" ? lhT("search.error_generic") : "") + "</p>",
        );
        console.error("AJAX error (filter_properties):", err);
      })
      .finally(function () {
        searchBtn.disabled = false;
        if (searchLoading) searchLoading.classList.add("hidden");
      });
  });

  // ── Inițializare stare buton ──────────────────────────────────────
  updateButton();

  // Dacă pagina s-a reîncărcat cu parametri GET, re-triggerăm căutarea automat
  const urlParams = new URLSearchParams(window.location.search);
  if (
    urlParams.has("check_in") &&
    getPropertyId() === "all" &&
    getActiveResultsTarget()
  ) {
    searchBtn.click();
  }
})();

// Homepage recenzii: rulează mereu (bara de căutare poate lipsi → return în IIFE-ul de mai sus).
(function () {
  "use strict";
  const track = document.getElementById("lh-home-reviews-track");
  const prev = document.getElementById("lh-home-reviews-prev");
  const next = document.getElementById("lh-home-reviews-next");
  if (!track || !prev || !next) return;

  const GAP_PX = 16;

  function getScrollStep() {
    const card = track.querySelector(".lh-home-review-card");
    if (!card) return Math.round(track.clientWidth * 0.85);
    return card.offsetWidth + GAP_PX;
  }

  function updateArrows() {
    const maxScroll = Math.max(0, track.scrollWidth - track.clientWidth);
    const leftDisabled = track.scrollLeft <= 2;
    const rightDisabled = track.scrollLeft >= maxScroll - 2;
    prev.disabled = leftDisabled;
    next.disabled = rightDisabled;
    prev.setAttribute("aria-disabled", leftDisabled ? "true" : "false");
    next.setAttribute("aria-disabled", rightDisabled ? "true" : "false");
  }

  prev.addEventListener("click", function () {
    track.scrollBy({ left: -getScrollStep(), behavior: "smooth" });
  });
  next.addEventListener("click", function () {
    track.scrollBy({ left: getScrollStep(), behavior: "smooth" });
  });
  track.addEventListener("scroll", updateArrows, { passive: true });
  window.addEventListener("resize", updateArrows);
  updateArrows();
})();
