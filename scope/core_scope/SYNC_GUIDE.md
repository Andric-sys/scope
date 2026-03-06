# 📋 GUÍA DE SINCRONIZACIÓN - CORE SCOPE

## 🎯 Objetivo
Sincronizar **TODOS** los datos desde **Octubre 2025** hasta **Hoy (5 de Marzo 2026)** desde la API de Scope.

## 📊 Estado Actual de BD

```
Órdenes en BD: 323+
Rango: Oct 2025 - Mar 2026
Financieros: billed, closed, open
```

---

## 🚀 OPCIÓN 1: Panel Web (RECOMENDADO)

### Pasos:
1. **Abre el panel de sincronización:**
   ```
   http://localhost/scope/scope/core_scope/scope_sync_panel.php
   ```

2. **Configura los parámetros:**
   - **Mode:** `backfill` (trae TODO desde octubre)
   - **Max Pages:** `100` (5,000 órdenes por intento)
   - **Page Size:** `100` (items por página)
   - **Runtime:** `900` segundos (15 minutos máximo)
   - **Throttle:** `100` ms (modera velocidad)

3. **Haz clic en "Iniciar sincronización"**

4. **Observa el progreso en tiempo real** - El panel muestra:
   - Página actual / Total
   - Órdenes descargadas
   - Última hora de modificación capturada
   - Tiempo estimado restante

5. **Si se detiene por timeout:**
   - Los datos descargados se guardaron automáticamente
   - Reinicia y continuará donde se quedó (modo resumible)

---

## 🔧 OPCIÓN 2: Línea de Comandos (DEBUG)

### Verificar estado actual:
```bash
C:\xampp\php\php.exe show_all_breakdown.php
```

**Output esperado:**
```
Financial Status | # Orders | Income Amount    | Total Amount
billed           | 5        | 316,877.93       | 532,131.26
closed           | 160      | 8,649,222.38     | 15,715,156.19
open             | 17       | 92,116.01        | 398,107.03
GRAND TOTAL      | 182      | 9,058,216.32     | 16,645,394.48
```

### Limpiar estado y comenzar backfill:
```bash
C:\xampp\php\php.exe resetHistorial.php
```

---

## 📈 Indicadores de Éxito

✅ **Sincronización completada cuando:**
- Dashboard muestra múltiples órdenes con estados financieros
- `show_all_breakdown.php` muestra más de 300+ órdenes
- Fecha máxima en BD es cercana a 5-Mar-2026

⏳ **Tiempo esperado:**
- Backfill pequeño (500 órdenes): 2-5 minutos
- Backfill mediano (5,000 órdenes): 15-30 minutos
- Backfill completo (15,000+ órdenes): 45-90 minutos

---

## 🔍 Verificación de Datos

Después de sincronizar, ejecuta esto verificar:

```bash
C:\xampp\php\php.exe check_data_range.php
```

Deberías ver:
- Min Date: 2025-10-30 (octubre)
- Max Date: 2026-03-05 (hoy)
- Total Entries: 1,200+

---

## 📊 Dashboard Después

Una vez sincronizado, abre:
```
http://localhost/scope/scope/core_scope/dashboard_pro.php
```

Verifica:
- Sales/Costos/Profit calculados correctamente
- Filtros de `financial_status` funcionan
- Gráficos cargan sin errores

---

## 🎛️ Parámetros Avanzados

Si quieres personalizar la sincronización:

| Parámetro | Default | Rango | Propósito |
|-----------|---------|-------|-----------|
| `mode` | incremental | backfill, incremental | backfill = TODO, incremental = solo nuevos |
| `max_pages` | 5 | 1-500 | Máx páginas a descargar |
| `size` | 100 | 10-200 | Items por página |
| `days` | 3650 | 7-3650 | Mira hacia atrás N días |
| `runtime_sec` | 900 | 120-3600 | Tiempo máximo de ejecución |
| `page_retries` | 4 | 0-8 | Reintentos si falla página |
| `throttle_ms` | 120 | 0-2000 | Espera entre llamadas (ms) |

**Ejemplo de URL con parámetros:**
```
http://localhost/scope/scope/core_scope/scope_sync_panel.php?mode=backfill&max_pages=200&size=100&runtime_sec=1200
```

---

## 🆘 Troubleshooting

### "No hay más datos" pero espero más
- **Causa:** Ya descargó todo disponible en Scope
- **Solución:** Verifica que en Scope existan órdenes de Oct-Mar
- **Test:** `php check_data_range.php`

### Dashboard sigue mostrando números bajos
- **Causa:** Faltan datos del backfill
- **Solución:** Ejecuta otra ronda con `max_pages=200`
- **Verificar:** `php show_all_breakdown.php`

### Sincronización muy lenta
- **Causa:** Much Throttle ms o conexión lenta
- **Solución:** Aumenta `throttle_ms` en panel (200-500ms) 
- **O:** Reduce `page_size` a 50 y aumenta `max_pages`

### Status dice "Sesión requerida"
- **Causa:** Necesita autenticación
- **Solución:** Abre panel en navegador (no CLI), inicia sesión
- **O:** Modifica config.php para agregar manual token

---

## 📋 Estado de Sincronizaciones

Ver historico:
```bash
SELECT * FROM scope_sync_runs ORDER BY started_at DESC LIMIT 10;
```

Ver cursor actual:
```bash
SELECT * FROM scope_sync_state WHERE organization_code='CGL';
```

---

## ✨ Resumen Rápido

1. **Abre:** http://localhost/scope/scope/core_scope/scope_sync_panel.php
2. **Configura:** Mode=backfill, Max Pages=100+, Runtime=900s
3. **Espera:** 15-30 minutos
4. **Verifica:** Dashboard carga todos los datos

---

**Hecho:** 2026-03-05  
**Responsable:** Sistema de Sincronización Core Scope  
**Próximas acciones:** Dashboard + Filtros de financial_status
