/**
 * 2030B – Be Smarter
 * CTC Engine v2 — Everything is incentivized
 *
 * Manages:
 *   • Wallet balance (liquid, staked, lifetime)
 *   • Earning events (challenges, projects, mentorship, governance)
 *   • Spending (services, marketplace, certifications)
 *   • Lock mechanism (duration tiers + multipliers)
 *   • Burn (0.05% per transaction)
 *   • Transaction history (in-memory + localStorage)
 *   • Emits events for UI components to react
 */

const CTCEngine = (() => {
  'use strict';

  // ─── Constants ────────────────────────────────────────────────────────────────
  const BURN_RATE = 0.0005; // 0.05% per transaction
  const LOCK_TIERS = [
    { days: 30,  multiplier: 1.0,  label: 'Base',   minAmount: 1000  },
    { days: 90,  multiplier: 1.25, label: '+25%',   minAmount: 5000  },
    { days: 180, multiplier: 1.6,  label: '+60%',   minAmount: 10000 },
    { days: 365, multiplier: 2.0,  label: '+100%',  minAmount: 25000 },
  ];

  const TX_TYPE = {
    EARN:    'earn',
    SPEND:   'spend',
    BURN:    'burn',
    LOCK:    'lock',
    UNLOCK:  'unlock',
    STAKE:   'stake',
    REWARD:  'reward',
  };

  const EARN_SOURCE = {
    CHALLENGE:   'challenge',
    PROJECT:     'project',
    MENTORSHIP:  'mentorship',
    GOVERNANCE:  'governance',
    STAKING:     'staking',
    CONTENT:     'content',
    REFERRAL:    'referral',
    SESSION:     'session',
    BONUS:       'bonus',
  };

  // ─── Wallet State ─────────────────────────────────────────────────────────────
  let _wallet = {
    liquid:   0,
    staked:   0,
    lifetime: 0,
    locks:    [],       // [{ id, amount, lockedAt, unlockAt, multiplier, tier }]
    version:  2,
  };

  let _txHistory = []; // [TXRecord, ...]
  let _pendingRewards = []; // queued rewards not yet claimed

  // ─── Event Bus ────────────────────────────────────────────────────────────────
  const _listeners = {};
  function on(ev, fn) { (_listeners[ev] = _listeners[ev] || []).push(fn); }
  function emit(ev, data) {
    (_listeners[ev] || []).forEach(fn => { try { fn(data); } catch(e) {} });
  }

  // ─── Persistence ─────────────────────────────────────────────────────────────
  function save() {
    try {
      localStorage.setItem('2030b_wallet', JSON.stringify(_wallet));
      localStorage.setItem('2030b_tx', JSON.stringify(_txHistory.slice(-200))); // keep last 200
    } catch(e) {}
  }

  function load() {
    try {
      const w = localStorage.getItem('2030b_wallet');
      if (w) {
        const parsed = JSON.parse(w);
        if (parsed && parsed.version === 2) _wallet = parsed;
      }
      const tx = localStorage.getItem('2030b_tx');
      if (tx) _txHistory = JSON.parse(tx);
    } catch(e) {}
  }

  // ─── Transaction Builder ──────────────────────────────────────────────────────
  function makeTX(type, amount, meta = {}) {
    return {
      id:        `tx_${Date.now()}_${Math.random().toString(36).slice(2,6)}`,
      type,
      amount,
      timestamp: Date.now(),
      balanceAfter: _wallet.liquid,
      ...meta,
    };
  }

  // ─── Earn ─────────────────────────────────────────────────────────────────────
  function earn(amount, source = EARN_SOURCE.BONUS, description = '') {
    if (amount <= 0) return null;

    _wallet.liquid   += amount;
    _wallet.lifetime += amount;

    const tx = makeTX(TX_TYPE.EARN, amount, { source, description, balanceAfter: _wallet.liquid });
    _txHistory.unshift(tx);
    save();

    emit('ctc-earned', { amount, source, description, wallet: { ..._wallet } });
    updateAllDisplays();

    showEarnToast(amount, source);
    console.log(`[CTC] Earned: +${amount} CTC (${source})`);
    return tx;
  }

  // ─── Spend ────────────────────────────────────────────────────────────────────
  function spend(amount, description = '', { skipBurn = false } = {}) {
    const burnAmount = skipBurn ? 0 : Math.ceil(amount * BURN_RATE);
    const totalDeducted = amount + burnAmount;

    if (_wallet.liquid < totalDeducted) {
      emit('ctc-insufficient', { required: totalDeducted, available: _wallet.liquid });
      return null;
    }

    _wallet.liquid -= totalDeducted;

    const tx = makeTX(TX_TYPE.SPEND, amount, { description, burnAmount, balanceAfter: _wallet.liquid });
    _txHistory.unshift(tx);

    if (burnAmount > 0) {
      _txHistory.unshift(makeTX(TX_TYPE.BURN, burnAmount, { description: `Burn fee for: ${description}`, balanceAfter: _wallet.liquid }));
    }

    save();
    emit('ctc-spent', { amount, burnAmount, description, wallet: { ..._wallet } });
    updateAllDisplays();

    console.log(`[CTC] Spent: -${amount} CTC — Burned: ${burnAmount} CTC (${description})`);
    return tx;
  }

  // ─── Lock ────────────────────────────────────────────────────────────────────
  function lock(amount, days) {
    const tier = LOCK_TIERS.find(t => t.days === days);
    if (!tier) { console.warn('[CTC] Unknown lock tier:', days); return null; }
    if (amount < tier.minAmount) {
      emit('ctc-lock-min-error', { required: tier.minAmount, provided: amount });
      return null;
    }
    if (_wallet.liquid < amount) {
      emit('ctc-insufficient', { required: amount, available: _wallet.liquid });
      return null;
    }

    _wallet.liquid -= amount;
    _wallet.staked += amount;

    const lockRecord = {
      id:         `lock_${Date.now()}`,
      amount,
      lockedAt:   Date.now(),
      unlockAt:   Date.now() + days * 86400000,
      days,
      multiplier: tier.multiplier,
      label:      tier.label,
      projectedReward: Math.floor(amount * (tier.multiplier - 1)),
    };

    _wallet.locks.push(lockRecord);

    const tx = makeTX(TX_TYPE.LOCK, amount, { lockId: lockRecord.id, days, multiplier: tier.multiplier, balanceAfter: _wallet.liquid });
    _txHistory.unshift(tx);
    save();

    emit('ctc-locked', { lockRecord, wallet: { ..._wallet } });
    updateAllDisplays();

    console.log(`[CTC] Locked: ${amount} CTC for ${days} days @ ${tier.multiplier}× multiplier`);
    return lockRecord;
  }

  // ─── Unlock (mature locks) ────────────────────────────────────────────────────
  function unlockMature() {
    const now = Date.now();
    const mature = _wallet.locks.filter(l => l.unlockAt <= now);

    mature.forEach(l => {
      const totalReturn = l.amount + l.projectedReward;
      _wallet.liquid += totalReturn;
      _wallet.staked -= l.amount;
      _wallet.lifetime += l.projectedReward;

      const tx = makeTX(TX_TYPE.UNLOCK, totalReturn, { lockId: l.id, principal: l.amount, reward: l.projectedReward, balanceAfter: _wallet.liquid });
      _txHistory.unshift(tx);

      emit('ctc-unlocked', { lockRecord: l, reward: l.projectedReward, wallet: { ..._wallet } });
      console.log(`[CTC] Unlocked: ${l.amount} CTC + ${l.projectedReward} reward`);
    });

    _wallet.locks = _wallet.locks.filter(l => l.unlockAt > now);
    if (mature.length) { save(); updateAllDisplays(); }
    return mature;
  }

  // ─── Award from Session ────────────────────────────────────────────────────────
  function awardFromSession(amount, sessionType, sessionId) {
    return earn(amount, EARN_SOURCE.SESSION, `Session reward: ${sessionType} (${sessionId})`);
  }

  // ─── Award from Challenge ─────────────────────────────────────────────────────
  function awardChallenge(amount, challengeId) {
    return earn(amount, EARN_SOURCE.CHALLENGE, `Challenge: ${challengeId}`);
  }

  // ─── Award from Project ───────────────────────────────────────────────────────
  function awardProject(amount, projectTitle) {
    return earn(amount, EARN_SOURCE.PROJECT, `Project: ${projectTitle}`);
  }

  // ─── Burn ─────────────────────────────────────────────────────────────────────
  function burn(amount, reason = '') {
    if (_wallet.liquid < amount) return null;
    _wallet.liquid -= amount;
    const tx = makeTX(TX_TYPE.BURN, amount, { reason, balanceAfter: _wallet.liquid });
    _txHistory.unshift(tx);
    save();
    emit('ctc-burned', { amount, reason });
    updateAllDisplays();
    return tx;
  }

  // ─── UI Display Updates ───────────────────────────────────────────────────────
  function updateAllDisplays() {
    const { liquid, staked, lifetime } = _wallet;
    const total = liquid + staked;

    // Topbar CTC pill
    const topbarEl = document.getElementById('topbar-ctc');
    if (topbarEl) topbarEl.textContent = liquid.toLocaleString();

    // Balance display component
    const totalEl  = document.getElementById('cbd-total');
    const liquidEl = document.getElementById('cbd-liquid');
    const stakedEl = document.getElementById('cbd-staked');
    const lifeEl   = document.getElementById('cbd-lifetime');

    if (totalEl)  totalEl.textContent  = total.toLocaleString();
    if (liquidEl) liquidEl.textContent = liquid.toLocaleString();
    if (stakedEl) stakedEl.textContent = staked.toLocaleString();
    if (lifeEl)   lifeEl.textContent   = lifetime.toLocaleString();
  }

  // ─── Earn Toast ───────────────────────────────────────────────────────────────
  function showEarnToast(amount, source) {
    let container = document.getElementById('ctc-toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'ctc-toast-container';
      container.style.cssText = 'position:fixed;bottom:80px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;pointer-events:none;';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.style.cssText = `
      background: rgba(104,211,145,0.15);
      border: 1px solid rgba(104,211,145,0.35);
      border-radius: 12px;
      padding: 10px 16px;
      display: flex; align-items: center; gap: 8px;
      font-size: 13px; font-weight: 700; color: #68D391;
      backdrop-filter: blur(12px);
      box-shadow: 0 4px 24px rgba(0,0,0,0.3);
      animation: ctcToastIn 0.3s ease;
    `;
    toast.innerHTML = `⚡ <span>+${amount.toLocaleString()} CTC</span><span style="font-weight:400;color:rgba(255,255,255,0.4);font-size:11px">${source}</span>`;

    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transition = 'opacity 0.3s';
      setTimeout(() => toast.remove(), 300);
    }, 2500);
  }

  // ─── Getters ──────────────────────────────────────────────────────────────────
  function getBalance()   { return { ..._wallet }; }
  function getHistory(limit = 50) { return _txHistory.slice(0, limit); }
  function getLocks()     { return [..._wallet.locks]; }
  function getLockTiers() { return [...LOCK_TIERS]; }
  function canAfford(amount) { return _wallet.liquid >= amount + Math.ceil(amount * BURN_RATE); }

  // ─── Modal Helpers ────────────────────────────────────────────────────────────
  function openLock()    { /* Hook to SpendConfirmationModal or LockMechanism UI */ emit('ui-open-lock', {}); }
  function openSend()    { emit('ui-open-send', {}); }
  function openHistory() { emit('ui-open-history', {}); }

  // ─── Init ─────────────────────────────────────────────────────────────────────
  function init(seedBalance = null) {
    load();

    // Seed for first-time users
    if (seedBalance !== null && _wallet.lifetime === 0) {
      _wallet.liquid   = seedBalance;
      _wallet.lifetime = seedBalance;
      save();
    }

    unlockMature();
    updateAllDisplays();

    // Check locks every minute
    setInterval(unlockMature, 60000);

    console.log(`[CTC] Engine initialized — Balance: ${_wallet.liquid} liquid, ${_wallet.staked} staked`);
    emit('ctc-init', { wallet: { ..._wallet } });
  }

  // ─── Inject CSS for toast animation once ──────────────────────────────────────
  (function injectStyles() {
    if (document.getElementById('ctc-engine-styles')) return;
    const s = document.createElement('style');
    s.id = 'ctc-engine-styles';
    s.textContent = `@keyframes ctcToastIn { from { transform:translateX(20px);opacity:0; } to { transform:translateX(0);opacity:1; } }`;
    document.head.appendChild(s);
  })();

  // ─── Public API ───────────────────────────────────────────────────────────────
  return {
    TX_TYPE, EARN_SOURCE, LOCK_TIERS,
    init,
    earn,
    spend,
    lock,
    burn,
    unlockMature,
    awardFromSession,
    awardChallenge,
    awardProject,
    canAfford,
    getBalance,
    getHistory,
    getLocks,
    getLockTiers,
    openLock,
    openSend,
    openHistory,
    on,
    emit,
  };
})();

// Auto-init with demo seed balance for new users
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => CTCEngine.init(12450));
} else {
  CTCEngine.init(12450);
}
