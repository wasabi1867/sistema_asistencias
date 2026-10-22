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

-- Las carreras que aparecían en las pantallas, con las claves del TecNM. Falta
-- confirmar con el instituto la lista completa y los nombres oficiales (en el
-- código aparecía "Licenciatura en Administración" y también "... de Empresas").
INSERT INTO carreras (clave, nombre) VALUES
  ('ISIC', 'Ingeniería en Sistemas Computacionales'),
  ('IIND', 'Ingeniería Industrial'),
  ('ICIV', 'Ingeniería Civil'),
  ('LADM', 'Licenciatura en Administración')
ON DUPLICATE KEY UPDATE nombre = nombre;
