-- ==============================================================
-- Datos iniciales: los catálogos que el sistema necesita para empezar.
-- ==============================================================
-- Se cargan una vez, después de esquema.sql. Se pueden repetir sin duplicar.
-- El primer administrador no va aquí: se crea con scripts/crear-admin.php, para
-- que su contraseña no quede escrita en el repositorio.

SET NAMES utf8mb4;

-- Los cuatro laboratorios que ya manejaban las pantallas.
INSERT INTO laboratorios (clave, nombre) VALUES
  ('programacion', 'Taller de Programación'),
  ('redes', 'Laboratorio de Redes'),
  ('laboratorio_a', 'Laboratorio A'),
  ('laboratorio_c', 'Laboratorio C')
ON DUPLICATE KEY UPDATE nombre = nombre;

-- Los 25 equipos del Taller de Programación que traían los dos esquemas
-- anteriores. Los demás laboratorios se configuran desde el sistema.
INSERT INTO equipos (laboratorio_id, etiqueta)
SELECT l.id, CONCAT('PC-', n.numero)
FROM laboratorios l
JOIN (
  SELECT 1 AS numero UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5
  UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10
  UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15
  UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20
  UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24 UNION ALL SELECT 25
) n
WHERE l.clave = 'programacion'
  AND NOT EXISTS (SELECT 1 FROM equipos e WHERE e.laboratorio_id = l.id);

-- Las carreras que aparecían en las pantallas, con las claves del TecNM. Falta
-- confirmar con el instituto la lista completa y los nombres oficiales (en el
-- código aparecía "Licenciatura en Administración" y también "... de Empresas").
INSERT INTO carreras (clave, nombre) VALUES
  ('ISIC', 'Ingeniería en Sistemas Computacionales'),
  ('IIND', 'Ingeniería Industrial'),
  ('ICIV', 'Ingeniería Civil'),
  ('LADM', 'Licenciatura en Administración')
ON DUPLICATE KEY UPDATE nombre = nombre;
