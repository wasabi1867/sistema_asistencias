# Publicar esta versión y dejar Firebase

Con esta versión, todas las pantallas leen y escriben en la base MySQL o MariaDB de Hostinger a través de la API ([`api/`](../api)), y Firebase deja de usarse. Este documento lista lo que hay que hacer una sola vez para publicarla.

## Primero: decidir qué pasa con los datos de Firebase

El sitio publicado hoy guarda todo en Firebase. En cuanto se publique esta versión, Firebase deja de recibir datos: lo que tenga hasta ese día es lo que se puede traer.

**No hay que descargar nada a mano.** `scripts/importar-de-firebase.php` lee Firebase directamente con la configuración web del proyecto (`projectId` y `apiKey`, las mismas que usaba el sitio anterior) y guarda una copia en una carpeta antes de importar. Lo que sí hace falta es el visto bueno de Andrés, porque son datos personales de los alumnos.

Hay dos formas de publicar:

**A. Traer los datos y luego publicar (recomendada).** Se siguen los pasos 1 a 7 de abajo en orden. Si todavía no se pueden traer, conviene esperar: mientras no se publique, el sitio anterior sigue guardando todo en Firebase y no se pierde nada.

**B. Publicar sin los datos y traerlos después.** Se saltan los pasos 1 y 2. En el paso 3 la base de Hostinger se crea vacía, importando `db/esquema.sql` y luego `db/datos-iniciales.sql`. Después se crea el primer administrador (paso 4) y el sistema se empieza a usar: el periodo, alumnos desde Excel, materias, cursos e inscripciones.

La importación se puede correr después sobre una base en uso: busca lo que ya existe en lugar de duplicarlo. Pero hay que tener en cuenta lo siguiente:
- **Las materias se buscan por nombre exacto.** Una capturada con otro nombre que en Firebase ("Programación Web" y "Programacion web I") queda dos veces.
- **Los cursos se buscan por materia, periodo y docente.** Si el docente es otro, se crea un curso más y las inscripciones de Firebase van a ese.
- **Los alumnos que estaban activos en Firebase se sobrescriben** con los datos de Firebase (nombre, carrera, semestre, grupo, turno) y quedan activos, aunque se hayan corregido o desactivado en el sistema nuevo.
- **Los equipos de cada laboratorio quedan como estaban en Firebase:** los que no estén allá se retiran, y "fuera de servicio" y las notas vuelven a los de Firebase.
- **Los periodos se buscan por fecha.** Si ningún periodo cubre la fecha de una clase de Firebase, la importación crea "AGO-DIC" (del 1 de agosto al 31 de diciembre) o "ENE-JUL" (del 1 de enero al 31 de julio) con su año. Si ya existe uno con ese nombre pero con otras fechas, la importación entera falla sin guardar nada; si el creado a mano se llama distinto, quedan dos periodos encimados. Para evitarlo, el periodo actual se crea como **AGO-DIC 2026, del 1 de agosto al 31 de diciembre de 2026**.
- **Las cuentas del personal se buscan por rol y por matrícula o correo.** Se actualizan sus datos, pero no su contraseña.
- **Hay que correrla contra la base de Hostinger.** Eso pide terminal (SSH) en el servidor, o que Hostinger permita conectarse a la base desde fuera ("MySQL remoto") para correrla desde la computadora con `api/config.local.php` apuntando allá. **Ninguna de las dos se ha comprobado** con el plan actual.

Siempre se corre primero con `--simular`, que hace todo, muestra el reporte y no guarda nada.

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

Esta versión ya no trae los archivos de Firebase Hosting (`firebase.json` y su página de bienvenida en `public/index.html`). Si en el servidor quedó esa página, el `index.html` de `out/` la reemplaza al subirlo.

Las páginas cambiaron de dirección: `/AdminPanel` ahora es `/admin`, `/lista-asistencias` es `/docente`, `/lista-asistenciasInvitado` es `/clase-invitada` y `/panel-laboratorista` es `/laboratorio`. El `.htaccess` redirige las direcciones viejas a las nuevas, así que los marcadores guardados siguen sirviendo. Los archivos viejos (`AdminPanel.html` y los demás) que queden en el servidor se pueden borrar; mientras estén, la redirección se aplica antes.

**Al subir no hay que borrar `api/config.local.php` del servidor.** `npm run build` no lo copia a `out/`, para no reemplazar el del servidor con los datos de la computadora.

Si el sitio queda en una subcarpeta y no en la raíz del dominio, hay que compilar con `NEXT_PUBLIC_API_URL=/subcarpeta/api` y configurar `basePath` en `next.config.mjs`.

## 6. Probar

- Entrar como administrador, docente y laboratorista.
- Abrir una clase, registrar a un alumno desde otro dispositivo y cerrarla.
- Revisar que la clase aparezca en los reportes y que el historial importado se vea.

## 7. Dejar Firebase como respaldo

Conviene dejarlo unas semanas sin borrar, pero de solo lectura. La aplicación escribía en Firebase sin iniciar sesión, así que sus reglas de seguridad deben permitir escribir a cualquiera que tenga la configuración web, que está en el código publicado. No revisé las reglas mismas.

## Qué cambió para cada quien

- **Alumnos:** el registro va en dos pasos: primero el número de control y luego la computadora, que se elige en una cuadrícula donde las ocupadas no se pueden tocar. La confirmación se queda unos segundos y la pantalla vuelve sola al principio para el siguiente. El servidor revisa:
  - que haya una clase abierta;
  - que esté inscrito en el curso, o que la clase sea de maestro invitado;
  - que la computadora esté libre;
  - que no esté ya registrado.

  Si se activa `exigir_codigo_de_clase`, además escriben el código que se muestra en el aula.
- **Personal:** todos entran por el botón **Personal** de la página principal (`/entrar`), eligiendo si son docente, laboratorista o administrador. Antes el administrador entraba desde un menú escondido. La sesión la lleva el servidor: una cuenta desactivada deja de entrar de inmediato. Cambiar la contraseña, el tema y cerrar sesión están en el menú con su nombre, arriba a la derecha.
- **Paneles:** los tres (docente, laboratorio y administrador) tienen el mismo menú lateral, que en el teléfono se abre con un botón. La sección abierta queda en la dirección, así que recargar o usar "atrás" no la pierde. Lo que se puede hacer con cada renglón de una tabla está en su botón "⋯".
- **Administrador:** un panel nuevo con tablero (clases abiertas, solicitudes y avisos), alumnos con carga desde Excel y cambio de semestre en grupo, cursos con sus inscripciones, materias y prácticas, cuentas del personal, y carreras, periodos y laboratorios.
- **Lector de credencial:** el lector físico funciona dentro de la ventana que abre **Registrar con mi credencial**, en el formulario de registro. Antes, un campo oculto de la página principal tenía siempre el foco.
- **Listas en vivo:** las pantallas consultan la API cada pocos segundos, en lugar de recibir los cambios al instante.
- **Bitácora:** la llena el servidor (quién, qué, cuándo y desde dónde), en lugar de cada pantalla.
