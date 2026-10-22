-- ==============================================================
-- 001: clases, solicitudes ligadas a la clase, bitácora con IP,
--      administradores con matrícula, materias con turno, eventos
-- ==============================================================
-- Se aplica UNA vez sobre una base creada con schema.sql:
--   php scripts/migrar.php            (si hay terminal)
--   o pegando este archivo en phpMyAdmin.
-- Compatible con MariaDB y MySQL 8.
--
-- Antes de aplicarla, la tabla `asistencias` (las asistencias en curso) no debe
-- tener dos filas de la misma persona: se agrega una regla que lo impide.

SET NAMES utf8mb4;

-- Registro de qué migraciones ya se aplicaron (lo usa scripts/migrar.php).
CREATE TABLE IF NOT EXISTS `migraciones` (
  `nombre` VARCHAR(150) NOT NULL,
  `aplicada_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cada clase que se abre en un laboratorio. Sustituye a EstadoClase,
-- EstadoClaseInvitado y ClassInformation de Firebase: mientras `cerrada_en` es
-- NULL la clase está abierta y los alumnos pueden registrarse.
CREATE TABLE IF NOT EXISTS `clases` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `laboratorio` VARCHAR(50) NOT NULL,
  `tipo` ENUM('regular', 'invitado') NOT NULL DEFAULT 'regular',
  `materia_id` VARCHAR(50) NULL COMMENT 'Solo en clases regulares',
  `materia` VARCHAR(150) NOT NULL DEFAULT '',
  `practica_id` INT NULL,
  `practica` VARCHAR(150) NOT NULL DEFAULT '',
  `docente_id` VARCHAR(30) NULL,
  `docente` VARCHAR(200) NOT NULL DEFAULT '',
  `departamento` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'Clases de maestro invitado',
  `grupo` VARCHAR(20) NOT NULL DEFAULT '',
  `abierta_por` VARCHAR(150) NOT NULL DEFAULT '',
  `abierta_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cerrada_en` DATETIME NULL,
  `cierre` ENUM('normal', 'externo', 'automatico') NULL COMMENT 'externo: la cerró el administrador o el laboratorista',
  `total_asistencias` INT NOT NULL DEFAULT 0,
  -- Solo una clase abierta de cada tipo por laboratorio. La columna vale NULL en
  -- las cerradas, y un índice único admite cualquier cantidad de NULL.
  `abierta_clave` VARCHAR(80) AS (IF(`cerrada_en` IS NULL, CONCAT(`laboratorio`, '|', `tipo`), NULL)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clases_abierta` (`abierta_clave`),
  KEY `idx_clases_laboratorio` (`laboratorio`, `cerrada_en`),
  KEY `idx_clases_abierta_en` (`abierta_en`),
  KEY `idx_clases_docente` (`docente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cada asistencia sabe a qué clase pertenece. Una persona solo puede estar
-- registrada en una clase a la vez.
ALTER TABLE `asistencias`
  ADD COLUMN `clase_id` INT NULL AFTER `materia_id`,
  ADD KEY `idx_asistencias_clase` (`clase_id`),
  ADD UNIQUE KEY `uq_asistencias_persona` (`alumno_id`);

-- El histórico guarda también nombre y apellido por separado: los reportes los
-- muestran en columnas distintas. nombre_completo se sigue llenando.
ALTER TABLE `asistencias_historico`
  ADD COLUMN `clase_id` INT NULL AFTER `id`,
  ADD COLUMN `nombre` VARCHAR(100) NOT NULL DEFAULT '' AFTER `nombre_completo`,
  ADD COLUMN `apellido` VARCHAR(100) NOT NULL DEFAULT '' AFTER `nombre`,
  ADD KEY `idx_hist_clase` (`clase_id`);

-- Las solicitudes se hacen desde una clase abierta: guardan cuál, qué materia y
-- qué equipo eligió el alumno. Se aprueban por esa sola vez (temporal) o
-- inscribiendo al alumno en la materia (permanente), como en el panel actual.
ALTER TABLE `solicitudes_ingreso`
  ADD COLUMN `clase_id` INT NULL AFTER `laboratorio`,
  ADD COLUMN `materia_id` VARCHAR(50) NULL AFTER `clase_id`,
  ADD COLUMN `equipo_id` VARCHAR(50) NOT NULL DEFAULT '' AFTER `materia_id`,
  ADD COLUMN `leida` TINYINT(1) NOT NULL DEFAULT 0 AFTER `estado`,
  MODIFY COLUMN `estado` ENUM('pendiente', 'aprobada', 'rechazada', 'aprobado_temporal', 'aprobado_permanente') DEFAULT 'pendiente';

-- Bitácora: de qué laboratorio y desde qué dirección. La dirección también sirve
-- para limitar los intentos de inicio de sesión.
ALTER TABLE `logs`
  ADD COLUMN `laboratorio` VARCHAR(50) NULL AFTER `detalles`,
  ADD COLUMN `ip` VARCHAR(45) NULL AFTER `laboratorio`,
  ADD KEY `idx_logs_fecha` (`fecha`),
  ADD KEY `idx_logs_intentos` (`accion`, `ip`, `fecha`);

-- En Firebase el administrador entra con matrícula o correo y tiene apellido.
ALTER TABLE `administradores`
  ADD COLUMN `matricula` VARCHAR(30) NULL AFTER `id`,
  ADD COLUMN `apellido` VARCHAR(100) NOT NULL DEFAULT '' AFTER `nombre`,
  ADD UNIQUE KEY `uq_administradores_matricula` (`matricula`);

ALTER TABLE `materias`
  ADD COLUMN `turno` VARCHAR(20) NOT NULL DEFAULT '' AFTER `semestre`;

-- Eventos del laboratorio (EventosLaboratorio en Firebase).
CREATE TABLE IF NOT EXISTS `eventos_laboratorio` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `nombre` VARCHAR(150) NOT NULL,
  `tipo` VARCHAR(50) NOT NULL DEFAULT '',
  `organizador` VARCHAR(150) NOT NULL DEFAULT '',
  `participantes` INT NOT NULL DEFAULT 0,
  `fecha` DATE NULL,
  `hora` VARCHAR(10) NOT NULL DEFAULT '',
  `duracion` VARCHAR(50) NOT NULL DEFAULT '',
  `descripcion` TEXT NULL,
  `resultados` TEXT NULL,
  `laboratorio` VARCHAR(50) NOT NULL DEFAULT 'programacion',
  `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_eventos_laboratorio` (`laboratorio`, `fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migraciones` (`nombre`) VALUES ('001-clases-solicitudes-y-bitacora');
