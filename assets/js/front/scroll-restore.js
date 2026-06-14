(function () {
  'use strict';

  var KEY = 'ouinpo:preserve-scroll';
  var MAX_AGE = 10000;
  var SELECTOR = '.ouinpo-preserve-scroll, [data-ouinpo-preserve-scroll="1"]';

  function now() {
    return Date.now ? Date.now() : new Date().getTime();
  }

  function samePage(url) {
    return url === window.location.origin + window.location.pathname;
  }

  function storage() {
    try {
      var store = window.sessionStorage;
      var testKey = KEY + ':test';
      store.setItem(testKey, '1');
      store.removeItem(testKey);
      return store;
    } catch (error) {
      return null;
    }
  }

  function remember() {
    var store = storage();
    if (!store) {
      return;
    }

    try {
      store.setItem(KEY, JSON.stringify({
        url: window.location.origin + window.location.pathname,
        y: window.pageYOffset || document.documentElement.scrollTop || 0,
        time: now()
      }));
    } catch (error) {
      // sessionStorage can fail in private browsing or strict embeds.
    }
  }

  function restore() {
    var store = storage();
    if (!store) {
      return;
    }

    try {
      var raw = store.getItem(KEY);
      if (!raw) {
        return;
      }

      store.removeItem(KEY);
      var data = JSON.parse(raw);
      var y = Number(data && data.y);
      var time = Number(data && data.time);

      if (!Number.isFinite(y) || y < 0 || !Number.isFinite(time)) {
        return;
      }

      if (now() - time > MAX_AGE || !samePage(String(data.url || ''))) {
        return;
      }

      window.requestAnimationFrame(function () {
        window.scrollTo(0, y);
      });
    } catch (error) {
      // Invalid JSON or blocked storage: ignore silently.
    }
  }

  function closestTarget(element) {
    return element && element.closest ? element.closest(SELECTOR) : null;
  }

  document.addEventListener('submit', function (event) {
    if (closestTarget(event.target)) {
      remember();
    }
  }, true);

  document.addEventListener('change', function (event) {
    var target = event.target;
    if (!target || !/^(SELECT|INPUT)$/i.test(target.tagName || '')) {
      return;
    }

    if (closestTarget(target)) {
      remember();
    }
  }, true);

  document.addEventListener('click', function (event) {
    if (closestTarget(event.target)) {
      remember();
    }
  }, true);

  window.OuinpoScrollRestore = {
    remember: remember
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', restore);
  } else {
    restore();
  }
})();
