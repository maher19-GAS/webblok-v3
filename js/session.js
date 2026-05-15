/**
 * session.js – 2030B Session & Global State Engine
 * Manages: CTC balance, cognitive progress, tier level, active sessions
 * Integrates with: slide-engine.js, ctc-engine.js, animations.js
 * Version: 3.0 | Vol 1+2+3 unified — NAE hooks, Cognitive Yield, UBCTC, Spatial
 */

window.SessionEngine = (function () {
  'use strict';

  // ─── Default State ────────────────────────────────────────────────────────
  const DEFAULTS = {
    user:       { id:null, name:'Alex Johnson', username:'@alex_j_2030b', avatar:'AJ', lang:'en', joined:null },
    ctc:        { liquid:12450, staked:4000, in_projects:250, pending:0 },
    cognition:  { level:8, level_name:'Quantum Thinker', overall:67, streak:14,
                  dimensions:{ analytical:85, creative:61, systems:68, emotional:58, pattern:79, memory:72, consciousness:43 },
                  flow_ratio:0, cognitive_velocity:0, yield_tier:'emerging' },
    tier:       { name:'Architect', index:2, locked:false },
    session:    { active:false, type:null, startTime:null, duration:0, score:0, ctcPending:0, slideIndex:null },
    reputation: { score:4250, level:8, nextLevel:5000 },
    prefs:      { darkMode:true, rtl:false, reducedMotion:false, highContrast:false },
    nae:        { active:false, mode:'focus', load_level:0.5, flow_state:false, adaptations_today:0, biometric_signals:{} },
    spatial:    { rooms_visited:[], current_room:null, cosmos_nodes:0 },
    ubctc:      { enrolled:false, monthly_allocation:0, last_distribution:null },
  };

  let _state = JSON.parse(JSON.stringify(DEFAULTS));
  let _listeners = {};
  let _sessionTimer = null;

  // ─── Init ─────────────────────────────────────────────────────────────────
  function init(overrides) {
    if (overrides) _merge(_state, overrides);
    _loadFromStorage();
    _emit('init', { state: getSnapshot() });
    return SessionEngine;
  }

  function reset() {
    _state = JSON.parse(JSON.stringify(DEFAULTS));
    _clearStorage();
    _emit('reset', {});
    return SessionEngine;
  }

  // ─── CTC ──────────────────────────────────────────────────────────────────
  function earnCTC(amount, source) {
    const n = Math.max(0, +amount || 0);
    _state.ctc.liquid += n;
    _emit('ctcEarned', { amount:n, source, balance:_state.ctc.liquid });
    _saveToStorage();
    return n;
  }

  function spendCTC(amount, purpose) {
    const n = Math.max(0, +amount || 0);
    if (_state.ctc.liquid < n) { _emit('insufficientFunds', { needed:n, have:_state.ctc.liquid }); return false; }
    _state.ctc.liquid -= n;
    _emit('ctcSpent', { amount:n, purpose, balance:_state.ctc.liquid });
    _saveToStorage();
    return true;
  }

  function stakeCTC(amount, days) {
    if (!spendCTC(amount, 'staking')) return false;
    _state.ctc.staked += amount;
    _emit('ctcStaked', { amount, days, totalStaked:_state.ctc.staked });
    _saveToStorage();
    return true;
  }

  function getTotalCTC() { return _state.ctc.liquid + _state.ctc.staked + _state.ctc.in_projects + _state.ctc.pending; }

  // ─── Session ──────────────────────────────────────────────────────────────
  function startSession(type, opts) {
    if (_state.session.active) { _emit('sessionAlreadyActive', { type:_state.session.type }); return false; }
    _state.session = { active:true, type, startTime:Date.now(), duration:0, score:0, ctcPending:(opts&&opts.ctcReward)||0, slideIndex:(opts&&opts.slideIndex)||null };
    _sessionTimer = setInterval(() => {
      _state.session.duration = Math.floor((Date.now()-_state.session.startTime)/1000);
      _emit('sessionTick', { duration:_state.session.duration });
    }, 1000);
    _emit('sessionStart', { type, session:{..._state.session} });
    return true;
  }

  function endSession(result) {
    if (!_state.session.active) return null;
    clearInterval(_sessionTimer); _sessionTimer = null;
    result = result || {};
    const score = result.score || _state.session.score;
    const ctcEarned = result.ctcEarned || _state.session.ctcPending;
    _state.session.active = false; _state.session.score = score;
    const summary = { ..._state.session, score, ctcEarned, endTime:Date.now() };
    if (ctcEarned > 0) earnCTC(ctcEarned, _state.session.type);
    if (result.repGained) gainReputation(result.repGained);
    if (result.dimGains)  applyDimensionGains(result.dimGains);
    _emit('sessionEnd', { summary });
    _saveToStorage();
    return summary;
  }

  function interruptSession() {
    clearInterval(_sessionTimer); _sessionTimer = null;
    _state.session.active = false;
    _emit('sessionInterrupted', { pending:_state.session.ctcPending });
    return SessionEngine;
  }

  function getSessionState() { return {..._state.session}; }
  function isSessionActive()  { return _state.session.active; }

  // ─── Cognition ────────────────────────────────────────────────────────────
  function applyDimensionGains(gains) {
    let changed = false;
    Object.entries(gains||{}).forEach(([dim, delta]) => {
      if (_state.cognition.dimensions[dim] !== undefined) {
        _state.cognition.dimensions[dim] = Math.min(100, _state.cognition.dimensions[dim] + (+delta));
        changed = true;
      }
    });
    if (changed) { _recalcOverall(); _emit('dimensionsUpdated', { dims:{..._state.cognition.dimensions}, overall:_state.cognition.overall }); _saveToStorage(); }
    return SessionEngine;
  }

  function _recalcOverall() {
    const v = Object.values(_state.cognition.dimensions);
    _state.cognition.overall = Math.round(v.reduce((s,x)=>s+x,0)/v.length);
  }

  function incrementStreak() {
    _state.cognition.streak++;
    _emit('streakUpdate', { streak:_state.cognition.streak });
    _saveToStorage();
    return _state.cognition.streak;
  }

  // ─── Reputation & Level ───────────────────────────────────────────────────
  function gainReputation(points) {
    _state.reputation.score += +points;
    const leveled = _checkLevelUp();
    _emit('reputationGained', { points:+points, total:_state.reputation.score, leveled });
    _saveToStorage();
    return _state.reputation.score;
  }

  function _checkLevelUp() {
    if (_state.reputation.score >= _state.reputation.nextLevel) {
      _state.cognition.level++;
      _state.reputation.nextLevel = Math.round(_state.reputation.nextLevel * 1.5);
      _emit('levelUp', { newLevel:_state.cognition.level, nextTarget:_state.reputation.nextLevel });
      return true;
    }
    return false;
  }

  // ─── Tier ─────────────────────────────────────────────────────────────────
  const TIERS = [
    {name:'Explorer', index:0, ctc_unlock:0},
    {name:'Thinker',  index:1, ctc_unlock:1000},
    {name:'Architect',index:2, ctc_unlock:5000},
    {name:'Visionary',index:3, ctc_unlock:20000},
  ];

  function checkTierUpgrade() {
    const total = getTotalCTC();
    const eligible = TIERS.filter(t => total >= t.ctc_unlock).pop();
    if (eligible && eligible.index > _state.tier.index) {
      const prev = {..._state.tier};
      _state.tier = {name:eligible.name, index:eligible.index, locked:false};
      _emit('tierUpgrade', {from:prev, to:{..._state.tier}});
      _saveToStorage(); return true;
    }
    return false;
  }

  function getTier() { return {..._state.tier}; }

  // ─── NAE Integration (Vol 3) ─────────────────────────────────────────────
  function updateNAE(signals) {
    Object.assign(_state.nae.biometric_signals, signals || {});
    const lat = _state.nae.biometric_signals.response_latency_norm || 0.5;
    const err = _state.nae.biometric_signals.error_rate || 0;
    _state.nae.load_level = Math.min(1, (lat * 0.55) + (err * 0.45));
    _state.nae.flow_state = _state.nae.load_level >= 0.35 && _state.nae.load_level <= 0.70;
    _state.nae.adaptations_today++;
    _emit('naeUpdate', { nae: {..._state.nae} });
    _saveToStorage();
    return SessionEngine;
  }

  function setNAEMode(mode) {
    const valid = ['focus','creative','memory','recovery','sprint'];
    if (valid.includes(mode)) { _state.nae.mode = mode; _emit('naeMode', { mode }); }
    return SessionEngine;
  }

  function getNAEState() { return { ..._state.nae }; }

  // ─── Spatial (Vol 3) ─────────────────────────────────────────────────────
  function enterSpatialRoom(roomId) {
    _state.spatial.current_room = roomId;
    if (!_state.spatial.rooms_visited.includes(roomId)) _state.spatial.rooms_visited.push(roomId);
    _emit('spatialEnter', { room: roomId, visited: _state.spatial.rooms_visited.length });
    return SessionEngine;
  }

  function exitSpatialRoom() {
    const prev = _state.spatial.current_room;
    _state.spatial.current_room = null;
    _emit('spatialExit', { room: prev });
    return SessionEngine;
  }

  function addCosmosNodes(n) {
    _state.spatial.cosmos_nodes += (+n || 0);
    _emit('cosmosGrow', { nodes: _state.spatial.cosmos_nodes });
    return SessionEngine;
  }

  function getSpatialState() { return { ..._state.spatial }; }

  // ─── UBCTC (Vol 3) ───────────────────────────────────────────────────────
  function enrollUBCTC(monthlyAmount) {
    _state.ubctc.enrolled = true;
    _state.ubctc.monthly_allocation = +monthlyAmount || 800;
    _emit('ubctcEnroll', { monthly: _state.ubctc.monthly_allocation });
    _saveToStorage();
    return SessionEngine;
  }

  function distributeUBCTC() {
    if (!_state.ubctc.enrolled) return false;
    earnCTC(_state.ubctc.monthly_allocation, 'ubctc');
    _state.ubctc.last_distribution = Date.now();
    _emit('ubctcDistribution', { amount: _state.ubctc.monthly_allocation });
    _saveToStorage();
    return true;
  }

  function getUBCTCState() { return { ..._state.ubctc }; }

  // ─── Cognitive Yield (Vol 3) ──────────────────────────────────────────────
  const YIELD_TIERS = [
    { tier:'emerging',   min:1,  max:10,  monthly_min:150,  monthly_max:500   },
    { tier:'developing', min:11, max:25,  monthly_min:500,  monthly_max:2000  },
    { tier:'advanced',   min:26, max:50,  monthly_min:2000, monthly_max:7500  },
    { tier:'elite',      min:51, max:100, monthly_min:7500, monthly_max:25000 },
  ];

  function calcCognitiveYield(growthPct) {
    const tier = YIELD_TIERS.find(t => growthPct >= t.min && growthPct <= t.max) || YIELD_TIERS[0];
    _state.cognition.yield_tier = tier.tier;
    const yield_est = Math.round(tier.monthly_min + ((growthPct - tier.min) / (tier.max - tier.min + 1)) * (tier.monthly_max - tier.monthly_min));
    _emit('cognitiveYield', { tier: tier.tier, growth_pct: growthPct, monthly_yield: yield_est });
    return yield_est;
  }

  function getCognitionState() { return { ..._state.cognition, dims: {..._state.cognition.dimensions} }; }

  // ─── Prefs ────────────────────────────────────────────────────────────────
  function setLang(lang) {
    _state.user.lang = lang; _state.prefs.rtl = (lang==='ar');
    document.documentElement.lang = lang;
    document.documentElement.dir  = _state.prefs.rtl ? 'rtl' : 'ltr';
    _emit('langChange', {lang, rtl:_state.prefs.rtl}); _saveToStorage();
    return SessionEngine;
  }

  function setPref(key, value) {
    if (key in _state.prefs) { _state.prefs[key]=value; _emit('prefChange',{key,value}); _saveToStorage(); }
    return SessionEngine;
  }

  // ─── Persistence ──────────────────────────────────────────────────────────
  const KEY = '2030b_session_v3';
  function _saveToStorage() {
    try { localStorage.setItem(KEY, JSON.stringify({ctc:_state.ctc, cognition:_state.cognition, tier:_state.tier, reputation:_state.reputation, prefs:_state.prefs, user:_state.user, nae:_state.nae, spatial:_state.spatial, ubctc:_state.ubctc})); } catch(e) {}
  }
  function _loadFromStorage() {
    try { const r=localStorage.getItem(KEY); if(r) _merge(_state, JSON.parse(r)); } catch(e) {}
  }
  function _clearStorage() { try { localStorage.removeItem(KEY); } catch(e) {} }

  // ─── Utils ────────────────────────────────────────────────────────────────
  function _merge(t, s) {
    Object.keys(s).forEach(k => {
      if (s[k]&&typeof s[k]==='object'&&!Array.isArray(s[k])&&t[k]&&typeof t[k]==='object') _merge(t[k],s[k]);
      else t[k]=s[k];
    });
  }

  // ─── Events ───────────────────────────────────────────────────────────────
  function on(event, handler) { if(!_listeners[event])_listeners[event]=[]; _listeners[event].push(handler); return SessionEngine; }
  function off(event, handler) { if(_listeners[event]) _listeners[event]=_listeners[event].filter(h=>h!==handler); return SessionEngine; }
  function _emit(event, data) { (_listeners[event]||[]).forEach(h=>{try{h(data);}catch(e){console.warn('[Session]',e);}}); }

  // ─── Snapshot ─────────────────────────────────────────────────────────────
  function getSnapshot() {
    return {
      user:       {..._state.user},
      ctc:        {..._state.ctc, total:getTotalCTC()},
      cognition:  {..._state.cognition, dims:{..._state.cognition.dimensions}},
      tier:       {..._state.tier},
      session:    {..._state.session},
      reputation: {..._state.reputation},
      prefs:      {..._state.prefs},
      nae:        {..._state.nae},
      spatial:    {..._state.spatial},
      ubctc:      {..._state.ubctc},
    };
  }

  return {
    init, reset, getSnapshot,
    earnCTC, spendCTC, stakeCTC, getTotalCTC,
    startSession, endSession, interruptSession, getSessionState, isSessionActive,
    applyDimensionGains, incrementStreak, getCognitionState,
    gainReputation, checkTierUpgrade, getTier,
    setLang, setPref,
    updateNAE, setNAEMode, getNAEState,
    enterSpatialRoom, exitSpatialRoom, addCosmosNodes, getSpatialState,
    enrollUBCTC, distributeUBCTC, getUBCTCState,
    calcCognitiveYield,
    on, off,
  };

})();
