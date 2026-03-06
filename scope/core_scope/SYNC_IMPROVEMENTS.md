# 🔄 MEJORAS DE SINCRONIZACIÓN - IMPLEMENTED

## 📌 Resumen Ejecutivo

Se ha mejorado el sistema de sincronización para optimizar la obtención de datos desde October 2025 hasta la fecha actual (March 5, 2026).

---

## ✅ Cambios Implementados

### 1. **Reset de Estado** (`resetHistorial.php`)
- ✓ Limpia cursor de sincronización
- ✓ Prepara BD para backfill completo
- ✓ Muestra historial de sincronizaciones previas
- ✓ Ejecutar: `C:\xampp\php\php.exe resetHistorial.php`

### 2. **Herramientas de Diagnóstico**

#### `check_data_range.php`
Verifica rango de datos disponible:
```bash
min_date: 2025-10-30
max_date: 2026-03-05
total_entries: 1,200+
```

#### `show_all_breakdown.php`
Desglose completo por `financial_status`:
```
Financial Status | # Orders | Income Amount | Total Amount
billed           | 5        | 316,877.93    | 532,131.26
closed           | 160      | 8,649,222.38  | 15,715,156.19
open             | 17       | 92,116.01     | 398,107.03
GRAND TOTAL      | 182      | 9,058,216.32  | 16,645,394.48
```

#### `debug_financial_status.php`
API REST para obtener breakdown via HTTP:
```bash
GET /scope_sync_status.php?from=2025-01-01&to=2025-01-31
```
Response: JSON con desglose por status

### 3. **Mejoras en Sincronización**

#### `sync_guide.php`
Interfaz informativa que muestra:
- Órdenes actuales en BD
- Historial de sincronizaciones
- Instrucciones para síncronización manual
- Parámetros de configuración recomendados

#### `run_backfill_sync.php`
Script que ejecuta sincronización automática vía HTTP requests

#### `direct_backfill_sync.php`
Sincronización directa sin necesidad de autenticación HTTP (para CLI)

### 4. **Documentación** (`SYNC_GUIDE.md`)
Guía completa con:
- Instrucciones paso a paso
- Parámetros avanzados
- Troubleshooting
- Indicadores de éxito

---

## 🎯 Flujo Recomendado

### Opción A: Panel Web (PREFERIDA)
```
1. Ejecutar: resetHistorial.php
2. Abrir: http://localhost/scope/scope/core_scope/scope_sync_panel.php
3. Configurar: Mode=backfill, Max Pages=100, Runtime=900
4. Iniciar y esp erar ~30 minutos
5. Verificar: http://localhost/scope/scope/core_scope/dashboard_pro.php
```

### Opción B: Línea de Comandos
```bash
# Limpiar y preparar
C:\xampp\php\php.exe resetHistorial.php

# Verificar datos
C:\xampp\php\php.exe show_all_breakdown.php

# (Luego abrir panel web para sincronizar)
```

---

## 📊 Verificación Post-Sincronización

```bash
# Ver todos los datos desglosados
C:\xampp\php\php.exe show_all_breakdown.php

# Verificar rango de fechas
C:\xampp\php\php.exe check_data_range.php

# Revisar historial de sincronizaciones
MySQL> SELECT * FROM scope_sync_runs ORDER BY started_at DESC LIMIT 5;
```

---

## 🔧 Configuración de Sincronización

### Parámetros en scope_sync_panel.php:

| Parámetro | Recomendado | Descripción |
|-----------|-------------|-------------|
| **Mode** | `backfill` | Descarga TODO (no solo cambios recientes) |
| **Max Pages** | `100-200` | ~5,000-10,000 órdenes |
| **Page Size** | `100` | Items por página |
| **Runtime** | `900` | 15 minutos máximo por ronda |
| **Throttle** | `100-200` | Milisegundos entre llamadas |
| **Page Retries** | `4` | Reintentos si falla página |

### Duración Estimada:
- 5,000 órdenes: 10-15 minutos
- 10,000 órdenes: 20-30 minutos
- 15,000+ órdenes: 45-90 minutos

---

## 💾 Estructura de Datos

La sincronización guarda:
- **scope_orders**: Metadata de órdenes (org, fecha, estatus financiero, etc)
- **scope_jobcosting_entries**: Líneas de costo/ingreso por orden
- **scope_sync_runs**: Historial de sincronizaciones
- **scope_sync_state**: Cursor actual para reanudar

### Campos Importantes:
```php
scope_orders.financial_status = 'billed' | 'open' | 'closed'
scope_jobcosting_entries.entry_type = 'income' | 'payable' | ...
scope_jobcosting_entries.amount_value = Monto neto
scope_jobcosting_entries.tax_value = IVA asociado
```

---

## 🎛️ Control de Sincronización

### Ver estado actual:
```sql
SELECT * FROM scope_sync_state WHERE organization_code='CGL';
```

### Limpiar y reiniciar:
```sql
DELETE FROM scope_sync_state WHERE organization_code='CGL';
-- Luego ejecutar: resetHistorial.php
```

### Ver últimas sincronizaciones:
```sql
SELECT run_uuid, started_at, fetched_count, upserted_orders, mensaje, http_status
FROM scope_sync_runs 
WHERE organization_code='CGL'
ORDER BY started_at DESC 
LIMIT 10;
```

---

## 📈 Métricas

Después de sincronización completa, esperas ver:
- ✅ 300-1000+ órdenes en BD
- ✅ Rango: octubre 2025 - marzo 2026
- ✅ Múltiples `financial_status` (billed, open, closed)
- ✅ Dashboard carga con gráficos

---

## 🔐 Notas de Seguridad

- `resetHistorial.php` limpia estado pero **NO borra** data
- Sincronización es **idempotente** (puedes ejecutar múltiples veces)
- Los datos se actualizan vía `ON DUPLICATE KEY UPDATE`
- Mantiene historial en `scope_sync_runs` para auditoría

---

## 📞 Próximos Pasos

1. ✅ Ejecutar: `resetHistorial.php`
2. ✅ Abrir: `scope_sync_panel.php`
3. ✅ Sincronizar con backfill completo
4. ✅ Verificar: `dashboard_pro.php`
5. ✅ Usar filtros de `financial_status` en dashboard
6. ✅ Comparar números con Excel

---

**Versión:** 1.0  
**Fecha:** 2026-03-05  
**Estado:** ✅ Listo para usar
