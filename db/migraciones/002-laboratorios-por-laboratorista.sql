-- Cada laboratorista atiende solo los laboratorios que le asigna el administrador
-- (D21 en docs/decisiones.md, pregunta 6 de las preguntas para el instituto).
--
-- Los laboratoristas que ya existen quedan con todos los laboratorios, como antes:
-- así nadie pierde lo que podía hacer, y el administrador les quita los que no
-- atienden.

CREATE TABLE laboratorios_del_personal (
  usuario_id      INT UNSIGNED NOT NULL,
  laboratorio_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (usuario_id, laboratorio_id),
  KEY ix_laboratorios_del_personal_laboratorio (laboratorio_id),
  CONSTRAINT fk_laboratorios_del_personal_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE,
  CONSTRAINT fk_laboratorios_del_personal_laboratorio FOREIGN KEY (laboratorio_id) REFERENCES laboratorios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO laboratorios_del_personal (usuario_id, laboratorio_id)
SELECT u.id, l.id FROM usuarios u CROSS JOIN laboratorios l WHERE FIND_IN_SET('laboratorista', u.roles);
