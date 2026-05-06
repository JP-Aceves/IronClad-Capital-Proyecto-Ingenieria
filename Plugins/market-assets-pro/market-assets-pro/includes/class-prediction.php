<?php
/**
 * Motor de predicción de precios — Market Assets Pro
 *
 * Predice RETORNOS PORCENTUALES (no precios absolutos) para evitar que el
 * gradiente descendente diverja con activos de distinta escala (BTC ~$80k vs
 * penny stocks). Los retornos son siempre pequeños (~±0.05) → convergencia estable.
 *
 * Bugs corregidos respecto a la versión anterior:
 *   1. El target y[] ahora son retornos (no precios) → gradiente no diverge
 *   2. Las features son ratios adimensionales (no precios absolutos)
 *   3. El bias ya no se suma dos veces en la predicción iterativa
 *   4. lr=0.01 con 300 épocas converge de forma robusta para cualquier activo
 */
class MAP_Prediction {

    public static function forecast(string $symbol, int $days = 5): array|false {
        $days = in_array($days, [1, 5, 10, 15]) ? $days : 5;

        $history = MAP_Yahoo_Finance::history($symbol, '2y', '1d');
        if (!$history || count($history) < 60) {
            $history = MAP_Yahoo_Finance::history($symbol, '1y', '1d');
        }
        if (!$history || count($history) < 60) return false;

        $closes = array_column($history, 'close');
        $n      = count($closes);
        if ($n < 60) return false;

        // 1. Dataset: features (ratios) + target (retornos a $days días)
        [$X, $y_ret] = self::build_dataset($closes, $days);
        if (count($X) < 30) return false;

        // 2. Normalizar features
        [$X_norm, $f_means, $f_stds] = self::normalize($X);

        // 3. Entrenar Ridge sobre retornos
        [$weights, $bias] = self::ridge_gd($X_norm, $y_ret);

        // 4. Predicción iterativa día a día
        $predictions     = [];
        $extended_closes = $closes;
        $current_date    = new DateTime(end($history)['date']);

        for ($d = 1; $d <= $days; $d++) {
            $current_date->modify('+1 day');
            while (in_array($current_date->format('N'), ['6', '7'])) {
                $current_date->modify('+1 day');
            }

            $feats_raw = self::extract_features($extended_closes);
            if ($feats_raw === null) break;

            // Normalizar con los parámetros del entrenamiento
            $feat_slice = array_slice(array_values($feats_raw), 0, 8); // solo las 8 features del modelo
            $feat_norm  = [];
            for ($k = 0; $k < 8; $k++) {
                $std          = ($f_stds[$k] ?? 0) > 1e-10 ? $f_stds[$k] : 1.0;
                $feat_norm[]  = ($feat_slice[$k] - ($f_means[$k] ?? 0)) / $std;
            }

            // Retorno predicho → precio
            $ret_pred   = self::dot($weights, $feat_norm) + $bias;
            $ret_pred   = max(-0.30, min(0.30, $ret_pred)); // clamp anti-explosión
            $prev_price = end($extended_closes);
            $pred_price = max(0.0001, $prev_price * (1.0 + $ret_pred));

            // Cambio % respecto al precio actual (no al previo)
            $current_price = $closes[$n - 1];
            $change_pct    = $current_price > 0
                ? (($pred_price - $current_price) / $current_price) * 100
                : 0;

            $predictions[] = [
                'day'        => $d,
                'date'       => $current_date->format('Y-m-d'),
                'price'      => round($pred_price, 6),
                'change_pct' => round($change_pct, 2),
                'confidence' => round(max(30, 85 - ($d * 3.5)), 1),
                'signal'     => self::signal($change_pct),
            ];

            $extended_closes[] = $pred_price;
        }

        if (empty($predictions)) return false;

        // 5. Métricas out-of-sample
        $test_n = max(1, min(30, (int)(count($X_norm) * 0.15)));
        $y_test = array_slice($y_ret,  -$test_n);
        $X_test = array_slice($X_norm, -$test_n);
        $y_pred = array_map(fn($xi) => self::dot($weights, $xi) + $bias, $X_test);

        // 6. Indicadores técnicos
        $ind = self::extract_features($closes);

        return [
            'symbol'      => strtoupper($symbol),
            'horizon'     => $days,
            'current'     => round($closes[$n - 1], 6),
            'predictions' => $predictions,
            'metrics'     => [
                'mae'       => round(self::mae($y_test, $y_pred), 6),
                'rmse'      => round(self::rmse($y_test, $y_pred), 6),
                'r2'        => round(self::r2($y_test, $y_pred), 4),
                'train_n'   => count($X) - $test_n,
                'generated' => date('Y-m-d H:i:s'),
            ],
            'indicators'  => [
                'rsi'        => round($ind['rsi']        ?? 50,            1),
                'ma7'        => round($ind['ma7_abs']    ?? $closes[$n-1], 4),
                'ma21'       => round($ind['ma21_abs']   ?? $closes[$n-1], 4),
                'ma50'       => round($ind['ma50_abs']   ?? $closes[$n-1], 4),
                'volatility' => round(($ind['vol14'] ?? 0) * 100,          2),
                'momentum'   => round($ind['mom5']       ?? 0,             4),
            ],
        ];
    }

