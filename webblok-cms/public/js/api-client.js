/**
 * WebBlok API Client — the live-data bridge for static exports.
 *
 * Exported (static) sites ship with this file. Any blok element on the page
 * that carries `data-wb-blok` (the blok key) and `data-wb-live` will be
 * re-hydrated at runtime from the V1 embed API, so a downloaded static site
 * can still show fresh content (prices, counters, listings, etc.).
 *
 * Configure once on the page:
 *   <script>window.WEBBLOK = { base: 'https://api.example.com', apiKey: 'wb_xxx' };</script>
 *   <script src="/js/api-client.js" defer></script>
 */
(function () {
  'use strict';

  var cfg = window.WEBBLOK || {};
  var BASE = (cfg.base || '').replace(/\/+$/, '');
  var API_KEY = cfg.apiKey || '';

  function headers() {
    var h = { Accept: 'application/json' };
    if (API_KEY) h['Authorization'] = 'Bearer ' + API_KEY;
    return h;
  }

  function fetchBlok(blokKey, params) {
    if (!BASE) return Promise.reject(new Error('WEBBLOK.base is not configured'));
    var qs = params ? '?' + new URLSearchParams(params).toString() : '';
    return fetch(BASE + '/api/v1/bloks/' + encodeURIComponent(blokKey) + qs, {
      headers: headers(),
      mode: 'cors',
    }).then(function (r) {
      if (!r.ok) throw new Error('WebBlok API error ' + r.status);
      return r.json();
    });
  }

  function applyData(el, data) {
    el.querySelectorAll('[data-wb-field]').forEach(function (node) {
      var key = node.getAttribute('data-wb-field');
      if (key && data && Object.prototype.hasOwnProperty.call(data, key)) {
        node.textContent = String(data[key]);
      }
    });
  }

  function hydrate() {
    document.querySelectorAll('[data-wb-blok][data-wb-live]').forEach(function (el) {
      var key = el.getAttribute('data-wb-blok');
      if (!key) return;
      fetchBlok(key)
        .then(function (payload) {
          applyData(el, (payload && payload.data) || payload || {});
          el.setAttribute('data-wb-hydrated', '1');
        })
        .catch(function (err) {
          if (window.console) console.warn('[WebBlok]', err.message);
        });
    });
  }

  window.WebBlokClient = { fetchBlok: fetchBlok, hydrate: hydrate };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', hydrate);
  } else {
    hydrate();
  }
})();
