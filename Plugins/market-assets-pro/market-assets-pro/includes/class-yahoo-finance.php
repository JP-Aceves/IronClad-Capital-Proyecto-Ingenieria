<?php
/**
 * Wrapper Yahoo Finance: cotizaciones en tiempo real + datos históricos.
 * Reutiliza la misma fuente que el plugin Live Market Ticker.
 */
class MAP_Yahoo_Finance {

    private const BASE  = 'https://query1.finance.yahoo.com/v8/finance/chart/';
    private const BASE2 = 'https://query2.finance.yahoo.com/v8/finance/chart/';

    // ─── Cotización rápida (precio actual + cambio 24h) ──────────────────────
    public static function quote(string $symbol): array|false {
        $url = add_query_arg([
            'interval' => '1d',
            'range'    => '5d',
        ], self::BASE . rawurlencode($symbol));

        $resp = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => ['User-Agent' => 'Mozilla/5.0'],
        ]);

        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
            // Fallback al segundo endpoint
            $resp = wp_remote_get(str_replace(self::BASE, self::BASE2, $url), [
                'timeout' => 10,
                'headers' => ['User-Agent' => 'Mozilla/5.0'],
            ]);
            if (is_wp_error($resp)) return false;
        }

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $meta = $body['chart']['result'][0]['meta'] ?? null;
        if (!$meta) return false;

        $prev  = $meta['chartPreviousClose'] ?? $meta['previousClose'] ?? 0;
        $price = $meta['regularMarketPrice'] ?? 0;
        $change_pct = $prev > 0 ? (($price - $prev) / $prev) * 100 : 0;

        return [
            'symbol'        => strtoupper($symbol),
            'name'          => $meta['longName'] ?? $meta['shortName'] ?? $symbol,
            'price'         => round($price, 6),
            'prev_close'    => round($prev, 6),
            'change'        => round($price - $prev, 6),
            'change_pct'    => round($change_pct, 2),
            'volume'        => $meta['regularMarketVolume'] ?? 0,
            'market_cap'    => $meta['marketCap'] ?? null,
            'currency'      => $meta['currency'] ?? 'USD',
            'exchange'      => $meta['exchangeName'] ?? '',
            'type'          => $meta['instrumentType'] ?? 'EQUITY',
            'timestamp'     => time(),
        ];
    }

    // ─── Serie histórica OHLCV ────────────────────────────────────────────────
    public static function history(string $symbol, string $period = '1y', string $interval = '1d'): array|false {
        $cache_key = "map_hist_{$symbol}_{$period}_{$interval}";
        $cached    = get_transient($cache_key);
        // No usar caché vacía (puede ser un fallo guardado previamente)
        if ($cached !== false && is_array($cached) && count($cached) > 0) return $cached;

        $url = add_query_arg([
            'interval' => $interval,
            'range'    => $period,
        ], self::BASE . rawurlencode($symbol));

        $resp = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => ['User-Agent' => 'Mozilla/5.0'],
        ]);

        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
            $resp = wp_remote_get(str_replace(self::BASE, self::BASE2, $url), [
                'timeout' => 15,
                'headers' => ['User-Agent' => 'Mozilla/5.0'],
            ]);
            if (is_wp_error($resp)) return false;
        }

        $body   = json_decode(wp_remote_retrieve_body($resp), true);
        $result = $body['chart']['result'][0] ?? null;
        if (!$result) return false;

        $timestamps = $result['timestamp'] ?? [];
        $q          = $result['indicators']['quote'][0] ?? [];
        $adjclose   = $result['indicators']['adjclose'][0]['adjclose'] ?? [];

        $rows = [];
        foreach ($timestamps as $i => $ts) {
            if (!isset($q['close'][$i])) continue;
            $rows[] = [
                'date'   => date('Y-m-d', $ts),
                'open'   => round((float)($q['open'][$i]  ?? 0), 6),
                'high'   => round((float)($q['high'][$i]  ?? 0), 6),
                'low'    => round((float)($q['low'][$i]   ?? 0), 6),
                'close'  => round((float)($q['close'][$i] ?? 0), 6),
                'volume' => (int)($q['volume'][$i] ?? 0),
                'adj_close' => round((float)($adjclose[$i] ?? $q['close'][$i] ?? 0), 6),
            ];
        }

        // Cache 15 min para datos intradía, 2h para datos diarios
        $ttl = ($interval === '1d') ? 7200 : 900;
        set_transient($cache_key, $rows, $ttl);

        return $rows;
    }

    // ─── Búsqueda de símbolos (autocompletar) ─────────────────────────────────
    public static function search(string $query): array {
        $url = add_query_arg([
            'q'           => $query,
            'quotesCount' => 10,
            'newsCount'   => 0,
            'listsCount'  => 0,
        ], 'https://query1.finance.yahoo.com/v1/finance/search');

        $resp = wp_remote_get($url, [
            'timeout' => 8,
            'headers' => ['User-Agent' => 'Mozilla/5.0'],
        ]);
        if (is_wp_error($resp)) return [];

        $body  = json_decode(wp_remote_retrieve_body($resp), true);
        $quotes = $body['quotes'] ?? [];

        $results = [];
        foreach ($quotes as $q) {
            if (empty($q['symbol'])) continue;
            $results[] = [
                'symbol'   => $q['symbol'],
                'name'     => $q['longname'] ?? $q['shortname'] ?? $q['symbol'],
                'exchange' => $q['exchDisp'] ?? $q['exchange'] ?? '',
                'type'     => $q['quoteType'] ?? 'EQUITY',
            ];
        }
        return $results;
    }
}
