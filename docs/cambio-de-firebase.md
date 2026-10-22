# Cambio de Firebase a la API

Con esta versión, todas las pantallas leen y escriben en la base MySQL o MariaDB de Hostinger a través de la API ([`api/`](../api)), y Firebase deja de usarse. Este documento lista lo que hay que hacer una sola vez para publicarla.

## Antes de empezar

- **El sitio va en Hostinger, junto a la API.** Firebase Hosting no ejecuta PHP, y la sesión va en una cookie que el navegador solo manda cuando la página y la API están en el mismo sitio.
- **PHP 8.1 o más nuevo.** Se revisa en el panel de Hostinger, en la configuración de PHP del sitio. Las pruebas corren en 8.3.
- **Hacerlo cuando no haya clases abiertas.** Las asistencias en curso no se copian.
- **Hace falta una computadora con PHP 8.1 o más nuevo y MySQL o MariaDB** para el respaldo y para revisar la importación. Con la PC de desarrollo basta.

## 1. Respaldar Firebase y revisar la importación

En la computadora, con una base local vacía configurada en `api/config.local.php` (ver [`config.ejemplo.php`](../api/config.ejemplo.php)):

```bash
php scripts/instalar.php
php scripts/importar-de-firebase.php --proyecto=integrador-7b39d --clave=APIKEY --respaldo=respaldo-firebase --simular
```

`APIKEY` es la `apiKey` de la configuración web de Firebase.
- **El primer comando** crea las tablas.
- **El segundo** descarga cada colección a `respaldo-firebase/` (JSON, tal como la da Firebase) y hace la importación completa, pero al final la deshace (`--simular`): solo muestra el reporte.

`respaldo-firebase/` tiene datos personales de alumnos y contraseñas cifradas. Git lo ignora: no se sube al repositorio ni al sitio.

El reporte dice cuántos registros de cada cosa se importarían y qué hay que revisar a mano. Lo más común son **carreras escritas de otra forma**: la importación las busca en el catálogo sin importar mayúsculas ni acentos, pero si una no está, sus alumnos quedan sin carrera. Para corregirlo:
1. Agregar la carrera al catálogo con [`db/datos-iniciales.sql`](../db/datos-iniciales.sql) o, después de publicar, desde la pantalla de carreras.
2. Volver a simular con `--desde=respaldo-firebase`, que ya no descarga nada.

## 2. Importar

Cuando el reporte esté bien, se importa en serio:

```bash
php scripts/importar-de-firebase.php --desde=respaldo-firebase
```

Se puede repetir sin duplicar nada. Cada quien entra con su contraseña de siempre:
- Las que venían cifradas (bcrypt) se copian tal cual y se vuelven a cifrar en el formato de PHP la primera vez que su dueño inicia sesión.
- Las que estuvieran en texto se cifran durante la importación.

## 3. Preparar la base en Hostinger

1. **Crear una base nueva y vacía** en el panel de Hostinger, con su usuario. La que existe ahora tiene las tablas del esquema anterior; se deja como estaba, por si hace falta consultarla.
2. **Crear las tablas:** en phpMyAdmin, sobre la base nueva, importar [`db/esquema.sql`](../db/esquema.sql). Está probado en MariaDB y en MySQL, así que sirve con cualquiera de los dos.
3. **Subir los datos que se importaron en la computadora**, sin las tablas:
   ```bash
   php scripts/exportar-datos.php respaldo-para-hostinger.sql
   ```
   Ese archivo se importa en la base nueva desde phpMyAdmin, en la pestaña Importar.
   - **Ya incluye los datos iniciales** (laboratorios, carreras, equipos), así que `datos-iniciales.sql` no se carga.
   - **Carga igual en MySQL que en MariaDB**, sin importar cuál tenga la computadora: se probó en los dos sentidos. `mysqldump` no sirve para esto, porque el de MariaDB escribe valores que MySQL rechaza.
   - **Tiene datos personales:** se borra de la computadora después de subirlo.

   *Si el plan de Hostinger da terminal (SSH)*, se puede hacer en el servidor: `php scripts/instalar.php` y luego la importación con `--desde`, subiendo la carpeta del respaldo fuera de `public_html` y borrándola al terminar.
4. **Crear `api/config.local.php` en el servidor**, con el administrador de archivos y a partir de `config.ejemplo.php`, con los datos de la base nueva.
5. **Cambiar la contraseña de la base anterior** en el panel. Estuvo publicada en el repositorio, y quitarla del código no la borra del historial de git.

## 4. Primer administrador

Si las cuentas de administrador venían de Firebase, ya entran con su contraseña. Si no, o para agregar otra:

```bash
php scripts/crear-admin.php correo@puertopenasco.tecnm.mx "Nombre" "Apellido"
```

Sin terminal en el servidor, se corre en la computadora antes de generar el archivo de datos del paso 3. También se puede crear desde phpMyAdmin:
1. Sacar la contraseña cifrada con `php scripts/crear-admin.php --solo-hash`.
2. Pegarla en esta consulta:

```sql
INSERT INTO usuarios (rol, email, nombre, apellido, password_hash)
VALUES ('admin', 'correo@puertopenasco.tecnm.mx', 'Nombre', 'Apellido', 'EL_HASH');
```

## 5. Compilar y subir el sitio

```bash
npm ci
npm run build
```

Se sube el contenido de `out/` a la carpeta del sitio en Hostinger. Incluye la carpeta `api/` con su `.htaccess`, que deja pasar las peticiones a `api/index.php` y no deja leer ningún otro archivo de la API.

**Al subir no hay que borrar `api/config.local.php` del servidor.** `npm run build` no lo copia a `out/`, para no reemplazar el del servidor con los datos de la computadora.

Si el sitio queda en una subcarpeta y no en la raíz del dominio, hay que compilar con `NEXT_PUBLIC_API_URL=/subcarpeta/api` y configurar `basePath` en `next.config.mjs`.

## 6. Probar

- Entrar como administrador, docente y laboratorista.
- Abrir una clase, registrar a un alumno desde otro dispositivo y cerrarla.
- Revisar que la clase aparezca en los reportes y que el historial importado se vea.

## 7. Dejar Firebase como respaldo

Conviene dejarlo unas semanas sin borrar, pero de solo lectura. La aplicación escribía en Firebase sin iniciar sesión, así que sus reglas de seguridad deben permitir escribir a cualquiera que tenga la configuración web, que está en el código publicado. No revisé las reglas mismas.

## Qué cambió para cada quien

- **Alumnos:** igual que antes. El servidor revisa:
  - que haya una clase abierta;
  - que esté inscrito en el curso, o que la clase sea de maestro invitado;
  - que la computadora esté libre;
  - que no esté ya registrado.

  Si se activa `exigir_codigo_de_clase`, además escriben el código que se muestra en el aula.
- **Personal:** la sesión la lleva el servidor. Una cuenta desactivada deja de entrar de inmediato.
- **Listas en vivo:** las pantallas consultan la API cada pocos segundos, en lugar de recibir los cambios al instante.
- **Bitácora:** la llena el servidor (quién, qué, cuándo y desde dónde), en lugar de cada pantalla.
