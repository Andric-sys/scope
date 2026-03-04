# Diagnóstico de Totales (aislado)

Esta carpeta es independiente para revisar diferencias de totales sin modificar archivos de `core_scope`.

## Archivos

- `auditar_dashboard.php`: script CLI que replica y desglosa la lógica de totales del dashboard.
- `query_checklist.sql`: checklist SQL manual para validar cifras.

## Uso rápido

Desde PowerShell en `C:\xampp\htdocs\scope`:

```powershell
& 'C:\xampp\php\php.exe' '.\scope\diagnostico_totales\auditar_dashboard.php'
```

Con rango manual:

```powershell
& 'C:\xampp\php\php.exe' '.\scope\diagnostico_totales\auditar_dashboard.php' --from=2025-03-01 --to=2026-02-28
```

## Qué valida

1. Ventas KPI (`income` sin `PT%`).
2. Costos KPI (`payable`).
3. TER (`income` con `PT%`).
4. Ingreso bruto `income` (incluyendo PT) y diferencia.
5. Montos de `entry_type` fuera de `income/payable`.

Con esto puedes identificar exactamente por qué no coincide con un “total bruto”.
