# Cambio de Firebase a la API en PHP

Con esta versión, todas las pantallas leen y escriben en la base MySQL/MariaDB de Hostinger a través de la API (`public/api`). Firebase deja de usarse. Este documento lista lo que hay que hacer una sola vez para publicarla.

## Antes de empezar

- **El sitio debe publicarse en Hostinger, junto a la API.** La API es PHP y Firebase Hosting no ejecuta PHP. La sesión va en una cookie que el navegador solo manda cuando la página y la API están en el mismo sitio.
- **Hacerlo cuando no haya clases abiertas.** Las asistencias en curso no se copian.

## Pasos

1. **Respaldar Firebase.** Antes de tocar nada, se descarga una copia de cada colección:

   ```bash
   php scripts/importar-de-firebase.php --proyecto=integrador-7b39d --respaldo=respaldo-firebase --sql=importacion.sql
   ```

   Esto escribe la copia en `respaldo-firebase/` (JSON, tal como la da Firebase) y las instrucciones de importación en `importacion.sql`. Todavía no toca ninguna base. `respaldo-firebase/` tiene datos personales de alumnos y contraseñas cifradas: no se sube a git ni al sitio.

2. **Preparar la base en Hostinger.**
   - Cambiar la contraseña de la base en el panel de Hostinger: la anterior estuvo publicada en el repositorio.
   - En la carpeta `api/` del sitio, crear `config.local.php` a partir de `config.ejemplo.php`, con los datos de la base.
   - Si la base está vacía, crear las tablas con `schema.sql`.
   - Aplicar las migraciones de `db/migraciones/` en orden, con `php scripts/migrar.php` o pegando cada archivo en phpMyAdmin.

3. **Importar los datos.** Con terminal en el servidor:

   ```bash
   php scripts/importar-de-firebase.php --desde=respaldo-firebase
   ```

   Sin terminal, se pega `importacion.sql` en phpMyAdmin. Las dos formas se pueden repetir sin duplicar nada. Se importan:
   - Cuentas de personal, alumnos con sus materias, materias con sus prácticas y equipos.
   - Historial de clases (`ClassInformation`) con sus asistentes.
   - Solicitudes, avisos, bitácora y eventos.

   Cada quien entra con su contraseña de siempre: venían cifradas con bcrypt y la API las acepta, y en el primer inicio de sesión se vuelven a cifrar en el formato de PHP.

4. **Compilar y subir el sitio.**

   ```bash
   npm ci
   npm run build
   ```

   Se sube el contenido de `out/` a la carpeta del sitio en Hostinger. Incluye la carpeta `api/`. Si el sitio queda en una subcarpeta y no en la raíz del dominio, hay que compilar con `NEXT_PUBLIC_API_URL=/subcarpeta/api` y configurar `basePath` en `next.config.mjs`. Al subir, **no borrar** `api/config.local.php` del servidor. `npm run build` quita de `out/` el `config.local.php` que haya en la computadora donde se compila, para no reemplazar el del servidor con otros datos.

5. **Probar.**
   - Entrar como administrador, docente y laboratorista.
   - Abrir una clase, registrar a un alumno desde otro dispositivo y cerrarla.
   - Revisar que la clase aparezca en los reportes.

6. **Dejar Firebase como respaldo.** Conviene dejarlo unas semanas sin borrar, y cambiar sus reglas de seguridad para que ya no se pueda escribir. Como la aplicación escribía en Firebase sin iniciar sesión en él, sus reglas deben permitir escribir a cualquiera que tenga la configuración web, que está en el código publicado (no revisé las reglas mismas).

## Qué cambió para cada quien

- **Alumnos:** igual que antes. Ahora el servidor revisa las reglas:
  - Que haya una clase abierta.
  - Que el alumno esté inscrito en la materia.
  - Que el equipo esté libre.
  - Que no se registre dos veces.
  
  Antes las revisaba el navegador, y se podían saltar.
- **Personal:** la sesión la lleva el servidor. Las pantallas ya no se abren solo con escribir un `maestroId` en el navegador.
- **Listas en vivo:** las pantallas consultan la API cada pocos segundos (de 3 a 15, según la pantalla) en lugar de recibir los cambios al instante.
- **Bitácora:** la llena el servidor con quién hizo cada cosa y desde dónde, en lugar de que cada pantalla escribiera la suya.
