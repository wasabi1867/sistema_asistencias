-- ==============================================================
-- SISTEMA DE CONTROL DE ASISTENCIAS Y LABORATORIOS DE CÓMPUTO
-- Base de Datos MySQL para Hostinger
-- ==============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. TABLA: alumnos
CREATE TABLE IF NOT EXISTS `alumnos` (
  `matricula` VARCHAR(30) NOT NULL,
  `nombre` VARCHAR(100) NOT NULL,
  `apellido` VARCHAR(100) NOT NULL,
  `carrera` VARCHAR(100) DEFAULT '',
  `semestre` VARCHAR(20) DEFAULT '',
  `turno` VARCHAR(20) DEFAULT '',
  `grupo` VARCHAR(20) DEFAULT '',
  `materias` LONGTEXT NULL COMMENT 'Lista de materias en formato JSON',
  `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`matricula`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. TABLA: docentes
CREATE TABLE IF NOT EXISTS `docentes` (
  `matricula` VARCHAR(30) NOT NULL,
  `nombre` VARCHAR(100) NOT NULL,
  `apellido` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) DEFAULT '',
  `password` VARCHAR(255) DEFAULT '',
  `departamento` VARCHAR(100) DEFAULT '',
  `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`matricula`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. TABLA: laboratoristas
CREATE TABLE IF NOT EXISTS `laboratoristas` (
  `matricula` VARCHAR(30) NOT NULL,
  `nombre` VARCHAR(100) NOT NULL,
  `apellido` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) DEFAULT '',
  `password` VARCHAR(255) DEFAULT '',
  `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`matricula`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. TABLA: administradores
CREATE TABLE IF NOT EXISTS `administradores` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `nombre` VARCHAR(100) DEFAULT 'Administrador',
  `password` VARCHAR(255) NOT NULL,
  `rol` VARCHAR(50) DEFAULT 'admin',
  `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. TABLA: materias
CREATE TABLE IF NOT EXISTS `materias` (
  `id` VARCHAR(50) NOT NULL,
  `nombre_materia` VARCHAR(150) NOT NULL,
  `docente_id` VARCHAR(30) NULL,
  `semestre` VARCHAR(20) DEFAULT '',
  `ciclo_escolar` VARCHAR(50) DEFAULT '',
  `laboratorio` VARCHAR(50) DEFAULT 'programacion',
  `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. TABLA: practicas
CREATE TABLE IF NOT EXISTS `practicas` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `materia_id` VARCHAR(50) NOT NULL,
  `numero_practica` VARCHAR(20) DEFAULT '',
  `titulo` VARCHAR(150) NOT NULL,
  `descripcion` TEXT NULL,
  `duracion` VARCHAR(50) DEFAULT '',
  `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_practica_materia` (`materia_id`),
  CONSTRAINT `fk_practica_materia` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. TABLA: equipos
CREATE TABLE IF NOT EXISTS `equipos` (
  `id` VARCHAR(50) NOT NULL,
  `laboratorio` VARCHAR(50) NOT NULL DEFAULT 'programacion',
  `fuera_de_servicio` TINYINT(1) NOT NULL DEFAULT 0,
  `en_uso` TINYINT(1) NOT NULL DEFAULT 0,
  `usuario_actual` VARCHAR(150) NULL,
  `notas` TEXT NULL,
  `estado_reparacion` ENUM('reportado', 'en_proceso', 'resuelto', 'ninguno') DEFAULT 'ninguno',
  `comentario_estado` TEXT NULL,
  `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`, `laboratorio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. TABLA: asistencias (en vivo)
CREATE TABLE IF NOT EXISTS `asistencias` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `alumno_id` VARCHAR(30) NOT NULL,
  `nombre` VARCHAR(100) NOT NULL,
  `apellido` VARCHAR(100) NOT NULL,
  `carrera` VARCHAR(100) DEFAULT '',
  `semestre` VARCHAR(20) DEFAULT '',
  `turno` VARCHAR(20) DEFAULT '',
  `grupo` VARCHAR(20) DEFAULT '',
  `equipo_id` VARCHAR(50) NOT NULL,
  `laboratorio` VARCHAR(50) NOT NULL DEFAULT 'programacion',
  `tipo_usuario` ENUM('estudiante', 'docente', 'invitado') DEFAULT 'estudiante',
  `materia_id` VARCHAR(50) NULL,
  `hora_entrada` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_asistencias_alumno` (`alumno_id`),
  KEY `idx_asistencias_equipo` (`equipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. TABLA: asistencias_historico
CREATE TABLE IF NOT EXISTS `asistencias_historico` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `alumno_id` VARCHAR(30) NOT NULL,
  `nombre_completo` VARCHAR(200) NOT NULL,
  `carrera` VARCHAR(100) DEFAULT '',
  `semestre` VARCHAR(20) DEFAULT '',
  `turno` VARCHAR(20) DEFAULT '',
  `grupo` VARCHAR(20) DEFAULT '',
  `materia` VARCHAR(150) DEFAULT '',
  `docente` VARCHAR(150) DEFAULT '',
  `equipo_id` VARCHAR(50) DEFAULT '',
  `laboratorio` VARCHAR(50) DEFAULT 'programacion',
  `tipo_usuario` VARCHAR(30) DEFAULT 'estudiante',
  `hora_entrada` DATETIME NOT NULL,
  `hora_salida` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `fecha` DATE NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hist_fecha` (`fecha`),
  KEY `idx_hist_alumno` (`alumno_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. TABLA: solicitudes_ingreso
CREATE TABLE IF NOT EXISTS `solicitudes_ingreso` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `alumno_id` VARCHAR(30) NOT NULL,
  `nombre` VARCHAR(150) NOT NULL,
  `carrera` VARCHAR(100) DEFAULT '',
  `motivo` TEXT NOT NULL,
  `laboratorio` VARCHAR(50) DEFAULT 'programacion',
  `estado` ENUM('pendiente', 'aprobada', 'rechazada') DEFAULT 'pendiente',
  `fecha_solicitud` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. TABLA: notificaciones_admin
CREATE TABLE IF NOT EXISTS `notificaciones_admin` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `tipo` VARCHAR(50) NOT NULL,
  `titulo` VARCHAR(150) NOT NULL,
  `mensaje` TEXT NOT NULL,
  `laboratorio` VARCHAR(50) DEFAULT 'programacion',
  `prioridad` VARCHAR(20) DEFAULT 'media',
  `leida` TINYINT(1) DEFAULT 0,
  `datos` LONGTEXT NULL COMMENT 'Datos extra en JSON',
  `fecha` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. TABLA: logs
CREATE TABLE IF NOT EXISTS `logs` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `accion` VARCHAR(100) NOT NULL,
  `usuario` VARCHAR(100) NOT NULL,
  `detalles` TEXT NULL,
  `fecha` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================
-- DATOS INICIALES POR DEFECTO
-- ==============================================================

-- Usuario Administrador Inicial (password: Admin123! con hash bcrypt)
INSERT INTO `administradores` (`email`, `nombre`, `password`, `rol`)
VALUES ('admin@puertopenasco.tecnm.mx', 'Administrador General', '$2a$10$wT0lV3xM6i1sN3GkR3n7s.yq17V26WbVjZ368Fj3v5Ocf4qG5XFjW', 'admin')
ON DUPLICATE KEY UPDATE `email` = `email`;

-- Inicializar equipos comunes para el Laboratorio de Programación (PC-1 a PC-25 y personal)
INSERT INTO `equipos` (`id`, `laboratorio`, `fuera_de_servicio`, `en_uso`) VALUES
('personal', 'programacion', 0, 0),
('PC-1', 'programacion', 0, 0),
('PC-2', 'programacion', 0, 0),
('PC-3', 'programacion', 0, 0),
('PC-4', 'programacion', 0, 0),
('PC-5', 'programacion', 0, 0),
('PC-6', 'programacion', 0, 0),
('PC-7', 'programacion', 0, 0),
('PC-8', 'programacion', 0, 0),
('PC-9', 'programacion', 0, 0),
('PC-10', 'programacion', 0, 0),
('PC-11', 'programacion', 0, 0),
('PC-12', 'programacion', 0, 0),
('PC-13', 'programacion', 0, 0),
('PC-14', 'programacion', 0, 0),
('PC-15', 'programacion', 0, 0),
('PC-16', 'programacion', 0, 0),
('PC-17', 'programacion', 0, 0),
('PC-18', 'programacion', 0, 0),
('PC-19', 'programacion', 0, 0),
('PC-20', 'programacion', 0, 0),
('PC-21', 'programacion', 0, 0),
('PC-22', 'programacion', 0, 0),
('PC-23', 'programacion', 0, 0),
('PC-24', 'programacion', 0, 0),
('PC-25', 'programacion', 0, 0)
ON DUPLICATE KEY UPDATE `laboratorio` = `laboratorio`;

SET FOREIGN_KEY_CHECKS = 1;
