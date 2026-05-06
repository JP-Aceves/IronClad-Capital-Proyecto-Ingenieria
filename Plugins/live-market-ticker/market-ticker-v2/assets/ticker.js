/**
 * Live Market Ticker — Frontend JS
 * Obtiene datos del endpoint REST de WordPress y actualiza el ticker
 */
(function () {
    'use strict';

    // ── Utilidades de formato ────────────────────────────────────────────────

    function formatPrice(price, currency) {
        if (price === null || price === undefined) return '—';
        const decimals = price >= 1000 ? 2 : price >= 1 ? 4 : 6;
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency || 'USD',
            minimumFractionDigits: 2,
            maximumFractionDigits: decimals,
        }).format(price);
    }

    function formatPct(pct) {
        const sign = pct >= 0 ? '+' : '';
        return sign + pct.toFixed(2) + '%';
    }

    function formatTime(ts) {
        return new Date(ts * 1000).toLocaleTimeString('es-ES', {
            hour: '2-digit', minute: '2-digit', second: '2-digit'
        });
    }

    function formatVolume(v) {
        if (v >= 1e9) return (v / 1e9).toFixed(2) + 'B';
        if (v >= 1e6) return (v / 1e6).toFixed(2) + 'M';
        if (v >= 1e3) return (v / 1e3).toFixed(1) + 'K';
        return v.toString();
    }

    // ── Sparkline SVG con área rellena ──────────────────────────────────────
    // Recibe precios CRUDOS (no normalizados). Hace la escala aquí.

    function buildSparkline(points, direction) {
        if (!points || points.length < 2) return '';

        const W = 80, H = 36, PX = 2, PY = 4;
        const drawW = W - PX * 2;
        const drawH = H - PY * 2;

        // --- Normalización robusta ---
        const minV = Math.min(...points);
        const maxV = Math.max(...points);
        let range  = maxV - minV;

        // Si todos los puntos son iguales, crear variación artificial mínima
        // para que se vea una línea horizontal centrada (no plana en el borde)
        if (range === 0 || range < minV * 0.0001) {
            range = minV * 0.02 || 1; // 2% de variación visible
        }

        const toY = v => PY + drawH - ((v - minV) / range) * drawH;
        const toX = (i, total) => PX + (i / (total - 1)) * drawW;

        const n = points.length;
        const coords = points.map((v, i) => [
            +toX(i, n).toFixed(2),
            +toY(v).toFixed(2)
        ]);

        // Path de la línea
        const lineD = coords.map((c, i) =>
            (i === 0 ? `M${c[0]},${c[1]}` : `L${c[0]},${c[1]}`)
        ).join(' ');

        // Path del área (línea + bajada a la base + cierre)
        const baseY  = PY + drawH;
        const areaD  = `${lineD} L${coords[n-1][0]},${baseY} L${coords[0][0]},${baseY} Z`;

        // Colores
        const colors = {
            up:   '#22c55e',
            down: '#ef4444',
            flat: '#94a3b8',
        };
        const stroke = colors[direction] || colors.flat;
        // ID único para el gradiente (evitar colisiones entre múltiples items)
        const gid = 'lg' + Math.random().toString(36).slice(2, 8);

        return `<svg class="lmt-sparkline lmt-sparkline-${direction}"
                     viewBox="0 0 ${W} ${H}"
                     preserveAspectRatio="none"
                     xmlns="http://www.w3.org/2000/svg"
                     aria-hidden="true">
            <defs>
                <linearGradient id="${gid}" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%"   stop-color="${stroke}" stop-opacity="0.4"/>
                    <stop offset="100%" stop-color="${stroke}" stop-opacity="0.03"/>
                </linearGradient>
            </defs>
            <path d="${areaD}" fill="url(#${gid})"/>
            <path d="${lineD}" fill="none" stroke="${stroke}" stroke-width="1.8"
                  stroke-linecap="round" stroke-linejoin="round"/>
        </svg>`;
    }

    // ── Mapa de logos ────────────────────────────────────────────────────────
    // Cryptos: CoinGecko (gratis, sin API key)
    // ETFs/Acciones: logos via logo.clearbit.com o iniciales estilizadas

    // ── Logos de acciones via logo.dev (fiable, sin API key para tamaño pequeño)
    // Formato: https://img.logo.dev/DOMINIO?token=pk_public&size=40
    // Para cryptos seguimos usando CoinGecko CDN

    // ── Metadatos del dashboard (se cargan async desde la API) ───────────────
    // Mapa símbolo → { label, ticker, color, iconType, iconUrl }
    let DASHBOARD_ASSETS = {};

    async function loadDashboardAssets() {
        try {
            const url = LMT_Config.api_url.replace('/quotes', '/assets');
            const res = await fetch(url, { cache: 'default' });
            if (res.ok) DASHBOARD_ASSETS = await res.json();
        } catch (e) { /* silencioso — usar fallbacks hardcodeados */ }
    }

    // Helper: genera SVG de círculo con texto
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

    // ── Logos externos solo para cryptos (CoinGecko, muy fiable) ──────────────
    const CRYPTO_LOGOS = {
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

    // ── SVG para todo lo demás (acciones, ETFs, forex, futuros) ──────────────
    // Diseño: fondo oscuro temático + iniciales/ticker en color claro
    const SVG_ICONS = {
        // ── Acciones tech ─────────────────────────────────────────────────
        'AAPL':  svgBadge('',    '#1c1917', '#e2e8f0'),   // Apple → logo SVG especial abajo
        'MSFT':  svgBadge('MS',  '#0369a1', '#bae6fd'),
        'NVDA':  svgBadge('NV',  '#16a34a', '#bbf7d0'),
        'TSLA':  svgBadge('T',   '#dc2626', '#fca5a5'),
        'AMZN':  svgBadge('az',  '#92400e', '#fde68a'),
        'GOOGL': svgBadge('G',   '#1d4ed8', '#bfdbfe'),
        'META':  svgBadge('f',   '#1d4ed8', '#93c5fd'),
        'NFLX':  svgBadge('N',   '#dc2626', '#fca5a5'),
        'AMD':   svgBadge('AMD', '#dc2626', '#fca5a5'),
        'INTC':  svgBadge('INT', '#0369a1', '#7dd3fc'),
        'ORCL':  svgBadge('OR',  '#dc2626', '#fca5a5'),
        'CRM':   svgBadge('CRM', '#0ea5e9', '#e0f2fe'),
        'ADBE':  svgBadge('AD',  '#dc2626', '#fca5a5'),
        'PYPL':  svgBadge('PP',  '#1d4ed8', '#bfdbfe'),
        'SHOP':  svgBadge('SH',  '#16a34a', '#bbf7d0'),
        'COIN':  svgBadge('C',   '#1d4ed8', '#93c5fd'),
        'MSTR':  svgBadge('MS',  '#ea580c', '#fed7aa'),
        'JPM':   svgBadge('JPM', '#1e3a5f', '#93c5fd'),
        'BAC':   svgBadge('BAC', '#dc2626', '#fca5a5'),
        'GS':    svgBadge('GS',  '#334155', '#cbd5e1'),
        'V':     svgBadge('V',   '#1d4ed8', '#bfdbfe'),
        'MA':    svgBadge('MA',  '#dc2626', '#fca5a5'),
        'DIS':   svgBadge('DIS', '#1d4ed8', '#bfdbfe'),
        'UBER':  svgBadge('U',   '#1c1917', '#e7e5e4'),
        'ABNB':  svgBadge('AB',  '#be185d', '#fbcfe8'),
        'HIMS':  svgBadge('HI',  '#0d9488', '#99f6e4'),
        'TMC':   svgBadge('TMC', '#292524', '#d6d3d1'),
        // ── ETFs ──────────────────────────────────────────────────────────
        'SPY':   svgBadge('SPY', '#1e3a5f', '#93c5fd'),
        'QQQ':   svgBadge('QQQ', '#1e3a5f', '#7dd3fc'),
        'DIA':   svgBadge('DIA', '#1e3a5f', '#93c5fd'),
        'IWM':   svgBadge('IWM', '#1e3a5f', '#93c5fd'),
        'GLD':   svgBadge('GLD', '#78350f', '#fde68a'),
        'SLV':   svgBadge('SLV', '#334155', '#e2e8f0'),
        'VTI':   svgBadge('VTI', '#14532d', '#86efac'),
        'ARKK':  svgBadge('ARK', '#312e81', '#c4b5fd'),
        'TLT':   svgBadge('TLT', '#1e3a5f', '#93c5fd'),
        'ITA':   svgBadge('ITA', '#1e3a5f', '#93c5fd'),
        'USO':   svgBadge('USO', '#78350f', '#fcd34d'),
        // ── Forex ─────────────────────────────────────────────────────────
        'EURUSD=X': svgBadge('EUR', '#1e3a8a', '#bfdbfe'),
        'GBPUSD=X': svgBadge('GBP', '#312e81', '#c4b5fd'),
        'USDJPY=X': svgBadge('JPY', '#7f1d1d', '#fca5a5'),
        // ── Futuros ───────────────────────────────────────────────────────
        'GC=F':  svgBadge('XAU', '#78350f', '#fde68a'),
        'SI=F':  svgBadge('XAG', '#334155', '#e2e8f0'),
        'CL=F':  svgBadge('WTI', '#7c2d12', '#fdba74'),
        'BZ=F':  svgBadge('BRT', '#78350f', '#fcd34d'),
    };

    function buildBadge(quote) {
        const sym  = quote.symbol;
        const dash = DASHBOARD_ASSETS[sym] || DASHBOARD_ASSETS[sym.toUpperCase()] || null;

        // ── 1. Datos del dashboard (iconUrl personalizada) ───────────────
        if (dash && dash.iconType === 'url' && dash.iconUrl) {
            const t  = (dash.ticker || quote.ticker).slice(0, 4);
            const fb = encodeURIComponent(
                `<svg viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'><circle cx='20' cy='20' r='20' fill='%23334155'/><text x='50%' y='54%' dominant-baseline='middle' text-anchor='middle' font-family='system-ui' font-weight='800' font-size='${t.length>=4?9:t.length===3?11:14}' fill='%23e2e8f0'>${t}</text></svg>`
            );
            return `<div class="lmt-badge lmt-badge-${dash.color || quote.color} lmt-badge-img">
                <img src="${dash.iconUrl}" alt="${t}" loading="lazy"
                     onerror="this.onerror=null;this.src='data:image/svg+xml,${fb}';" />
            </div>`;
        }

        // ── 2. SVG inline (acciones, ETFs, forex, futuros — 100% fiable) ─
        if (SVG_ICONS[sym]) {
            return `<div class="lmt-badge lmt-badge-${quote.color} lmt-badge-svg">${SVG_ICONS[sym]}</div>`;
        }

        // ── 3. Imagen externa solo para cryptos (CoinGecko muy fiable) ───
        const logoUrl = CRYPTO_LOGOS[sym];
        if (logoUrl) {
            const t  = quote.ticker.slice(0, 4);
            const fs = t.length >= 4 ? 9 : t.length === 3 ? 11 : 14;
            const fb = encodeURIComponent(
                `<svg viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'><circle cx='20' cy='20' r='20' fill='%23334155'/><text x='50%' y='54%' dominant-baseline='middle' text-anchor='middle' font-family='system-ui' font-weight='800' font-size='${fs}' fill='%23e2e8f0'>${t}</text></svg>`
            );
            return `<div class="lmt-badge lmt-badge-${quote.color} lmt-badge-img">
                <img src="${logoUrl}" alt="${quote.ticker}" loading="lazy"
                     onerror="this.onerror=null;this.src='data:image/svg+xml,${fb}';" />
            </div>`;
        }

        // ── 4. Fallback genérico: SVG con iniciales ───────────────────────
        const t = quote.ticker.slice(0, 4);
        return `<div class="lmt-badge lmt-badge-${quote.color} lmt-badge-svg">${svgBadge(t, '#334155', '#e2e8f0')}</div>`;
    }

    // ── Renderizar tarjeta de activo ─────────────────────────────────────────

    function buildItem(quote, showSparkline) {
        // Sobrescribir label/ticker con datos del dashboard si existen
        const dash = DASHBOARD_ASSETS[quote.symbol] || DASHBOARD_ASSETS[quote.symbol.toUpperCase()] || null;
        if (dash) {
            if (dash.label)  quote = { ...quote, label:  dash.label  };
            if (dash.ticker) quote = { ...quote, ticker: dash.ticker };
            if (dash.color)  quote = { ...quote, color:  dash.color  };
        }

        const dir = quote.change_pct > 0.05 ? 'up' : quote.change_pct < -0.05 ? 'down' : 'flat';
        const changeClass = dir === 'up' ? 'lmt-change-up' : dir === 'down' ? 'lmt-change-down' : 'lmt-change-flat';
        const sparkline = showSparkline ? buildSparkline(quote.sparkline, dir) : '';

        // Datos del tooltip serializados en data-attributes (el tooltip
        // se renderiza en el <body> via JS para escapar overflow:hidden del tema)
        const ttData = JSON.stringify({
            label:      quote.label,
            symbol:     quote.symbol,
            price:      formatPrice(quote.price, quote.currency),
            change:     (quote.change >= 0 ? '+' : '') + formatPrice(Math.abs(quote.change || 0), quote.currency),
            change_pct: formatPct(quote.change_pct),
            high:       quote.high  ? formatPrice(quote.high, quote.currency) : '—',
            low:        quote.low   ? formatPrice(quote.low,  quote.currency) : '—',
            volume:     quote.volume ? formatVolume(quote.volume) : '—',
            dir:        dir,
        });

        return `<div class="lmt-item" data-symbol="${quote.symbol}" data-tt='${ttData.replace(/'/g, '&apos;')}'>
            ${buildBadge(quote)}
            <div class="lmt-info">
                <span class="lmt-label">${quote.label}</span>
                <div class="lmt-price-row">
                    <span class="lmt-price">${formatPrice(quote.price, quote.currency)}</span>
                    <span class="lmt-change ${changeClass}">${formatPct(quote.change_pct)}</span>
                </div>
            </div>
            ${sparkline}
        </div>`;
    }

    // ── Flash de actualización ───────────────────────────────────────────────

    function flashItem(el, direction) {
        el.classList.remove('lmt-flash-up', 'lmt-flash-down');
        void el.offsetWidth; // reflow
        if (direction === 'up')   el.classList.add('lmt-flash-up');
        if (direction === 'down') el.classList.add('lmt-flash-down');
    }

    // ── Inicializar un ticker ────────────────────────────────────────────────

    function initTicker(section) {
        const symbols      = section.dataset.symbols || 'BTC-USD,ETH-USD,SPY';
        const refreshRate  = parseInt(section.dataset.refresh || '60') * 1000;
        const showSparkline= section.dataset.sparkline !== 'false';
        const speed        = section.dataset.speed || 'normal';

        const inner  = section.querySelector('.lmt-ticker-inner');
        const dot    = section.querySelector('.lmt-status-dot');
        const status = section.querySelector('.lmt-status-text');
        const lastUp = section.querySelector('.lmt-last-update');

        let prevData = {};
        let firstLoad = true;

        // ── Fetch ────────────────────────────────────────────────────────────
        async function fetchQuotes() {
            try {
                const url = LMT_Config.api_url + '?symbols=' + encodeURIComponent(symbols);
                const res = await fetch(url, {
                    headers: { 'X-WP-Nonce': LMT_Config.nonce },
                    cache: 'no-store',
                });

                if (!res.ok) throw new Error('HTTP ' + res.status);

                const json = await res.json();
                if (!json.success || !json.data.length) throw new Error('No data');

                renderTicker(json.data, json.timestamp);
                setStatus('live', 'En directo');

            } catch (err) {
                console.warn('[LiveTicker] Error:', err.message);
                if (firstLoad) {
                    inner.innerHTML = `<div class="lmt-error-msg">⚠ No se pudieron cargar las cotizaciones.</div>`;
                }
                setStatus('error', 'Error al conectar');
            }
        }

        // ── Render ───────────────────────────────────────────────────────────
        function renderTicker(quotes, timestamp) {
            const html = quotes.map(q => buildItem(q, showSparkline)).join('');

            if (firstLoad) {
                inner.classList.remove('lmt-loading');

                // Repetir suficientes copias para que un set supere el ancho de pantalla
                const screenW   = window.innerWidth || 1440;
                const itemW     = 240;
                const minCopies = Math.ceil((screenW * 2) / (quotes.length * itemW)) + 1;
                const copies    = Math.max(minCopies, 4);

                let repeated = '';
                for (let i = 0; i < copies; i++) repeated += html;

                // Duplicar el bloque para que el loop sea perfecto y sin salto
                inner.innerHTML = repeated + repeated;

                // Duración proporcional al número de items visible
                const totalItems = quotes.length * copies;
                const secPerItem = speed === 'fast' ? 3 : speed === 'slow' ? 8 : 5;
                inner.style.animationDuration = (totalItems * secPerItem) + 's';
                inner.classList.add('lmt-animate');
                firstLoad = false;
            } else {
                // Actualización suave: cambiar solo los valores que cambiaron
                quotes.forEach(q => {
                    const prev = prevData[q.symbol];
                    if (!prev) return;

                    const dir = q.price > prev.price ? 'up' : q.price < prev.price ? 'down' : null;

                    section.querySelectorAll(`[data-symbol="${q.symbol}"]`).forEach(el => {
                        const priceEl    = el.querySelector('.lmt-price');
                        const changeEl   = el.querySelector('.lmt-change');
                        const sparklineEl= el.querySelector('.lmt-sparkline');

                        if (priceEl)  priceEl.textContent  = formatPrice(q.price, q.currency);
                        if (changeEl) {
                            changeEl.textContent = formatPct(q.change_pct);
                            changeEl.className   = 'lmt-change ' + (
                                q.change_pct > 0.05 ? 'lmt-change-up' :
                                q.change_pct < -0.05 ? 'lmt-change-down' : 'lmt-change-flat'
                            );
                        }
                        // Redibujar sparkline con datos frescos
                        if (sparklineEl && q.sparkline && q.sparkline.length >= 2) {
                            const newDir = q.change_pct > 0.05 ? 'up' : q.change_pct < -0.05 ? 'down' : 'flat';
                            const newSvg = buildSparkline(q.sparkline, newDir);
                            const tmp = document.createElement('div');
                            tmp.innerHTML = newSvg;
                            sparklineEl.replaceWith(tmp.firstElementChild);
                        }
                        if (dir) flashItem(el, dir);
                    });
                });
            }

            // Actualizar historial previo
            quotes.forEach(q => prevData[q.symbol] = q);
            if (lastUp) lastUp.textContent = 'Actualizado ' + formatTime(timestamp);
        }

        // ── Status ───────────────────────────────────────────────────────────
        function setStatus(state, text) {
            dot.className    = 'lmt-status-dot lmt-' + state;
            status.textContent = text;
        }

        // ── Bucle de actualización ───────────────────────────────────────────
        fetchQuotes();
        setInterval(fetchQuotes, refreshRate);

        // Pausar cuando la pestaña no está visible
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) fetchQuotes();
        });
    }

    // ── Tooltip global con position:fixed ───────────────────────────────────
    // Se renderiza directamente en el <body> para escapar cualquier
    // overflow:hidden del tema de WordPress.

    let lmtTT = null;

    function ensureTooltip() {
        if (lmtTT) return lmtTT;
        lmtTT = document.createElement('div');
        lmtTT.id = 'lmt-global-tooltip';
        lmtTT.setAttribute('role', 'tooltip');
        document.body.appendChild(lmtTT);
        return lmtTT;
    }

    function showTooltip(item, data) {
        const tt = ensureTooltip();
        const dirIcon = data.dir === 'up' ? '▲' : data.dir === 'down' ? '▼' : '—';
        const dirCls  = 'lmt-tt-' + data.dir;

        tt.innerHTML = `
            <div class="lmt-tt-header">
                <span class="lmt-tt-name">${data.label}</span>
                <span class="lmt-tt-symbol">${data.symbol}</span>
            </div>
            <div class="lmt-tt-price">${data.price}</div>
            <div class="lmt-tt-change ${dirCls}">${dirIcon} ${data.change} (${data.change_pct})</div>
            <div class="lmt-tt-grid">
                <div class="lmt-tt-cell"><span class="lmt-tt-lbl">Máx.</span><span class="lmt-tt-val">${data.high}</span></div>
                <div class="lmt-tt-cell"><span class="lmt-tt-lbl">Mín.</span><span class="lmt-tt-val">${data.low}</span></div>
                <div class="lmt-tt-cell lmt-tt-full"><span class="lmt-tt-lbl">Volumen</span><span class="lmt-tt-val">${data.volume}</span></div>
            </div>
            <div class="lmt-tt-arrow"></div>`;

        tt.classList.add('lmt-tt-visible');
        positionTooltip(item, tt);
    }

    function positionTooltip(item, tt) {
        const rect   = item.getBoundingClientRect();
        const ttW    = tt.offsetWidth  || 210;
        const ttH    = tt.offsetHeight || 150;
        const margin = 10;

        // position:fixed usa coordenadas del viewport — NO sumar scrollY
        let left = rect.left + rect.width / 2 - ttW / 2;
        let top  = rect.top - ttH - margin;

        // Evitar salirse por los bordes horizontales
        left = Math.max(margin, Math.min(left, window.innerWidth - ttW - margin));

        // Si no cabe arriba del ticker, mostrar debajo
        if (top < margin) {
            top = rect.bottom + margin;
            tt.classList.add('lmt-tt-below');
        } else {
            tt.classList.remove('lmt-tt-below');
        }

        tt.style.left = left + 'px';
        tt.style.top  = top  + 'px';

        // Posición de la flecha centrada bajo el activo
        const arrowLeft = (rect.left + rect.width / 2) - left;
        const arrow = tt.querySelector('.lmt-tt-arrow');
        if (arrow) arrow.style.left = Math.max(12, Math.min(arrowLeft, ttW - 12)) + 'px';
    }

    function hideTooltip() {
        if (lmtTT) lmtTT.classList.remove('lmt-tt-visible', 'lmt-tt-below');
    }

    function initGlobalTooltip() {
        // Delegación de eventos en el document
        document.addEventListener('mouseover', function(e) {
            const item = e.target.closest('.lmt-item[data-tt]');
            if (!item) return;
            try {
                const data = JSON.parse(item.dataset.tt.replace(/&apos;/g, "'"));
                showTooltip(item, data);
            } catch(err) { /* silencioso */ }
        });
        document.addEventListener('mouseout', function(e) {
            const item = e.target.closest('.lmt-item[data-tt]');
            if (!item) return;
            if (!item.contains(e.relatedTarget)) hideTooltip();
        });
        // Ocultar al hacer scroll
        window.addEventListener('scroll', hideTooltip, { passive: true });
    }

    // ── Arrancar todos los tickers de la página ──────────────────────────────
    document.addEventListener('DOMContentLoaded', async function () {
        await loadDashboardAssets();   // cargar iconos/nombres del dashboard primero
        initGlobalTooltip();
        document.querySelectorAll('.lmt-ticker-section').forEach(initTicker);
    });

})();
