<?php
/**
 * Clase para obtener datos de Yahoo Finance
 */
class LMT_Yahoo_Finance {

    // Mapa de símbolos a metadatos visuales
    private static $symbol_meta = [
        // ── Criptomonedas ──────────────────────────────────────────────────
        'BTC-USD'  => ['label' => 'Bitcoin',      'ticker' => 'BTC',  'color' => 'orange'],
        'ETH-USD'  => ['label' => 'Ethereum',     'ticker' => 'ETH',  'color' => 'blue'],
        'SOL-USD'  => ['label' => 'Solana',       'ticker' => 'SOL',  'color' => 'purple'],
        'BNB-USD'  => ['label' => 'BNB',          'ticker' => 'BNB',  'color' => 'yellow'],
        'XRP-USD'  => ['label' => 'Ripple',       'ticker' => 'XRP',  'color' => 'sky'],
        'ADA-USD'  => ['label' => 'Cardano',      'ticker' => 'ADA',  'color' => 'teal'],
        'DOGE-USD' => ['label' => 'Dogecoin',     'ticker' => 'DOGE', 'color' => 'amber'],
        'DOT-USD'  => ['label' => 'Polkadot',     'ticker' => 'DOT',  'color' => 'pink'],
        'AVAX-USD' => ['label' => 'Avalanche',    'ticker' => 'AVAX', 'color' => 'red'],
        'LINK-USD' => ['label' => 'Chainlink',    'ticker' => 'LINK', 'color' => 'blue'],
        'UNI-USD'  => ['label' => 'Uniswap',      'ticker' => 'UNI',  'color' => 'pink'],
        'MATIC-USD'=> ['label' => 'Polygon',      'ticker' => 'MATIC','color' => 'purple'],
        'LTC-USD'  => ['label' => 'Litecoin',     'ticker' => 'LTC',  'color' => 'slate'],
        'ATOM-USD' => ['label' => 'Cosmos',       'ticker' => 'ATOM', 'color' => 'indigo'],
        'FIL-USD'  => ['label' => 'Filecoin',     'ticker' => 'FIL',  'color' => 'sky'],
        'NEAR-USD' => ['label' => 'NEAR Protocol','ticker' => 'NEAR', 'color' => 'green'],
        'APT-USD'  => ['label' => 'Aptos',        'ticker' => 'APT',  'color' => 'teal'],
        'SUI-USD'  => ['label' => 'Sui',          'ticker' => 'SUI',  'color' => 'blue'],
        // ── ETFs índice ────────────────────────────────────────────────────
        'SPY'  => ['label' => 'S&P 500 ETF',    'ticker' => 'SPY',  'color' => 'slate'],
        'QQQ'  => ['label' => 'Nasdaq ETF',     'ticker' => 'QQQ',  'color' => 'slate'],
        'DIA'  => ['label' => 'Dow Jones ETF',  'ticker' => 'DIA',  'color' => 'slate'],
        'IWM'  => ['label' => 'Russell 2000',   'ticker' => 'IWM',  'color' => 'slate'],
        'VTI'  => ['label' => 'Total Market',   'ticker' => 'VTI',  'color' => 'green'],
        'GLD'  => ['label' => 'Gold ETF',       'ticker' => 'GLD',  'color' => 'yellow'],
        'SLV'  => ['label' => 'Silver ETF',     'ticker' => 'SLV',  'color' => 'slate'],
        'ARKK' => ['label' => 'ARK Innov.',     'ticker' => 'ARKK', 'color' => 'indigo'],
        'TLT'  => ['label' => 'Bonos 20Y',      'ticker' => 'TLT',  'color' => 'slate'],
        'USO'  => ['label' => 'Petróleo ETF',   'ticker' => 'USO',  'color' => 'amber'],
        // ── Acciones tech ──────────────────────────────────────────────────
        'AAPL'  => ['label' => 'Apple',          'ticker' => 'AAPL', 'color' => 'slate'],
        'MSFT'  => ['label' => 'Microsoft',      'ticker' => 'MSFT', 'color' => 'blue'],
        'NVDA'  => ['label' => 'NVIDIA',         'ticker' => 'NVDA', 'color' => 'green'],
        'TSLA'  => ['label' => 'Tesla',          'ticker' => 'TSLA', 'color' => 'red'],
        'AMZN'  => ['label' => 'Amazon',         'ticker' => 'AMZN', 'color' => 'amber'],
        'GOOGL' => ['label' => 'Alphabet',       'ticker' => 'GOOGL','color' => 'sky'],
        'META'  => ['label' => 'Meta',           'ticker' => 'META', 'color' => 'blue'],
        'NFLX'  => ['label' => 'Netflix',        'ticker' => 'NFLX', 'color' => 'red'],
        'AMD'   => ['label' => 'AMD',            'ticker' => 'AMD',  'color' => 'red'],
        'INTC'  => ['label' => 'Intel',          'ticker' => 'INTC', 'color' => 'blue'],
        'ORCL'  => ['label' => 'Oracle',         'ticker' => 'ORCL', 'color' => 'red'],
        'CRM'   => ['label' => 'Salesforce',     'ticker' => 'CRM',  'color' => 'sky'],
        'ADBE'  => ['label' => 'Adobe',          'ticker' => 'ADBE', 'color' => 'red'],
        'PYPL'  => ['label' => 'PayPal',         'ticker' => 'PYPL', 'color' => 'blue'],
        'SHOP'  => ['label' => 'Shopify',        'ticker' => 'SHOP', 'color' => 'green'],
        'COIN'  => ['label' => 'Coinbase',       'ticker' => 'COIN', 'color' => 'blue'],
        'MSTR'  => ['label' => 'MicroStrategy',  'ticker' => 'MSTR', 'color' => 'orange'],
        // ── Otras acciones ─────────────────────────────────────────────────
        'JPM'   => ['label' => 'JPMorgan',       'ticker' => 'JPM',  'color' => 'slate'],
        'BAC'   => ['label' => 'Bank of America','ticker' => 'BAC',  'color' => 'red'],
        'GS'    => ['label' => 'Goldman Sachs',  'ticker' => 'GS',   'color' => 'slate'],
        'V'     => ['label' => 'Visa',           'ticker' => 'V',    'color' => 'blue'],
        'MA'    => ['label' => 'Mastercard',     'ticker' => 'MA',   'color' => 'red'],
        'DIS'   => ['label' => 'Disney',         'ticker' => 'DIS',  'color' => 'sky'],
        'UBER'  => ['label' => 'Uber',           'ticker' => 'UBER', 'color' => 'slate'],
        'ABNB'  => ['label' => 'Airbnb',         'ticker' => 'ABNB', 'color' => 'pink'],
        'HIMS'  => ['label' => 'Hims & Hers',    'ticker' => 'HIMS', 'color' => 'green'],
        'TMC'   => ['label' => 'TMC',            'ticker' => 'TMC',  'color' => 'slate'],
        // ── Forex / Divisas ────────────────────────────────────────────────
        'EURUSD=X' => ['label' => 'EUR/USD', 'ticker' => 'EUR', 'color' => 'sky'],
        'GBPUSD=X' => ['label' => 'GBP/USD', 'ticker' => 'GBP', 'color' => 'indigo'],
        'USDJPY=X' => ['label' => 'USD/JPY', 'ticker' => 'JPY', 'color' => 'red'],
        'DOT-EUR'  => ['label' => 'DOT/EUR',  'ticker' => 'DOT', 'color' => 'pink'],
        // ── Materias primas (futuros) ──────────────────────────────────────
        'GC=F'  => ['label' => 'Oro',            'ticker' => 'XAU',  'color' => 'yellow'],
        'SI=F'  => ['label' => 'Plata',          'ticker' => 'XAG',  'color' => 'slate'],
        'CL=F'  => ['label' => 'Crudo WTI',      'ticker' => 'WTI',  'color' => 'amber'],
        'BZ=F'  => ['label' => 'Crudo Brent',    'ticker' => 'BRENT','color' => 'amber'],
        'ITA'   => ['label' => 'iShares Italy',  'ticker' => 'ITA',  'color' => 'green'],
    ];

