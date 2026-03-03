-- Tabla para almacenar metas de ventas mensuales y anuales
CREATE TABLE IF NOT EXISTS `metas_ventas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `anio` int(11) NOT NULL,
  `mes` int(11) NOT NULL COMMENT '0 = anual, 1-12 = mes específico',
  `meta` decimal(12,2) NOT NULL,
  `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `anio_mes` (`anio`, `mes`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Metas de ventas mensuales y anuales';

-- Insertar metas predeterminadas para 2026
INSERT INTO `metas_ventas` (`anio`, `mes`, `meta`) VALUES
(2026, 0, 5000000.00),  -- Meta anual
(2026, 1, 416666.67),    -- Enero
(2026, 2, 416666.67),    -- Febrero
(2026, 3, 416666.67),    -- Marzo
(2026, 4, 416666.67),    -- Abril
(2026, 5, 416666.67),    -- Mayo
(2026, 6, 416666.67),    -- Junio
(2026, 7, 416666.67),    -- Julio
(2026, 8, 416666.67),    -- Agosto
(2026, 9, 416666.67),    -- Septiembre
(2026, 10, 416666.67),   -- Octubre
(2026, 11, 416666.67),   -- Noviembre
(2026, 12, 416666.67)    -- Diciembre
ON DUPLICATE KEY UPDATE `meta` = VALUES(`meta`);
