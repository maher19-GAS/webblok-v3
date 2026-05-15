/**
 * slide-engine.js – 2030B Slide System Core
 * CRITICAL component: drives navigation, state, progress, transitions
 * Manages: slide index, history, lang switching, keyboard/swipe/click nav
 * Version: 3.0 | Vol 1+2+3 unified
 */

window.SlideEngine = (function () {
  'use strict';

  // ─── Config ───────────────────────────────────────────────────────────────
  let _config = {
    totalSlides:  150,
    startIndex:   1,
    baseUrl:      'slides/',
    filePattern:  'slide-{{n}}.html',
    padDigits:    2,    // 2 for slides 1-99, auto-extends to 3 for 100+
    transitionMs: 380,
    autoPlay:     false,
    autoPlayMs:   8000,
    lang:         'en',
    rtl:          false,
  };

  // ─── State ────────────────────────────────────────────────────────────────
  let _state = {
    current:         1,
    history:         [],
    isTransitioning: false,
    autoPlayTimer:   null,
    listeners:       {},
  };

  // ─── Init ─────────────────────────────────────────────────────────────────
  function init(options = {}) {
    Object.assign(_config, options);
    _state.current = _config.startIndex;
    _bindKeyboard();
    _bindTouch();
    _updateProgress(_state.current);
    _updateCounter(_state.current);
    _emit('init', { current: _state.current });
    return SlideEngine;
  }

  // ─── Navigation ───────────────────────────────────────────────────────────
  function goTo(n, opts = {}) {
    const target = Math.max(1, Math.min(Math.round(n), _config.totalSlides));
    if (target === _state.current && !opts.force) return SlideEngine;
    if (_state.isTransitioning && !opts.force) return SlideEngine;

    _state.history.push(_state.current);
    const prev = _state.current;
    _state.current = target;

    const dir = opts.direction || (target > prev ? 'forward' : 'backward');
    _emit('beforeNavigate', { from: prev, to: target, direction: dir });
    _applyTransition(prev, target, dir);
    return SlideEngine;
  }

  function next()  { return goTo(_state.current + 1, { direction: 'forward' }); }
  function prev()  { return goTo(_state.current - 1, { direction: 'backward' }); }
  function first() { return goTo(1); }
  function last()  { return goTo(_config.totalSlides); }

  function back() {
    if (_state.history.length) {
      const p = _state.history.pop();
      return goTo(p, { direction: 'backward', force: true });
    }
    return SlideEngine;
  }

  // ─── Transition ───────────────────────────────────────────────────────────
  function _applyTransition(from, to, direction) {
    _state.isTransitioning = true;

    const container = document.getElementById('slide-frame');
    if (!container) { _finishNav(from, to); return; }

    const existing = container.querySelector('iframe');
    const slideIn  = document.createElement('iframe');
    slideIn.className  = 'slide-iframe';
    slideIn.src        = _buildUrl(to);
    slideIn.setAttribute('allowfullscreen', '');
    slideIn.style.cssText = `position:absolute;inset:0;width:100%;height:100%;border:none;opacity:0;transform:translateX(${direction==='forward'?'40px':'-40px'});transition:opacity ${_config.transitionMs}ms ease,transform ${_config.transitionMs}ms ease;`;
    container.appendChild(slideIn);

    requestAnimationFrame(() => requestAnimationFrame(() => {
      slideIn.style.opacity = '1';
      slideIn.style.transform = 'translateX(0)';
      if (existing) {
        existing.style.opacity = '0';
        existing.style.transform = `translateX(${direction==='forward'?'-40px':'40px'})`;
        existing.style.transition = `opacity ${_config.transitionMs}ms ease, transform ${_config.transitionMs}ms ease`;
      }
      setTimeout(() => {
        if (existing) existing.remove();
        _finishNav(from, to);
      }, _config.transitionMs + 20);
    }));

    _updateProgress(to);
    _updateCounter(to);
  }

  function _finishNav(from, to) {
    _state.isTransitioning = false;
    _emit('navigate', { from, to, current: to });
  }

  // ─── URL Building ─────────────────────────────────────────────────────────
  function _buildUrl(n) {
    // Auto-pad: 3 digits for slide numbers >= 100, else 2
    const digits = n >= 100 ? 3 : 2;
    const padded = String(n).padStart(digits, '0');
    const file   = _config.filePattern.replace('{{n}}', padded);
    return _config.baseUrl + file;
  }

  // ─── Progress & Counter ───────────────────────────────────────────────────
  function _updateProgress(n) {
    const fill = document.getElementById('se-progress-fill');
    if (fill && _config.totalSlides > 1) {
      fill.style.width = (((n - 1) / (_config.totalSlides - 1)) * 100).toFixed(1) + '%';
    }
  }

  function _updateCounter(n) {
    const el = document.getElementById('slide-counter');
    if (el) el.textContent = `${n} / ${_config.totalSlides}`;
  }

  // ─── Keyboard ─────────────────────────────────────────────────────────────
  function _bindKeyboard() {
    document.addEventListener('keydown', (e) => {
      if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
      if (e.ctrlKey || e.metaKey || e.altKey) return;
      switch (e.key) {
        case 'ArrowRight': case 'ArrowDown': case 'PageDown': e.preventDefault(); next();  break;
        case 'ArrowLeft':  case 'ArrowUp':   case 'PageUp':   e.preventDefault(); prev();  break;
        case 'Home': e.preventDefault(); first(); break;
        case 'End':  e.preventDefault(); last();  break;
        case 'Backspace': back(); break;
        case 'f': case 'F': toggleFullscreen(); break;
        case 'p': case 'P': toggleAutoPlay(); break;
      }
    });
  }

  // ─── Touch / Swipe ────────────────────────────────────────────────────────
  function _bindTouch() {
    let sX = 0, sY = 0;
    document.addEventListener('touchstart', e => { sX = e.touches[0].clientX; sY = e.touches[0].clientY; }, { passive: true });
    document.addEventListener('touchend',   e => {
      const dx = e.changedTouches[0].clientX - sX;
      const dy = e.changedTouches[0].clientY - sY;
      if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 50) dx < 0 ? next() : prev();
    }, { passive: true });
  }

  // ─── AutoPlay ─────────────────────────────────────────────────────────────
  function startAutoPlay(ms) {
    stopAutoPlay();
    _config.autoPlayMs = ms || _config.autoPlayMs;
    _state.autoPlayTimer = setInterval(() => {
      if (_state.current < _config.totalSlides) next();
      else stopAutoPlay();
    }, _config.autoPlayMs);
    _config.autoPlay = true;
    _emit('autoplay', { active: true });
    return SlideEngine;
  }

  function stopAutoPlay() {
    clearInterval(_state.autoPlayTimer);
    _state.autoPlayTimer = null;
    _config.autoPlay = false;
    _emit('autoplay', { active: false });
    return SlideEngine;
  }

  function toggleAutoPlay() { return _config.autoPlay ? stopAutoPlay() : startAutoPlay(); }

  // ─── Language ─────────────────────────────────────────────────────────────
  function setLang(lang) {
    _config.lang = lang;
    _config.rtl  = (lang === 'ar');
    document.documentElement.lang = lang;
    document.documentElement.dir  = _config.rtl ? 'rtl' : 'ltr';
    _emit('langChange', { lang, rtl: _config.rtl });
    goTo(_state.current, { force: true });
    return SlideEngine;
  }

  function getLang() { return _config.lang; }

  // ─── Fullscreen ───────────────────────────────────────────────────────────
  function toggleFullscreen() {
    const el = document.documentElement;
    if (!document.fullscreenElement) {
      (el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen).call(el);
    } else {
      (document.exitFullscreen || document.webkitExitFullscreen || document.mozCancelFullScreen).call(document);
    }
  }

  // ─── Event Bus ────────────────────────────────────────────────────────────
  function on(event, handler) {
    if (!_state.listeners[event]) _state.listeners[event] = [];
    _state.listeners[event].push(handler);
    return SlideEngine;
  }

  function off(event, handler) {
    if (_state.listeners[event])
      _state.listeners[event] = _state.listeners[event].filter(h => h !== handler);
    return SlideEngine;
  }

  function _emit(event, data) {
    (_state.listeners[event] || []).forEach(h => { try { h(data); } catch(e) { console.warn('[SlideEngine]', e); } });
  }

  // ─── Getters ──────────────────────────────────────────────────────────────
  function getCurrent() { return _state.current; }
  function getTotal()   { return _config.totalSlides; }
  function getHistory() { return [..._state.history]; }
  function getConfig()  { return { ..._config }; }
  function isFirst()    { return _state.current === (_config.startIndex || 1); }
  function isLast()     { return _state.current === _config.totalSlides; }

  // ─── Volume helpers ───────────────────────────────────────────────────────
  function getVolume() {
    const n = _state.current;
    if (n <= 50)  return 1;
    if (n <= 100) return 2;
    return 3;
  }

  function goToVolume(vol) {
    const start = vol === 1 ? 1 : vol === 2 ? 51 : 101;
    return goTo(start);
  }

  // ─── Thumbnail Rail ───────────────────────────────────────────────────────
  function buildThumbnailRail(containerId, range) {
    const el = document.getElementById(containerId);
    if (!el) return SlideEngine;
    const start = (range && range[0]) || 1;
    const end   = (range && range[1]) || _config.totalSlides;
    el.innerHTML = '';

    for (let i = start; i <= end; i++) {
      const t = document.createElement('div');
      t.className   = 'slide-thumb';
      t.dataset.slide = i;
      t.textContent = i;
      const active = i === _state.current;
      t.style.cssText = `min-width:52px;height:56px;border-radius:8px;border:1px solid rgba(255,255,255,${active?'0.25':'0.08'});background:rgba(255,255,255,${active?'0.08':'0.03'});display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:${active?700:400};color:${active?'#63B3ED':'rgba(255,255,255,0.4)'};cursor:pointer;transition:all 0.2s;flex-shrink:0;`;
      t.addEventListener('click', () => goTo(i));
      el.appendChild(t);
    }

    on('navigate', ({ to }) => {
      el.querySelectorAll('.slide-thumb').forEach(t => {
        const n = +t.dataset.slide;
        const a = n === to;
        t.style.borderColor = a ? 'rgba(255,255,255,0.25)' : 'rgba(255,255,255,0.08)';
        t.style.background  = a ? 'rgba(255,255,255,0.08)' : 'rgba(255,255,255,0.03)';
        t.style.fontWeight  = a ? '700' : '400';
        t.style.color       = a ? '#63B3ED' : 'rgba(255,255,255,0.4)';
        if (a) t.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
      });
    });

    return SlideEngine;
  }

  // ─── Public API ───────────────────────────────────────────────────────────
  return {
    init, goTo, next, prev, first, last, back,
    startAutoPlay, stopAutoPlay, toggleAutoPlay,
    setLang, getLang,
    toggleFullscreen,
    on, off,
    getCurrent, getTotal, getHistory, getConfig,
    isFirst, isLast,
    getVolume, goToVolume,
    buildThumbnailRail,
  };

})();
