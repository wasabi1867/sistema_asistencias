-- ==============================================================
-- 002: fecha de las prácticas
-- ==============================================================
-- En Firebase cada práctica lleva la fecha en que se realiza; la pantalla del
-- docente la pide al crearla y la muestra al elegirla.

SET NAMES utf8mb4;

ALTER TABLE `practicas`
  ADD COLUMN `fecha` DATE NULL AFTER `duracion`;

INSERT INTO `migraciones` (`nombre`) VALUES ('002-practicas-con-fecha');