    /**
     * Obtiene datos de uno o varios símbolos desde Yahoo Finance
     */
    public static function fetch(array $symbols): array {
        $results = [];
        
        // Intentar desde caché de WordPress (2 minutos)
        $cache_key = 'lmt_' . md5(implode(',', $symbols));
        $cached    = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        foreach ($symbols as $symbol) {
            $data = self::fetch_single($symbol);
            if ($data) {
                $results[$symbol] = $data;
            }
        }

        if (!empty($results)) {
            set_transient($cache_key, $results, 120); // 2 min cache
        }

        return $results;
    }

    /**
     * Consulta Yahoo Finance para un símbolo.
     * Usa range=1mo&interval=1d para garantizar ~20 velas diarias
     * sin importar si el mercado está abierto o cerrado.
     */
    private static function fetch_single(string $symbol): ?array {
        $url = sprintf(
            'https://query1.finance.yahoo.com/v8/finance/chart/%s?interval=1d&range=1mo',
            urlencode($symbol)
        );

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . ')',
                'Accept'     => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        if (empty($json['chart']['result'][0])) {
            return null;
        }

        $result = $json['chart']['result'][0];
        $meta   = $result['meta'] ?? [];
        $quote  = $result['indicators']['quote'][0] ?? [];

        // Limpiar nulos manteniendo orden temporal
        $closes = array_values(array_filter($quote['close'] ?? [], fn($v) => $v !== null));
        $highs  = array_values(array_filter($quote['high']  ?? [], fn($v) => $v !== null));
        $lows   = array_values(array_filter($quote['low']   ?? [], fn($v) => $v !== null));

        if (empty($closes)) {
            return null;
        }

        $price      = (float) ($meta['regularMarketPrice'] ?? end($closes));
        $prev       = (float) ($meta['previousClose'] ?? $meta['chartPreviousClose'] ?? $closes[count($closes) - 2] ?? $price);
        $change     = $price - $prev;
        $change_pct = $prev > 0 ? ($change / $prev) * 100 : 0;

        $sym_meta = self::$symbol_meta[$symbol] ?? [
            'label'  => $symbol,
            'ticker' => strtok($symbol, '-'),
            'color'  => 'slate',
        ];

        // Sparkline: enviar precios CRUDOS (el JS normaliza).
        // Tomamos hasta 30 cierres diarios. Si hay menos de 2, añadimos
        // el precio actual para garantizar al menos 2 puntos.
        $raw = array_slice($closes, -30);
        if (count($raw) < 2) {
            $raw[] = $price;
        }
        // Redondear a 6 decimales para ahorrar bytes
        $sparkline = array_map(fn($v) => round((float)$v, 6), $raw);

        return [
            'symbol'     => $symbol,
            'ticker'     => $sym_meta['ticker'],
            'label'      => $sym_meta['label'],
            'color'      => $sym_meta['color'],
            'price'      => $price,
            'change'     => $change,
            'change_pct' => $change_pct,
            'high'       => !empty($highs) ? max($highs) : null,
            'low'        => !empty($lows)  ? min($lows)  : null,
            'volume'     => $meta['regularMarketVolume'] ?? null,
            'sparkline'  => $sparkline,
            'currency'   => $meta['currency'] ?? 'USD',
            'updated_at' => time(),
        ];
    }
}
