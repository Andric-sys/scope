-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 27-02-2026 a las 17:07:44
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `core_global_logistics`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `areas`
--

CREATE TABLE `areas` (
  `id_area` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `estatus` enum('activa','inactiva') NOT NULL DEFAULT 'activa',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `areas`
--

INSERT INTO `areas` (`id_area`, `nombre`, `descripcion`, `estatus`, `creado_en`, `actualizado_en`) VALUES
(1, 'Recursos Humanos', 'Recursos humanos', 'activa', '2026-02-23 13:53:11', '2026-02-23 13:53:11');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `areas_por_rol`
--

CREATE TABLE `areas_por_rol` (
  `id_rol` int(11) NOT NULL,
  `id_area` int(11) NOT NULL,
  `permitido` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cargos`
--

CREATE TABLE `cargos` (
  `id_cargo` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `estatus` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `cargos`
--

INSERT INTO `cargos` (`id_cargo`, `nombre`, `descripcion`, `estatus`, `creado_en`, `actualizado_en`) VALUES
(1, 'Gerente RH', 'Gerente de Recursos humanos', 'activo', '2026-02-23 14:35:56', '2026-02-23 14:35:56');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empleados`
--

CREATE TABLE `empleados` (
  `id_empleado` int(11) NOT NULL,
  `no_empleado` varchar(50) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `id_area` int(11) DEFAULT NULL,
  `id_cargo` int(11) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `correo` varchar(150) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `estatus` enum('activo','baja') NOT NULL DEFAULT 'activo',
  `fecha_nacimiento` date DEFAULT NULL,
  `fecha_ingreso` date DEFAULT NULL,
  `fecha_salida` date DEFAULT NULL,
  `foto_perfil` varchar(255) DEFAULT NULL,
  `oficina` varchar(100) DEFAULT NULL,
  `periodicidad` enum('semanal','quincenal','mensual') NOT NULL DEFAULT 'mensual',
  `edad` int(11) DEFAULT NULL,
  `sexo` enum('masculino','femenino','otro') DEFAULT NULL,
  `nacionalidad` varchar(50) DEFAULT NULL,
  `estado_civil` enum('soltero','casado','divorciado','viudo') DEFAULT NULL,
  `curp` char(18) DEFAULT NULL,
  `rfc` char(13) DEFAULT NULL,
  `nss` char(11) DEFAULT NULL,
  `colonia` varchar(100) DEFAULT NULL,
  `codigo_postal` char(5) DEFAULT NULL,
  `ciudad` varchar(100) DEFAULT NULL,
  `estado` varchar(100) DEFAULT NULL,
  `tipo_licencia` varchar(50) DEFAULT NULL,
  `ultimo_grado_estudios` varchar(255) DEFAULT NULL,
  `fuente_reclutamiento` varchar(255) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `empleados`
--

INSERT INTO `empleados` (`id_empleado`, `no_empleado`, `nombre`, `apellido`, `id_area`, `id_cargo`, `telefono`, `correo`, `direccion`, `estatus`, `fecha_nacimiento`, `fecha_ingreso`, `fecha_salida`, `foto_perfil`, `oficina`, `periodicidad`, `edad`, `sexo`, `nacionalidad`, `estado_civil`, `curp`, `rfc`, `nss`, `colonia`, `codigo_postal`, `ciudad`, `estado`, `tipo_licencia`, `ultimo_grado_estudios`, `fuente_reclutamiento`, `creado_en`, `actualizado_en`) VALUES
(1, '1', 'Andric Alonso', '0', 1, 1, '7711095023', 'andricsur@hotmail.com', 'TULE 411', 'activo', NULL, '2026-02-02', NULL, 'uploads/empleados/emp_20260223_210353_590a735dfc0a.jpg', NULL, 'mensual', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-23 14:03:53', '2026-02-23 14:36:09');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `estatus` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id_rol`, `nombre`, `descripcion`, `estatus`, `creado_en`, `actualizado_en`) VALUES
(1, 'Administrador', 'Accseso total al sistema', 'activo', '2026-02-18 22:59:41', '2026-02-18 22:59:41'),
(2, 'RH', 'Recusrsos Humanos', 'activo', '2026-02-23 13:50:53', '2026-02-23 13:50:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sesiones_usuarios`
--

CREATE TABLE `sesiones_usuarios` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_sesion` varchar(128) NOT NULL,
  `huella_dispositivo` char(64) NOT NULL,
  `agente_usuario` varchar(255) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `visto_en` datetime NOT NULL DEFAULT current_timestamp(),
  `revocado_en` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `sesiones_usuarios`
--

INSERT INTO `sesiones_usuarios` (`id`, `id_usuario`, `id_sesion`, `huella_dispositivo`, `agente_usuario`, `ip`, `creado_en`, `visto_en`, `revocado_en`) VALUES
(1, 8, 'g3m464s2ro12569jfcgmgjt0h6', '22a9592fc0094ee70cdc96952c0c264c729716252d53fc631951c939a5c0e30d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-02-18 23:02:19', '2026-02-18 23:02:58', '2026-02-23 13:41:58'),
(2, 8, '4oquu8otvipi69m6kc372c5uhj', '22a9592fc0094ee70cdc96952c0c264c729716252d53fc631951c939a5c0e30d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-02-23 13:41:58', '2026-02-23 14:04:35', '2026-02-23 14:04:35'),
(3, 9, 'vactif1eotkeahukrn764ka451', '22a9592fc0094ee70cdc96952c0c264c729716252d53fc631951c939a5c0e30d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-02-23 14:05:12', '2026-02-23 14:08:57', '2026-02-23 14:08:57'),
(4, 8, 'odud030irk3u75o6ilibqct9h2', '22a9592fc0094ee70cdc96952c0c264c729716252d53fc631951c939a5c0e30d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-02-23 14:34:53', '2026-02-23 14:35:20', '2026-02-23 14:35:20'),
(5, 9, 'nri76id1aglcqn847ih6u62tnu', '22a9592fc0094ee70cdc96952c0c264c729716252d53fc631951c939a5c0e30d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-02-23 14:35:25', '2026-02-23 14:36:15', '2026-02-23 21:49:46'),
(6, 8, 'kol16vs85j5ko3v58uaekcsi15', '22a9592fc0094ee70cdc96952c0c264c729716252d53fc631951c939a5c0e30d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-02-23 21:48:32', '2026-02-23 21:48:43', '2026-02-23 21:48:45'),
(7, 8, 'cqrqimdra5003mhrp4b8vq34s1', '22a9592fc0094ee70cdc96952c0c264c729716252d53fc631951c939a5c0e30d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-02-23 21:48:45', '2026-02-23 21:49:42', '2026-02-23 21:49:42'),
(8, 9, '33rcrcbijdn78kudpagejsa527', '22a9592fc0094ee70cdc96952c0c264c729716252d53fc631951c939a5c0e30d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-02-23 21:49:46', '2026-02-23 21:52:35', '2026-02-23 21:52:35'),
(9, 8, 'ad2buda9peamnnh4h0tqbg3ok9', '22a9592fc0094ee70cdc96952c0c264c729716252d53fc631951c939a5c0e30d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-02-23 21:52:39', '2026-02-23 22:09:45', '2026-02-25 18:53:15'),
(10, 8, 'u336mcimk4f4p0bkj1i1segbf2', '22a9592fc0094ee70cdc96952c0c264c729716252d53fc631951c939a5c0e30d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-02-25 18:53:15', '2026-02-25 18:58:00', '2026-02-25 21:31:29'),
(11, 8, 'qh25r1ajtgv6662p50qu359m1f', '22a9592fc0094ee70cdc96952c0c264c729716252d53fc631951c939a5c0e30d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-02-25 21:31:29', '2026-02-25 21:59:46', '2026-02-25 21:59:46'),
(12, 8, 'pnj1ic7igup921vq5ffekn0gmq', '22a9592fc0094ee70cdc96952c0c264c729716252d53fc631951c939a5c0e30d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-02-25 22:01:09', '2026-02-25 22:16:01', '2026-02-25 22:16:01'),
(13, 8, '2s4g3jrrc70tdglvnv3tbv09tg', '22a9592fc0094ee70cdc96952c0c264c729716252d53fc631951c939a5c0e30d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-02-25 22:21:21', '2026-02-25 22:21:26', '2026-02-25 22:21:26'),
(14, 8, 'ol5f978rqmoi7k8ummhdrluj8h', '22a9592fc0094ee70cdc96952c0c264c729716252d53fc631951c939a5c0e30d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-02-25 22:44:54', '2026-02-25 22:44:55', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `apellido` varchar(150) NOT NULL,
  `correo` varchar(150) NOT NULL,
  `num_telefono` varchar(45) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `id_empleado` int(11) DEFAULT NULL,
  `id_area` int(11) DEFAULT NULL,
  `foto_perfil` varchar(255) DEFAULT NULL,
  `estatus` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombre`, `apellido`, `correo`, `num_telefono`, `password`, `id_rol`, `id_empleado`, `id_area`, `foto_perfil`, `estatus`, `creado_en`, `actualizado_en`) VALUES
(8, 'Admin', 'Sistema', 'admin@coreglobal.com', '0000000000', 'Admin123*', 1, NULL, NULL, NULL, 'activo', '2026-02-18 23:00:15', '2026-02-18 23:00:15'),
(9, 'Andric', 'Alamilla', 'andricsur@hotmail.com', '7711095023', 'admin123456', 2, NULL, NULL, 'uploads/usuarios/usr_20260223_205243_059fe2a75cd8.png', 'activo', '2026-02-23 13:52:43', '2026-02-23 13:52:43'),
(10, 'Mei', 'Andrade', 'iansebas@hotmail.com', '7712424073', 'epazote3000', 2, NULL, 1, 'uploads/usuarios/usr_20260224_045405_d877acbb9fc8.jpg', 'activo', '2026-02-23 21:54:05', '2026-02-23 21:54:05');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios_vistas`
--

CREATE TABLE `usuarios_vistas` (
  `id_rol` int(11) NOT NULL,
  `id_vista` int(11) NOT NULL,
  `permitido` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios_vistas`
--

INSERT INTO `usuarios_vistas` (`id_rol`, `id_vista`, `permitido`, `creado_en`) VALUES
(1, 1, 1, '2026-02-23 13:50:16'),
(1, 2, 1, '2026-02-23 13:50:16'),
(1, 3, 1, '2026-02-23 13:50:16'),
(1, 4, 1, '2026-02-23 13:50:16'),
(1, 5, 1, '2026-02-23 13:50:16'),
(1, 6, 1, '2026-02-23 13:50:16'),
(1, 7, 1, '2026-02-23 13:50:16'),
(1, 8, 1, '2026-02-23 13:50:16'),
(1, 9, 1, '2026-02-23 13:50:16'),
(1, 10, 1, '2026-02-23 13:50:16'),
(2, 1, 1, '2026-02-23 13:51:21'),
(2, 2, 0, '2026-02-23 13:51:21'),
(2, 3, 0, '2026-02-23 13:51:21'),
(2, 4, 0, '2026-02-23 13:51:21'),
(2, 5, 0, '2026-02-23 13:51:21'),
(2, 6, 0, '2026-02-23 13:51:21'),
(2, 7, 1, '2026-02-23 13:51:21'),
(2, 8, 1, '2026-02-23 13:51:21'),
(2, 9, 1, '2026-02-23 13:51:21'),
(2, 10, 0, '2026-02-23 13:51:21');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `vistas`
--

CREATE TABLE `vistas` (
  `id_vista` int(11) NOT NULL,
  `path` varchar(200) NOT NULL,
  `clave` varchar(80) NOT NULL,
  `titulo` varchar(120) NOT NULL,
  `estatus` enum('activa','inactiva') NOT NULL DEFAULT 'activa',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `vistas`
--

INSERT INTO `vistas` (`id_vista`, `path`, `clave`, `titulo`, `estatus`, `creado_en`, `actualizado_en`) VALUES
(1, 'dashboard.php', 'DASHBOARD', 'Dashboard', 'activa', '2026-02-23 13:49:01', '2026-02-23 13:49:01'),
(2, 'usuarios.php', 'USUARIOS', 'Gestión de Usuarios', 'activa', '2026-02-23 13:49:01', '2026-02-23 13:49:01'),
(3, 'roles.php', 'ROLES', 'Gestión de Roles', 'activa', '2026-02-23 13:49:01', '2026-02-23 13:49:01'),
(4, 'permisos_por_rol.php', 'PERMISOS_VISTAS', 'Permisos de Vistas', 'activa', '2026-02-23 13:49:01', '2026-02-23 13:49:01'),
(5, 'areas_por_rol.php', 'PERMISOS_AREAS', 'Permisos de Áreas', 'activa', '2026-02-23 13:49:01', '2026-02-23 13:49:01'),
(6, 'vistas.php', 'VISTAS_ADMIN', 'Administración de Vistas', 'activa', '2026-02-23 13:49:01', '2026-02-23 13:49:01'),
(7, 'areas.php', 'AREAS', 'Gestión de Áreas', 'activa', '2026-02-23 13:49:01', '2026-02-23 13:49:01'),
(8, 'cargos.php', 'CARGOS', 'Gestión de Cargos', 'activa', '2026-02-23 13:49:01', '2026-02-23 13:49:01'),
(9, 'empleados.php', 'EMPLEADOS', 'Gestión de Empleados', 'activa', '2026-02-23 13:49:01', '2026-02-23 13:49:01'),
(10, 'sesiones.php', 'SESIONES', 'Sesiones Activas', 'activa', '2026-02-23 13:49:01', '2026-02-23 13:49:01');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `areas`
--
ALTER TABLE `areas`
  ADD PRIMARY KEY (`id_area`),
  ADD UNIQUE KEY `uq_areas_nombre` (`nombre`);

--
-- Indices de la tabla `areas_por_rol`
--
ALTER TABLE `areas_por_rol`
  ADD PRIMARY KEY (`id_rol`,`id_area`),
  ADD KEY `fk_ar_area` (`id_area`);

--
-- Indices de la tabla `cargos`
--
ALTER TABLE `cargos`
  ADD PRIMARY KEY (`id_cargo`),
  ADD UNIQUE KEY `uq_cargos_nombre` (`nombre`);

--
-- Indices de la tabla `empleados`
--
ALTER TABLE `empleados`
  ADD PRIMARY KEY (`id_empleado`),
  ADD UNIQUE KEY `uq_empleados_no_empleado` (`no_empleado`),
  ADD UNIQUE KEY `uq_empleados_curp` (`curp`),
  ADD UNIQUE KEY `uq_empleados_rfc` (`rfc`),
  ADD UNIQUE KEY `uq_empleados_nss` (`nss`),
  ADD KEY `idx_empleados_area` (`id_area`),
  ADD KEY `idx_empleados_cargo` (`id_cargo`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `uq_roles_nombre` (`nombre`);

--
-- Indices de la tabla `sesiones_usuarios`
--
ALTER TABLE `sesiones_usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sesiones_id_sesion` (`id_sesion`),
  ADD KEY `idx_sesiones_usuario` (`id_usuario`),
  ADD KEY `idx_sesiones_visto` (`visto_en`),
  ADD KEY `idx_sesiones_revocado` (`revocado_en`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `uq_usuarios_correo` (`correo`),
  ADD KEY `idx_usuarios_rol` (`id_rol`),
  ADD KEY `idx_usuarios_empleado` (`id_empleado`),
  ADD KEY `idx_usuarios_area` (`id_area`);

--
-- Indices de la tabla `usuarios_vistas`
--
ALTER TABLE `usuarios_vistas`
  ADD PRIMARY KEY (`id_rol`,`id_vista`),
  ADD KEY `fk_uv_vista` (`id_vista`);

--
-- Indices de la tabla `vistas`
--
ALTER TABLE `vistas`
  ADD PRIMARY KEY (`id_vista`),
  ADD UNIQUE KEY `uq_vistas_path` (`path`),
  ADD UNIQUE KEY `uq_vistas_clave` (`clave`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `areas`
--
ALTER TABLE `areas`
  MODIFY `id_area` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `cargos`
--
ALTER TABLE `cargos`
  MODIFY `id_cargo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `empleados`
--
ALTER TABLE `empleados`
  MODIFY `id_empleado` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `sesiones_usuarios`
--
ALTER TABLE `sesiones_usuarios`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `vistas`
--
ALTER TABLE `vistas`
  MODIFY `id_vista` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `areas_por_rol`
--
ALTER TABLE `areas_por_rol`
  ADD CONSTRAINT `fk_ar_area` FOREIGN KEY (`id_area`) REFERENCES `areas` (`id_area`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ar_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `empleados`
--
ALTER TABLE `empleados`
  ADD CONSTRAINT `fk_empleados_area` FOREIGN KEY (`id_area`) REFERENCES `areas` (`id_area`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_empleados_cargo` FOREIGN KEY (`id_cargo`) REFERENCES `cargos` (`id_cargo`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `sesiones_usuarios`
--
ALTER TABLE `sesiones_usuarios`
  ADD CONSTRAINT `fk_sesiones_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_area` FOREIGN KEY (`id_area`) REFERENCES `areas` (`id_area`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_usuarios_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleados` (`id_empleado`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_usuarios_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuarios_vistas`
--
ALTER TABLE `usuarios_vistas`
  ADD CONSTRAINT `fk_uv_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_uv_vista` FOREIGN KEY (`id_vista`) REFERENCES `vistas` (`id_vista`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
