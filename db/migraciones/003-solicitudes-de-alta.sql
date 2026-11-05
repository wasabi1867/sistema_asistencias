-- El alumno que llega después de la carga pide su alta desde la pantalla de registro,
-- y el administrador la aprueba (D22 en docs/decisiones.md, pregunta 3 de las
-- preguntas para el instituto).

CREATE TABLE solicitudes_de_alta (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  matricula       VARCHAR(20)  NOT NULL,
  nombre          VARCHAR(100) NOT NULL,
  apellido        VARCHAR(100) NOT NULL,
  semestre        TINYINT UNSIGNED NULL,
  grupo           VARCHAR(20)  NULL,
  -- Dónde la pidió: el laboratorio y, si había una abierta, la clase.
  laboratorio_id  INT UNSIGNED NOT NULL,
  clase_id        INT UNSIGNED NULL,
  estado          ENUM('pendiente', 'aprobada', 'rechazada') NOT NULL DEFAULT 'pendiente',
  -- El alumno que quedó dado de alta al aprobarla.
  alumno_id       INT UNSIGNED NULL,
  comentario      VARCHAR(255) NULL,
  -- La red desde donde se pidió, para limitar cuántas llegan de una misma red.
  ip              VARCHAR(45)  NULL,
  creada_en       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resuelta_por    INT UNSIGNED NULL,
  resuelta_en     DATETIME     NULL,
  -- Una sola solicitud pendiente por matrícula.
  pendiente       VARCHAR(20) AS (IF(estado = 'pendiente', matricula, NULL)) STORED,
  PRIMARY KEY (id),
  UNIQUE KEY uq_solicitudes_de_alta_pendiente (pendiente),
  KEY ix_solicitudes_de_alta_estado (estado, creada_en),
  KEY ix_solicitudes_de_alta_ip (ip, creada_en),
  KEY ix_solicitudes_de_alta_laboratorio (laboratorio_id),
  KEY ix_solicitudes_de_alta_clase (clase_id, laboratorio_id),
  KEY ix_solicitudes_de_alta_alumno (alumno_id),
  KEY ix_solicitudes_de_alta_resuelta_por (resuelta_por),
  CONSTRAINT fk_solicitudes_de_alta_laboratorio FOREIGN KEY (laboratorio_id) REFERENCES laboratorios (id),
  CONSTRAINT fk_solicitudes_de_alta_clase FOREIGN KEY (clase_id, laboratorio_id) REFERENCES clases (id, laboratorio_id),
  -- Si después se borra al alumno, la solicitud se queda como constancia.
  CONSTRAINT fk_solicitudes_de_alta_alumno FOREIGN KEY (alumno_id) REFERENCES alumnos (id) ON DELETE SET NULL,
  CONSTRAINT fk_solicitudes_de_alta_resuelta_por FOREIGN KEY (resuelta_por) REFERENCES usuarios (id),
  CONSTRAINT ck_solicitudes_de_alta_resolucion CHECK ((estado = 'pendiente') = (resuelta_en IS NULL)),
  CONSTRAINT ck_solicitudes_de_alta_semestre CHECK (semestre IS NULL OR semestre BETWEEN 1 AND 15)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
