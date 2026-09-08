-- MySQL dump 10.13  Distrib 8.0.45, for Win64 (x86_64)
--
-- Host: localhost    Database: control_fiscalizacion
-- ------------------------------------------------------
-- Server version	9.7.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
SET @MYSQLDUMP_TEMP_LOG_BIN = @@SESSION.SQL_LOG_BIN;
SET @@SESSION.SQL_LOG_BIN= 0;

--
-- GTID state at the beginning of the backup 
--

SET @@GLOBAL.GTID_PURGED=/*!80000 '+'*/ '4764b9e7-3e79-11f1-b336-00ff98138502:1-26815';

--
-- Table structure for table `actuados`
--

DROP TABLE IF EXISTS `actuados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `actuados` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `expediente_id` bigint unsigned NOT NULL,
  `catalogo_actuado_id` smallint unsigned NOT NULL,
  `usuario_id` bigint unsigned DEFAULT NULL,
  `fecha_hora` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estado_anterior_id` smallint unsigned DEFAULT NULL,
  `estado_nuevo_id` smallint unsigned NOT NULL,
  `contenido` json NOT NULL,
  `actuado_referencia_id` bigint unsigned DEFAULT NULL,
  `ip_origen` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hash_actuado` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hash_anterior` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `actuados_catalogo_actuado_id_foreign` (`catalogo_actuado_id`),
  KEY `actuados_usuario_id_foreign` (`usuario_id`),
  KEY `actuados_estado_anterior_id_foreign` (`estado_anterior_id`),
  KEY `actuados_estado_nuevo_id_foreign` (`estado_nuevo_id`),
  KEY `actuados_actuado_referencia_id_foreign` (`actuado_referencia_id`),
  KEY `idx_actuados_exp_fecha` (`expediente_id`,`fecha_hora`),
  CONSTRAINT `actuados_actuado_referencia_id_foreign` FOREIGN KEY (`actuado_referencia_id`) REFERENCES `actuados` (`id`),
  CONSTRAINT `actuados_catalogo_actuado_id_foreign` FOREIGN KEY (`catalogo_actuado_id`) REFERENCES `catalogo_actuados` (`id`),
  CONSTRAINT `actuados_estado_anterior_id_foreign` FOREIGN KEY (`estado_anterior_id`) REFERENCES `catalogo_estados` (`id`),
  CONSTRAINT `actuados_estado_nuevo_id_foreign` FOREIGN KEY (`estado_nuevo_id`) REFERENCES `catalogo_estados` (`id`),
  CONSTRAINT `actuados_expediente_id_foreign` FOREIGN KEY (`expediente_id`) REFERENCES `expedientes` (`id`),
  CONSTRAINT `actuados_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_actuados_hash_before_insert` BEFORE INSERT ON `actuados` FOR EACH ROW BEGIN
    DECLARE v_hash_prev CHAR(64);

    SELECT hash_actuado INTO v_hash_prev
    FROM actuados
    WHERE expediente_id = NEW.expediente_id
    ORDER BY fecha_hora DESC, id DESC
    LIMIT 1;

    SET NEW.hash_anterior = v_hash_prev;

    SET NEW.hash_actuado = SHA2(
        CONCAT(
            IFNULL(v_hash_prev, ''),
            CAST(NEW.expediente_id AS CHAR),
            CAST(NEW.catalogo_actuado_id AS CHAR),
            IFNULL(CAST(NEW.usuario_id AS CHAR), 'SYSTEM'),
            CAST(NEW.fecha_hora AS CHAR),
            CAST(NEW.contenido AS CHAR)
        ),
        256
    );
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_actuados_inmutable_update` BEFORE UPDATE ON `actuados` FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'OPERACIÓN NO PERMITIDA: Los actuados son inmutables por mandato institucional (RN-01). Emita un Actuado de Enmienda.';
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `trg_actuados_inmutable_delete` BEFORE DELETE ON `actuados` FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'OPERACIÓN NO PERMITIDA: El borrado físico de actuados está prohibido por la Restricción Legal Crítica.';
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `adjuntos`
--

DROP TABLE IF EXISTS `adjuntos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adjuntos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `actuado_id` bigint unsigned NOT NULL,
  `nombre_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ruta_almacenamiento` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `hash_sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanio_bytes` bigint unsigned DEFAULT NULL,
  `subido_por` bigint unsigned NOT NULL,
  `subido_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `adjuntos_actuado_id_foreign` (`actuado_id`),
  KEY `adjuntos_subido_por_foreign` (`subido_por`),
  CONSTRAINT `adjuntos_actuado_id_foreign` FOREIGN KEY (`actuado_id`) REFERENCES `actuados` (`id`),
  CONSTRAINT `adjuntos_subido_por_foreign` FOREIGN KEY (`subido_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `asignaciones`
