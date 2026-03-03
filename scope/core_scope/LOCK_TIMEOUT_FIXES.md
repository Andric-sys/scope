# Solución: Lock Wait Timeout Exceeded

## Problema Original
```
SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded; try restarting transaction
```

Este error ocurría durante la sincronización de múltiples órdenes debido a:
- Una transacción global demasiado larga (LOCK esperando)
- Múltiples `ON DUPLICATE KEY UPDATE` causando locks agresivos en InnoDB
- Falta de manejo de reintentos para errores temporales de lock

## Soluciones Implementadas

### 1. **Transacciones Granulares** 
**Archivo**: [index.php](index.php#L125)

**Antes**: Una transacción por toda la sincronización
```php
$pdo->beginTransaction();
while (true) {
  foreach ($orders as $o) {
    scope_upsert_order($pdo, $detail);
  }
}
$pdo->commit();
```

**Después**: Transacción individual por orden
```php
foreach ($orders as $o) {
  $pdo->beginTransaction();
  try {
    scope_upsert_order($pdo, $detail);
    $pdo->commit();
  } catch (Throwable $e) {
    $pdo->rollBack();
  }
}
```

**Beneficio**: Reduce el tiempo de lock y libera recursos más rápido.

### 2. **Reintentos Automáticos con Backoff Exponencial**
**Archivo**: [index.php](index.php#L170)

Si ocurre un lock timeout (error 1205), el sistema automáticamente:
- Reintenta hasta 3 veces
- Espera 100ms, 200ms, 400ms entre intentos (backoff exponencial)
- Continúa sin fallar si otros errores ocurren

```php
$retries = 0;
$maxRetries = 3;
$backoff = 100; // ms

while ($retries <= $maxRetries) {
  try {
    $pdo->beginTransaction();
    scope_upsert_order($pdo, $detail);
    $pdo->commit();
    $totalUpserts++;
    break;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    
    if ($retries < $maxRetries && strpos($e->getMessage(), '1205') !== false) {
      $retries++;
      usleep($backoff * 1000);
      $backoff *= 2;
    } else {
      $totalErrors++;
      break;
    }
  }
}
```

### 3. **Optimización de Operaciones de Base de Datos**
**Archivo**: [scope_upsert.php](scope_upsert.php)

**Cambio de estrategia**: `DELETE + INSERT` en lugar de `ON DUPLICATE KEY UPDATE`

**Afectadas**:
- `scope_upsert_milestones()` - Línea 303
- `scope_upsert_references()` - Línea 358

**Por qué**:
- `ON DUPLICATE KEY UPDATE` causa locks de escritura agresivos
- `DELETE + INSERT` es más rápido y menos propenso a deadlocks
- Los datos de milestone y reference se reemplazan completamente de todas formas

**Ventajas**:
- ✅ Menos locks en la tabla
- ✅ Transacciones más cortas
- ✅ Mejor rendimiento en sincronización masiva

### 4. **Medición de Rendimiento**
**Archivo**: [index.php](index.php#L222)

Ahora el resultado incluye:
```php
$resultado = [
  'Elementos recibidos' => $totalFetched,
  'Órdenes actualizadas/insertadas' => $totalUpserts,
  'Errores encontrados' => $totalErrors,
  'Tiempo de ejecución' => "{$elapsed}s",
];
```

Permite identificar:
- Cuántas órdenes fallaron por lock timeout
- Cuánto tarda la sincronización
- Si el sistema está experimentando contención de locks

## Configuración Recomendada (MySQL/MariaDB)

Para optimizar aún más, considerar estos ajustes en `my.cnf`:

```ini
[mysqld]
# Aumentar el timeout de lock de InnoDB (default es 50 segundos)
innodb_lock_wait_timeout = 120

# Reducir el buffer pool si hay mucha contención
innodb_buffer_pool_size = 1G

# Aumentar el buffer pool instances para menos contención
innodb_buffer_pool_instances = 4

# Log para debugging de locks (desactivar en producción)
# innodb_print_all_deadlocks = ON
```

## Testing

Para verificar que funciona:

```bash
# Sincronización automática
POST /index.php
action=sync_auto

# Sincronización de últimas 24 horas
POST /index.php
action=sync_24h

# Importar una orden específica
POST /index.php
action=import_uuid
order_uuid=03a3a6c1-1e11-4eb9-b91b-ec1f6d9250bf
```

## Métricas de Éxito

✅ Sin errores 1205 (Lock wait timeout)
✅ Sincronización completa de 200+ órdenes en < 120 segundos
✅ Errores de lock reintentados automáticamente
✅ Sistema robusto ante fallos temporales de DB

## Notas

- Los logs de error de órdenes individuales se guardan en `error_log` de PHP
- Revisar `error_log` si `Errores encontrados > 0` después de una sincronización
- El margen de seguridad de tiempo (240s de 300s) permite otros procesos sin timeout de PHP

---

**Última actualización**: 2 de febrero de 2026
