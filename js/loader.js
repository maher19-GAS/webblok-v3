/**
 * 2030B – Be Smarter
 * Component Loader System
 * Handles AJAX loading of HTML components with caching & prefetch
 */

const ComponentLoader = (() => {
  // Cache for loaded components
  const cache = new Map();

  // Active component targets map: {path: [targetId, ...]}
  const pendingLoads = new Map();

  /**
   * Load a single HTML component into a target element
   * @param {string} path     - Relative path to component HTML file
   * @param {string} targetId - DOM element ID to inject HTML into
   * @param {object} data     - Optional data to template into the component
   * @returns {Promise<string>} - Resolved with the HTML content
   */
  async function loadComponent(path, targetId, data = {}) {
    const target = document.getElementById(targetId);
    if (!target) {
      console.warn(`[Loader] Target #${targetId} not found`);
      return '';
    }

    let html;

    // Use cache if available
    if (cache.has(path)) {
      html = cache.get(path);
    } else {
      try {
        const res = await fetch(path);
        if (!res.ok) throw new Error(`HTTP ${res.status} for ${path}`);
        html = await res.text();
        cache.set(path, html);
      } catch (err) {
        console.error(`[Loader] Failed to load component: ${path}`, err);
        return '';
      }
    }

    // Apply data templating (simple {{key}} substitution)
    if (Object.keys(data).length > 0) {
      html = applyTemplate(html, data);
    }

    target.innerHTML = html;
    return html;
  }

  /**
   * Load multiple components in parallel
   * @param {Array<{path, target, data}>} components
   * @returns {Promise<void>}
   */
  async function loadComponents(components) {
    const promises = components.map(({ path, target, data = {} }) =>
      loadComponent(path, target, data)
    );
    await Promise.all(promises);
  }

  /**
   * Load multiple components in sequence (order preserved)
   * @param {Array<{path, target, data}>} components
   */
  async function loadComponentsSequential(components) {
    for (const comp of components) {
      await loadComponent(comp.path, comp.target, comp.data || {});
    }
  }

  /**
   * Simple {{key}} template substitution
   * @param {string} html
   * @param {object} data
   * @returns {string}
   */
  function applyTemplate(html, data) {
    return html.replace(/\{\{(\w+)\}\}/g, (match, key) => {
      return key in data ? data[key] : match;
    });
  }

  /**
   * Prefetch components into cache without rendering
   * @param {string[]} paths
   */
  async function prefetch(paths) {
    const promises = paths.map(async (path) => {
      if (!cache.has(path)) {
        try {
          const res = await fetch(path);
          if (res.ok) {
            const html = await res.text();
            cache.set(path, html);
          }
        } catch (e) {
          // Silent fail for prefetch
        }
      }
    });
    await Promise.all(promises);
  }

  /**
   * Clear cache (useful for dev / hot reload)
   */
  function clearCache() {
    cache.clear();
  }

  return { loadComponent, loadComponents, loadComponentsSequential, prefetch, clearCache };
})();

// ─── SLIDE LOADER ─────────────────────────────────────────────────────────────

/**
 * Full slide load sequence:
 * 1. Show skeleton (already in DOM)
 * 2. Wait 500ms
 * 3. Load components in parallel
 * 4. Hide skeleton, show content
 * 5. Trigger animations
 *
 * @param {Array<{path, target, data}>} components
 * @param {string} skeletonId  - ID of skeleton container
 * @param {string} contentId   - ID of real content container
 * @param {number} delay       - Skeleton display time in ms (default 500)
 */
async function loadSlide(components, skeletonId = 'skeleton', contentId = 'content', delay = 500) {
  const skeleton = document.getElementById(skeletonId);
  const content  = document.getElementById(contentId);

  if (!content) return;

  // Show skeleton
  if (skeleton) skeleton.style.display = 'block';
  if (content)  content.style.display  = 'none';

  // Wait for skeleton display duration
  await new Promise(resolve => setTimeout(resolve, delay));

  // Load all components in parallel
  await ComponentLoader.loadComponents(components);

  // Swap skeleton ↔ content
  if (skeleton) {
    skeleton.style.opacity = '0';
    skeleton.style.transition = 'opacity 0.3s';
    setTimeout(() => { skeleton.style.display = 'none'; }, 300);
  }

  content.style.display = 'flex';
  content.style.opacity = '0';
  content.style.transition = 'opacity 0.4s';
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      content.style.opacity = '1';
      // Trigger entry animations
      AnimationSystem.animateSlideIn(content);
    });
  });
}

// ─── JSON DATA LOADER ──────────────────────────────────────────────────────────

let _langData = {};
let _currentLang = 'en';

