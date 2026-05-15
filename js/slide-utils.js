/**
 * 2030B – Shared Slide Utilities
 * Used by all 50 slides for common rendering patterns
 */

// ─── FOOTER NAV BUILDER ──────────────────────────────────────────────────────

function buildFooterNav(activeIndex, lang) {
  const isAr = lang === 'ar';
  const items = isAr
    ? [
        { icon: '🏠', label: 'الرئيسية', badge: 0 },
        { icon: '🚀', label: 'المشاريع', badge: 2 },
        { icon: '💎', label: 'المحفظة', badge: 0 },
        { icon: '🧠', label: 'التعلم', badge: 3 },
        { icon: '👤', label: 'الملف', badge: 0 },
      ]
    : [
        { icon: '🏠', label: 'Home',     badge: 0 },
        { icon: '🚀', label: 'Projects', badge: 2 },
        { icon: '💎', label: 'Wallet',   badge: 0 },
        { icon: '🧠', label: 'Learn',    badge: 3 },
        { icon: '👤', label: 'Profile',  badge: 0 },
      ];

  return `<nav class="slide-footer-nav">
    ${items.map((item, i) => `
      <div class="nav-item ${i === activeIndex ? 'active' : ''}">
        <span class="nav-icon">${item.icon}</span>
        <span class="nav-label">${item.label}</span>
        ${item.badge > 0 ? `<span class="nav-badge">${item.badge}</span>` : ''}
      </div>
    `).join('')}
  </nav>`;
}

// ─── SLIDE HEADER BUILDER ────────────────────────────────────────────────────

function buildSlideHeader(data, rightBtn = '') {
  return `
    <div class="slide-header fade-in">
      <div class="slide-header-top">
        <div>
          <h1>${data.header}</h1>
          <h2>${data.subtitle}</h2>
        </div>
        ${rightBtn ? `<div class="header-icon-btn">${rightBtn}</div>` : ''}
      </div>
    </div>`;
}

// ─── STAT CARDS ROW BUILDER ──────────────────────────────────────────────────

function buildStatCard(label, value, icon, trend = '', trendClass = 'positive') {
  return `
    <div class="stat-card fade-in">
      <div class="stat-icon">${icon}</div>
      <div class="stat-label">${label}</div>
      <div class="stat-value text-mono">${value}</div>
      ${trend ? `<div class="stat-trend ${trendClass}">
        <span>${trendClass === 'positive' ? '↑' : '↓'}</span>
        <span>${trend}</span>
      </div>` : ''}
    </div>`;
}

// ─── PROGRESS BAR BUILDER ────────────────────────────────────────────────────

function buildProgressBar(label, value, fillClass = '', showLabel = true) {
  return `
    <div class="progress-row fade-in">
      ${showLabel ? `<div class="progress-row-header">
        <span class="progress-label">${label}</span>
        <span class="progress-value">${value}%</span>
      </div>` : ''}
      <div class="progress-track">
        <div class="progress-fill ${fillClass}" data-progress="${value}" style="width:0%"></div>
      </div>
    </div>`;
}

// ─── TAG CHIPS BUILDER ───────────────────────────────────────────────────────

function buildTagChips(tags, colorMap = {}) {
  const colors = ['blue', 'green', 'purple', 'teal', 'gold', 'red'];
  return tags.map((tag, i) => {
    const color = colorMap[tag] || colors[i % colors.length];
    return `<span class="tag-chip ${color}">${tag}</span>`;
  }).join('');
}

// ─── FILTER BAR BUILDER ──────────────────────────────────────────────────────

function buildFilterBar(filters, activeIndex = 0) {
  return `<div class="filter-bar fade-in">
    ${filters.map((f, i) => `<span class="tag-chip ${i === activeIndex ? 'active' : ''}">${f}</span>`).join('')}
  </div>`;
}

// ─── TIMELINE BUILDER ────────────────────────────────────────────────────────

function buildTimeline(milestones) {
  return `<div class="timeline fade-in">
    ${milestones.map(m => {
      const dotClass = m.status === 'completed' ? 'completed' : m.status === 'in_progress' ? 'active' : '';
      return `
        <div class="timeline-item">
          <div class="timeline-dot ${dotClass}"></div>
          <div class="timeline-title">${m.title}</div>
          <div class="timeline-meta">${m.date || m.year || m.eta || ''} ${m.status ? `• ${m.status.replace('_', ' ')}` : ''}</div>
        </div>`;
    }).join('')}
  </div>`;
}

