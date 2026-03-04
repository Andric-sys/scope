-- ======================================
-- CHECKLIST DE VALIDACIÓN DE TOTALES
-- ======================================
-- Ajusta fechas según el rango que estés auditando
SET @from_date = '2025-03-01';
SET @to_date   = '2026-02-28';

-- 1) KPI del dashboard (misma regla de negocio)
SELECT
  COALESCE(SUM(CASE WHEN entry_type='income'  AND (charge_type_code IS NULL OR charge_type_code NOT LIKE 'PT%') THEN local_amount_value ELSE 0 END),0) AS kpi_sales,
  COALESCE(SUM(CASE WHEN entry_type='payable' THEN local_amount_value ELSE 0 END),0) AS kpi_costs,
  COALESCE(SUM(CASE WHEN entry_type='income'  AND charge_type_code LIKE 'PT%' THEN local_amount_value ELSE 0 END),0) AS kpi_ter
FROM scope_jobcosting_entries
WHERE invoice_date IS NOT NULL
  AND DATE(invoice_date) BETWEEN @from_date AND @to_date;

-- 2) Ingreso bruto income (incluye PT)
SELECT
  COALESCE(SUM(CASE WHEN entry_type='income' THEN local_amount_value ELSE 0 END),0) AS income_total_raw
FROM scope_jobcosting_entries
WHERE invoice_date IS NOT NULL
  AND DATE(invoice_date) BETWEEN @from_date AND @to_date;

-- 3) Diferencia por exclusión PT y tipos fuera de income/payable
SELECT
  COALESCE(SUM(CASE WHEN entry_type='income' AND charge_type_code LIKE 'PT%' THEN local_amount_value ELSE 0 END),0) AS excluded_pt,
  COALESCE(SUM(CASE WHEN entry_type NOT IN ('income','payable') THEN local_amount_value ELSE 0 END),0) AS excluded_other_entry_types
FROM scope_jobcosting_entries
WHERE invoice_date IS NOT NULL
  AND DATE(invoice_date) BETWEEN @from_date AND @to_date;

-- 4) Catálogo real de entry_type en el rango (para detectar sorpresas)
SELECT
  entry_type,
  COUNT(*) AS rows_count,
  COALESCE(SUM(local_amount_value),0) AS amount_sum,
  COALESCE(SUM(local_tax_value),0) AS tax_sum
FROM scope_jobcosting_entries
WHERE invoice_date IS NOT NULL
  AND DATE(invoice_date) BETWEEN @from_date AND @to_date
GROUP BY entry_type
ORDER BY rows_count DESC;