/**
 * Resolve the correct JSON filename for a given language + volume.
 * Vol 1 (slides 1–50)  → en.json / ar.json
 * Vol 2 (slides 51–100) → en2.json / ar2.json
 * Vol 3 (slides 101–150)→ en3.json / ar3.json
 * @param {string} lang - 'en' or 'ar'
 * @param {number} vol  - 1 | 2 | 3  (default 1)
 * @returns {string} filename e.g. 'en2.json'
 */
function _volFile(lang, vol) {
  const suffix = vol > 1 ? String(vol) : '';
  return `${lang}${suffix}.json`;
}

/**
 * Derive volume number from slide number.
 * @param {number} slideNum
 * @returns {number} 1 | 2 | 3
 */
function _slideVol(slideNum) {
  if (slideNum >= 101) return 3;
  if (slideNum >= 51)  return 2;
  return 1;
}

/**
 * Load a specific language+volume JSON file.
 * Cache key is the resolved filename (e.g. 'en2').
 * @param {string} lang - 'en' or 'ar'
 * @param {number} vol  - 1 | 2 | 3
 */
async function loadLangVol(lang = 'en', vol = 1) {
  const file = _volFile(lang, vol);
  const cacheKey = file.replace('.json', ''); // e.g. 'en2'
  if (_langData[cacheKey]) return _langData[cacheKey];

  // Try multiple paths: prefer parent-relative first (slides/ iframe context),
  // then root-relative, then absolute root
  const paths = [
    `../${file}`,       // from slides/slide-XX.html → resolves to root
    `${file}`,          // root-relative (when viewer itself calls loadLang)
    `/${file}`,         // absolute root fallback
  ];
  for (const path of paths) {
    try {
      const res = await fetch(path);
      if (res.ok) {
        const json = await res.json();
        _langData[cacheKey] = json;
        return json;
      }
    } catch (e) {
      // try next
    }
  }
  console.error(`[Loader] Failed to load ${file} from any path`);
  return null;
}

/**
 * Load language JSON file (Vol 1 default — kept for back-compat).
 * @param {string} lang - 'en' or 'ar'
 */
async function loadLang(lang = 'en') {
  return loadLangVol(lang, 1);
}

/**
 * Get slide data by slide number — automatically selects the correct volume JSON.
 * @param {number} slideNum
 * @param {string} lang
 */
async function getSlideData(slideNum, lang = _currentLang) {
  const vol  = _slideVol(slideNum);
  const data = await loadLangVol(lang, vol);
  if (!data) return null;
  return data.slides.find(s => s.id === slideNum) || null;
}

/**
 * Get brand data (uses Vol 1 brand block by default).
 */
async function getBrandData(lang = _currentLang) {
  const data = await loadLangVol(lang, 1);
  return data ? data.brand : {};
}

// ─── LANGUAGE SWITCHING ─────────────────────────────────────────────────────────

/**
 * Switch UI language and direction
 * @param {string} lang - 'en' | 'ar' | 'fr'
 */
function switchLanguage(lang) {
  _currentLang = lang;
  document.documentElement.setAttribute('lang', lang);
  document.documentElement.setAttribute('dir', lang === 'ar' ? 'rtl' : 'ltr');
  document.body.setAttribute('dir', lang === 'ar' ? 'rtl' : 'ltr');
  localStorage.setItem('2030b_lang', lang);

  // Update all lang toggle buttons
  document.querySelectorAll('.lang-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.lang === lang);
  });

  // Dispatch event for slides to re-render
  document.dispatchEvent(new CustomEvent('langChange', { detail: { lang } }));
}

/**
 * Initialize language from localStorage or browser
 */
function initLanguage() {
  const saved = localStorage.getItem('2030b_lang');
  // Detect browser language: Arabic → 'ar', French → 'fr', everything else → 'en'
  let browser = 'en';
  if (navigator.language.startsWith('ar')) browser = 'ar';
  else if (navigator.language.startsWith('fr')) browser = 'fr';
  const lang = saved || browser;
  switchLanguage(lang);
  return lang;
}

// ─── THEME ─────────────────────────────────────────────────────────────────────

function initTheme() {
  const saved = localStorage.getItem('2030b_theme') || 'cosmic-dark';
  document.body.dataset.theme = saved;
}

// ─── EXPORTS ──────────────────────────────────────────────────────────────────

window.Loader = {
  loadComponent: ComponentLoader.loadComponent,
  loadComponents: ComponentLoader.loadComponents,
  loadSlide,
  getSlideData,
  getBrandData,
  loadLang,
  loadLangVol,
  switchLanguage,
  initLanguage,
  initTheme,
  prefetch: ComponentLoader.prefetch,
  clearCache: ComponentLoader.clearCache,
  get currentLang() { return _currentLang; }
};