// ─── CAPABILITY GRID BUILDER ─────────────────────────────────────────────────

function buildCapabilityGrid(dimensions) {
  return `<div class="capability-grid fade-in">
    ${dimensions.map(d => `
      <div class="capability-item">
        <div class="capability-header">
          <span class="capability-name">${d.name}</span>
          <span class="capability-score">${d.value}%</span>
        </div>
        <div class="progress-track">
          <div class="progress-fill" data-progress="${d.value}" style="width:0%"></div>
        </div>
      </div>`).join('')}
  </div>`;
}

// ─── LIST ITEMS BUILDER ──────────────────────────────────────────────────────

function buildListItems(items) {
  return items.map(item => `
    <div class="list-item fade-in">
      <div class="list-item-icon">${item.icon || '📌'}</div>
      <div class="list-item-body">
        <div class="list-item-title">${item.title}</div>
        <div class="list-item-sub">${item.subtitle || item.sub || ''}</div>
      </div>
      <div class="list-item-meta">${item.meta || item.amount || ''}</div>
    </div>`).join('');
}

// ─── AVATAR BUILDER ──────────────────────────────────────────────────────────

function buildAvatar(initials, size = 'avatar-md', gradient = '') {
  return `<div class="avatar ${size}" ${gradient ? `style="background:${gradient}"` : ''}>${initials}</div>`;
}

// ─── RADIAL PROGRESS BUILDER ─────────────────────────────────────────────────

function buildRadialProgress(value, label = '', size = 130, radius = 52) {
  const circumference = 2 * Math.PI * radius;
  return `
    <div class="radial-container fade-in">
      <svg class="radial-svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">
        <defs>
          <linearGradient id="rg${size}" x1="0%" y1="0%" x2="100%" y2="0%">
            <stop offset="0%" style="stop-color:#63B3ED"/>
            <stop offset="100%" style="stop-color:#9F7AEA"/>
          </linearGradient>
        </defs>
        <circle class="radial-bg" cx="${size/2}" cy="${size/2}" r="${radius}" stroke-width="10"/>
        <circle class="radial-fill" cx="${size/2}" cy="${size/2}" r="${radius}" stroke-width="10"
          stroke="url(#rg${size})"
          stroke-dasharray="${circumference.toFixed(1)}"
          stroke-dashoffset="${circumference.toFixed(1)}"
          data-target-offset="${(circumference - (value / 100) * circumference).toFixed(1)}"
          style="transition:stroke-dashoffset 1.2s cubic-bezier(0.16,1,0.3,1)"/>
      </svg>
      <div class="radial-text">
        <span class="pct">${value}%</span>
        <span class="pct-label">${label}</span>
      </div>
    </div>`;
}

// ─── BAR CHART BUILDER ───────────────────────────────────────────────────────

function buildBarChart(data, labels = [], color = null) {
  const max = Math.max(...data, 1);
  const bars = data.map((v, i) => {
    const h = ((v / max) * 100).toFixed(1);
    const grad = color || 'linear-gradient(180deg, #63B3ED, #9F7AEA)';
    return `<div class="chart-bar-item">
      <div class="chart-bar" data-value="${h}" style="height:0%;background:${grad}"></div>
      ${labels[i] ? `<span class="chart-bar-label">${labels[i]}</span>` : ''}
    </div>`;
  }).join('');
  return `<div class="chart-bar-container" style="height:100px">${bars}</div>`;
}

// ─── NOTIFICATION ITEMS ──────────────────────────────────────────────────────

function buildNotifications(notifications) {
  return notifications.map(n => `
    <div class="notification-item ${n.unread ? 'unread' : ''} fade-in">
      <div class="notif-icon-wrap">${n.icon}</div>
      <div class="notif-body">
        <div class="notif-message">${n.message}</div>
        <div class="notif-time">${n.time}</div>
      </div>
    </div>`).join('');
}

// ─── PROJECT CARDS BUILDER ───────────────────────────────────────────────────

function buildProjectCard(p, lang = 'en') {
  const cta = lang === 'ar' ? 'انضم' : 'Join';
  const tags = (p.tags || []).map(t => `<span class="tag-chip blue" style="font-size:10px">${t}</span>`).join('');
  return `
    <div class="project-card fade-in">
      <div class="project-card-top">
        <div>
          <div class="project-card-title">${p.title}</div>
          <div style="display:flex;gap:8px;margin-top:4px;flex-wrap:wrap">
            ${tags}
          </div>
        </div>
        <div class="project-match">${p.match}%</div>
      </div>
      <div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px">
        <span class="project-ctc">${p.ctc}</span>
        <button class="btn btn-primary btn-sm">${cta}</button>
      </div>
    </div>`;
}

