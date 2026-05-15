/**
 * 2030B – Be Smarter
 * Animation System
 * Entry animations, stagger effects, micro-interactions, charts
 */

const AnimationSystem = (() => {

  // ─── ENTRY ANIMATIONS ─────────────────────────────────────────────────────

  /**
   * Animate all .fade-in elements inside a container with stagger
   * @param {HTMLElement} container
   * @param {number} baseDelay - ms before first element
   * @param {number} stagger   - ms between each element
   */
  function animateSlideIn(container, baseDelay = 50, stagger = 80) {
    const items = container.querySelectorAll('.fade-in, .scale-in, .slide-up');
    items.forEach((el, i) => {
      setTimeout(() => {
        el.classList.add('show');
      }, baseDelay + i * stagger);
    });
  }

  /**
   * Trigger stagger animation on a group of elements
   * @param {HTMLElement[]} elements
   * @param {number} stagger
   */
  function staggerElements(elements, stagger = 80) {
    elements.forEach((el, i) => {
      setTimeout(() => {
        el.classList.add('show');
      }, i * stagger);
    });
  }

  /**
   * Fade in single element
   */
  function fadeIn(el, delay = 0) {
    if (!el) return;
    setTimeout(() => el.classList.add('show'), delay);
  }

  // ─── PROGRESS BAR ANIMATION ───────────────────────────────────────────────

  /**
   * Animate a progress bar fill from 0 to target value
   * @param {HTMLElement} fillEl - The .progress-fill element
   * @param {number} value       - Target percentage 0–100
   * @param {number} delay       - Start delay in ms
   */
  function animateProgressBar(fillEl, value, delay = 300) {
    if (!fillEl) return;
    fillEl.style.width = '0%';
    setTimeout(() => {
      fillEl.style.width = `${Math.min(100, Math.max(0, value))}%`;
    }, delay);
  }

  /**
   * Animate all progress bars in a container
   * @param {HTMLElement} container
   */
  function animateAllProgressBars(container) {
    const bars = container.querySelectorAll('[data-progress]');
    bars.forEach((bar, i) => {
      const value = parseFloat(bar.dataset.progress) || 0;
      animateProgressBar(bar, value, 400 + i * 100);
    });
  }

  // ─── RADIAL PROGRESS ANIMATION ────────────────────────────────────────────

  /**
   * Animate a radial (SVG circle) progress
   * @param {SVGCircleElement} circleEl - The .radial-fill circle element
   * @param {number} value             - Percentage 0–100
   * @param {number} radius            - Circle radius
   * @param {number} delay
   */
  function animateRadial(circleEl, value, radius = 52, delay = 400) {
    if (!circleEl) return;
    const circumference = 2 * Math.PI * radius;
    circleEl.style.strokeDasharray = circumference;
    circleEl.style.strokeDashoffset = circumference;

    setTimeout(() => {
      const offset = circumference - (value / 100) * circumference;
      circleEl.style.strokeDashoffset = offset;
    }, delay);
  }

  /**
   * Initialize all radial progress elements in the page
   * Looks for [data-radial-value] attribute
   */
  function initRadials(container = document) {
    container.querySelectorAll('[data-radial-value]').forEach(svg => {
      const value = parseFloat(svg.dataset.radialValue) || 0;
      const radius = parseFloat(svg.dataset.radialRadius) || 52;
      const circle = svg.querySelector('.radial-fill');
      if (circle) animateRadial(circle, value, radius);
    });
  }

  // ─── BAR CHART ANIMATION ──────────────────────────────────────────────────

  /**
   * Animate a bar chart – grows bars upward
   * @param {HTMLElement} container - Container with .chart-bar elements
   * @param {number[]} values       - Array of values (0–100 normalized)
   * @param {number} delay
   */
  function animateBarChart(container, values, delay = 400) {
    const bars = container.querySelectorAll('.chart-bar');
    bars.forEach((bar, i) => {
      bar.style.height = '0%';
      setTimeout(() => {
        const h = values[i] !== undefined ? values[i] : parseFloat(bar.dataset.value) || 0;
        bar.style.height = `${h}%`;
      }, delay + i * 80);
    });
  }

  // ─── LINE CHART (SVG) ──────────────────────────────────────────────────────

  /**
   * Draw a simple SVG line chart
   * @param {HTMLElement|string} containerOrId
   * @param {number[]} data
   * @param {object} options
   */
  function drawLineChart(containerOrId, data, options = {}) {
    const container = typeof containerOrId === 'string'
      ? document.getElementById(containerOrId)
      : containerOrId;
    if (!container) return;

    const {
      width     = container.clientWidth || 300,
      height    = 120,
      color     = '#63B3ED',
      fillColor = 'rgba(99,179,237,0.1)',
      strokeWidth = 2,
      showDots  = true,
      showArea  = true,
      animated  = true,
    } = options;

    if (data.length < 2) return;

    const padding = 8;
    const min = Math.min(...data);
    const max = Math.max(...data);
    const range = max - min || 1;

    const points = data.map((v, i) => ({
      x: padding + (i / (data.length - 1)) * (width - padding * 2),
      y: height - padding - ((v - min) / range) * (height - padding * 2),
    }));

    const pathD = points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x.toFixed(1)} ${p.y.toFixed(1)}`).join(' ');
    const areaD = pathD + ` L ${points[points.length - 1].x} ${height} L ${points[0].x} ${height} Z`;

    const svgNS = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(svgNS, 'svg');
    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
    svg.setAttribute('width', '100%');
    svg.setAttribute('height', height);
    svg.style.overflow = 'visible';

    if (showArea) {
      const area = document.createElementNS(svgNS, 'path');
      area.setAttribute('d', areaD);
      area.setAttribute('fill', fillColor);
      svg.appendChild(area);
    }

    const line = document.createElementNS(svgNS, 'path');
    line.setAttribute('d', pathD);
    line.setAttribute('fill', 'none');
    line.setAttribute('stroke', color);
    line.setAttribute('stroke-width', strokeWidth);
    line.setAttribute('stroke-linecap', 'round');
    line.setAttribute('stroke-linejoin', 'round');

    if (animated) {
      const length = line.getTotalLength ? line.getTotalLength() : 1000;
      line.style.strokeDasharray  = length;
      line.style.strokeDashoffset = length;
      line.style.transition = 'stroke-dashoffset 1.2s cubic-bezier(0.16,1,0.3,1)';
      svg.appendChild(line);
      requestAnimationFrame(() => {
        setTimeout(() => { line.style.strokeDashoffset = '0'; }, 300);
      });
    } else {
      svg.appendChild(line);
    }

    if (showDots) {
      points.forEach(p => {
        const dot = document.createElementNS(svgNS, 'circle');
        dot.setAttribute('cx', p.x);
        dot.setAttribute('cy', p.y);
        dot.setAttribute('r', 3);
        dot.setAttribute('fill', color);
        dot.setAttribute('stroke', '#0B0F1A');
        dot.setAttribute('stroke-width', 1.5);
        svg.appendChild(dot);
      });
    }

    container.innerHTML = '';
    container.appendChild(svg);
  }

  // ─── COUNTER ANIMATION ────────────────────────────────────────────────────

  /**
   * Animate a number counter from 0 to target
   * @param {HTMLElement} el
   * @param {number} target
   * @param {number} duration - ms
   * @param {string} suffix   - e.g. 'CTC', '%'
   */
  function animateCounter(el, target, duration = 1000, suffix = '') {
    if (!el) return;
    const start = performance.now();
    const startVal = 0;

    function update(now) {
      const elapsed = now - start;
      const progress = Math.min(elapsed / duration, 1);
      const ease = 1 - Math.pow(1 - progress, 3); // easeOutCubic
      const value = Math.round(startVal + (target - startVal) * ease);
      el.textContent = value.toLocaleString() + (suffix ? ` ${suffix}` : '');
      if (progress < 1) requestAnimationFrame(update);
    }

    requestAnimationFrame(update);
  }

  /**
   * Init all [data-counter] elements in container
   */
  function initCounters(container = document) {
    container.querySelectorAll('[data-counter]').forEach(el => {
      const target = parseFloat(el.dataset.counter) || 0;
      const suffix = el.dataset.suffix || '';
      animateCounter(el, target, 1200, suffix);
    });
  }

  // ─── PARTICLES SYSTEM ─────────────────────────────────────────────────────

  function initParticles(canvasId = 'particles-canvas', count = 40) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');

    canvas.width  = window.innerWidth;
    canvas.height = window.innerHeight;

    const particles = Array.from({ length: count }, () => ({
      x:    Math.random() * canvas.width,
      y:    Math.random() * canvas.height,
      r:    Math.random() * 1.5 + 0.5,
      dx:   (Math.random() - 0.5) * 0.4,
      dy:   (Math.random() - 0.5) * 0.4,
      opacity: Math.random() * 0.4 + 0.1,
    }));

    function draw() {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      particles.forEach(p => {
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
        ctx.fillStyle = `rgba(99,179,237,${p.opacity})`;
        ctx.fill();

        p.x += p.dx;
        p.y += p.dy;

        if (p.x < 0) p.x = canvas.width;
        if (p.x > canvas.width) p.x = 0;
        if (p.y < 0) p.y = canvas.height;
        if (p.y > canvas.height) p.y = 0;
      });
      requestAnimationFrame(draw);
    }

    draw();

    window.addEventListener('resize', () => {
      canvas.width  = window.innerWidth;
      canvas.height = window.innerHeight;
    });
  }

  // ─── MICRO-INTERACTIONS ───────────────────────────────────────────────────

  /**
   * Add ripple effect on click
   * @param {HTMLElement} el
   */
  function addRipple(el) {
    el.addEventListener('click', (e) => {
      const rect = el.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;

      const ripple = document.createElement('span');
      ripple.style.cssText = `
        position: absolute;
        width: 0; height: 0;
        border-radius: 50%;
        background: rgba(255,255,255,0.15);
        transform: translate(-50%, -50%);
        left: ${x}px; top: ${y}px;
        animation: ripple-anim 0.6s ease-out forwards;
        pointer-events: none;
        z-index: 99;
      `;
      el.style.position = 'relative';
      el.style.overflow = 'hidden';
      el.appendChild(ripple);
      setTimeout(() => ripple.remove(), 700);
    });
  }

  /**
   * Init all ripple elements
   */
  function initRipples(container = document) {
    container.querySelectorAll('.btn, .glass-card, .action-card, .project-card').forEach(addRipple);
  }

  // ─── TOOLTIP ──────────────────────────────────────────────────────────────

  function initTooltips(container = document) {
    container.querySelectorAll('[data-tooltip]').forEach(el => {
      el.addEventListener('mouseenter', (e) => {
        const tip = document.createElement('div');
        tip.className = 'tooltip';
        tip.textContent = el.dataset.tooltip;
        tip.style.cssText = `
          position: fixed;
          background: rgba(11,15,26,0.95);
          border: 1px solid rgba(99,179,237,0.3);
          color: rgba(255,255,255,0.9);
          font-size: 12px;
          padding: 5px 10px;
          border-radius: 6px;
          z-index: 9999;
          pointer-events: none;
          white-space: nowrap;
          backdrop-filter: blur(10px);
        `;
        document.body.appendChild(tip);

        const rect = el.getBoundingClientRect();
        tip.style.top  = `${rect.top - tip.offsetHeight - 8}px`;
        tip.style.left = `${rect.left + rect.width / 2 - tip.offsetWidth / 2}px`;

        el._tooltip = tip;
      });

      el.addEventListener('mouseleave', () => {
        if (el._tooltip) { el._tooltip.remove(); el._tooltip = null; }
      });
    });
  }

  // ─── GLOBAL INIT ──────────────────────────────────────────────────────────

  /**
   * Initialize all animations for a slide or the full page
   * @param {HTMLElement} container
   */
  function initAll(container = document.body) {
    setTimeout(() => animateSlideIn(container), 100);
    setTimeout(() => animateAllProgressBars(container), 200);
    setTimeout(() => initRadials(container), 300);
    setTimeout(() => initCounters(container), 200);
    initRipples(container);
    initTooltips(container);
  }

  // CSS keyframes for ripple – inject once
  if (!document.getElementById('anim-styles')) {
    const style = document.createElement('style');
    style.id = 'anim-styles';
    style.textContent = `
      @keyframes ripple-anim {
        to { width: 300px; height: 300px; opacity: 0; }
      }
    `;
    document.head.appendChild(style);
  }

  return {
    animateSlideIn,
    staggerElements,
    fadeIn,
    animateProgressBar,
    animateAllProgressBars,
    animateRadial,
    initRadials,
    animateBarChart,
    drawLineChart,
    animateCounter,
    initCounters,
    initParticles,
    addRipple,
    initRipples,
    initTooltips,
    initAll,
  };
})();

window.AnimationSystem = AnimationSystem;
