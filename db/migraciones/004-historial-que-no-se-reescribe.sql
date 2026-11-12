-- Lo que ya pasó no cambia cuando cambian el curso, la lista o el alumno (D24 en
-- docs/decisiones.md):
-- * dar de baja a un alumno ya no borra su inscripción, solo le pone fecha;
-- * cada clase guarda al docente que la dio;
-- * cada asistencia guarda la carrera, el semestre, el turno y el grupo que tenía
--   el alumno al entrar;
-- * el sistema cierra las clases que se quedaron abiertas (cierre 'vencida').
--
-- Lo que ya está registrado queda con los datos de hoy: el docente actual de cada
-- curso y los datos actuales de cada alumno, que es lo mejor que se sabe.

ALTER TABLE inscripciones
  ADD COLUMN baja_en DATETIME NULL AFTER solicitud_id;

ALTER TABLE clases
  ADD COLUMN docente_id INT UNSIGNED NULL AFTER practica_id,
  MODIFY cierre ENUM('normal', 'externo', 'automatico', 'vencida') NULL,
  ADD KEY ix_clases_docente (docente_id),
  ADD CONSTRAINT fk_clases_docente FOREIGN KEY (docente_id) REFERENCES usuarios (id);

UPDATE clases cl JOIN cursos cu ON cu.id = cl.curso_id SET cl.docente_id = cu.docente_id;

ALTER TABLE asistencias
  ADD COLUMN carrera_id INT UNSIGNED NULL AFTER alumno_id,
  ADD COLUMN semestre TINYINT UNSIGNED NULL AFTER carrera_id,
  ADD COLUMN turno ENUM('matutino', 'vespertino') NULL AFTER semestre,
  ADD COLUMN grupo VARCHAR(20) NULL AFTER turno,
  ADD KEY ix_asistencias_carrera (carrera_id),
  ADD CONSTRAINT fk_asistencias_carrera FOREIGN KEY (carrera_id) REFERENCES carreras (id);

UPDATE asistencias s JOIN alumnos a ON a.id = s.alumno_id
SET s.carrera_id = a.carrera_id, s.semestre = a.semestre, s.turno = a.turno, s.grupo = a.grupo;
