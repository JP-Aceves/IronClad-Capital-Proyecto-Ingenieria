# 📈 Live Market Ticker — Plugin de WordPress

Ticker en tiempo real de ETFs, criptomonedas y acciones usando Yahoo Finance.

---

## Instalación

1. **Copiar** la carpeta `live-market-ticker` completa a `/wp-content/plugins/`
2. **Activar** el plugin en WordPress → Plugins
3. Ir a **Ajustes → 📈 Live Ticker** para configurar los símbolos
4. Añadir el shortcode donde quieras el ticker:

```
[live_ticker]
```

---

## Uso del shortcode

### Configuración global (desde el admin)
```
[live_ticker]
```

### Personalizado por instancia
```
[live_ticker symbols="BTC-USD,ETH-USD,SPY,QQQ" refresh="60" speed="normal"]
```

### Parámetros disponibles

| Parámetro      | Valores                    | Default     | Descripción                         |
|----------------|----------------------------|-------------|-------------------------------------|
| `symbols`      | Tickers separados por `,`  | Config admin| Símbolos a mostrar                  |
| `refresh`      | Número en segundos         | `60`        | Frecuencia de actualización (mín 30)|
| `speed`        | `slow` / `normal` / `fast` | `normal`    | Velocidad del scroll                |
| `show_sparkline`| `true` / `false`          | `true`      | Mini gráfico de tendencia           |

---

## Símbolos soportados

### Criptomonedas
Usar siempre el sufijo `-USD`:
```
BTC-USD, ETH-USD, SOL-USD, BNB-USD, XRP-USD, ADA-USD, DOGE-USD, DOT-USD, AVAX-USD, MATIC-USD
```

### ETFs populares
```
SPY   → S&P 500 ETF (iShares)
QQQ   → Nasdaq-100 ETF (Invesco)
DIA   → Dow Jones ETF
IWM   → Russell 2000 ETF
GLD   → Gold ETF
SLV   → Silver ETF
VTI   → Vanguard Total Market
ARKK  → ARK Innovation
```

### Acciones individuales
```
AAPL, MSFT, NVDA, TSLA, AMZN, GOOGL, META, NFLX
```

---

## Solución de problemas

### El ticker no muestra datos
- Verifica que tu servidor tenga acceso a `query1.finance.yahoo.com`
- Comprueba la consola del navegador para errores
- Yahoo Finance puede tener rate limits: aumenta el `refresh` a 120s o más

### El scroll no funciona
- Asegúrate de que no hay conflictos CSS con tu tema
- El plugin usa `animation: lmt-scroll` — revisa si algún plugin la sobreescribe

### Precio desactualizado
- Yahoo Finance tiene un delay de ~15 minutos en datos de mercado
- Los precios de crypto suelen ser más inmediatos

---

## Estructura del plugin

```
live-market-ticker/
├── live-market-ticker.php          ← Archivo principal
├── includes/
│   ├── class-yahoo-finance.php     ← Conexión a Yahoo Finance API
│   ├── class-rest-api.php          ← Endpoint REST /wp-json/live-ticker/v1/quotes
│   ├── class-admin.php             ← Página de configuración en admin
│   └── class-shortcode.php        ← Shortcode [live_ticker]
└── assets/
    ├── ticker.css                  ← Estilos
    └── ticker.js                  ← Lógica frontend
```

---

## Endpoint REST (para desarrolladores)

El plugin expone un endpoint público:

```
GET /wp-json/live-ticker/v1/quotes?symbols=BTC-USD,ETH-USD,SPY
```

Respuesta:
```json
{
  "success": true,
  "timestamp": 1709123456,
  "data": [
    {
      "symbol": "BTC-USD",
      "ticker": "BTC",
      "label": "Bitcoin",
      "color": "orange",
      "price": 64230.12,
      "change": 1537.82,
      "change_pct": 2.45,
      "high": 65000.00,
      "low": 62100.00,
      "volume": 28000000000,
      "sparkline": [45, 52, 48, 61, 70, 65, 80, 90, 85, 100],
      "currency": "USD"
    }
  ]
}
```