// ─── SLIDE INIT HELPER ───────────────────────────────────────────────────────

async function initSlide(slideNum, renderFn) {
  const lang = window.Loader ? window.Loader.currentLang : 'en';
  const data = window.Loader ? await window.Loader.getSlideData(slideNum, lang) : null;
  const brand = window.Loader ? await window.Loader.getBrandData(lang) : { name: '2030B', tagline: 'Be Smarter' };

  const skeleton = document.getElementById('skeleton');
  const content  = document.getElementById('content');

  if (skeleton) skeleton.style.display = 'block';
  if (content)  { content.style.display = 'none'; content.style.opacity = '0'; }

  await new Promise(r => setTimeout(r, 500));

  // If data is null, show a fallback message
  if (!data) {
    if (skeleton) skeleton.style.display = 'none';
    if (content) {
      content.style.display = 'flex';
      content.style.opacity = '1';
      content.innerHTML = `
        <div style="padding:24px;text-align:center;display:flex;flex-direction:column;align-items:center;gap:16px;min-height:60vh;justify-content:center">
          <div style="font-size:48px">⚛️</div>
          <div style="font-size:18px;font-weight:700;color:#fff">Slide ${slideNum}</div>
          <div style="font-size:13px;color:rgba(255,255,255,0.5)">Loading content data...</div>
          <div class="loading-spinner" style="width:32px;height:32px;border-width:2px"></div>
        </div>`;
    }
    return;
  }

  if (content && data) {
    content.innerHTML = renderFn(data, lang, brand);

    if (skeleton) {
      skeleton.style.opacity = '0';
      skeleton.style.transition = 'opacity 0.3s';
      setTimeout(() => skeleton.style.display = 'none', 300);
    }

    content.style.display = 'flex';
    requestAnimationFrame(() => requestAnimationFrame(() => {
      content.style.opacity = '1';
      content.style.transition = 'opacity 0.4s';
      AnimationSystem.initAll(content);

      // Animate progress bars
      setTimeout(() => {
        content.querySelectorAll('[data-progress]').forEach(el => {
          AnimationSystem.animateProgressBar(el, parseFloat(el.dataset.progress));
        });
        // Animate radial offsets
        content.querySelectorAll('[data-target-offset]').forEach(circle => {
          setTimeout(() => {
            circle.style.strokeDashoffset = circle.dataset.targetOffset;
          }, 400);
        });
        // Animate bar charts
        content.querySelectorAll('.chart-bar').forEach((bar, i) => {
          const v = bar.dataset.value || '0';
          setTimeout(() => bar.style.height = `${v}%`, 400 + i * 60);
        });
      }, 200);
    }));
  }

  // Init footer nav language
  const footerEl = document.getElementById('footer-nav');
  if (footerEl) {
    footerEl.innerHTML = buildFooterNav(0, lang);
  }

  // Language switch listener
  document.addEventListener('langChange', async (e) => {
    const newLang = e.detail.lang;
    const newData = window.Loader ? await window.Loader.getSlideData(slideNum, newLang) : null;
    const newBrand = window.Loader ? await window.Loader.getBrandData(newLang) : brand;
    if (content && newData) {
      content.innerHTML = renderFn(newData, newLang, newBrand);
      content.style.opacity = '1';
      AnimationSystem.initAll(content);
      setTimeout(() => {
        content.querySelectorAll('[data-progress]').forEach(el => {
          AnimationSystem.animateProgressBar(el, parseFloat(el.dataset.progress));
        });
        content.querySelectorAll('[data-target-offset]').forEach(c => {
          setTimeout(() => c.style.strokeDashoffset = c.dataset.targetOffset, 400);
        });
        content.querySelectorAll('.chart-bar').forEach((bar, i) => {
          const v = bar.dataset.value || '0';
          setTimeout(() => bar.style.height = `${v}%`, 400 + i * 60);
        });
      }, 200);
    }
    const footerEl2 = document.getElementById('footer-nav');
    if (footerEl2) footerEl2.innerHTML = buildFooterNav(0, newLang);
  });
}

window.SlideUtils = {
  buildFooterNav, buildSlideHeader, buildStatCard, buildProgressBar,
  buildTagChips, buildFilterBar, buildTimeline, buildCapabilityGrid,
  buildListItems, buildAvatar, buildRadialProgress, buildBarChart,
  buildNotifications, buildProjectCard, initSlide
};