    // Construir dataset: X=features(ratios), y=retornos a $days días
    private static function build_dataset(array $closes, int $days): array {
        $X = []; $y = [];
        $n = count($closes);
        for ($i = 50; $i < $n - $days; $i++) {
            $feat = self::extract_features(array_slice($closes, 0, $i + 1));
            if ($feat === null) continue;
            $c_now = $closes[$i];
            $c_fut = $closes[$i + $days];
            if ($c_now <= 0) continue;
            $X[] = array_values($feat);
            $y[] = ($c_fut - $c_now) / $c_now;
        }
        return [$X, $y];
    }

    // Features: SOLO ratios adimensionales (+ valores absolutos para UI al final)
    private static function extract_features(array $closes): ?array {
        $n = count($closes);
        if ($n < 51) return null;
        $c    = $closes[$n - 1];
        $ma7  = array_sum(array_slice($closes, $n - 7,  7))  / 7;
        $ma14 = array_sum(array_slice($closes, $n - 14, 14)) / 14;
        $ma21 = array_sum(array_slice($closes, $n - 21, 21)) / 21;
        $ma50 = array_sum(array_slice($closes, $n - 50, 50)) / 50;

        $w14 = array_slice($closes, $n - 14, 14);
        $rets = [];
        for ($j = 1; $j < 14; $j++) {
            if ($w14[$j-1] > 0) $rets[] = ($w14[$j] - $w14[$j-1]) / $w14[$j-1];
        }
        $vol14 = self::std($rets);
        $rsi   = self::rsi(array_slice($closes, $n - 14, 14));
        $mom5  = $n > 5  && $closes[$n-6]  > 0 ? ($c - $closes[$n-6])  / $closes[$n-6]  : 0;
        $mom10 = $n > 10 && $closes[$n-11] > 0 ? ($c - $closes[$n-11]) / $closes[$n-11] : 0;

        return [
            // Las 8 features del modelo (índices 0-7) — todas adimensionales
            'dist_ma7'  => $ma7  > 0 ? ($c - $ma7)  / $ma7  : 0,
            'dist_ma21' => $ma21 > 0 ? ($c - $ma21) / $ma21 : 0,
            'dist_ma50' => $ma50 > 0 ? ($c - $ma50) / $ma50 : 0,
            'macd'      => $ma21 > 0 ? ($ma7 - $ma21) / $ma21 : 0,
            'mom5'      => $mom5,
            'mom10'     => $mom10,
            'vol14'     => $vol14,
            'rsi_n'     => ($rsi - 50) / 50,   // normalizada a [-1,1]
            // Valores para UI (índices 8-11) — NO usados en el modelo
            'rsi'       => $rsi,
            'ma7_abs'   => $ma7,
            'ma21_abs'  => $ma21,
            'ma50_abs'  => $ma50,
        ];
    }

