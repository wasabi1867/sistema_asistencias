-- ==============================================================
-- SISTEMA DE CONTROL DE ASISTENCIAS A LABORATORIOS — ITSPP
-- Esquema de la base de datos
-- ==============================================================
-- Funciona igual en MySQL 8.0 y en MariaDB 10.6 o más nuevo: las pruebas
-- corren en los dos, porque no sabemos cuál dará Hostinger.
--
-- La explicación de cada tabla y de cada decisión está en docs/modelo-de-datos.md.
--
-- Convenciones:
-- * Las horas son DATETIME en hora de Puerto Peñasco (America/Hermosillo, sin
--   horario de verano). La API fija esa zona en cada conexión. No se usa
--   TIMESTAMP porque convierte las horas según la zona de cada conexión.
-- * Lo que tiene historial no se borra: se desactiva (`activo`).
-- * Las reglas que la base puede garantizar las garantiza ella (llaves foráneas,
--   CHECK e índices únicos), aunque la API también las revise.
-- * Restricción de MySQL que explica varias llaves sin ON DELETE: una columna que
--   alimenta una columna calculada o un CHECK no admite CASCADE ni SET NULL.

SET NAMES utf8mb4;

-- --------------------------------------------------------------
-- Catálogos
-- --------------------------------------------------------------

