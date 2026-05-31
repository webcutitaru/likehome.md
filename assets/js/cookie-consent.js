/**
 * Cookie consent: localStorage, banner, modal.
 * După încărcare: window.LH_COOKIE_CONSENT = { analytics, marketing, essential: true }
 * Eveniment: window.dispatchEvent(new CustomEvent('lh:cookie-consent', { detail }))
 *
 * Integrare GTM / gtag / Meta Pixel: ascultă 'lh:cookie-consent' și încarcă scripturi doar dacă
 * detail.analytics sau detail.marketing sunt true. Exemplu la sfârșitul fișierului (comentat).
 */
(function () {
  "use strict";

  var STORAGE_KEY = "lh_cookie_v1";
  var CONSENT_VERSION = 1;

  function parseStored(raw) {
    if (!raw) return null;
    try {
      var o = JSON.parse(raw);
      if (!o || o.v !== CONSENT_VERSION) return null;
      if (typeof o.analytics !== "boolean" || typeof o.marketing !== "boolean") return null;
      return o;
    } catch (e) {
      return null;
    }
  }

  function readConsent() {
    try {
      return parseStored(localStorage.getItem(STORAGE_KEY));
    } catch (e) {
      return null;
    }
  }

  function writeConsent(analytics, marketing) {
    var payload = {
      v: CONSENT_VERSION,
      essential: true,
      analytics: !!analytics,
      marketing: !!marketing,
      ts: Date.now(),
    };
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
    } catch (e) {}
    return payload;
  }

  function publishConsent(detail, fromUser) {
    window.LH_COOKIE_CONSENT = {
      essential: true,
      analytics: detail.analytics,
      marketing: detail.marketing,
      updatedAt: detail.ts,
    };
    try {
      window.dispatchEvent(
        new CustomEvent("lh:cookie-consent", { detail: window.LH_COOKIE_CONSENT })
      );
    } catch (e) {}
    if (fromUser && typeof window.lhOnCookieConsent === "function") {
      try {
        window.lhOnCookieConsent(window.LH_COOKIE_CONSENT);
      } catch (e2) {}
    }
  }

  function hideBanner() {
    var b = document.getElementById("lh-cookie-banner");
    if (b) b.classList.add("hidden");
  }

  function showBanner() {
    var b = document.getElementById("lh-cookie-banner");
    if (b) b.classList.remove("hidden");
  }

  function closeModal() {
    var m = document.getElementById("lh-cookie-modal");
    var bd = document.getElementById("lh-cookie-modal-backdrop");
    if (m) {
      m.classList.add("hidden");
      m.setAttribute("aria-hidden", "true");
    }
    if (bd) {
      bd.classList.add("hidden");
      bd.setAttribute("aria-hidden", "true");
    }
    document.body.classList.remove("overflow-hidden");
  }

  function openModal(prefill) {
    var m = document.getElementById("lh-cookie-modal");
    var bd = document.getElementById("lh-cookie-modal-backdrop");
    var ta = document.getElementById("lh-cookie-toggle-analytics");
    var tm = document.getElementById("lh-cookie-toggle-marketing");
    if (prefill && ta && tm) {
      ta.checked = !!prefill.analytics;
      tm.checked = !!prefill.marketing;
    }
    if (bd) {
      bd.classList.remove("hidden");
      bd.setAttribute("aria-hidden", "false");
    }
    if (m) {
      m.classList.remove("hidden");
      m.setAttribute("aria-hidden", "false");
    }
    document.body.classList.add("overflow-hidden");
  }

  function applyChoice(analytics, marketing, fromUser) {
    var stored = writeConsent(analytics, marketing);
    publishConsent(stored, fromUser);
    hideBanner();
    closeModal();
  }

  window.lhOpenCookieSettings = function () {
    hideBanner();
    var existing = readConsent();
    openModal(existing || { analytics: false, marketing: false });
  };

  function wireFooterAndPrivacy() {
    var foot = document.getElementById("lh-footer-cookie-settings");
    if (foot) {
      foot.addEventListener("click", function () {
        window.lhOpenCookieSettings();
      });
    }
    var priv = document.getElementById("lh-privacy-open-cookies");
    if (priv) {
      priv.addEventListener("click", function () {
        hideBanner();
        var ex = readConsent();
        openModal(ex || { analytics: false, marketing: false });
      });
    }
  }

  function modalDismissOrBanner() {
    closeModal();
    if (!readConsent()) showBanner();
  }

  function wireCookieModalDocumentControls() {
    document.addEventListener("click", function (e) {
      var t = e.target;
      if (!(t instanceof Element)) return;

      var bd = document.getElementById("lh-cookie-modal-backdrop");
      if (bd && !bd.classList.contains("hidden") && t === bd) {
        modalDismissOrBanner();
        return;
      }

      if (t.closest("#lh-cookie-modal-cancel")) {
        modalDismissOrBanner();
        return;
      }

      if (t.closest("#lh-cookie-modal-save")) {
        var ta = document.getElementById("lh-cookie-toggle-analytics");
        var tm = document.getElementById("lh-cookie-toggle-marketing");
        applyChoice(!!(ta && ta.checked), !!(tm && tm.checked), true);
      }
    });

    document.addEventListener("keydown", function (e) {
      if (e.key !== "Escape") return;
      var m = document.getElementById("lh-cookie-modal");
      if (!m || m.classList.contains("hidden")) return;
      modalDismissOrBanner();
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    wireFooterAndPrivacy();
    wireCookieModalDocumentControls();

    var btnAll = document.getElementById("lh-cookie-accept-all");
    var btnRej = document.getElementById("lh-cookie-reject");
    var btnCust = document.getElementById("lh-cookie-customize");

    if (btnAll) btnAll.addEventListener("click", function () {
      applyChoice(true, true, true);
    });
    if (btnRej) btnRej.addEventListener("click", function () {
      applyChoice(false, false, true);
    });
    if (btnCust) btnCust.addEventListener("click", function () {
      hideBanner();
      openModal({ analytics: false, marketing: false });
    });

    var existing = readConsent();
    if (existing) {
      publishConsent(existing, false);
      hideBanner();
    } else {
      showBanner();
    }
  });

  // Google Analytics 4 – actualizează Consent Mode v2 la alegerea utilizatorului
  window.addEventListener('lh:cookie-consent', function (ev) {
    var d = ev.detail;
    if (typeof gtag !== 'function') return;
    gtag('consent', 'update', {
      analytics_storage: d.analytics ? 'granted' : 'denied',
      ad_storage:        d.marketing ? 'granted' : 'denied'
    });
  });
})();
