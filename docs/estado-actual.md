# Estado actual

Qué funciona, qué se verificó y qué falta. Se actualiza con cada cambio.

Última actualización: 24 de octubre de 2026 (respaldos automáticos y documentos para el instituto).

## En resumen

**El sistema está publicado** desde el 22 de octubre de 2026 en https://laboratorios.soycorrecaminos.xyz, con la base de Hostinger (MariaDB 11.8.9) recién instalada: tiene los catálogos iniciales y una cuenta de administrador, pero todavía no tiene periodos, alumnos, materias, cursos ni cuentas de docentes y laboratoristas. Antes de usarlo en un laboratorio hay que cargar eso.

**En el instituto se sigue usando la versión anterior**, publicada en Vercel (https://asistencialaboratorios.vercel.app; su última publicación es del 15 de octubre de 2026), que guarda todo en Firebase. Sus datos son reales y siguen creciendo. El plan (decisión D12 de [decisiones.md](decisiones.md)):
- un piloto del sistema nuevo con un grupo real;
- luego un **día del cambio**, en que se deja la anterior y se importan sus datos.

Para eso hace falta lo de la sección "Para probar con datos reales" de [preguntas-para-el-instituto.md](preguntas-para-el-instituto.md), que se le pidió a Andrés el 22 de octubre. Para entregarlo al instituto se prepararon el 23 de octubre tres documentos, que están fuera del repositorio (D17 de [decisiones.md](decisiones.md)):
- una presentación del sistema en PDF;
- el oficio de autorización para usar los datos de los alumnos, dirigido al jefe de división de Sistemas Computacionales y firmado por Andrés;
- el cuestionario en Word.

Los pasos del cambio están en [cambio-de-firebase.md](cambio-de-firebase.md).

**La base se respalda cada semana**, con copia cifrada en Google Drive ("Respaldos" en [cambio-de-firebase.md](cambio-de-firebase.md#respaldos)).

En la carpeta del subdominio había una página de "Próximamente" y una compilación vieja del sistema original en `frontend/`, con su API sin protección. Las dos se retiraron al publicar, con respaldo.

## Qué funciona

**Alumno** (sin cuenta, en `/`):
- Registra su entrada con su número de control y la computadora que ocupa, o que trae la suya. No escribe ningún otro dato: su nombre y su carrera los toma la base.
- Si no está inscrito en el curso de la clase, puede pedir permiso para entrar.
- Puede usar el lector de credencial en lugar de escribir el número.

**Docente** (`/docente`):
- Abre la clase de uno de sus cursos con una práctica, ve en vivo quién entra y quién falta, registra a alguien a mano y la cierra (la salida de todos queda registrada).
- Exporta la hoja de registro en PDF o Excel.
- Lleva sus prácticas, consulta sus clases anteriores y la asistencia de cada curso.

**Laboratorista** (`/laboratorio` y `/clase-invitada`):
- Quién está dentro y en qué computadora, con el mapa de equipos.
- Equipos: fuera de servicio, notas y reportes de falla con su historial.
- Horario del laboratorio, clases de maestro invitado y eventos.
- Historial, reportes y bitácora.
- Finalizar una clase que el docente dejó abierta.

**Administrador** (`/admin`):
- Tablero con lo pendiente: clases abiertas, solicitudes de ingreso y avisos.
- Alumnos: alta, edición, carga desde Excel y cambio de semestre o grupo en bloque.
- Cursos e inscripciones (pegando una lista de números de control), materias y prácticas comunes.
- Cuentas del personal, carreras, periodos y laboratorios.

**Por debajo:**
- La API revisa la sesión y el rol en cada una de sus 84 rutas.
- Las reglas del registro (clase abierta, alumno inscrito, computadora libre, sin entrar dos veces) las hace cumplir el servidor, y la base las garantiza aunque lleguen dos registros a la vez.
- Todo cambio queda en la bitácora, con quién, cuándo y desde dónde.
- GitHub revisa cada hora que el sitio, la API y la base respondan, y que el certificado no esté por vencer. Si algo falla, avisa por correo.
- `scripts/importar-de-firebase.php` convierte los datos de Firebase a la base nueva.
- `scripts/exportar-datos.php` respalda la base y `scripts/restaurar.php` la restaura, revisando antes que el respaldo esté completo y sea de la misma versión del esquema.

## Qué se verificó

| Qué | Cómo | Cuándo |
|---|---|---|
| La API | 245 pruebas de PHPUnit contra una base real, en MariaDB 10.6 y 11.8 (la misma versión que Hostinger, también en la PC de desarrollo; la 11.8 con el aislamiento READ COMMITTED de Hostinger) y en MySQL 8.0 | 23 de octubre, en cada pull request |
| La base de Hostinger | Instalada con `scripts/instalar.php` por MySQL remoto con TLS verificado; los registros de cada tabla coinciden con una base local instalada igual, más el administrador | 22 de octubre |
| Los respaldos de la base | Primer respaldo de producción con `scripts/exportar-datos.php` (47 renglones de 19 tablas), restaurado con `scripts/restaurar.php` en una base vacía de la computadora con el mismo resultado. Las pruebas cubren respaldos cortados o de otra versión del esquema | 23 de octubre |
| La copia cifrada de los respaldos | Subida a Google Drive con rclone `crypt` y comprobada con `rclone cryptcheck`. Se recuperó desde cero, con solo las claves del gestor de contraseñas, y salió idéntica | 24 de octubre |
| La conexión cifrada (`db_tls`) | Contra Hostinger: cifra con TLSv1.3, verifica el certificado y rechaza la conexión con una autoridad equivocada | 22 de octubre |
| El sitio publicado | Las páginas, la API leyendo la base desde el servidor, inicio y cierre de sesión con la cookie `secure; HttpOnly; SameSite=Lax`, y 14 pasos en Edge (computadora y teléfono) sin errores. `api/config.local.php` y `api/src/` no se pueden descargar | 22 de octubre |
| Next.js 16.4.1 | `npm audit --omit=dev` sin avisos de Next.js (quedan 3 moderados de Tailwind, que solo corre al compilar). Los 47 pasos pasan con la CSP aplicada, compilado con webpack. Falta publicarlo | 24 de octubre |
| Las cabeceras de seguridad (CSP, HSTS, Referrer-Policy, Permissions-Policy) | Apache 2.4 acepta el `.htaccess` y las manda. Los 47 pasos pasan con la CSP aplicada. Con el hash del script del tema roto a propósito, la prueba falla con 10 errores en la consola. Falta publicarlas | 24 de octubre |
| El CDN de Hostinger | No guarda copias: las páginas y `/api` salen `DYNAMIC`, y la API manda `Cache-Control: no-store` | 22 de octubre |
| Las redirecciones en Hostinger | `soycorrecaminos.xyz/laboratorios/...` y las direcciones viejas llevan al subdominio (LiteSpeed aplica el `.htaccess`) | 22 de octubre |
| Las reglas de la base | 35 intentos de romperlas, en MariaDB 11.5 y MySQL 8.0 | 21 de octubre |
| La importación de Firebase | Con datos de prueba inventados que imitan las colecciones de Firebase (`tests/api/firebase/`) | 22 de octubre |
| Las pantallas | `npm run lint` y `npm run build` sin errores | 22 de octubre, en cada pull request |
| Los flujos de las pantallas | 47 pasos en el navegador (`npm run prueba:pantallas`), a lo ancho de una computadora y de un teléfono (390 px), contra el sitio compilado y la API real. Pasan en Edge con jsPDF 4, incluida la hoja de registro en PDF. En GitHub corren en Chrome en cada pull request | 23 de octubre |
| Las dependencias de las pantallas | `npm audit --omit=dev`: quedan solo avisos de Next.js (de su servidor, que no se usa) y de PostCSS y Tailwind (solo corren al compilar) | 23 de octubre |
| El encabezado del registro en el teléfono | El nombre de los cuatro laboratorios se lee completo a 360 y 390 px | 23 de octubre |
| La vigilancia del sitio | Sus tres revisiones, a mano contra el sitio publicado: la página responde, la API devuelve 4 laboratorios y el certificado vence el 6 de diciembre de 2026. Una dirección inexistente hace fallar la revisión | 24 de octubre |
| La publicación desde el repositorio | `scripts/publicar.php`: lista el servidor por FTPS, se niega a subir si `out/api/config.local.php` existe, y `subir --simular` cuenta 102 archivos (3.5 MB) sin subir nada. `npm run prueba:produccion` pasa contra el sitio publicado, en las páginas públicas | 24 de octubre |
| Las redirecciones de las direcciones anteriores | Apache 2.4, en la raíz y en una subcarpeta | 22 de octubre |
| El contraste de los colores | Todos cumplen AA (4.5:1) en los dos temas | 22 de octubre |


## Qué no se ha verificado

- **Una clase completa en el sitio publicado:** abrir una clase, registrar a un alumno desde un teléfono y cerrarla. Se hará al cargar los primeros datos reales, para no dejar datos de prueba en la base de producción.
- **La importación con los datos reales de Firebase.** No se han descargado: falta la autorización del instituto.
- **Las reglas de seguridad de Firebase.** Por cómo funciona la aplicación de Vercel (escribe sin iniciar sesión y compara las contraseñas en el navegador), lo más probable es que dejen leer y escribir a cualquiera.
- **Un lector de pantalla** y un **lector de credencial físico**.
- **Un teléfono real.** Se probó con el ancho de un teléfono en el navegador de la computadora.

## Limitaciones conocidas

- Nada impide que alguien registre la entrada de un compañero con su número de control. El código de clase (`exigir_codigo_de_clase`) lo dificulta, pero está apagado.
- Las listas en vivo se consultan cada pocos segundos; no llegan al instante.

## Pendientes, en orden

1. **Lo pedido a Andrés y al instituto** (sección "Para probar con datos reales" de [preguntas-para-el-instituto.md](preguntas-para-el-instituto.md)):
   - entregar al instituto la presentación, el oficio y el cuestionario (listos desde el 23 de octubre);
   - quién usa y quién controla la aplicación de Vercel y Firebase (Andrés no los controla: los pide a quien los maneja);
   - acceso de lectura a Firebase, que da esa persona;
   - la autorización por escrito para usar los datos de los alumnos;
   - la lista de alumnos en Excel, las materias con sus horarios y el personal;
   - un piloto y la fecha del cambio.
2. **Respaldar la base cada semana** mientras se use, y antes del piloto y del día del cambio ("Respaldos" en [cambio-de-firebase.md](cambio-de-firebase.md#respaldos)). El primero se hizo el 23 de octubre. Desde el 24, una tarea programada en la computadora de David lo hace cada viernes, con copia cifrada en Google Drive. Solo hay que autorizar la IP en MySQL remoto ese rato y quitarla después.
3. **El piloto:** cargar el periodo AGO-DIC 2026 (del 1 de agosto al 31 de diciembre), la cuenta del docente, los alumnos del grupo, la materia, el curso y las inscripciones, y probar una clase completa en el laboratorio. Los pasos están en [guia-del-administrador.md](guia-del-administrador.md). Andrés va a cargar a los alumnos, al menos los de Sistemas, y necesita su propia cuenta de administrador: falta su correo para crearla.
4. **Simular la importación de Firebase** en cuanto haya autorización y acceso de lectura, para conocer los datos y lo que habrá que revisar a mano.
5. **El día del cambio:** importar los datos de Firebase y retirar la aplicación de Vercel ([cambio-de-firebase.md](cambio-de-firebase.md)).
6. **Quitar la autorización de MySQL remoto** en hPanel mientras no haga falta correr scripts desde la computadora. Para el respaldo semanal se autoriza la IP solo ese rato.
7. **El resto de las respuestas del instituto:** carreras, permisos por rol, uso fuera de clase y si el registro debe exigir la inscripción.

## Historial

| Fecha | Pull request | Qué cambió |
|---|---|---|
| 15 de octubre de 2026 | — | Última publicación en Vercel de la versión original, con Firebase (https://asistencialaboratorios.vercel.app). Es la que usa el instituto; puede estar en uso desde antes |
| 21 de octubre de 2026 | — | Andrés sube el sistema original a este repositorio: pantallas en Next.js que guardaban todo en Firebase, y una API en PHP sin usar |
| 22 de octubre de 2026 | #1 | La API en PHP pide sesión y revisa el rol; la contraseña de la base sale del código |
| 22 de octubre de 2026 | #2 | Clases en la API; el servidor hace cumplir las reglas del registro |
| 22 de octubre de 2026 | #3 | Las pantallas dejan Firebase y usan la API; script de importación de Firebase |
| 22 de octubre de 2026 | #4 | Base rediseñada (de 12 a 19 tablas), API reescrita con 84 rutas y 238 pruebas, panel nuevo del administrador. Incluye #1 a #3 |
| 22 de octubre de 2026 | #5 | Rediseño de las pantallas: colores, registro en dos pasos, `/entrar` y direcciones nuevas |
| 22 de octubre de 2026 | #6 | Documentación al día: este documento y la guía para asistentes (`CLAUDE.md`) |
| 22 de octubre de 2026 | #7 | Conexión cifrada con la base para los scripts, redirección al subdominio, corrección del BOM en `crear-admin.php`, las pruebas que fallaban de 5 p.m. a medianoche por calcular "hoy" en UTC, y la guía de publicación con el plan real. **Publicación en Hostinger** |
| 22 de octubre de 2026 | #8 | La aplicación anterior sigue en uso en Vercel: plan del día del cambio, lo pedido al instituto para el piloto, y el registro de decisiones ([decisiones.md](decisiones.md)) |
| 22 de octubre de 2026 | #9 | Guía del administrador y plantilla de Excel para cargar alumnos |
| 23 de octubre de 2026 | #10 | Andrés no controla Vercel ni Firebase: lo de la aplicación anterior se pide a quien la maneja |
| 23 de octubre de 2026 | #11 | Respaldos de la base: el respaldo lee todo en un mismo instante y se restaura con `scripts/restaurar.php`; la integración continua prueba con el aislamiento de Hostinger (READ COMMITTED) |
| 23 de octubre de 2026 | #12 | jsPDF 4 y demás dependencias con fallas conocidas; el nombre del laboratorio ya no se corta en el teléfono |
| 23 de octubre de 2026 | #13 | La prueba de las pantallas (47 pasos en el navegador) entra al repositorio y corre en cada pull request |
| 24 de octubre de 2026 | #14 | Respaldos automáticos cada viernes con copia cifrada en Google Drive; documentos para el instituto; datos del instituto y de quién es quién |
