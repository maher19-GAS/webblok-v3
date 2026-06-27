/**
 * WebBlok Embed Widget — drop a single blok onto any third-party page.
 *
 * Usage:
 *   <div data-webblok-widget
 *        data-base="https://api.example.com"
 *        data-key="wb_publishable_xxx"
 *        data-blok="pricing_table"></div>
 *   <script src="https://api.example.com/js/widget.js" async></script>
 *
 * The widget fetches the rendered blok HTML/data from the V1 embed API and
 * injects it into the host element. It is intentionally dependency-free.
 */
(function () {
  'use strict';

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function buildHtml(data) {
    if (typeof data === 'string') return data;
    if (data && typeof data.html === 'string') return data.html;
    var body = Object.keys(data || {})
      .map(function (k) {
        return '<div class="wb-w-row"><strong>' + escapeHtml(k) + ':</strong> ' + escapeHtml(data[k]) + '</div>';
      })
      .join('');
    return '<div class="wb-widget">' + body + '</div>';
  }

  function mount(el) {
    var base = (el.getAttribute('data-base') || '').replace(/\/+$/, '');
    var key = el.getAttribute('data-key') || '';
    var blok = el.getAttribute('data-blok') || '';
    if (!base || !blok) {
      el.innerHTML = '<!-- WebBlok widget: missing data-base or data-blok -->';
      return;
    }
    el.innerHTML = '<div class="wb-widget-loading">Loading…</div>';

    fetch(base + '/api/v1/bloks/' + encodeURIComponent(blok), {
      headers: key
        ? { Authorization: 'Bearer ' + key, Accept: 'application/json' }
        : { Accept: 'application/json' },
      mode: 'cors',
    })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function (payload) {
        el.innerHTML = buildHtml((payload && payload.data) || payload);
        el.setAttribute('data-webblok-ready', '1');
      })
      .catch(function (err) {
        el.innerHTML = '<!-- WebBlok widget error: ' + escapeHtml(err.message) + ' -->';
      });
  }

  function init() {
    document.querySelectorAll('[data-webblok-widget]:not([data-webblok-ready])').forEach(mount);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.WebBlokWidget = { init: init, mount: mount };
})();
