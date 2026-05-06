/**
 * Market Assets Pro — JS v8
 * - Filtros activos corregidos
 * - Períodos: 1D, 1S, 1M, 6M, 1A, 2A, 5A
 * - Predicciones con 2 años de datos automáticamente
 * - Todo en español
 */

(function () {
  'use strict';

  var cfg      = window.MAP_Config || {};
  var API_BASE = (cfg.api_url || '/wp-json/map/v1/').replace(/\/+$/, '') + '/';

  /* ── Espera a que #map-app exista ──────────────────────────────────────── */
  function waitFor(cb, n) {
    n = n || 0;
    var el = document.getElementById('map-app');
    if (el) { cb(el); return; }
    if (n > 60) { console.error('[MAP] #map-app no encontrado'); return; }
    setTimeout(function () { waitFor(cb, n + 1); }, 100);
  }
  waitFor(function (app) { MAP_init(app); });

  /* ── HTTP ──────────────────────────────────────────────────────────────── */
  function apiGet(path) {
    return fetch(API_BASE + path, { method: 'GET', headers: { 'Content-Type': 'application/json' } })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .catch(function (e) { console.warn('[MAP] GET ' + path, e.message); return null; });
  }
  function apiPost(path, data) {
    // map_nonce + map_user_id se verifican dentro del callback PHP.
    // NO enviamos X-WP-Nonce porque WordPress lo rechaza con 403 cuando
    // no puede asociarlo a un usuario autenticado (setup con IP directa).
    var payload = Object.assign({}, data, {
      map_nonce:   cfg.map_nonce || '',
      map_user_id: cfg.user_id   || 0
    });
    return fetch(API_BASE + path, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function (r) { return r.json(); }).catch(function () { return null; });
  }

  /* ── Formatters ────────────────────────────────────────────────────────── */
  function fmtP(n) {
    if (n == null || isNaN(+n)) return '—';
    n = +n;
    if (n >= 10000) return '$' + n.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (n >= 1)     return '$' + n.toFixed(4);
    return '$' + n.toFixed(6);
  }
  function fmtPct(n) {
    if (n == null || isNaN(+n)) return '—';
    n = +n; return (n >= 0 ? '+' : '') + n.toFixed(2) + '%';
  }
  function fmtVol(n) {
    if (!n) return '—'; n = +n;
    if (n >= 1e12) return (n / 1e12).toFixed(2) + 'B';
    if (n >= 1e9)  return (n / 1e9).toFixed(2) + 'MM';
    if (n >= 1e6)  return (n / 1e6).toFixed(2) + 'M';
    if (n >= 1e3)  return (n / 1e3).toFixed(2) + 'K';
    return String(n);
  }
  function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  /* ── Señal / Sentimiento ───────────────────────────────────────────────── */
  function sig(p) {
    if (p == null) return { l: '—', c: '#94a3b8' };
    p = +p;
    if (p > 3)  return { l: 'Compra fuerte', c: '#34d399' };
    if (p > 1)  return { l: 'Comprar',        c: '#4ade80' };
    if (p > -1) return { l: 'Mantener',       c: '#94a3b8' };
    if (p > -3) return { l: 'Vender',         c: '#fb923c' };
    return            { l: 'Venta fuerte',    c: '#f87171' };
  }
  function sent(p) {
    if (p == null || (+p >= -1 && +p <= 1))
      return { l: 'Neutral',  bg: 'rgba(100,116,139,.18)', c: '#94a3b8', br: 'rgba(100,116,139,.3)' };
    if (+p > 1)
      return { l: 'Alcista',  bg: 'rgba(34,197,94,.15)',   c: '#4ade80', br: 'rgba(34,197,94,.35)' };
    return  { l: 'Bajista',   bg: 'rgba(239,68,68,.15)',   c: '#f87171', br: 'rgba(239,68,68,.35)' };
  }

  /* ── Iconos ─────────────────────────────────────────────────────────────── */
  var ICONS = {
    'BTC-USD':'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PGNpcmNsZSBjeD0iMTYiIGN5PSIxNiIgcj0iMTYiIGZpbGw9IiNmNzkzMWEiLz48dGV4dCB4PSIxNiIgeT0iMjIiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCxzYW5zLXNlcmlmIiBmb250LXdlaWdodD0iOTAwIiBmb250LXNpemU9IjE2IiBmaWxsPSIjZmZmIj7igr88L3RleHQ+PC9zdmc+',
    'ETH-USD':'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PGNpcmNsZSBjeD0iMTYiIGN5PSIxNiIgcj0iMTYiIGZpbGw9IiM2MjdlZWEiLz48dGV4dCB4PSIxNiIgeT0iMjIiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCxzYW5zLXNlcmlmIiBmb250LXdlaWdodD0iNzAwIiBmb250LXNpemU9IjE2IiBmaWxsPSIjZmZmIj7OnjwvdGV4dD48L3N2Zz4=',
    'SOL-USD':'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PGNpcmNsZSBjeD0iMTYiIGN5PSIxNiIgcj0iMTYiIGZpbGw9IiM5OTQ1ZmYiLz48ZGVmcz48bGluZWFyR3JhZGllbnQgaWQ9ImciIHgxPSIwJSIgeTE9IjAlIiB4Mj0iMTAwJSIgeTI9IjEwMCUiPjxzdG9wIG9mZnNldD0iMCUiIHN0eWxlPSJzdG9wLWNvbG9yOiM5OTQ1ZmYiLz48c3RvcCBvZmZzZXQ9IjEwMCUiIHN0eWxlPSJzdG9wLWNvbG9yOiMxNGYxOTUiLz48L2xpbmVhckdyYWRpZW50PjwvZGVmcz48Y2lyY2xlIGN4PSIxNiIgY3k9IjE2IiByPSIxNiIgZmlsbD0idXJsKCNnKSIvPjx0ZXh0IHg9IjE2IiB5PSIyMSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZm9udC1mYW1pbHk9IkFyaWFsLHNhbnMtc2VyaWYiIGZvbnQtd2VpZ2h0PSI3MDAiIGZvbnQtc2l6ZT0iMTEiIGZpbGw9IiNmZmYiPlNPTDwvdGV4dD48L3N2Zz4=',
    'BNB-USD':'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PGNpcmNsZSBjeD0iMTYiIGN5PSIxNiIgcj0iMTYiIGZpbGw9IiNmM2JhMmYiLz48dGV4dCB4PSIxNiIgeT0iMjIiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCxzYW5zLXNlcmlmIiBmb250LXdlaWdodD0iNzAwIiBmb250LXNpemU9IjEzIiBmaWxsPSIjMWExYTJlIj5CTkI8L3RleHQ+PC9zdmc+',
    'XRP-USD':'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PGNpcmNsZSBjeD0iMTYiIGN5PSIxNiIgcj0iMTYiIGZpbGw9IiMwMGFhZTQiLz48dGV4dCB4PSIxNiIgeT0iMjIiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCxzYW5zLXNlcmlmIiBmb250LXdlaWdodD0iNzAwIiBmb250LXNpemU9IjEzIiBmaWxsPSIjZmZmIj5YUlA8L3RleHQ+PC9zdmc+',
    'ADA-USD':'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PGNpcmNsZSBjeD0iMTYiIGN5PSIxNiIgcj0iMTYiIGZpbGw9IiMwMDMzYWQiLz48dGV4dCB4PSIxNiIgeT0iMjIiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCxzYW5zLXNlcmlmIiBmb250LXdlaWdodD0iNzAwIiBmb250LXNpemU9IjEyIiBmaWxsPSIjZmZmIj5BREE8L3RleHQ+PC9zdmc+',
    'DOGE-USD':'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PGNpcmNsZSBjeD0iMTYiIGN5PSIxNiIgcj0iMTYiIGZpbGw9IiNjMmE2MzMiLz48dGV4dCB4PSIxNiIgeT0iMjIiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCxzYW5zLXNlcmlmIiBmb250LXdlaWdodD0iNzAwIiBmb250LXNpemU9IjExIiBmaWxsPSIjZmZmIj5ET0dFPC90ZXh0Pjwvc3ZnPg==',
    'DOT-USD':'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PGNpcmNsZSBjeD0iMTYiIGN5PSIxNiIgcj0iMTYiIGZpbGw9IiNlNjAwN2EiLz48dGV4dCB4PSIxNiIgeT0iMjEiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCxzYW5zLXNlcmlmIiBmb250LXdlaWdodD0iNzAwIiBmb250LXNpemU9IjEzIiBmaWxsPSIjZmZmIj5ET1Q8L3RleHQ+PC9zdmc+',
    'DOT-EUR':'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PGNpcmNsZSBjeD0iMTYiIGN5PSIxNiIgcj0iMTYiIGZpbGw9IiNlNjAwN2EiLz48dGV4dCB4PSIxNiIgeT0iMjEiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCxzYW5zLXNlcmlmIiBmb250LXdlaWdodD0iNzAwIiBmb250LXNpemU9IjEzIiBmaWxsPSIjZmZmIj5ET1Q8L3RleHQ+PC9zdmc+',
    'LINK-USD':'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PGNpcmNsZSBjeD0iMTYiIGN5PSIxNiIgcj0iMTYiIGZpbGw9IiMyYTVhZGEiLz48dGV4dCB4PSIxNiIgeT0iMjEiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCxzYW5zLXNlcmlmIiBmb250LXdlaWdodD0iNzAwIiBmb250LXNpemU9IjEzIiBmaWxsPSIjZmZmIj5MSU5LPC90ZXh0Pjwvc3ZnPg==',
    'AVAX-USD':'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PGNpcmNsZSBjeD0iMTYiIGN5PSIxNiIgcj0iMTYiIGZpbGw9IiNlODQxNDIiLz48dGV4dCB4PSIxNiIgeT0iMjEiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCxzYW5zLXNlcmlmIiBmb250LXdlaWdodD0iNzAwIiBmb250LXNpemU9IjEzIiBmaWxsPSIjZmZmIj5BVkFYPC90ZXh0Pjwvc3ZnPg==',
    'MATIC-USD':'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PGNpcmNsZSBjeD0iMTYiIGN5PSIxNiIgcj0iMTYiIGZpbGw9IiM4MjQ3ZTUiLz48dGV4dCB4PSIxNiIgeT0iMjEiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCxzYW5zLXNlcmlmIiBmb250LXdlaWdodD0iNzAwIiBmb250LXNpemU9IjEzIiBmaWxsPSIjZmZmIj5NQVRJQzwvdGV4dD48L3N2Zz4=',
    'LTC-USD':'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PGNpcmNsZSBjeD0iMTYiIGN5PSIxNiIgcj0iMTYiIGZpbGw9IiNiZmJiYmIiLz48dGV4dCB4PSIxNiIgeT0iMjEiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCxzYW5zLXNlcmlmIiBmb250LXdlaWdodD0iNzAwIiBmb250LXNpemU9IjEzIiBmaWxsPSIjZmZmIj5MVEM8L3RleHQ+PC9zdmc+',
    'UNI-USD':'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PGNpcmNsZSBjeD0iMTYiIGN5PSIxNiIgcj0iMTYiIGZpbGw9IiNmZjAwN2EiLz48dGV4dCB4PSIxNiIgeT0iMjEiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCxzYW5zLXNlcmlmIiBmb250LXdlaWdodD0iNzAwIiBmb250LXNpemU9IjEzIiBmaWxsPSIjZmZmIj5VTkk8L3RleHQ+PC9zdmc+',
    'ATOM-USD':'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PGNpcmNsZSBjeD0iMTYiIGN5PSIxNiIgcj0iMTYiIGZpbGw9IiM2ZjRjZmYiLz48dGV4dCB4PSIxNiIgeT0iMjEiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCxzYW5zLXNlcmlmIiBmb250LXdlaWdodD0iNzAwIiBmb250LXNpemU9IjEzIiBmaWxsPSIjZmZmIj5BVE9NPC90ZXh0Pjwvc3ZnPg==',
    'SPY':'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PGNpcmNsZSBjeD0iMTYiIGN5PSIxNiIgcj0iMTYiIGZpbGw9IiMxYTNhNWMiLz48dGV4dCB4PSIxNiIgeT0iMjEiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCxzYW5zLXNlcmlmIiBmb250LXdlaWdodD0iNzAwIiBmb250LXNpemU9IjEzIiBmaWxsPSIjZmZmIj5TUFk8L3RleHQ+PC9zdmc+',
    'QQQ':'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PGNpcmNsZSBjeD0iMTYiIGN5PSIxNiIgcj0iMTYiIGZpbGw9IiMxYTFhNWMiLz48dGV4dCB4PSIxNiIgeT0iMjEiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCxzYW5zLXNlcmlmIiBmb250LXdlaWdodD0iNzAwIiBmb250LXNpemU9IjEzIiBmaWxsPSIjNjBhNWZhIj5RUVE8L3RleHQ+PC9zdmc+',
    'GLD':'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PGNpcmNsZSBjeD0iMTYiIGN5PSIxNiIgcj0iMTYiIGZpbGw9IiM3YzVjMDAiLz48dGV4dCB4PSIxNiIgeT0iMjEiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCxzYW5zLXNlcmlmIiBmb250LXdlaWdodD0iNzAwIiBmb250LXNpemU9IjEzIiBmaWxsPSIjZmJiZjI0Ij5HTEQ8L3RleHQ+PC9zdmc+',
    'IWM':'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PGNpcmNsZSBjeD0iMTYiIGN5PSIxNiIgcj0iMTYiIGZpbGw9IiMxYTNhMWEiLz48dGV4dCB4PSIxNiIgeT0iMjEiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCxzYW5zLXNlcmlmIiBmb250LXdlaWdodD0iNzAwIiBmb250LXNpemU9IjEzIiBmaWxsPSIjNGFkZTgwIj5JV008L3RleHQ+PC9zdmc+',
    'AAPL':'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PGNpcmNsZSBjeD0iMTYiIGN5PSIxNiIgcj0iMTYiIGZpbGw9IiM1NTUiLz48dGV4dCB4PSIxNiIgeT0iMjIiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCxzYW5zLXNlcmlmIiBmb250LXdlaWdodD0iNzAwIiBmb250LXNpemU9IjE4IiBmaWxsPSIjZmZmIj48L3RleHQ+PC9zdmc+',
    'MSFT':'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PGNpcmNsZSBjeD0iMTYiIGN5PSIxNiIgcj0iMTYiIGZpbGw9IiNmZmYiLz48cmVjdCB4PSI4IiB5PSI4IiB3aWR0aD0iNyIgaGVpZ2h0PSI3IiBmaWxsPSIjZjI1MDIyIi8+PHJlY3QgeD0iMTciIHk9IjgiIHdpZHRoPSI3IiBoZWlnaHQ9IjciIGZpbGw9IiM3ZmJhMDAiLz48cmVjdCB4PSI4IiB5PSIxNyIgd2lkdGg9IjciIGhlaWdodD0iNyIgZmlsbD0iIzAwYTRlZiIvPjxyZWN0IHg9IjE3IiB5PSIxNyIgd2lkdGg9IjciIGhlaWdodD0iNyIgZmlsbD0iI2ZmYjkwMCIvPjwvc3ZnPg==',
    'GOOGL':'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PGNpcmNsZSBjeD0iMTYiIGN5PSIxNiIgcj0iMTYiIGZpbGw9IiNmZmYiLz48dGV4dCB4PSIxNiIgeT0iMjMiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCxzYW5zLXNlcmlmIiBmb250LXdlaWdodD0iOTAwIiBmb250LXNpemU9IjE5IiBmaWxsPSIjNDI4NWY0Ij5HPC90ZXh0Pjwvc3ZnPg==',
    'AMZN':'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PGNpcmNsZSBjeD0iMTYiIGN5PSIxNiIgcj0iMTYiIGZpbGw9IiMxMzE5MjEiLz48dGV4dCB4PSIxNiIgeT0iMjAiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCxzYW5zLXNlcmlmIiBmb250LXdlaWdodD0iOTAwIiBmb250LXNpemU9IjEyIiBmaWxsPSIjZmY5OTAwIj5hbXpuPC90ZXh0PjxwYXRoIGQ9Ik04IDIyIFExNiAyNiAyNCAyMiIgc3Ryb2tlPSIjZmY5OTAwIiBzdHJva2Utd2lkdGg9IjIiIGZpbGw9Im5vbmUiLz48L3N2Zz4=',
    'TSLA':'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PGNpcmNsZSBjeD0iMTYiIGN5PSIxNiIgcj0iMTYiIGZpbGw9IiNjYzAwMDAiLz48dGV4dCB4PSIxNiIgeT0iMjIiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCxzYW5zLXNlcmlmIiBmb250LXdlaWdodD0iNzAwIiBmb250LXNpemU9IjEwIiBmaWxsPSIjZmZmIj5UU0xBPC90ZXh0Pjwvc3ZnPg==',
    'NVDA':'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PGNpcmNsZSBjeD0iMTYiIGN5PSIxNiIgcj0iMTYiIGZpbGw9IiM3NmI5MDAiLz48dGV4dCB4PSIxNiIgeT0iMjIiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCxzYW5zLXNlcmlmIiBmb250LXdlaWdodD0iNzAwIiBmb250LXNpemU9IjEwIiBmaWxsPSIjZmZmIj5OVkRBPC90ZXh0Pjwvc3ZnPg==',
    'META':'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAzMiAzMiI+PGNpcmNsZSBjeD0iMTYiIGN5PSIxNiIgcj0iMTYiIGZpbGw9IiMwODY2ZmYiLz48dGV4dCB4PSIxNiIgeT0iMjIiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCxzYW5zLXNlcmlmIiBmb250LXdlaWdodD0iNzAwIiBmb250LXNpemU9IjExIiBmaWxsPSIjZmZmIj5NRVRBPC90ZXh0Pjwvc3ZnPg==',
  };
  var PAL = {
    blue:{bg:'rgba(59,130,246,.18)',t:'#3b82f6'},orange:{bg:'rgba(249,115,22,.18)',t:'#f97316'},
    purple:{bg:'rgba(168,85,247,.18)',t:'#a855f7'},green:{bg:'rgba(34,197,94,.18)',t:'#22c55e'},
    yellow:{bg:'rgba(234,179,8,.18)',t:'#ca8a04'},amber:{bg:'rgba(245,158,11,.18)',t:'#d97706'},
    teal:{bg:'rgba(20,184,166,.18)',t:'#0d9488'},indigo:{bg:'rgba(99,102,241,.18)',t:'#6366f1'},
    slate:{bg:'rgba(100,116,139,.18)',t:'#64748b'},red:{bg:'rgba(239,68,68,.18)',t:'#ef4444'},
    pink:{bg:'rgba(236,72,153,.18)',t:'#ec4899'},
  };
  function mkIcon(sym, col, sz) {
    sz = sz || 36;
    var url = ICONS[sym.toUpperCase()];
    var p   = PAL[col] || PAL.slate;
    var tk  = sym.replace(/-USD$|-EUR$|-GBP$/i,'').slice(0,4).toUpperCase();
    var s   = 'width:'+sz+'px;height:'+sz+'px;border-radius:50%;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;';
    if (url) {
      // data URI: mostrar directamente sin fondo extra
      return '<div style="'+s+'background:transparent;"><img src="'+url+'" alt="'+tk+'" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" onerror="this.parentNode.innerHTML=\'<span style=&quot;background:'+p.bg+';color:'+p.t+';font-size:10px;font-weight:700;width:100%;height:100%;display:flex;align-items:center;justify-content:center;border-radius:50%;&quot;>'+tk+'</span>\'"></div>';
    }
    return '<div style="'+s+'background:'+p.bg+';color:'+p.t+';font-size:10px;font-weight:700;">'+tk+'</div>';
  }

  /* ── Estado ─────────────────────────────────────────────────────────────── */
  var S = {
    assets:[], quotes:{}, page:1, perPage:10,
    filter:'all', search:'',
    detailSym:null, detailChart:null,
    detailPeriod:'6mo', detailType:'line', detailDays:5,
    selAsset:null, selColor:'blue',
    lastPred: null,  // última predicción cargada, para repintarla al cambiar período
  };

  /* ── INIT ───────────────────────────────────────────────────────────────── */
  function MAP_init(app) {
    S.perPage = parseInt(app.dataset.perPage, 10) || 10;

    var tbody   = app.querySelector('#map-tbody');
    var showEl  = app.querySelector('#map-showing');
    var pagesEl = app.querySelector('#map-pages');
    var searchEl = app.querySelector('#map-search');
    var acEl    = app.querySelector('#map-autocomplete');

    /* ── Render tabla ────────────────────────────────────────────────────── */
    function renderTable() {
      var filtered = S.assets.filter(function (a) {
        var type = detectType(a.symbol);
        var okF  = S.filter==='all' || S.filter===type || (S.filter==='watchlist' && a.source==='personal');
        var q    = S.search.toLowerCase();
        var okS  = !q || (a.label||'').toLowerCase().indexOf(q)>=0 || a.symbol.toLowerCase().indexOf(q)>=0;
        return okF && okS;
      });
      var total = filtered.length;
      var pages = Math.max(1, Math.ceil(total / S.perPage));
      if (S.page > pages) S.page = 1;
      var start = (S.page-1)*S.perPage;
      var items = filtered.slice(start, start+S.perPage);

      if (tbody) {
        tbody.innerHTML = items.length
          ? items.map(buildRow).join('')
          : '<tr><td colspan="7" style="padding:40px 24px;text-align:center;color:#64748b;font-size:14px;">'
            + (S.assets.length
              ? 'No se encontraron activos para este filtro.'
              : 'No hay activos configurados. Ve a <strong>Market Assets</strong> en WordPress Admin para añadir activos.')
            + '</td></tr>';
        items.forEach(function (a) {
          var tr = tbody.querySelector('tr[data-sym="'+CSS.escape(a.symbol)+'"]');
          if (tr) tr.addEventListener('click', function () { openDetail(a.symbol); });
          drawSpark(a);
        });
      }
      if (showEl) showEl.textContent = total
        ? 'Mostrando '+(start+1)+'–'+Math.min(start+S.perPage,total)+' de '+total+' activos' : '';
      renderPages(pages);
    }

    function detectType(sym) {
      if (/[-](USD|EUR|GBP|BTC)$/i.test(sym)) return 'crypto';
      var etfs = ['SPY','QQQ','GLD','SLV','TLT','IWM','VTI','VNQ','EEM','XLF','XLK','ARKK','IBIT','DIA','VOO'];
      return etfs.indexOf(sym.toUpperCase())>=0 ? 'etf' : 'stock';
    }

    function buildRow(a) {
      var q   = S.quotes[a.symbol] || {};
      var p   = q.change_pct!=null ? +q.change_pct : null;
      var sg  = sig(p); var sn = sent(p);
      var pc  = p==null?'#94a3b8':(p>=0?'#4ade80':'#f87171');
      var arr = p==null?'':(p>=0
        ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#4ade80" stroke-width="2.5"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>'
        : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2.5"><polyline points="22 17 13.5 8.5 8.5 13.5 2 7"/><polyline points="16 17 22 17 22 11"/></svg>');
      var spk = 'spk_'+a.symbol.replace(/[^a-zA-Z0-9]/g,'_');
      return '<tr data-sym="'+esc(a.symbol)+'" class="map-row">'
        +'<td style="padding:14px 24px;white-space:nowrap;"><div style="display:flex;align-items:center;gap:10px;">'+mkIcon(a.symbol,a.color||'slate',36)
        +'<div><p style="font-size:14px;font-weight:600;color:#f1f5f9;margin:0 0 2px;">'+esc(a.label||a.symbol)+'</p>'
        +'<p style="font-size:12px;color:#64748b;margin:0;">'+esc(a.symbol)+'</p></div></div></td>'
        +'<td style="padding:14px 24px;text-align:right;white-space:nowrap;"><p style="font-size:14px;font-weight:600;color:#f1f5f9;margin:0;">'+fmtP(q.price)+'</p></td>'
        +'<td style="padding:14px 24px;text-align:right;white-space:nowrap;"><div style="display:flex;align-items:center;justify-content:flex-end;gap:4px;"><p style="font-size:14px;font-weight:500;color:'+pc+';margin:0;">'+fmtPct(p)+'</p>'+arr+'</div></td>'
        +'<td style="padding:14px 24px;text-align:center;"><canvas id="'+spk+'" width="100" height="36" style="display:inline-block;vertical-align:middle;"></canvas></td>'
        +'<td style="padding:14px 24px;text-align:center;white-space:nowrap;"><span style="display:inline-flex;align-items:center;padding:3px 12px;border-radius:999px;font-size:12px;font-weight:600;background:'+sn.bg+';color:'+sn.c+';border:1px solid '+sn.br+';">'+sn.l+'</span></td>'
        +'<td style="padding:14px 24px;text-align:center;white-space:nowrap;"><p style="font-size:14px;font-weight:700;color:'+sg.c+';margin:0;">'+sg.l+'</p></td>'
        +'<td style="padding:14px 24px;text-align:right;white-space:nowrap;">'
        +'<div style="display:flex;align-items:center;justify-content:flex-end;gap:6px;">'
        +'<button class="map-view-btn" data-sym="'+esc(a.symbol)+'" style="padding:5px 14px;font-size:12px;font-weight:500;border-radius:8px;cursor:pointer;border:1px solid rgba(148,163,184,.25);background:rgba(148,163,184,.1);color:#94a3b8;transition:all .15s;">Ver →</button>'
        +(a.source==='personal' ? '<button class="map-del-btn" data-sym="'+esc(a.symbol)+'" title="Eliminar de mi lista" style="width:28px;height:28px;display:flex;align-items:center;justify-content:center;border-radius:6px;cursor:pointer;border:1px solid rgba(239,68,68,.3);background:rgba(239,68,68,.1);color:#f87171;transition:all .15s;">✕</button>' : '')
        +'</div>'
        +'</td>'
        +'</tr>';
    }

    /* ── Sparklines ──────────────────────────────────────────────────────── */
    function drawSpark(a) {
      apiGet('history/'+encodeURIComponent(a.symbol)+'?period=7d&interval=1d').then(function (data) {
        if (!data||!data.data||data.data.length<2) return;
        var id = 'spk_'+a.symbol.replace(/[^a-zA-Z0-9]/g,'_');
        var cv = document.getElementById(id);
        if (!cv||cv._c) return; cv._c=true;
        var pr = data.data.map(function(d){return d.close;});
        var up = pr[pr.length-1]>=pr[0]; var co = up?'#4ade80':'#f87171';
        new Chart(cv.getContext('2d'),{type:'line',data:{labels:data.data.map(function(d){return d.date;}),datasets:[{data:pr,borderColor:co,borderWidth:1.5,pointRadius:0,tension:.4,fill:true,backgroundColor:up?'rgba(74,222,128,.1)':'rgba(248,113,113,.1)'}]},options:{responsive:false,animation:false,plugins:{legend:{display:false},tooltip:{enabled:false}},scales:{x:{display:false},y:{display:false}}}});
      });
    }

    /* ── Paginación ──────────────────────────────────────────────────────── */
    function renderPages(total) {
      if (!pagesEl) return;
      var sa='padding:4px 10px;font-size:12px;font-weight:500;border-radius:6px;cursor:pointer;transition:all .12s;background:#3b82f6;color:#fff;border:1px solid #3b82f6;';
      var sn='padding:4px 10px;font-size:12px;font-weight:500;border-radius:6px;cursor:pointer;transition:all .12s;background:transparent;color:#94a3b8;border:1px solid rgba(148,163,184,.25);';
      var sd='padding:4px 10px;font-size:12px;font-weight:500;border-radius:6px;cursor:default;background:transparent;color:rgba(148,163,184,.3);border:1px solid rgba(148,163,184,.12);';
      var h='<button style="'+(S.page===1?sd:sn)+'" '+(S.page===1?'disabled':'onclick="mapGoPage('+(S.page-1)+')"')+'><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button>';
      for(var i=1;i<=total;i++) h+='<button style="'+(i===S.page?sa:sn)+'" onclick="mapGoPage('+i+')">'+i+'</button>';
      h+='<button style="'+(S.page===total?sd:sn)+'" '+(S.page===total?'disabled':'onclick="mapGoPage('+(S.page+1)+')"')+'><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></button>';
      pagesEl.innerHTML=h;
    }
    window.mapGoPage=function(p){S.page=p;renderTable();app.scrollIntoView({behavior:'smooth',block:'start'});};

    /* ── Filtros ─────────────────────────────────────────────────────────── */
    function setFilter(f) {
      S.filter=f; S.page=1;
      app.querySelectorAll('.map-filter-btn').forEach(function(b){
        var active = b.dataset.filter===f;
        // Limpiar todas las clases de estado y aplicar estilos inline
        b.style.cssText = active
          ? 'background:#f1f5f9 !important;color:#0f172a !important;border-color:transparent !important;font-weight:700;'
          : '';
      });
      renderTable();
    }
    app.querySelectorAll('.map-filter-btn').forEach(function(btn){
      btn.addEventListener('click',function(){setFilter(btn.dataset.filter);});
    });
    setFilter('all'); // inicializar con "Todos" activo

    /* ── Búsqueda ────────────────────────────────────────────────────────── */
    if (searchEl) {
      var st;
      searchEl.addEventListener('input',function(){
        clearTimeout(st);
        S.search=searchEl.value.trim(); S.page=1; renderTable();
        if (S.search.length>=2) {
          st=setTimeout(function(){
            apiGet('search?q='+encodeURIComponent(S.search)).then(function(res){
              var it=(res&&res.results)?res.results.slice(0,6):[];
              if(!it.length||!acEl){return;}
              acEl.innerHTML=it.map(function(r){return '<div class="map-ac" data-sym="'+esc(r.symbol)+'" data-name="'+esc(r.name)+'" style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;cursor:pointer;border-bottom:1px solid rgba(148,163,184,.1);" onmouseover="this.style.background=\'rgba(148,163,184,.08)\'" onmouseout="this.style.background=\'\'"><div><span style="font-size:13px;font-weight:600;color:#3b82f6;">'+esc(r.symbol)+'</span><span style="font-size:12px;color:#64748b;margin-left:8px;">'+esc(r.name)+'</span></div><span style="font-size:10px;background:rgba(148,163,184,.12);padding:2px 6px;border-radius:4px;color:#64748b;">'+esc(r.type)+'</span></div>';}).join('');
              acEl.style.display='block';
              acEl.querySelectorAll('.map-ac').forEach(function(row){
                row.addEventListener('click',function(){
                  acEl.style.display='none'; searchEl.value=''; S.search='';
                  var found=S.assets.find(function(a){return a.symbol===row.dataset.sym;});
                  if(found) openDetail(row.dataset.sym); renderTable();
                });
              });
            });
          },300);
        } else if(acEl) acEl.style.display='none';
      });
      document.addEventListener('click',function(e){if(acEl&&!searchEl.contains(e.target)&&!acEl.contains(e.target))acEl.style.display='none';});
    }

    /* ── View btn (delegado) ─────────────────────────────────────────────── */
    if(tbody){
      tbody.addEventListener('click',function(e){
        var vbtn=e.target.closest('.map-view-btn');
        if(vbtn){e.stopPropagation();openDetail(vbtn.dataset.sym);return;}
        var dbtn=e.target.closest('.map-del-btn');
        if(dbtn){
          e.stopPropagation();
          var sym=dbtn.dataset.sym;
          if(confirm('¿Eliminar '+sym+' de tu lista personal?')){
            fetch(API_BASE+'assets/'+encodeURIComponent(sym)
              +'?map_nonce='+encodeURIComponent(cfg.map_nonce||'')
              +'&map_user_id='+encodeURIComponent(cfg.user_id||0),{
              method:'DELETE',
              credentials:'same-origin',
              headers:{'Content-Type':'application/json'}
            }).then(function(){
              S.assets=S.assets.filter(function(a){return !(a.symbol===sym&&a.source==='personal');});
              delete S.quotes[sym];
              renderTable();
            });
          }
        }
      });
    }

    /* ── Modal detalle ───────────────────────────────────────────────────── */
    var detModal=document.getElementById('map-detail-modal');
    document.getElementById('map-modal-close')&&document.getElementById('map-modal-close').addEventListener('click',closeDetail);
    if(detModal) detModal.addEventListener('click',function(e){if(e.target===detModal)closeDetail();});

    function closeDetail(){
      if(detModal)detModal.style.display='none';
      document.body.style.overflow='';
      if(S.detailChart){S.detailChart.destroy();S.detailChart=null;}
      S.detailSym=null;
    }

    function openDetail(symbol){
      S.detailSym=symbol; S.detailPeriod='6mo'; S.detailType='line'; S.detailDays=5;
      S.lastPred=null; // limpiar predicción anterior al abrir nuevo activo
      var a=S.assets.find(function(x){return x.symbol===symbol;})||{};
      var q=S.quotes[symbol]||{};
      var p=q.change_pct!=null?+q.change_pct:null;
      var sg=sig(p);
      document.getElementById('md-icon').innerHTML=mkIcon(symbol,a.color||'slate',52);
      setTxt('md-name', a.label||q.name||symbol);
      setTxt('md-symbol', symbol);
      setTxt('md-price', fmtP(q.price));
      var chEl=document.getElementById('md-change');
      if(chEl){chEl.textContent=fmtPct(p);chEl.style.color=p==null?'#94a3b8':(p>=0?'#4ade80':'#f87171');}
      setTxt('md-vol', fmtVol(q.volume));
      setTxt('md-rsi','…');setTxt('md-ma7','…');setTxt('md-ma21','…');setTxt('md-vp','…');
      var sigEl=document.getElementById('md-signal');
      if(sigEl){sigEl.textContent=sg.l;sigEl.style.color=sg.c;}
      resetTabs('map-period-btn','6mo','period');
      resetTabs('map-type-btn','line','type');
      resetTabs('map-pred-btn','5','days');
      if(detModal){detModal.style.display='flex';document.body.style.overflow='hidden';}
      loadChart(); loadPred();
    }

    /* ── Gráfico ─────────────────────────────────────────────────────────── */
    // Mapeo período → intervalo Yahoo Finance
    var PERIOD_MAP = {
      '1d':  { period:'1d',  interval:'5m'  },
      '1wk': { period:'5d',  interval:'30m' },
      '1mo': { period:'1mo', interval:'1d'  },
      '6mo': { period:'6mo', interval:'1d'  },
      '1y':  { period:'1y',  interval:'1d'  },
      '2y':  { period:'2y',  interval:'1wk' },
      '5y':  { period:'5y',  interval:'1wk' },
    };

    /* ── Pinta el tramo de predicción sobre el gráfico activo ─────────────
       Funciona en modo Línea para cualquier período diario (1M, 6M, 1A, 2A, 5A).
       Se llama desde loadPred() y también cuando el usuario cambia período/tipo,
       así la predicción siempre está visible sin necesidad de volver a pedirla. */
    function paintPredOnChart(preds) {
      // Solo en modo línea, períodos con granularidad diaria y si hay predicciones
      var dailyPeriods = ['1mo','6mo','1y','2y','5y'];
      if (!S.detailChart || S.detailType !== 'line' ||
          dailyPeriods.indexOf(S.detailPeriod) === -1 || !preds || !preds.length) return;

      var chart      = S.detailChart;
      // Recuperar datos originales del histórico (antes de haberlos extendido)
      // Los guardamos en _histLabels/_histData para que los repintados funcionen
      if (!chart._histLabels) {
        chart._histLabels = chart.data.labels.slice();
        chart._histData   = chart.data.datasets[0].data.slice();
      }
      var histLabels = chart._histLabels;
      var histPrices = chart._histData;

      var anchorPrice = histPrices[histPrices.length - 1];
      var predLabels  = preds.map(function(p){ return p.date; });
      var predPrices  = preds.map(function(p){ return p.price; });

      // Histórico extendido: los N días de predicción van como null
      var histExt = histPrices.slice();
      for (var i = 0; i < predLabels.length; i++) histExt.push(null);

      // Dataset predicción: nulls hasta el último punto histórico, luego ancla + días
      var predExt = [];
      for (var j = 0; j < histPrices.length - 1; j++) predExt.push(null);
      predExt.push(anchorPrice); // punto de unión con el histórico
      for (var k = 0; k < predPrices.length; k++) predExt.push(predPrices[k]);

      // Gradiente morado para el relleno de predicción
      var ctx2  = chart.canvas.getContext('2d');
      var gPred = ctx2.createLinearGradient(0, 0, 0, 280);
      gPred.addColorStop(0, 'rgba(139,92,246,.22)');
      gPred.addColorStop(1, 'rgba(139,92,246,0)');

      // Actualizar etiquetas (histórico + días predichos)
      chart.data.labels = histLabels.concat(predLabels);
      chart.data.datasets[0].data = histExt;

      // Eliminar dataset de predicción previo si existe
      if (chart.data.datasets.length > 1) chart.data.datasets.splice(1);

      // Radios de puntos: solo visible en el punto de unión y en el último día
      var dotR = predExt.map(function(v, idx) {
        if (v === null) return 0;
        if (idx === histPrices.length - 1) return 5; // punto de unión
        if (idx === predExt.length - 1)    return 6; // último día predicho
        return 3;                                     // días intermedios
      });

      chart.data.datasets.push({
        label: 'Predicción',
        data: predExt,
        borderColor: '#a78bfa',
        borderWidth: 2,
        borderDash: [7, 4],
        pointRadius: dotR,
        pointHoverRadius: 7,
        pointBackgroundColor: predExt.map(function(v, idx) {
          return idx === histPrices.length - 1 ? '#94a3b8' : '#a78bfa';
        }),
        pointBorderColor: predExt.map(function(v, idx) {
          return idx === histPrices.length - 1 ? '#94a3b8' : '#7c3aed';
        }),
        pointBorderWidth: 2,
        tension: 0.35,
        fill: true,
        backgroundColor: gPred,
        spanGaps: false,
      });

      // Tooltip: distingue precio real de predicción
      chart.options.plugins.tooltip.callbacks.label = function(c) {
        if (c.raw === null) return null;
        var prefix = c.dataset.label === 'Predicción' ? '🔮 Predicción' : '📈 Precio';
        return prefix + ': ' + fmtP(c.raw);
      };
      // Annotation visual: línea vertical en el punto de separación
      chart.options.plugins.tooltip.callbacks.title = function(items) {
        var item = items[0];
        var isPred = item && item.dataset && item.dataset.label === 'Predicción';
        return (isPred ? '🗓 ' : '') + (item ? item.label : '');
      };

      chart.update('none');
    }

    function loadChart(onDone){
      var sym=S.detailSym; var pm=PERIOD_MAP[S.detailPeriod]||PERIOD_MAP['6mo'];
      if(!sym) return;
      if(S.detailChart){S.detailChart.destroy();S.detailChart=null;}
      var loadEl=document.getElementById('md-chart-loading');
      var canvas=document.getElementById('md-chart');
      if(loadEl){loadEl.style.display='flex';}
      if(canvas){canvas.style.opacity='0';}

      apiGet('history/'+encodeURIComponent(sym)+'?period='+pm.period+'&interval='+pm.interval).then(function(data){
        if(!data||!data.data||data.data.length<2){
          if(loadEl){loadEl.style.display='flex';loadEl.innerHTML='<span style="color:#64748b;font-size:13px;">Sin datos disponibles.</span>';}
          return;
        }
        if(loadEl)loadEl.style.display='none';
        if(canvas)canvas.style.opacity='1';
        var rows=data.data;
        var closes=rows.map(function(d){return d.close;});
        var labels=rows.map(function(d){return d.date;});
        var up=closes[closes.length-1]>=closes[0]; var color=up?'#4ade80':'#f87171';
        var grid='rgba(255,255,255,.04)'; var tick='#475569';
        var ctx=canvas.getContext('2d');
        if(S.detailType==='candle'){
          S.detailChart=new Chart(ctx,{type:'bar',data:{labels:labels,datasets:[{data:rows.map(function(d){return[Math.min(d.open,d.close),Math.max(d.open,d.close)];}),backgroundColor:rows.map(function(d){return d.close>=d.open?'rgba(74,222,128,.7)':'rgba(248,113,113,.7)';}),borderColor:rows.map(function(d){return d.close>=d.open?'#4ade80':'#f87171';}),borderWidth:1,borderSkipped:false}]},options:chartOpts(labels,tick,grid)});
        } else {
          var grad=ctx.createLinearGradient(0,0,0,280);
          grad.addColorStop(0,up?'rgba(74,222,128,.18)':'rgba(248,113,113,.18)');
          grad.addColorStop(1,'rgba(0,0,0,0)');
          S.detailChart=new Chart(ctx,{type:'line',data:{labels:labels,datasets:[{data:closes,borderColor:color,borderWidth:2,pointRadius:0,pointHoverRadius:5,tension:.3,fill:true,backgroundColor:grad}]},options:chartOpts(labels,tick,grid)});
        }
        // Indicadores técnicos (se piden en paralelo, sin bloquear el gráfico)
        apiGet('predict/'+encodeURIComponent(sym)+'?days=5').then(function(pred){
          if(pred&&pred.indicators){
            var ind=pred.indicators;
            setTxt('md-rsi', ind.rsi!=null?+ind.rsi.toFixed(1):'—');
            setTxt('md-ma7',  ind.ma7?fmtP(ind.ma7):'—');
            setTxt('md-ma21', ind.ma21?fmtP(ind.ma21):'—');
            setTxt('md-vp',   ind.volatility!=null?(+ind.volatility).toFixed(2)+'%':'—');
          }
        });
        // Ejecutar callback (ej: repintar predicción)
        if(typeof onDone === 'function') onDone();
      });
    }

    function chartOpts(labels,tc,gc){
      var n=labels.length;
      // Para 1D/1S usamos intervalos de tiempo más cortos
      var unit = n>400?'month':n>100?'week':n>30?'day':n>5?'hour':'minute';
      return {
        responsive:true,maintainAspectRatio:false,animation:{duration:300},
        interaction:{mode:'index',intersect:false},
        plugins:{legend:{display:false},tooltip:{backgroundColor:'rgba(15,23,42,.95)',titleColor:'#64748b',bodyColor:'#f1f5f9',borderColor:'rgba(255,255,255,.08)',borderWidth:1,
          callbacks:{label:function(ctx){return Array.isArray(ctx.raw)?'Rango: '+fmtP(ctx.raw[0])+' – '+fmtP(ctx.raw[1]):'Precio: '+fmtP(ctx.raw);}}}},
        scales:{
          x:{type:'time',time:{unit:unit,displayFormats:{minute:'HH:mm',hour:'HH:mm',day:'dd MMM',week:'dd MMM',month:'MMM yy'}},grid:{color:gc},ticks:{color:tc,maxTicksLimit:8,maxRotation:0}},
          y:{position:'right',grid:{color:gc},ticks:{color:tc,callback:function(v){return fmtP(v);}}}
        }
      };
    }

    document.querySelectorAll('.map-period-btn').forEach(function(btn){
      btn.addEventListener('click',function(){
        S.detailPeriod=btn.dataset.period;
        resetTabs('map-period-btn',btn.dataset.period,'period');
        loadChart(function(){ if(S.lastPred) paintPredOnChart(S.lastPred); });
      });
    });
    document.querySelectorAll('.map-type-btn').forEach(function(btn){
      btn.addEventListener('click',function(){
        S.detailType=btn.dataset.type;
        resetTabs('map-type-btn',btn.dataset.type,'type');
        loadChart(function(){ if(S.lastPred) paintPredOnChart(S.lastPred); });
      });
    });

    /* ── Predicción ──────────────────────────────────────────────────────── */
    function loadPred(){
      var sym=S.detailSym; var days=S.detailDays;
      if(!sym) return;
      var el=document.getElementById('md-pred-content');
      if(!el) return;
      el.innerHTML='<div style="display:flex;align-items:center;gap:8px;color:#64748b;font-size:13px;padding:12px 0;"><span class="material-symbols-outlined map-spin" style="font-size:18px;">refresh</span>Calculando predicción a '+days+' día(s)\u2026</div>';

      apiGet('predict/'+encodeURIComponent(sym)+'?days='+days).then(function(data){
        if(!data||data.error){
          el.innerHTML='<p style="color:#64748b;font-size:13px;padding:8px 0;">\u26a0\ufe0f Datos históricos insuficientes para generar predicción (se necesitan ≥60 días de datos).</p>';
          return;
        }
        var preds=data.predictions||[];

        // Guardar predicciones en estado para repintarlas si cambia período/tipo
        S.lastPred = preds;
        paintPredOnChart(preds);

        // ── Tarjetas ──────────────────────────────────────────────────────
        var cols=Math.max(1,Math.min(preds.length,5));
        var cards=preds.map(function(p){
          var sg=sig(p.change_pct); var pc=+p.change_pct>=0?'#4ade80':'#f87171';
          return '<div style="background:rgba(148,163,184,.06);border:1px solid rgba(148,163,184,.12);border-radius:12px;padding:12px;text-align:center;">'
            +'<p style="font-size:10px;color:#64748b;text-transform:uppercase;letter-spacing:.06em;margin:0 0 2px;">Día '+p.day+'</p>'
            +'<p style="font-size:11px;color:#475569;margin:0 0 8px;">'+p.date+'</p>'
            +'<p style="font-size:16px;font-weight:700;color:#f1f5f9;margin:0 0 2px;">'+fmtP(p.price)+'</p>'
            +'<p style="font-size:12px;font-weight:600;color:'+pc+';margin:0 0 4px;">'+fmtPct(p.change_pct)+'</p>'
            +'<p style="font-size:10px;color:#475569;margin:0 0 4px;">Confianza '+p.confidence+'%</p>'
            +'<p style="font-size:11px;font-weight:600;color:'+sg.c+';margin:0;">'+sg.l+'</p>'
            +'</div>';
        }).join('');
        var m=data.metrics||{};
        el.innerHTML='<div style="display:grid;grid-template-columns:repeat('+cols+',1fr);gap:8px;margin-bottom:12px;">'+cards+'</div>'
          +'<div style="display:flex;flex-wrap:wrap;gap:12px;font-size:11px;color:#64748b;background:rgba(148,163,184,.05);border-radius:10px;padding:10px 14px;border:1px solid rgba(148,163,184,.1);">'
          +'<span>Modelo: <strong style="color:#94a3b8;">Ridge + XGBoost</strong></span>'
          +'<span>MAE: <strong style="color:#94a3b8;">'+(m.mae||'\u2014')+'</strong></span>'
          +'<span>R\u00b2: <strong style="color:#94a3b8;">'+(m.r2||'\u2014')+'</strong></span>'
          +'<span>Generado: <strong style="color:#94a3b8;">'+(m.generated||'\u2014')+'</strong></span>'
          +'</div>'
          +'<p style="font-size:11px;color:#475569;margin-top:8px;">\u26a0\ufe0f Estimaciones estadísticas únicamente. No constituyen asesoramiento financiero.</p>';
      });
    }

    document.querySelectorAll('.map-pred-btn').forEach(function(btn){
      btn.addEventListener('click',function(){
        S.detailDays=parseInt(btn.dataset.days,10);
        resetTabs('map-pred-btn',btn.dataset.days,'days');
        loadPred();
      });
    });

    /* ── Modal añadir ────────────────────────────────────────────────────── */
    var addModal=document.getElementById('map-add-modal');
    var openAddBtn=app.querySelector('#map-open-add');
    if(openAddBtn&&addModal) openAddBtn.addEventListener('click',function(){addModal.style.display='flex';document.body.style.overflow='hidden';});
    document.getElementById('map-add-close')&&document.getElementById('map-add-close').addEventListener('click',closeAdd);
    if(addModal) addModal.addEventListener('click',function(e){if(e.target===addModal)closeAdd();});

    function closeAdd(){
      if(addModal)addModal.style.display='none';
      document.body.style.overflow='';
      var sd=document.getElementById('map-add-selected');
      if(sd)sd.style.display='none';
      S.selAsset=null;
      var ai=document.getElementById('map-add-search');
      if(ai)ai.value='';
    }

    // Búsqueda en modal añadir
    var addInp=document.getElementById('map-add-search');
    var addRes=document.getElementById('map-add-results');
    if(addInp&&addRes){
      var at;
      addInp.addEventListener('input',function(){
        clearTimeout(at);
        if(addInp.value.length<2){addRes.style.display='none';return;}
        at=setTimeout(function(){
          apiGet('search?q='+encodeURIComponent(addInp.value)).then(function(data){
            var it=(data&&data.results)?data.results.slice(0,8):[];
            if(!it.length){addRes.style.display='none';return;}
            addRes.innerHTML=it.map(function(r){return '<div class="map-add-ac" data-sym="'+esc(r.symbol)+'" data-name="'+esc(r.name)+'" style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;cursor:pointer;border-bottom:1px solid rgba(148,163,184,.1);" onmouseover="this.style.background=\'rgba(148,163,184,.08)\'" onmouseout="this.style.background=\'\'"><div><span style="font-size:13px;font-weight:600;color:#3b82f6;">'+esc(r.symbol)+'</span><span style="font-size:12px;color:#64748b;margin-left:8px;">'+esc(r.name)+'</span></div><span style="font-size:10px;background:rgba(148,163,184,.12);padding:2px 6px;border-radius:4px;color:#64748b;">'+esc(r.type)+'</span></div>';}).join('');
            addRes.style.display='block';
            addRes.querySelectorAll('.map-add-ac').forEach(function(row){
              row.addEventListener('click',function(){
                S.selAsset={symbol:row.dataset.sym,name:row.dataset.name};
                setTxt('map-sel-name',row.dataset.name);
                setTxt('map-sel-sym',row.dataset.sym);
                var sd=document.getElementById('map-add-selected');
                if(sd)sd.style.display='block';
                addRes.style.display='none'; addInp.value='';
              });
            });
          });
        },280);
      });
      document.addEventListener('click',function(e){if(!addInp.contains(e.target)&&!addRes.contains(e.target))addRes.style.display='none';});
    }

    // Color picker
    app.querySelectorAll('.map-swatch').forEach(function(sw){
      sw.addEventListener('click',function(){
        S.selColor=sw.dataset.color;
        app.querySelectorAll('.map-swatch').forEach(function(s){s.style.outline='none';s.style.transform='scale(1)';});
        sw.style.outline='3px solid #fff'; sw.style.transform='scale(1.2)';
      });
    });

    // Confirmar añadir
    var confBtn=document.getElementById('map-confirm-add');
    if(confBtn){
      confBtn.addEventListener('click',function(){
        if(!S.selAsset) return;
        confBtn.disabled=true; confBtn.textContent='Añadiendo…';
        apiPost('assets',{symbol:S.selAsset.symbol,label:S.selAsset.name,color:S.selColor}).then(function(res){
          confBtn.disabled=false; confBtn.textContent='Añadir a mi lista';
          if(!res){
            alert('Error de red al añadir el activo. Comprueba la consola.');
            return;
          }
          if(res.error){
            alert('Error: '+res.error);
            return;
          }
          closeAdd(); S.quotes={}; loadAssets();
        });
      });
    }

    // ESC
    document.addEventListener('keydown',function(e){if(e.key==='Escape'){closeDetail();closeAdd();}});

    /* ── Helpers ─────────────────────────────────────────────────────────── */
    function setTxt(id,v){var el=document.getElementById(id);if(el)el.textContent=v;}
    function resetTabs(cls,val,attr){
      document.querySelectorAll('.'+cls).forEach(function(b){
        var active=b.dataset[attr]===val;
        b.style.background=active?'#3b82f6':'';
        b.style.color=active?'#fff':'';
        b.style.borderColor=active?'#3b82f6':'';
      });
    }

    /* ── Cargar activos ──────────────────────────────────────────────────── */
    function showSkeletons(){
      if(!tbody) return;
      var h='';
      for(var i=0;i<7;i++){
        h+='<tr style="border-bottom:1px solid rgba(148,163,184,.1);">'
          +'<td style="padding:14px 24px;"><div style="display:flex;gap:10px;align-items:center;"><div style="width:36px;height:36px;border-radius:50%;background:rgba(148,163,184,.12);flex-shrink:0;"></div><div><div style="height:12px;width:90px;border-radius:4px;background:rgba(148,163,184,.12);margin-bottom:6px;"></div><div style="height:10px;width:55px;border-radius:4px;background:rgba(148,163,184,.1);"></div></div></div></td>'
          +'<td style="padding:14px 24px;text-align:right;"><div style="height:12px;width:70px;border-radius:4px;background:rgba(148,163,184,.12);margin-left:auto;"></div></td>'
          +'<td style="padding:14px 24px;text-align:right;"><div style="height:12px;width:55px;border-radius:4px;background:rgba(148,163,184,.12);margin-left:auto;"></div></td>'
          +'<td style="padding:14px 24px;text-align:center;"><div style="height:36px;width:100px;border-radius:6px;background:rgba(148,163,184,.1);margin:0 auto;"></div></td>'
          +'<td style="padding:14px 24px;text-align:center;"><div style="height:22px;width:65px;border-radius:999px;background:rgba(148,163,184,.1);margin:0 auto;"></div></td>'
          +'<td style="padding:14px 24px;text-align:center;"><div style="height:12px;width:55px;border-radius:4px;background:rgba(148,163,184,.1);margin:0 auto;"></div></td>'
          +'<td style="padding:14px 24px;text-align:right;"><div style="height:28px;width:60px;border-radius:8px;background:rgba(148,163,184,.1);margin-left:auto;"></div></td>'
          +'</tr>';
      }
      tbody.innerHTML=h;
    }

    function loadAssets(){
      showSkeletons();
      apiGet('assets').then(function(res){
        if(!res||!res.assets){
          if(tbody) tbody.innerHTML='<tr><td colspan="7" style="padding:40px 24px;text-align:center;color:#64748b;font-size:14px;">Error conectando al API REST. Revisa la consola del navegador.</td></tr>';
          return;
        }
        S.assets=res.assets;
        if(!S.assets.length){
          if(tbody) tbody.innerHTML='<tr><td colspan="7" style="padding:40px 24px;text-align:center;color:#64748b;font-size:14px;">No hay activos configurados. Ve a <strong>Market Assets</strong> en WordPress Admin para añadir activos.</td></tr>';
          return;
        }
        renderTable();
        // Cargar cotizaciones de uno en uno, actualizando la tabla según llegan
        S.assets.forEach(function(a){
          apiGet('quote/'+encodeURIComponent(a.symbol)).then(function(q){
            if(q&&!q.error){S.quotes[a.symbol]=q;renderTable();}
          });
        });
      });
    }

    loadAssets();
  }

})();
