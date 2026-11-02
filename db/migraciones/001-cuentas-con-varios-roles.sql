-- Una cuenta por persona, con uno o varios roles, en lugar de una cuenta por rol.
-- Ver D20 en docs/decisiones.md.
--
-- Si alguien tuviera dos cuentas con el mismo correo o la misma matrícula, el
-- índice único nuevo fallaría: antes hay que unirlas a mano. En producción solo
-- había una cuenta cuando se escribió esta migración.

ALTER TABLE usuarios ADD COLUMN roles SET('admin', 'laboratorista', 'docente') NOT NULL AFTER id;
UPDATE usuarios SET roles = rol;
ALTER TABLE usuarios
  DROP INDEX uq_usuarios_matricula,
  DROP INDEX uq_usuarios_email,
  DROP COLUMN rol,
  ADD UNIQUE KEY uq_usuarios_matricula (matricula),
  ADD UNIQUE KEY uq_usuarios_email (email),
  ADD CONSTRAINT ck_usuarios_roles CHECK (roles <> '');

ALTER TABLE bitacora CHANGE COLUMN rol roles VARCHAR(40) NULL;
