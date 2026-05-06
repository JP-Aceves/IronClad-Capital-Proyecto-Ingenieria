# Market Assets Pro

Plugin de WordPress que extiende **Live Market Ticker** con una lista completa de criptomonedas, stocks y ETFs con:

- 📊 Tabla interactiva con precios en tiempo real (Yahoo Finance)
- 🔍 Búsqueda y filtros por tipo de activo
- 📈 Gráficos detallados (línea + velas) con múltiples períodos
- 🤖 Predicciones de precio a 1, 5, 10 y 15 días (modelo XGBoost/Ridge)
- 👤 Panel personal: cada usuario registrado puede añadir sus activos
- ⚙️ Panel de administración para gestionar la lista global

---

## Instalación

1. Sube la carpeta `market-assets-pro/` a `/wp-content/plugins/`
2. Activa el plugin en **Plugins → Plugins instalados**
3. Configura los activos globales en **Market Assets** del menú lateral

---

## Uso

Añade el shortcode donde quieras mostrar la lista:

```
[market_assets_list]
```

### Parámetros opcionales

| Parámetro | Valores | Por defecto | Descripción |
|---|---|---|---|
| `show_search` | true/false | true | Mostrar barra de búsqueda |
| `show_add_btn` | true/false | true | Botón "Añadir activo" (solo usuarios logados) |
| `items_per_page` | número | 10 | Filas por página |
| `default_filter` | all/crypto/etf/stock | all | Filtro activo al cargar |

### Ejemplo

```
[market_assets_list show_search="true" items_per_page="15" default_filter="crypto"]
```

---

## Endpoints REST

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/wp-json/map/v1/assets` | Lista de activos |
| POST | `/wp-json/map/v1/assets` | Añadir activo (requiere login) |
| DELETE | `/wp-json/map/v1/assets/{symbol}` | Eliminar activo |
| GET | `/wp-json/map/v1/quote/{symbol}` | Cotización en tiempo real |
| GET | `/wp-json/map/v1/history/{symbol}?period=1y` | Histórico OHLCV |
| GET | `/wp-json/map/v1/predict/{symbol}?days=5` | Predicción de precio |
| GET | `/wp-json/map/v1/search?q=bitcoin` | Búsqueda de tickers |

---

## Sobre el modelo de predicción

El motor de predicción implementa las mismas características que el notebook XGBoost adjunto:

- Medias móviles (MA7, MA14, MA21, MA50)
- RSI (14 períodos)
- Momentum (5 y 10 días)
- Distancia relativa a las medias
- MACD simplificado
- Volatilidad histórica (14 días)

El modelo entrena con hasta 2 años de datos históricos y calcula predicciones iterativas para cada día del horizonte elegido. Las métricas de calidad (MAE, RMSE, R²) se calculan en un conjunto de test out-of-sample.

> ⚠️ Las predicciones son estimaciones estadísticas y no constituyen asesoramiento financiero.

---

## Requisitos

- WordPress 6.0+
- PHP 8.0+
- Plugin **Live Market Ticker v2** instalado (para compartir la fuente de datos Yahoo Finance, opcional)
- Acceso a internet para llamadas a la API de Yahoo Finance

---

## Compatibilidad

- ✅ Modo claro / oscuro automático (CSS variables)
- ✅ Compatible con el tema IronClad
- ✅ Responsive (móvil, tablet, escritorio)
- ✅ Múltiples instancias del shortcode en la misma página
