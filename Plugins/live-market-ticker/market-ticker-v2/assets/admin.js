/**
 * Live Market Ticker — Admin Dashboard JS
 */
(function () {
    'use strict';

    // ── Estado global ──────────────────────────────────────────────────────────
    let state = {
        assets:        [],   // [{symbol, label, ticker, type, color, iconType, iconUrl, inTicker}]
        tickerOrder:   [],   // symbols en el orden del ticker
        settings:      {},
        dirty:         false,
        dragSrc:       null,
        filter:        'all',
        search:        '',
        editingAsset:  null,
    };

    // ── Paleta de colores disponibles ─────────────────────────────────────────
    const COLORS = {
        orange: '#ea580c', blue: '#2563eb', purple: '#9333ea',
        yellow: '#ca8a04', sky: '#0284c7',  teal: '#0d9488',
        amber:  '#d97706', pink: '#db2777', green: '#16a34a',
        indigo: '#4f46e5', slate: '#475569', red: '#dc2626',
    };
    const COLOR_BG = {
        orange: '#fff7ed', blue: '#eff6ff', purple: '#faf5ff',
        yellow: '#fefce8', sky: '#f0f9ff',  teal: '#f0fdfa',
        amber:  '#fffbeb', pink: '#fdf2f8', green: '#f0fdf4',
        indigo: '#eef2ff', slate: '#f8fafc', red: '#fef2f2',
    };

    // ── SVG badge helper ───────────────────────────────────────────────────────
    function svgBadge(text, bg, fg) {
        const len = text.length;
        const fs  = len >= 4 ? 9 : len === 3 ? 11 : 14;
        return `<svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
            <circle cx="20" cy="20" r="20" fill="${bg}"/>
            <text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle"
                  font-family="system-ui,sans-serif" font-weight="800"
                  font-size="${fs}" fill="${fg}">${text}</text>
        </svg>`;
    }

    const CRYPTO_IMG = {
        'BTC-USD':   'https://assets.coingecko.com/coins/images/1/small/bitcoin.png',
        'ETH-USD':   'https://assets.coingecko.com/coins/images/279/small/ethereum.png',
        'SOL-USD':   'https://assets.coingecko.com/coins/images/4128/small/solana.png',
        'BNB-USD':   'https://assets.coingecko.com/coins/images/825/small/bnb-icon2_2x.png',
        'XRP-USD':   'https://assets.coingecko.com/coins/images/44/small/xrp-symbol-white-128.png',
        'ADA-USD':   'https://assets.coingecko.com/coins/images/975/small/cardano.png',
        'DOGE-USD':  'https://assets.coingecko.com/coins/images/5/small/dogecoin.png',
        'DOT-USD':   'https://assets.coingecko.com/coins/images/12171/small/polkadot.png',
        'DOT-EUR':   'https://assets.coingecko.com/coins/images/12171/small/polkadot.png',
        'AVAX-USD':  'https://assets.coingecko.com/coins/images/12559/small/Avalanche_Circle_RedWhite_Trans.png',
        'MATIC-USD': 'https://assets.coingecko.com/coins/images/4713/small/matic-token-icon.png',
        'LINK-USD':  'https://assets.coingecko.com/coins/images/877/small/chainlink-new-logo.png',
        'LINK':      'https://assets.coingecko.com/coins/images/877/small/chainlink-new-logo.png',
        'UNI-USD':   'https://assets.coingecko.com/coins/images/12504/small/uniswap-uni.png',
        'LTC-USD':   'https://assets.coingecko.com/coins/images/2/small/litecoin.png',
        'ATOM-USD':  'https://assets.coingecko.com/coins/images/1481/small/cosmos_hub.png',
        'FIL-USD':   'https://assets.coingecko.com/coins/images/12817/small/filecoin.png',
        'NEAR-USD':  'https://assets.coingecko.com/coins/images/10365/small/near.jpg',
        'APT-USD':   'https://assets.coingecko.com/coins/images/26455/small/aptos_round.png',
        'SUI-USD':   'https://assets.coingecko.com/coins/images/26375/small/sui_asset.jpeg',
    };

    function getBadgeHtml(asset, size = 40) {
        if (asset.iconType === 'url' && asset.iconUrl) {
            return `<img src="${asset.iconUrl}" alt="${asset.ticker}"
                        style="width:${size}px;height:${size}px;object-fit:contain;border-radius:50%"
                        onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
                    <div style="display:none;width:${size}px;height:${size}px">
                        ${svgBadge(asset.ticker.slice(0,4), '#334155', '#e2e8f0')}
                    </div>`;
        }
        if (CRYPTO_IMG[asset.symbol]) {
            return `<img src="${CRYPTO_IMG[asset.symbol]}" alt="${asset.ticker}"
                        style="width:${size}px;height:${size}px;object-fit:contain;border-radius:50%">`;
        }
        const fgColor = COLORS[asset.color] || '#e2e8f0';
        const bgColor = COLOR_BG[asset.color] ? darken(COLOR_BG[asset.color]) : '#334155';
        return svgBadge(asset.ticker.slice(0, 4), bgColor, fgColor);
    }

    function darken(hex) {
        // Convert light bg colors to dark versions for dark backgrounds
        const darkMap = {
            '#fff7ed': '#431407', '#eff6ff': '#1e3a5f', '#faf5ff': '#2e1065',
            '#fefce8': '#422006', '#f0f9ff': '#082f49', '#f0fdfa': '#022c22',
            '#fffbeb': '#451a03', '#fdf2f8': '#500724', '#f0fdf4': '#052e16',
            '#eef2ff': '#1e1b4b', '#f8fafc': '#0f172a', '#fef2f2': '#450a0a',
        };
        return darkMap[hex] || '#334155';
    }

    // ── Bootstrap ─────────────────────────────────────────────────────────────
    function init() {
        const cfg = window.LMT_Admin_Data || {};
        state.settings    = cfg.settings    || {};
        state.assets      = cfg.assets      || [];
        state.tickerOrder = cfg.tickerOrder || [];

        renderAll();
        bindEvents();
    }

    // ── Render principal ──────────────────────────────────────────────────────
    function renderAll() {
        renderStats();
        renderTickerStrip();
        renderAssetGrid();
        renderSettings();
        updateShortcode();
        setDirty(false);
    }

    function renderStats() {
        const el = document.getElementById('lmt-stats-row');
        if (!el) return;
        const total   = state.assets.length;
        const active  = state.tickerOrder.length;
        const cryptos = state.assets.filter(a => a.type === 'crypto').length;
        const stocks  = state.assets.filter(a => a.type !== 'crypto').length;
        el.innerHTML = `
            <div class="lmt-stat"><div class="lmt-stat-num">${total}</div><div class="lmt-stat-lbl">Activos totales</div></div>
            <div class="lmt-stat"><div class="lmt-stat-num" style="color:#22c55e">${active}</div><div class="lmt-stat-lbl">En el ticker</div></div>
            <div class="lmt-stat"><div class="lmt-stat-num" style="color:#f59e0b">${cryptos}</div><div class="lmt-stat-lbl">Criptomonedas</div></div>
            <div class="lmt-stat"><div class="lmt-stat-num" style="color:#0ea5e9">${stocks}</div><div class="lmt-stat-lbl">ETFs / Acciones</div></div>`;
    }

    // ── Ticker strip (chips arrastrables) ─────────────────────────────────────
    function renderTickerStrip() {
        const strip = document.getElementById('lmt-ticker-strip');
        if (!strip) return;
        if (!state.tickerOrder.length) {
            strip.innerHTML = '<span class="lmt-ticker-order-empty">Sin activos seleccionados. Activa activos desde la lista de abajo.</span>';
            return;
        }
        strip.innerHTML = state.tickerOrder.map(sym => {
            const a = state.assets.find(x => x.symbol === sym);
            if (!a) return '';
            const badge = getBadgeHtml(a, 20);
            return `<div class="lmt-ticker-chip" draggable="true" data-sym="${sym}">
                <div class="chip-badge">${badge}</div>
                <span>${a.ticker}</span>
                <button class="chip-remove" data-sym="${sym}" title="Quitar del ticker">×</button>
            </div>`;
        }).join('');

        // Drag & drop para reordenar
        strip.querySelectorAll('.lmt-ticker-chip').forEach(chip => {
            chip.addEventListener('dragstart', e => {
                state.dragSrc = chip.dataset.sym;
                chip.classList.add('dragging');
            });
            chip.addEventListener('dragend', () => {
                chip.classList.remove('dragging');
                state.dragSrc = null;
            });
            chip.addEventListener('dragover', e => {
                e.preventDefault();
                const target = e.currentTarget.dataset.sym;
                if (state.dragSrc && state.dragSrc !== target) {
                    const from = state.tickerOrder.indexOf(state.dragSrc);
                    const to   = state.tickerOrder.indexOf(target);
                    if (from !== -1 && to !== -1) {
                        state.tickerOrder.splice(from, 1);
                        state.tickerOrder.splice(to, 0, state.dragSrc);
                        renderTickerStrip();
                        setDirty(true);
                    }
                }
            });
        });
        strip.querySelectorAll('.chip-remove').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                removeFromTicker(btn.dataset.sym);
            });
        });
    }

    // ── Asset grid ────────────────────────────────────────────────────────────
    function renderAssetGrid() {
        const grid = document.getElementById('lmt-asset-grid');
        if (!grid) return;

        const filtered = state.assets.filter(a => {
            if (state.filter !== 'all' && a.type !== state.filter) return false;
            if (state.search) {
                const q = state.search.toLowerCase();
                return a.symbol.toLowerCase().includes(q) ||
                       a.label.toLowerCase().includes(q)  ||
                       a.ticker.toLowerCase().includes(q);
            }
            return true;
        });

        if (!filtered.length) {
            grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:#94a3b8;padding:32px">No se encontraron activos</div>';
            return;
        }

        grid.innerHTML = filtered.map(a => {
            const inTicker = state.tickerOrder.includes(a.symbol);
            const typeCls  = { crypto: 'lmt-type-crypto', etf: 'lmt-type-etf', stock: 'lmt-type-stock', forex: 'lmt-type-forex', future: 'lmt-type-future' }[a.type] || '';
            const typeLabel = { crypto: 'Cripto', etf: 'ETF', stock: 'Acción', forex: 'Forex', future: 'Futuro' }[a.type] || a.type;
            const badge = getBadgeHtml(a, 40);

            return `<div class="lmt-asset-card${inTicker ? ' active-in-ticker' : ''}" data-sym="${a.symbol}">
                <div class="lmt-asset-badge">${badge}</div>
                <div class="lmt-asset-info">
                    <div class="lmt-asset-name">${a.label}</div>
                    <div class="lmt-asset-meta">
                        <span class="lmt-asset-sym">${a.symbol}</span>
                        <span class="lmt-asset-type ${typeCls}">${typeLabel}</span>
                    </div>
                </div>
                <button class="lmt-toggle-btn ${inTicker ? 'on' : ''}" data-sym="${a.symbol}" title="${inTicker ? 'Quitar del ticker' : 'Añadir al ticker'}">
                    <span class="dashicons ${inTicker ? 'dashicons-yes' : 'dashicons-plus'}"></span>
                </button>
                <div class="lmt-asset-actions">
                    <button class="lmt-btn-icon" data-action="edit" data-sym="${a.symbol}" title="Editar">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                    <button class="lmt-btn-icon danger" data-action="delete" data-sym="${a.symbol}" title="Eliminar">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            </div>`;
        }).join('');

        grid.querySelectorAll('.lmt-toggle-btn').forEach(btn => {
            btn.addEventListener('click', () => toggleTicker(btn.dataset.sym));
        });
        grid.querySelectorAll('[data-action="edit"]').forEach(btn => {
            btn.addEventListener('click', () => openEditModal(btn.dataset.sym));
        });
        grid.querySelectorAll('[data-action="delete"]').forEach(btn => {
            btn.addEventListener('click', () => deleteAsset(btn.dataset.sym));
        });
    }

    // ── Settings panel ────────────────────────────────────────────────────────
    function renderSettings() {
        const s = state.settings;
        // Velocidad
        document.querySelectorAll('.lmt-speed-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.speed === (s.ticker_speed || 'normal'));
        });
        // Refresh
        const rInput = document.getElementById('lmt-refresh-input');
        if (rInput) rInput.value = s.refresh_rate || 60;
        // Toggles
        const sparkToggle = document.getElementById('lmt-sparkline-toggle');
        if (sparkToggle) sparkToggle.checked = s.show_sparkline !== false;
    }

    function updateShortcode() {
        const syms = state.tickerOrder.join(',');
        const speed = state.settings.ticker_speed || 'normal';
        const refresh = state.settings.refresh_rate || 60;
        const sc = `[live_ticker symbols="${syms}" speed="${speed}" refresh="${refresh}"]`;
        const box = document.getElementById('lmt-shortcode-value');
        if (box) box.textContent = syms ? sc : '[live_ticker]';
    }

    // ── Acciones sobre activos ────────────────────────────────────────────────
    function toggleTicker(sym) {
        const idx = state.tickerOrder.indexOf(sym);
        if (idx === -1) {
            state.tickerOrder.push(sym);
        } else {
            state.tickerOrder.splice(idx, 1);
        }
        renderTickerStrip();
        renderAssetGrid();
        updateShortcode();
        renderStats();
        setDirty(true);
    }

    function removeFromTicker(sym) {
        state.tickerOrder = state.tickerOrder.filter(s => s !== sym);
        renderTickerStrip();
        renderAssetGrid();
        updateShortcode();
        renderStats();
        setDirty(true);
    }

    function deleteAsset(sym) {
        if (!confirm(`¿Eliminar "${sym}" de la lista de activos?`)) return;
        state.assets      = state.assets.filter(a => a.symbol !== sym);
        state.tickerOrder = state.tickerOrder.filter(s => s !== sym);
        renderAll();
        setDirty(true);
    }

    // ── Modal de edición ──────────────────────────────────────────────────────
    function openEditModal(sym) {
        const asset = state.assets.find(a => a.symbol === sym) || {
            symbol: '', label: '', ticker: '', type: 'stock', color: 'slate', iconType: 'svg', iconUrl: ''
        };
        state.editingAsset = JSON.parse(JSON.stringify(asset));
        renderEditModal(asset.symbol ? 'edit' : 'add');
    }

    function openAddModal() {
        state.editingAsset = { symbol: '', label: '', ticker: '', type: 'stock', color: 'blue', iconType: 'svg', iconUrl: '' };
        renderEditModal('add');
    }

    function renderEditModal(mode) {
        const a   = state.editingAsset;
        const isEdit = mode === 'edit';

        const colorGrid = Object.entries(COLORS).map(([name, hex]) =>
            `<div class="lmt-color-dot ${a.color === name ? 'selected' : ''}"
                  style="background:${hex}" data-color="${name}" title="${name}"></div>`
        ).join('');

        const typeOpts = ['crypto','etf','stock','forex','future'].map(t =>
            `<option value="${t}" ${a.type===t?'selected':''}>${{crypto:'Cripto',etf:'ETF',stock:'Acción',forex:'Forex',future:'Futuro'}[t]}</option>`
        ).join('');

        const html = `<div class="lmt-modal-overlay" id="lmt-modal">
            <div class="lmt-modal">
                <div class="lmt-modal-header">
                    <h3>${isEdit ? '✏️ Editar activo' : '➕ Añadir activo'}</h3>
                    <button class="lmt-modal-close" id="lmt-modal-close">×</button>
                </div>
                <div class="lmt-modal-body">
                    <div class="lmt-badge-preview">
                        <div class="lmt-badge-preview-circle" id="badge-preview-circle">
                            ${getBadgeHtml(a, 56)}
                        </div>
                        <span>Vista previa del icono</span>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        <div class="lmt-field">
                            <label>Símbolo Yahoo Finance</label>
                            <input type="text" id="m-symbol" placeholder="BTC-USD, AAPL…"
                                   value="${a.symbol}" ${isEdit ? 'readonly style="background:#f1f5f9"' : ''}>
                        </div>
                        <div class="lmt-field">
                            <label>Ticker (badge)</label>
                            <input type="text" id="m-ticker" placeholder="BTC" value="${a.ticker}" maxlength="6">
                        </div>
                    </div>

                    <div class="lmt-field">
                        <label>Nombre completo</label>
                        <input type="text" id="m-label" placeholder="Bitcoin" value="${a.label}">
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        <div class="lmt-field">
                            <label>Tipo</label>
                            <select id="m-type">${typeOpts}</select>
                        </div>
                        <div class="lmt-field">
                            <label>Color del badge</label>
                            <div class="lmt-color-grid" id="m-color-grid">${colorGrid}</div>
                        </div>
                    </div>

                    <div class="lmt-field">
                        <label>Tipo de icono</label>
                        <div class="lmt-icon-type">
                            <button class="lmt-icon-type-btn ${a.iconType!=='url'?'active':''}" data-itype="svg">
                                🔤 Texto/color
                            </button>
                            <button class="lmt-icon-type-btn ${a.iconType==='url'?'active':''}" data-itype="url">
                                🌐 URL de imagen
                            </button>
                        </div>
                        <div id="m-icon-url-wrap" style="${a.iconType==='url'?'':'display:none'}">
                            <input type="url" id="m-icon-url" placeholder="https://…" value="${a.iconUrl||''}">
                            <p style="font-size:0.75rem;color:#64748b;margin:4px 0 0">
                                URL de imagen cuadrada o circular (PNG, SVG). Recomendado: 40×40px mínimo.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="lmt-modal-footer">
                    <button class="lmt-btn lmt-btn-outline" id="lmt-modal-cancel">Cancelar</button>
                    <button class="lmt-btn lmt-btn-primary" id="lmt-modal-save">
                        <span class="dashicons dashicons-yes"></span>
                        ${isEdit ? 'Guardar cambios' : 'Añadir activo'}
                    </button>
                </div>
            </div>
        </div>`;

        document.body.insertAdjacentHTML('beforeend', html);
        bindModalEvents();
    }

    function bindModalEvents() {
        const modal = document.getElementById('lmt-modal');
        if (!modal) return;

        const closeModal = () => { modal.remove(); state.editingAsset = null; };
        document.getElementById('lmt-modal-close')?.addEventListener('click', closeModal);
        document.getElementById('lmt-modal-cancel')?.addEventListener('click', closeModal);
        modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

        // Color picker
        modal.querySelectorAll('.lmt-color-dot').forEach(dot => {
            dot.addEventListener('click', () => {
                modal.querySelectorAll('.lmt-color-dot').forEach(d => d.classList.remove('selected'));
                dot.classList.add('selected');
                state.editingAsset.color = dot.dataset.color;
                updateBadgePreview();
            });
        });

        // Icon type toggle
        modal.querySelectorAll('.lmt-icon-type-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                modal.querySelectorAll('.lmt-icon-type-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                state.editingAsset.iconType = btn.dataset.itype;
                const wrap = document.getElementById('m-icon-url-wrap');
                if (wrap) wrap.style.display = btn.dataset.itype === 'url' ? '' : 'none';
                updateBadgePreview();
            });
        });

        // Live preview on input
        ['m-ticker', 'm-icon-url'].forEach(id => {
            document.getElementById(id)?.addEventListener('input', () => {
                state.editingAsset.ticker  = document.getElementById('m-ticker')?.value || '';
                state.editingAsset.iconUrl = document.getElementById('m-icon-url')?.value || '';
                updateBadgePreview();
            });
        });

        // Save
        document.getElementById('lmt-modal-save')?.addEventListener('click', () => {
            const sym    = document.getElementById('m-symbol')?.value.trim().toUpperCase();
            const label  = document.getElementById('m-label')?.value.trim();
            const ticker = document.getElementById('m-ticker')?.value.trim().toUpperCase();
            const type   = document.getElementById('m-type')?.value;
            const iconUrl = document.getElementById('m-icon-url')?.value.trim();

            if (!sym || !label || !ticker) {
                showNotice('Por favor completa Símbolo, Ticker y Nombre.', 'error');
                return;
            }

            const existing = state.assets.findIndex(a => a.symbol === sym);
            const asset = {
                symbol:   sym,
                label:    label,
                ticker:   ticker,
                type:     type,
                color:    state.editingAsset.color,
                iconType: state.editingAsset.iconType,
                iconUrl:  iconUrl,
            };

            if (existing !== -1) {
                state.assets[existing] = asset;
            } else {
                state.assets.push(asset);
            }

            closeModal();
            renderAll();
            setDirty(true);
            showNotice(`Activo "${label}" ${existing !== -1 ? 'actualizado' : 'añadido'} correctamente.`, 'success');
        });
    }

    function updateBadgePreview() {
        const circle = document.getElementById('badge-preview-circle');
        if (!circle || !state.editingAsset) return;
        circle.innerHTML = getBadgeHtml(state.editingAsset, 56);
    }

    // ── Eventos globales ──────────────────────────────────────────────────────
    function bindEvents() {
        // Tabs
        document.querySelectorAll('.lmt-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.lmt-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.lmt-panel').forEach(p => p.classList.remove('active'));
                tab.classList.add('active');
                const target = document.getElementById('panel-' + tab.dataset.tab);
                if (target) target.classList.add('active');
            });
        });

        // Filtros
        document.querySelectorAll('.lmt-filter-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.lmt-filter-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                state.filter = btn.dataset.filter;
                renderAssetGrid();
            });
        });

        // Búsqueda
        const searchInput = document.getElementById('lmt-search');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                state.search = searchInput.value;
                renderAssetGrid();
            });
        }

        // Botón añadir
        document.getElementById('lmt-add-btn')?.addEventListener('click', openAddModal);

        // Speed buttons
        document.querySelectorAll('.lmt-speed-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.lmt-speed-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                state.settings.ticker_speed = btn.dataset.speed;
                setDirty(true);
                updateShortcode();
            });
        });

        // Refresh input
        document.getElementById('lmt-refresh-input')?.addEventListener('input', e => {
            state.settings.refresh_rate = Math.max(30, parseInt(e.target.value) || 60);
            setDirty(true);
            updateShortcode();
        });

        // Sparkline toggle
        document.getElementById('lmt-sparkline-toggle')?.addEventListener('change', e => {
            state.settings.show_sparkline = e.target.checked;
            setDirty(true);
        });

        // Guardar
        document.getElementById('lmt-save-btn')?.addEventListener('click', saveAll);

        // Copiar shortcode
        document.getElementById('lmt-copy-shortcode')?.addEventListener('click', () => {
            const text = document.getElementById('lmt-shortcode-value')?.textContent || '';
            navigator.clipboard.writeText(text).then(() => {
                const btn = document.getElementById('lmt-copy-shortcode');
                btn.textContent = '✓ Copiado';
                btn.classList.add('copied');
                setTimeout(() => { btn.textContent = 'Copiar'; btn.classList.remove('copied'); }, 2000);
            });
        });
    }

    // ── Guardar via AJAX ──────────────────────────────────────────────────────
    async function saveAll() {
        const btn = document.getElementById('lmt-save-btn');
        const statusEl = document.getElementById('lmt-save-status');
        if (btn) btn.disabled = true;
        setStatus('saving', '💾 Guardando…');

        try {
            const res = await fetch(window.LMT_Admin_Data.ajaxUrl, {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    new URLSearchParams({
                    action:   'lmt_save_dashboard',
                    nonce:    window.LMT_Admin_Data.nonce,
                    assets:   JSON.stringify(state.assets),
                    order:    JSON.stringify(state.tickerOrder),
                    settings: JSON.stringify(state.settings),
                }),
            });
            const data = await res.json();
            if (data.success) {
                setStatus('saved', '✓ Guardado');
                setDirty(false);
                showNotice('Configuración guardada correctamente.', 'success');
            } else {
                throw new Error(data.data || 'Error desconocido');
            }
        } catch (err) {
            setStatus('', '⚠ Error al guardar');
            showNotice('Error: ' + err.message, 'error');
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    function setDirty(val) {
        state.dirty = val;
        const btn = document.getElementById('lmt-save-btn');
        if (btn) {
            btn.style.opacity = val ? '1' : '0.6';
            btn.title = val ? 'Hay cambios sin guardar' : 'Sin cambios pendientes';
        }
        if (!val) setStatus('saved', '✓ Todo guardado');
        else       setStatus('', '● Cambios pendientes');
    }

    function setStatus(cls, msg) {
        const el = document.getElementById('lmt-save-status');
        if (!el) return;
        el.className = 'lmt-save-status' + (cls ? ' ' + cls : '');
        el.textContent = msg;
    }

    // ── Notificaciones ────────────────────────────────────────────────────────
    function showNotice(msg, type = 'info') {
        const existing = document.getElementById('lmt-notice');
        if (existing) existing.remove();

        const icons = { success: '✓', error: '⚠', info: 'ℹ' };
        const html = `<div class="lmt-notice lmt-notice-${type}" id="lmt-notice">
            <span>${icons[type]}</span> ${msg}
        </div>`;
        const container = document.getElementById('lmt-notice-area');
        if (container) container.insertAdjacentHTML('afterbegin', html);
        setTimeout(() => document.getElementById('lmt-notice')?.remove(), 4000);
    }

    // ── Arrancar ──────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', init);

})();