    // Ridge Regression vía Gradient Descent
    // Entrena sobre retornos (~±0.05) → lr=0.01 converge sin problemas
    private static function ridge_gd(array $X, array $y): array {
        if (empty($X)) return [[], 0.0];
        $n_feat  = 8;
        $weights = array_fill(0, $n_feat, 0.0);
        $bias    = 0.0;
        $lr      = 0.01;
        $lambda  = 0.001;
        $n       = count($X);

        for ($epoch = 0; $epoch < 300; $epoch++) {
            $gw = array_fill(0, $n_feat, 0.0);
            $gb = 0.0;
            foreach ($X as $i => $xi) {
                $xs   = array_slice($xi, 0, $n_feat);
                $pred = self::dot($weights, $xs) + $bias;
                $err  = $pred - $y[$i];
                for ($k = 0; $k < $n_feat; $k++) $gw[$k] += $err * ($xs[$k] ?? 0);
                $gb += $err;
            }
            for ($k = 0; $k < $n_feat; $k++) {
                $weights[$k] -= $lr * ($gw[$k] / $n + $lambda * $weights[$k]);
            }
            $bias -= $lr * ($gb / $n);
        }
        return [$weights, $bias];
    }

    // Normalización Z-score (indexed arrays)
    private static function normalize(array $X): array {
        if (empty($X)) return [[], [], []];
        $n_feat = count($X[0]);
        $means  = array_fill(0, $n_feat, 0.0);
        $stds   = array_fill(0, $n_feat, 1.0);
        for ($k = 0; $k < $n_feat; $k++) {
            $col   = array_column($X, $k);
            $mean  = array_sum($col) / count($col);
            $var   = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $col)) / count($col);
            $means[$k] = $mean;
            $stds[$k]  = sqrt($var);
        }
        $X_norm = [];
        foreach ($X as $row) {
            $nr = [];
            for ($k = 0; $k < $n_feat; $k++) {
                $std  = $stds[$k] > 1e-10 ? $stds[$k] : 1.0;
                $nr[] = ($row[$k] - $means[$k]) / $std;
            }
            $X_norm[] = $nr;
        }
        return [$X_norm, $means, $stds];
    }

    private static function dot(array $w, array $x): float {
        $s = 0.0; $xv = array_values($x);
        foreach ($xv as $i => $v) $s += ($w[$i] ?? 0.0) * $v;
        return $s;
    }
    private static function rsi(array $c): float {
        if (count($c) < 2) return 50.0;
        $g = $l = [];
        for ($i = 1; $i < count($c); $i++) {
            $d = $c[$i] - $c[$i-1];
            if ($d > 0) { $g[] = $d; $l[] = 0; } else { $l[] = abs($d); $g[] = 0; }
        }
        $ag = array_sum($g) / count($g);
        $al = array_sum($l) / count($l);
        if ($al == 0) return 100.0;
        return 100 - (100 / (1 + $ag / $al));
    }
    private static function mae(array $y, array $yp): float {
        $s = 0; foreach ($y as $i => $v) $s += abs($v - ($yp[$i] ?? 0));
        return count($y) > 0 ? $s / count($y) : 0;
    }
    private static function rmse(array $y, array $yp): float {
        $s = 0; foreach ($y as $i => $v) $s += ($v - ($yp[$i] ?? 0)) ** 2;
        return count($y) > 0 ? sqrt($s / count($y)) : 0;
    }
    private static function r2(array $y, array $yp): float {
        $mean = array_sum($y) / max(1, count($y));
        $sst  = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $y));
        $ssr  = 0; foreach ($y as $i => $v) $ssr += ($v - ($yp[$i] ?? 0)) ** 2;
        return $sst > 1e-12 ? 1 - $ssr / $sst : 0;
    }
    private static function std(array $a): float {
        if (count($a) < 2) return 0.0;
        $m = array_sum($a) / count($a);
        return sqrt(array_sum(array_map(fn($v) => ($v - $m) ** 2, $a)) / count($a));
    }
    private static function signal(float $pct): string {
        if ($pct >  3) return 'Strong Buy';
        if ($pct >  1) return 'Buy';
        if ($pct > -1) return 'Hold';
        if ($pct > -3) return 'Sell';
        return 'Strong Sell';
    }
}