--

DROP TABLE IF EXISTS `asignaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asignaciones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `expediente_id` bigint unsigned NOT NULL,
  `usuario_id` bigint unsigned NOT NULL,
  `rol_id` smallint unsigned NOT NULL,
  `actuado_origen_id` bigint unsigned NOT NULL,
  `fecha_asignacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `activa` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `asignaciones_expediente_id_foreign` (`expediente_id`),
  KEY `asignaciones_usuario_id_foreign` (`usuario_id`),
  KEY `asignaciones_rol_id_foreign` (`rol_id`),
  KEY `asignaciones_actuado_origen_id_foreign` (`actuado_origen_id`),
  CONSTRAINT `asignaciones_actuado_origen_id_foreign` FOREIGN KEY (`actuado_origen_id`) REFERENCES `actuados` (`id`),
  CONSTRAINT `asignaciones_expediente_id_foreign` FOREIGN KEY (`expediente_id`) REFERENCES `expedientes` (`id`),
  CONSTRAINT `asignaciones_rol_id_foreign` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `asignaciones_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auditoria_usuarios`
--

DROP TABLE IF EXISTS `auditoria_usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auditoria_usuarios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` bigint unsigned NOT NULL,
  `usuario_objetivo_id` bigint unsigned NOT NULL,
  `accion` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_origen` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `auditoria_usuarios_admin_id_foreign` (`admin_id`),
  KEY `auditoria_usuarios_usuario_objetivo_id_foreign` (`usuario_objetivo_id`),
  CONSTRAINT `auditoria_usuarios_admin_id_foreign` FOREIGN KEY (`admin_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `auditoria_usuarios_usuario_objetivo_id_foreign` FOREIGN KEY (`usuario_objetivo_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `catalogo_actuados`
--

DROP TABLE IF EXISTS `catalogo_actuados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `catalogo_actuados` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fase` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol_id` smallint unsigned DEFAULT NULL,
  `reglamento_id` smallint unsigned DEFAULT NULL,
  `estado_origen_id` smallint unsigned DEFAULT NULL,
  `estado_destino_id` smallint unsigned DEFAULT NULL,
  `es_automatico` tinyint(1) NOT NULL DEFAULT '0',
  `requiere_adjunto` tinyint(1) NOT NULL DEFAULT '0',
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `catalogo_actuados_codigo_unique` (`codigo`),
  KEY `catalogo_actuados_rol_id_foreign` (`rol_id`),
  KEY `catalogo_actuados_reglamento_id_foreign` (`reglamento_id`),
  KEY `catalogo_actuados_estado_origen_id_foreign` (`estado_origen_id`),
  KEY `catalogo_actuados_estado_destino_id_foreign` (`estado_destino_id`),
  CONSTRAINT `catalogo_actuados_estado_destino_id_foreign` FOREIGN KEY (`estado_destino_id`) REFERENCES `catalogo_estados` (`id`),
  CONSTRAINT `catalogo_actuados_estado_origen_id_foreign` FOREIGN KEY (`estado_origen_id`) REFERENCES `catalogo_estados` (`id`),
  CONSTRAINT `catalogo_actuados_reglamento_id_foreign` FOREIGN KEY (`reglamento_id`) REFERENCES `reglamentos` (`id`),
  CONSTRAINT `catalogo_actuados_rol_id_foreign` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `catalogo_estados`
--

DROP TABLE IF EXISTS `catalogo_estados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `catalogo_estados` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado_padre_id` smallint unsigned DEFAULT NULL,
  `es_final` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `catalogo_estados_codigo_unique` (`codigo`),
  KEY `catalogo_estados_estado_padre_id_foreign` (`estado_padre_id`),
  CONSTRAINT `catalogo_estados_estado_padre_id_foreign` FOREIGN KEY (`estado_padre_id`) REFERENCES `catalogo_estados` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `catalogo_requisitos`
--

DROP TABLE IF EXISTS `catalogo_requisitos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `catalogo_requisitos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reglamento_id` smallint unsigned NOT NULL,
  `descripcion` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL,
  `orden` smallint NOT NULL DEFAULT '0',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `catalogo_requisitos_reglamento_id_foreign` (`reglamento_id`),
  CONSTRAINT `catalogo_requisitos_reglamento_id_foreign` FOREIGN KEY (`reglamento_id`) REFERENCES `reglamentos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `evaluaciones_admisibilidad`
--

DROP TABLE IF EXISTS `evaluaciones_admisibilidad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `evaluaciones_admisibilidad` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `expediente_id` bigint unsigned NOT NULL,
  `requisito_id` bigint unsigned NOT NULL,
  `cumple` tinyint(1) NOT NULL,
  `actuado_id` bigint unsigned NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `evaluaciones_admisibilidad_expediente_id_foreign` (`expediente_id`),
  KEY `evaluaciones_admisibilidad_requisito_id_foreign` (`requisito_id`),
  KEY `evaluaciones_admisibilidad_actuado_id_foreign` (`actuado_id`),
  CONSTRAINT `evaluaciones_admisibilidad_actuado_id_foreign` FOREIGN KEY (`actuado_id`) REFERENCES `actuados` (`id`),
  CONSTRAINT `evaluaciones_admisibilidad_expediente_id_foreign` FOREIGN KEY (`expediente_id`) REFERENCES `expedientes` (`id`),
  CONSTRAINT `evaluaciones_admisibilidad_requisito_id_foreign` FOREIGN KEY (`requisito_id`) REFERENCES `catalogo_requisitos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `expedientes`
--

DROP TABLE IF EXISTS `expedientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expedientes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nurej_code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nurej_padre_id` bigint unsigned DEFAULT NULL,
  `via` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reglamento_id` smallint unsigned NOT NULL,
  `estado_actual_id` smallint unsigned NOT NULL,
  `resumen_hechos` text COLLATE utf8mb4_unicode_ci,
  `fecha_ingreso` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `expedientes_nurej_code_unique` (`nurej_code`),
  KEY `expedientes_creado_por_foreign` (`creado_por`),
  KEY `expedientes_reglamento_id_foreign` (`reglamento_id`),
  KEY `idx_expedientes_estado` (`estado_actual_id`),
  KEY `idx_expedientes_padre` (`nurej_padre_id`),
  KEY `idx_expedientes_via` (`via`),
  CONSTRAINT `expedientes_creado_por_foreign` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `expedientes_estado_actual_id_foreign` FOREIGN KEY (`estado_actual_id`) REFERENCES `catalogo_estados` (`id`),
  CONSTRAINT `expedientes_nurej_padre_id_foreign` FOREIGN KEY (`nurej_padre_id`) REFERENCES `expedientes` (`id`),
  CONSTRAINT `expedientes_reglamento_id_foreign` FOREIGN KEY (`reglamento_id`) REFERENCES `reglamentos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `feriados`
--

DROP TABLE IF EXISTS `feriados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `feriados` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `descripcion` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ambito` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NACIONAL',
  PRIMARY KEY (`id`),
  UNIQUE KEY `feriados_fecha_unique` (`fecha`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `impugnaciones`
--

DROP TABLE IF EXISTS `impugnaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `impugnaciones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `expediente_id` bigint unsigned NOT NULL,
  `actuado_rechazo_id` bigint unsigned NOT NULL,
  `fecha_presentacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_limite_resolucion` date NOT NULL,
  `resultado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  `actuado_resolucion_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `impugnaciones_expediente_id_foreign` (`expediente_id`),
  KEY `impugnaciones_actuado_rechazo_id_foreign` (`actuado_rechazo_id`),
  KEY `impugnaciones_actuado_resolucion_id_foreign` (`actuado_resolucion_id`),
  CONSTRAINT `impugnaciones_actuado_rechazo_id_foreign` FOREIGN KEY (`actuado_rechazo_id`) REFERENCES `actuados` (`id`),
  CONSTRAINT `impugnaciones_actuado_resolucion_id_foreign` FOREIGN KEY (`actuado_resolucion_id`) REFERENCES `actuados` (`id`),
  CONSTRAINT `impugnaciones_expediente_id_foreign` FOREIGN KEY (`expediente_id`) REFERENCES `expedientes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nurej_sequences`
--

DROP TABLE IF EXISTS `nurej_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nurej_sequences` (
  `anio` smallint unsigned NOT NULL,
  `correlativo` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`anio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `parametros_plazo`
--

DROP TABLE IF EXISTS `parametros_plazo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parametros_plazo` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reglamento_id` smallint unsigned NOT NULL,
  `tipo_plazo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtipo` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dias_habiles` smallint NOT NULL,
  `base_legal` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_parametro_regla` (`reglamento_id`,`tipo_plazo`,`subtipo`),
  CONSTRAINT `parametros_plazo_reglamento_id_foreign` FOREIGN KEY (`reglamento_id`) REFERENCES `reglamentos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `partes`
--

DROP TABLE IF EXISTS `partes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `partes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `expediente_id` bigint unsigned NOT NULL,
  `tipo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_completo` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `documento_identidad` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cargo_institucion` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actuado_origen_id` bigint unsigned DEFAULT NULL,
  `vigente_desde` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `vigente_hasta` timestamp NULL DEFAULT NULL,
  `es_version_actual` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `partes_actuado_origen_id_foreign` (`actuado_origen_id`),
  KEY `idx_partes_expediente` (`expediente_id`,`es_version_actual`),
  CONSTRAINT `partes_actuado_origen_id_foreign` FOREIGN KEY (`actuado_origen_id`) REFERENCES `actuados` (`id`),
  CONSTRAINT `partes_expediente_id_foreign` FOREIGN KEY (`expediente_id`) REFERENCES `expedientes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `plazos`
--

DROP TABLE IF EXISTS `plazos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `plazos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `expediente_id` bigint unsigned NOT NULL,
  `tipo_plazo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parametro_plazo_id` bigint unsigned DEFAULT NULL,
  `dias_habiles_otorgados` smallint NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_limite` date NOT NULL,
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VIGENTE',
  `fecha_pausa` date DEFAULT NULL,
  `fecha_reanudacion` date DEFAULT NULL,
  `fuera_de_plazo` tinyint(1) NOT NULL DEFAULT '0',
  `actuado_disparador_id` bigint unsigned NOT NULL,
  `actuado_cierre_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `plazos_expediente_id_foreign` (`expediente_id`),
  KEY `plazos_parametro_plazo_id_foreign` (`parametro_plazo_id`),
  KEY `plazos_actuado_disparador_id_foreign` (`actuado_disparador_id`),
  KEY `plazos_actuado_cierre_id_foreign` (`actuado_cierre_id`),
  CONSTRAINT `plazos_actuado_cierre_id_foreign` FOREIGN KEY (`actuado_cierre_id`) REFERENCES `actuados` (`id`),
  CONSTRAINT `plazos_actuado_disparador_id_foreign` FOREIGN KEY (`actuado_disparador_id`) REFERENCES `actuados` (`id`),
  CONSTRAINT `plazos_expediente_id_foreign` FOREIGN KEY (`expediente_id`) REFERENCES `expedientes` (`id`),
  CONSTRAINT `plazos_parametro_plazo_id_foreign` FOREIGN KEY (`parametro_plazo_id`) REFERENCES `parametros_plazo` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reglamentos`
--

DROP TABLE IF EXISTS `reglamentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reglamentos` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1.0',
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `reglamentos_codigo_version_unique` (`codigo`,`version`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_codigo_unique` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sesiones_acceso`
--

DROP TABLE IF EXISTS `sesiones_acceso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sesiones_acceso` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `ip_origen` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `login_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `logout_at` timestamp NULL DEFAULT NULL,
  `exitoso` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sesiones_acceso_usuario_id_foreign` (`usuario_id`),
  CONSTRAINT `sesiones_acceso_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sorteo_pesos`
--

DROP TABLE IF EXISTS `sorteo_pesos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sorteo_pesos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint unsigned NOT NULL,
  `reglamento_id` smallint unsigned NOT NULL,
  `peso` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_usuario_reglamento` (`usuario_id`,`reglamento_id`),
  KEY `sorteo_pesos_reglamento_id_foreign` (`reglamento_id`),
  CONSTRAINT `sorteo_pesos_reglamento_id_foreign` FOREIGN KEY (`reglamento_id`) REFERENCES `reglamentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sorteo_pesos_usuario_id_foreign` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `suspensiones_plazo`
--

DROP TABLE IF EXISTS `suspensiones_plazo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suspensiones_plazo` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `motivo` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `creado_por` bigint unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `suspensiones_plazo_creado_por_foreign` (`creado_por`),
  CONSTRAINT `suspensiones_plazo_creado_por_foreign` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transferencias`
--

DROP TABLE IF EXISTS `transferencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transferencias` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `expediente_id` bigint unsigned NOT NULL,
  `unidad_destino` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actuado_remision_id` bigint unsigned NOT NULL,
  `actuado_recepcion_id` bigint unsigned DEFAULT NULL,
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDIENTE',
  PRIMARY KEY (`id`),
  KEY `transferencias_expediente_id_foreign` (`expediente_id`),
  KEY `transferencias_actuado_remision_id_foreign` (`actuado_remision_id`),
  KEY `transferencias_actuado_recepcion_id_foreign` (`actuado_recepcion_id`),
  CONSTRAINT `transferencias_actuado_recepcion_id_foreign` FOREIGN KEY (`actuado_recepcion_id`) REFERENCES `actuados` (`id`),
  CONSTRAINT `transferencias_actuado_remision_id_foreign` FOREIGN KEY (`actuado_remision_id`) REFERENCES `actuados` (`id`),
  CONSTRAINT `transferencias_expediente_id_foreign` FOREIGN KEY (`expediente_id`) REFERENCES `expedientes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ci` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombres` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellidos` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cargo` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol_id` smallint unsigned NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuarios_ci_unique` (`ci`),
  UNIQUE KEY `usuarios_username_unique` (`username`),
  KEY `usuarios_rol_id_foreign` (`rol_id`),
  CONSTRAINT `usuarios_rol_id_foreign` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping events for database 'control_fiscalizacion'
--

--
-- Dumping routines for database 'control_fiscalizacion'
--
SET @@SESSION.SQL_LOG_BIN = @MYSQLDUMP_TEMP_LOG_BIN;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-08 13:04:17