CREATE TABLE laboratorios (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  -- Identificador corto que usan las pantallas y las direcciones (`programacion`).
  clave      VARCHAR(30)  NOT NULL,
  nombre     VARCHAR(100) NOT NULL,
  ubicacion  VARCHAR(100) NULL,
  activo     BOOLEAN      NOT NULL DEFAULT TRUE,
  PRIMARY KEY (id),
  UNIQUE KEY uq_laboratorios_clave (clave),
  CONSTRAINT ck_laboratorios_clave CHECK (clave REGEXP '^[a-z0-9_]+$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE carreras (
  id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  clave   VARCHAR(20)  NOT NULL,
  nombre  VARCHAR(150) NOT NULL,
  activa  BOOLEAN      NOT NULL DEFAULT TRUE,
  PRIMARY KEY (id),
  UNIQUE KEY uq_carreras_clave (clave),
  UNIQUE KEY uq_carreras_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Semestre escolar (agosto–diciembre, enero–junio). Los cursos pertenecen a uno.
CREATE TABLE periodos (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre        VARCHAR(50)  NOT NULL,
  fecha_inicio  DATE         NOT NULL,
  fecha_fin     DATE         NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_periodos_nombre (nombre),
  KEY ix_periodos_fechas (fecha_inicio, fecha_fin),
  CONSTRAINT ck_periodos_fechas CHECK (fecha_fin > fecha_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- Personas
-- --------------------------------------------------------------

-- Cuentas del personal: administradores, laboratoristas y docentes. Al iniciar
-- sesión se elige el rol, así que la misma persona puede tener una cuenta en
-- cada rol con el mismo correo o la misma matrícula.
CREATE TABLE usuarios (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  rol            ENUM('admin', 'laboratorista', 'docente') NOT NULL,
  -- Número de empleado; con él o con el correo se inicia sesión.
  matricula      VARCHAR(30)  NULL,
  nombre         VARCHAR(100) NOT NULL,
  apellido       VARCHAR(100) NOT NULL DEFAULT '',
  email          VARCHAR(150) NULL,
  password_hash  VARCHAR(255) NOT NULL,
  departamento   VARCHAR(150) NULL,
  activo         BOOLEAN      NOT NULL DEFAULT TRUE,
  creado_en      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_matricula (rol, matricula),
  UNIQUE KEY uq_usuarios_email (rol, email),
  CONSTRAINT ck_usuarios_identificacion CHECK (matricula IS NOT NULL OR email IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Los alumnos no tienen cuenta: entran con su número de control (matrícula).
CREATE TABLE alumnos (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  matricula       VARCHAR(20)  NOT NULL,
  nombre          VARCHAR(100) NOT NULL,
  apellido        VARCHAR(100) NOT NULL,
  carrera_id      INT UNSIGNED NULL,
  semestre        TINYINT UNSIGNED NULL,
  turno           ENUM('matutino', 'vespertino') NULL,
  grupo           VARCHAR(20)  NULL,
  activo          BOOLEAN      NOT NULL DEFAULT TRUE,
  creado_en       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_alumnos_matricula (matricula),
  KEY ix_alumnos_apellido (apellido, nombre),
  KEY ix_alumnos_carrera (carrera_id, semestre, grupo),
  CONSTRAINT fk_alumnos_carrera FOREIGN KEY (carrera_id) REFERENCES carreras (id),
  CONSTRAINT ck_alumnos_semestre CHECK (semestre IS NULL OR semestre BETWEEN 1 AND 15)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- Materias, cursos, inscripciones, prácticas y horario
-- --------------------------------------------------------------

-- La materia en sí, sin docente, grupo ni periodo.
CREATE TABLE materias (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  -- Clave oficial (por ejemplo AED-1286), si se conoce.
  clave       VARCHAR(30)  NULL,
  nombre      VARCHAR(150) NOT NULL,
  carrera_id  INT UNSIGNED NULL,
  semestre    TINYINT UNSIGNED NULL,
  activa      BOOLEAN      NOT NULL DEFAULT TRUE,
  PRIMARY KEY (id),
  UNIQUE KEY uq_materias_clave (clave),
  UNIQUE KEY uq_materias_nombre (carrera_id, nombre),
  KEY ix_materias_nombre (nombre),
  CONSTRAINT fk_materias_carrera FOREIGN KEY (carrera_id) REFERENCES carreras (id),
  CONSTRAINT ck_materias_semestre CHECK (semestre IS NULL OR semestre BETWEEN 1 AND 15)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Una materia que un docente imparte a un grupo en un periodo. Es lo que el
-- docente abre como clase y a lo que se inscriben los alumnos.
CREATE TABLE cursos (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  materia_id      INT UNSIGNED NOT NULL,
  periodo_id      INT UNSIGNED NOT NULL,
  grupo           VARCHAR(20)  NOT NULL,
  turno           ENUM('matutino', 'vespertino') NULL,
  docente_id      INT UNSIGNED NULL,
  -- Laboratorio donde se imparte normalmente; NULL si puede ser cualquiera.
  laboratorio_id  INT UNSIGNED NULL,
  creado_en       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cursos (materia_id, periodo_id, grupo),
  KEY ix_cursos_periodo (periodo_id),
  KEY ix_cursos_docente (docente_id, periodo_id),
  KEY ix_cursos_laboratorio (laboratorio_id),
  CONSTRAINT fk_cursos_materia FOREIGN KEY (materia_id) REFERENCES materias (id),
  CONSTRAINT fk_cursos_periodo FOREIGN KEY (periodo_id) REFERENCES periodos (id),
  CONSTRAINT fk_cursos_docente FOREIGN KEY (docente_id) REFERENCES usuarios (id),
  CONSTRAINT fk_cursos_laboratorio FOREIGN KEY (laboratorio_id) REFERENCES laboratorios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Qué alumnos llevan cada curso. Sustituye a la lista JSON alumnos.materias.
CREATE TABLE inscripciones (
  curso_id     INT UNSIGNED NOT NULL,
  alumno_id    INT UNSIGNED NOT NULL,
  -- Las faltas se cuentan desde aquí: las clases anteriores no son falta.
  inscrito_en  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  -- Si entró por una solicitud aprobada, cuál.
  solicitud_id INT UNSIGNED NULL,
  PRIMARY KEY (curso_id, alumno_id),
  KEY ix_inscripciones_alumno (alumno_id),
  CONSTRAINT fk_inscripciones_curso FOREIGN KEY (curso_id) REFERENCES cursos (id) ON DELETE CASCADE,
  CONSTRAINT fk_inscripciones_alumno FOREIGN KEY (alumno_id) REFERENCES alumnos (id) ON DELETE CASCADE
  -- fk_inscripciones_solicitud se agrega al final, cuando ya existe solicitudes_ingreso.
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Las prácticas de un curso. Son del curso y no de la materia porque cada
-- docente y cada grupo llevan las suyas, con su fecha; para reusarlas en otro
-- periodo se copian.
CREATE TABLE practicas (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  curso_id     INT UNSIGNED NOT NULL,
  numero       SMALLINT UNSIGNED NOT NULL,
  titulo       VARCHAR(150) NOT NULL,
  descripcion  TEXT         NULL,
  -- Texto libre como "2 horas": solo se muestra, no se calcula con él.
  duracion     VARCHAR(50)  NULL,
  -- Fecha planeada.
  fecha        DATE         NULL,
  activa       BOOLEAN      NOT NULL DEFAULT TRUE,
  PRIMARY KEY (id),
  UNIQUE KEY uq_practicas_numero (curso_id, numero),
  CONSTRAINT fk_practicas_curso FOREIGN KEY (curso_id) REFERENCES cursos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- En qué laboratorio y a qué hora se imparte cada curso cada semana. Antes el
-- horario solo vivía en la pantalla y se perdía al recargarla.
CREATE TABLE horarios (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  curso_id        INT UNSIGNED NOT NULL,
  laboratorio_id  INT UNSIGNED NOT NULL,
  -- 1 = lunes … 6 = sábado
  dia_semana      TINYINT UNSIGNED NOT NULL,
  hora_inicio     TIME         NOT NULL,
  hora_fin        TIME         NOT NULL,
  PRIMARY KEY (id),
  KEY ix_horarios_laboratorio (laboratorio_id, dia_semana, hora_inicio),
  KEY ix_horarios_curso (curso_id),
  CONSTRAINT fk_horarios_curso FOREIGN KEY (curso_id) REFERENCES cursos (id) ON DELETE CASCADE,
  CONSTRAINT fk_horarios_laboratorio FOREIGN KEY (laboratorio_id) REFERENCES laboratorios (id),
  CONSTRAINT ck_horarios_dia CHECK (dia_semana BETWEEN 1 AND 6),
  CONSTRAINT ck_horarios_horas CHECK (hora_fin > hora_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- Equipos
-- --------------------------------------------------------------

-- Las computadoras de cada laboratorio. Si un equipo está ocupado no se guarda
-- aquí: se sabe porque tiene una asistencia sin hora de salida. El equipo propio
-- del alumno no es una fila: en asistencias es equipo_id NULL.
CREATE TABLE equipos (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  laboratorio_id     INT UNSIGNED NOT NULL,
  -- Lo que se ve en la pantalla y en la etiqueta física: PC-1, PC-2...
  etiqueta           VARCHAR(30)  NOT NULL,
  fuera_de_servicio  BOOLEAN      NOT NULL DEFAULT FALSE,
  notas              TEXT         NULL,
  activo             BOOLEAN      NOT NULL DEFAULT TRUE,
  actualizado_en     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_equipos_etiqueta (laboratorio_id, etiqueta),
  CONSTRAINT fk_equipos_laboratorio FOREIGN KEY (laboratorio_id) REFERENCES laboratorios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cada falla reportada de un equipo y su seguimiento. Antes el estado de
-- reparación se sobrescribía en el equipo y no quedaba historial.
CREATE TABLE reportes_de_equipo (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  equipo_id       INT UNSIGNED NOT NULL,
  descripcion     TEXT         NOT NULL,
  estado          ENUM('reportado', 'en_proceso', 'resuelto') NOT NULL DEFAULT 'reportado',
  comentario      TEXT         NULL,
  reportado_por   INT UNSIGNED NULL,
  reportado_en    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  resuelto_en     DATETIME     NULL,
  PRIMARY KEY (id),
  KEY ix_reportes_equipo (equipo_id, reportado_en),
  KEY ix_reportes_estado (estado),
  KEY ix_reportes_reportado_por (reportado_por),
  CONSTRAINT fk_reportes_equipo FOREIGN KEY (equipo_id) REFERENCES equipos (id),
  CONSTRAINT fk_reportes_reportado_por FOREIGN KEY (reportado_por) REFERENCES usuarios (id),
  CONSTRAINT ck_reportes_resuelto CHECK ((estado = 'resuelto') = (resuelto_en IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- Clases y asistencias
-- --------------------------------------------------------------

-- Una sesión en un laboratorio. Mientras cerrada_en es NULL está abierta y se
-- puede registrar la entrada.
-- * regular: la abre el docente para uno de sus cursos; solo entran los inscritos.
-- * invitado: la abre el personal para un maestro o un evento de fuera; entra
--   cualquiera, incluso gente de fuera con su nombre. Docente, materia y práctica
--   se escriben a mano.
CREATE TABLE clases (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  laboratorio_id       INT UNSIGNED NOT NULL,
  tipo                 ENUM('regular', 'invitado') NOT NULL,
  curso_id             INT UNSIGNED NULL,
  practica_id          INT UNSIGNED NULL,
  -- Solo en clases de invitado:
  docente_invitado     VARCHAR(200) NULL,
  departamento         VARCHAR(150) NULL,
  materia_invitado     VARCHAR(150) NULL,
  practica_invitado    VARCHAR(150) NULL,
  grupo_invitado       VARCHAR(20)  NULL,
  abierta_por          INT UNSIGNED NOT NULL,
  abierta_en           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cerrada_por          INT UNSIGNED NULL,
  cerrada_en           DATETIME     NULL,
  -- normal: la cerró quien la abrió; externo: la cerró otra persona del
  -- personal; automatico: se cerró sola al cerrar el docente la pestaña.
  cierre               ENUM('normal', 'externo', 'automatico') NULL,
  -- Solo tienen valor mientras la clase está abierta. Sus índices únicos impiden
  -- dos clases abiertas del mismo tipo en un laboratorio, o del mismo curso.
  laboratorio_abierto  VARCHAR(40) AS (IF(cerrada_en IS NULL, CONCAT(laboratorio_id, '-', tipo), NULL)) STORED,
  curso_abierto        INT UNSIGNED AS (IF(cerrada_en IS NULL, curso_id, NULL)) STORED,
  PRIMARY KEY (id),
  UNIQUE KEY uq_clases_laboratorio_abierto (laboratorio_abierto),
  UNIQUE KEY uq_clases_curso_abierto (curso_abierto),
  KEY ix_clases_laboratorio (laboratorio_id, abierta_en),
  KEY ix_clases_curso (curso_id, abierta_en),
  KEY ix_clases_abierta_en (abierta_en),
  KEY ix_clases_practica (practica_id),
  KEY ix_clases_abierta_por (abierta_por),
  KEY ix_clases_cerrada_por (cerrada_por),
  CONSTRAINT fk_clases_laboratorio FOREIGN KEY (laboratorio_id) REFERENCES laboratorios (id),
  CONSTRAINT fk_clases_curso FOREIGN KEY (curso_id) REFERENCES cursos (id),
  CONSTRAINT fk_clases_practica FOREIGN KEY (practica_id) REFERENCES practicas (id),
  CONSTRAINT fk_clases_abierta_por FOREIGN KEY (abierta_por) REFERENCES usuarios (id),
  CONSTRAINT fk_clases_cerrada_por FOREIGN KEY (cerrada_por) REFERENCES usuarios (id),
  CONSTRAINT ck_clases_tipo CHECK (
       (tipo = 'regular'  AND curso_id IS NOT NULL AND materia_invitado IS NULL)
    OR (tipo = 'invitado' AND curso_id IS NULL AND practica_id IS NULL AND materia_invitado IS NOT NULL)
  ),
  CONSTRAINT ck_clases_cierre CHECK ((cerrada_en IS NULL) = (cierre IS NULL)),
  CONSTRAINT ck_clases_horas CHECK (cerrada_en IS NULL OR cerrada_en >= abierta_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cada entrada a una clase. Mientras hora_salida es NULL la persona sigue dentro.
-- Sustituye a asistencias (las de ahora) y asistencias_historico (las pasadas):
-- es la misma fila antes y después de la salida.
CREATE TABLE asistencias (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clase_id           INT UNSIGNED NOT NULL,
  -- NULL: trae su propio equipo.
  equipo_id          INT UNSIGNED NULL,
  tipo_persona       ENUM('alumno', 'docente', 'invitado') NOT NULL,
  alumno_id          INT UNSIGNED NULL,
  usuario_id         INT UNSIGNED NULL,
  invitado_nombre    VARCHAR(100) NULL,
  invitado_apellido  VARCHAR(100) NULL,
  hora_entrada       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  hora_salida        DATETIME     NULL,
  -- Si la registró el personal a mano, quién.
  registrada_por     INT UNSIGNED NULL,
  -- Si entró porque se aprobó su solicitud, cuál.
  solicitud_id       INT UNSIGNED NULL,
  -- Solo tienen valor mientras la persona sigue dentro. Sus índices únicos impiden
  -- que un equipo tenga dos ocupantes o que alguien esté en dos clases a la vez.
  equipo_ocupado     INT UNSIGNED AS (IF(hora_salida IS NULL, equipo_id, NULL)) STORED,
  alumno_dentro      INT UNSIGNED AS (IF(hora_salida IS NULL, alumno_id, NULL)) STORED,
  usuario_dentro     INT UNSIGNED AS (IF(hora_salida IS NULL, usuario_id, NULL)) STORED,
  PRIMARY KEY (id),
  UNIQUE KEY uq_asistencias_equipo_ocupado (equipo_ocupado),
  UNIQUE KEY uq_asistencias_alumno_dentro (alumno_dentro),
  UNIQUE KEY uq_asistencias_usuario_dentro (usuario_dentro),
  KEY ix_asistencias_clase (clase_id),
  KEY ix_asistencias_alumno (alumno_id, hora_entrada),
  KEY ix_asistencias_usuario (usuario_id),
  KEY ix_asistencias_equipo (equipo_id, hora_entrada),
  KEY ix_asistencias_hora_entrada (hora_entrada),
  KEY ix_asistencias_registrada_por (registrada_por),
  KEY ix_asistencias_solicitud (solicitud_id),
  CONSTRAINT fk_asistencias_clase FOREIGN KEY (clase_id) REFERENCES clases (id),
  CONSTRAINT fk_asistencias_equipo FOREIGN KEY (equipo_id) REFERENCES equipos (id),
  CONSTRAINT fk_asistencias_alumno FOREIGN KEY (alumno_id) REFERENCES alumnos (id),
  CONSTRAINT fk_asistencias_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id),
  CONSTRAINT fk_asistencias_registrada_por FOREIGN KEY (registrada_por) REFERENCES usuarios (id),
  -- fk_asistencias_solicitud se agrega al final, cuando ya existe solicitudes_ingreso.
  CONSTRAINT ck_asistencias_horas CHECK (hora_salida IS NULL OR hora_salida >= hora_entrada),
  CONSTRAINT ck_asistencias_persona CHECK (
       (tipo_persona = 'alumno'   AND alumno_id IS NOT NULL AND usuario_id IS NULL AND invitado_nombre IS NULL)
    OR (tipo_persona = 'docente'  AND usuario_id IS NOT NULL AND alumno_id IS NULL AND invitado_nombre IS NULL)
    OR (tipo_persona = 'invitado' AND invitado_nombre IS NOT NULL AND alumno_id IS NULL AND usuario_id IS NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un alumno que no está inscrito en el curso de la clase abierta pide entrar.
-- El administrador la aprueba solo por esa vez (temporal), la aprueba
-- inscribiéndolo en el curso (permanente) o la rechaza.
CREATE TABLE solicitudes_ingreso (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  clase_id      INT UNSIGNED NOT NULL,
  alumno_id     INT UNSIGNED NOT NULL,
  -- El equipo que eligió; NULL si trae el suyo.
  equipo_id     INT UNSIGNED NULL,
  motivo        TEXT         NULL,
  estado        ENUM('pendiente', 'aprobada_temporal', 'aprobada_permanente', 'rechazada') NOT NULL DEFAULT 'pendiente',
  leida         BOOLEAN      NOT NULL DEFAULT FALSE,
  creada_en     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resuelta_por  INT UNSIGNED NULL,
  resuelta_en   DATETIME     NULL,
  -- Una sola solicitud pendiente por alumno y clase.
  pendiente     VARCHAR(30) AS (IF(estado = 'pendiente', CONCAT(clase_id, '-', alumno_id), NULL)) STORED,
  PRIMARY KEY (id),
  UNIQUE KEY uq_solicitudes_pendiente (pendiente),
  KEY ix_solicitudes_clase (clase_id),
  KEY ix_solicitudes_alumno (alumno_id),
  KEY ix_solicitudes_estado (estado, creada_en),
  KEY ix_solicitudes_equipo (equipo_id),
  KEY ix_solicitudes_resuelta_por (resuelta_por),
  CONSTRAINT fk_solicitudes_clase FOREIGN KEY (clase_id) REFERENCES clases (id),
  CONSTRAINT fk_solicitudes_alumno FOREIGN KEY (alumno_id) REFERENCES alumnos (id),
  CONSTRAINT fk_solicitudes_equipo FOREIGN KEY (equipo_id) REFERENCES equipos (id),
  CONSTRAINT fk_solicitudes_resuelta_por FOREIGN KEY (resuelta_por) REFERENCES usuarios (id),
  CONSTRAINT ck_solicitudes_resolucion CHECK ((estado = 'pendiente') = (resuelta_en IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE inscripciones
  ADD KEY ix_inscripciones_solicitud (solicitud_id),
  ADD CONSTRAINT fk_inscripciones_solicitud FOREIGN KEY (solicitud_id) REFERENCES solicitudes_ingreso (id);

ALTER TABLE asistencias
  ADD CONSTRAINT fk_asistencias_solicitud FOREIGN KEY (solicitud_id) REFERENCES solicitudes_ingreso (id);

-- --------------------------------------------------------------
-- Eventos, avisos y bitácora
-- --------------------------------------------------------------

-- Talleres, concursos y otras actividades del laboratorio fuera de clase.
CREATE TABLE eventos (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  laboratorio_id  INT UNSIGNED NOT NULL,
  nombre          VARCHAR(150) NOT NULL,
  tipo            VARCHAR(50)  NULL,
  organizador     VARCHAR(150) NULL,
  participantes   SMALLINT UNSIGNED NULL,
  fecha           DATE         NULL,
  hora            TIME         NULL,
  duracion        VARCHAR(50)  NULL,
  descripcion     TEXT         NULL,
  resultados      TEXT         NULL,
  creado_por      INT UNSIGNED NULL,
  creado_en       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_eventos_laboratorio (laboratorio_id, fecha),
  KEY ix_eventos_creado_por (creado_por),
  CONSTRAINT fk_eventos_laboratorio FOREIGN KEY (laboratorio_id) REFERENCES laboratorios (id),
  CONSTRAINT fk_eventos_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Avisos para el administrador (y, los de equipos, para el laboratorista). Cada
-- uno apunta a aquello de lo que avisa, en lugar de copiar sus datos en JSON: la
-- pantalla muestra siempre el estado actual.
CREATE TABLE avisos (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo            ENUM('solicitud', 'equipo', 'clase', 'sistema') NOT NULL,
  titulo          VARCHAR(150) NOT NULL,
  mensaje         TEXT         NULL,
  prioridad       ENUM('baja', 'media', 'alta') NOT NULL DEFAULT 'media',
  laboratorio_id  INT UNSIGNED NULL,
  solicitud_id    INT UNSIGNED NULL,
  reporte_id      INT UNSIGNED NULL,
  clase_id        INT UNSIGNED NULL,
  leido           BOOLEAN      NOT NULL DEFAULT FALSE,
  creado_en       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_avisos_pendientes (leido, creado_en),
  KEY ix_avisos_laboratorio (laboratorio_id, creado_en),
  KEY ix_avisos_solicitud (solicitud_id),
  KEY ix_avisos_reporte (reporte_id),
  KEY ix_avisos_clase (clase_id),
  CONSTRAINT fk_avisos_laboratorio FOREIGN KEY (laboratorio_id) REFERENCES laboratorios (id),
  CONSTRAINT fk_avisos_solicitud FOREIGN KEY (solicitud_id) REFERENCES solicitudes_ingreso (id) ON DELETE CASCADE,
  CONSTRAINT fk_avisos_reporte FOREIGN KEY (reporte_id) REFERENCES reportes_de_equipo (id) ON DELETE CASCADE,
  CONSTRAINT fk_avisos_clase FOREIGN KEY (clase_id) REFERENCES clases (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quién hizo qué, desde dónde y cuándo. La llena la API; solo se inserta.
CREATE TABLE bitacora (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  fecha           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  usuario_id      INT UNSIGNED NULL,
  -- Copia del nombre o de lo que se escribió al intentar entrar: se conserva
  -- aunque la cuenta cambie o no exista.
  usuario         VARCHAR(150) NOT NULL DEFAULT '',
  rol             VARCHAR(20)  NULL,
  accion          VARCHAR(60)  NOT NULL,
  -- Sobre qué fue: tabla e identificador.
  entidad         VARCHAR(40)  NULL,
  entidad_id      VARCHAR(40)  NULL,
  detalles        TEXT         NULL,
  laboratorio_id  INT UNSIGNED NULL,
  ip              VARCHAR(45)  NULL,
  PRIMARY KEY (id),
  KEY ix_bitacora_fecha (fecha),
  KEY ix_bitacora_usuario (usuario_id, fecha),
  KEY ix_bitacora_entidad (entidad, entidad_id),
  KEY ix_bitacora_laboratorio (laboratorio_id, fecha),
  -- Para limitar los intentos fallidos de inicio de sesión por dirección.
  KEY ix_bitacora_intentos (accion, ip, fecha),
  CONSTRAINT fk_bitacora_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL,
  CONSTRAINT fk_bitacora_laboratorio FOREIGN KEY (laboratorio_id) REFERENCES laboratorios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Qué migraciones de db/migraciones/ ya se aplicaron (scripts/migrar.php).
CREATE TABLE migraciones (
  nombre       VARCHAR(150) NOT NULL,
  aplicada_en  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
