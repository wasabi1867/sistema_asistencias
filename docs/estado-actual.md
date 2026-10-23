# Estado actual

Qué funciona, qué se verificó y qué falta. Se actualiza con cada cambio.

Última actualización: 22 de octubre de 2026.

## En resumen

**El sistema está publicado** desde el 22 de octubre de 2026 en https://laboratorios.soycorrecaminos.xyz, con la base de Hostinger (MariaDB 11.8.9) recién instalada: tiene los catálogos iniciales y una cuenta de administrador, pero todavía no tiene periodos, alumnos, materias, cursos ni cuentas de docentes y laboratoristas. Antes de usarlo en un laboratorio hay que cargar eso.

**En el instituto se sigue usando la versión anterior**, publicada en Vercel (https://asistencialaboratorios.vercel.app; su última publicación es del 15 de octubre de 2026), que guarda todo en Firebase. Sus datos son reales y siguen creciendo. El plan (decisión D12 de [decisiones.md](decisiones.md)):
- un piloto del sistema nuevo con un grupo real;
- luego un **día del cambio**, en que se deja la anterior y se importan sus datos.

Para eso hace falta lo de la sección "Para probar con datos reales" de [preguntas-para-el-instituto.md](preguntas-para-el-instituto.md), que se le pidió a Andrés el 22 de octubre. Los pasos están en [cambio-de-firebase.md](cambio-de-firebase.md).

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
- `scripts/importar-de-firebase.php` convierte los datos de Firebase a la base nueva.

## Qué se verificó

| Qué | Cómo | Cuándo |
|---|---|---|
| La API | 243 pruebas de PHPUnit contra una base real, en MariaDB 10.6 y 11.8 (la misma versión que Hostinger, también en la PC de desarrollo) y en MySQL 8.0 | 22 de octubre, en cada pull request |
| La base de Hostinger | Instalada con `scripts/instalar.php` por MySQL remoto con TLS verificado; los registros de cada tabla coinciden con una base local instalada igual, más el administrador | 22 de octubre |
| La conexión cifrada (`db_tls`) | Contra Hostinger: cifra con TLSv1.3, verifica el certificado y rechaza la conexión con una autoridad equivocada | 22 de octubre |
| El sitio publicado | Las páginas, la API leyendo la base desde el servidor, inicio y cierre de sesión con la cookie `secure; HttpOnly; SameSite=Lax`, y 14 pasos en Edge (computadora y teléfono) sin errores. `api/config.local.php` y `api/src/` no se pueden descargar | 22 de octubre |
| El CDN de Hostinger | No guarda copias: las páginas y `/api` salen `DYNAMIC`, y la API manda `Cache-Control: no-store` | 22 de octubre |
| Las redirecciones en Hostinger | `soycorrecaminos.xyz/laboratorios/...` y las direcciones viejas llevan al subdominio (LiteSpeed aplica el `.htaccess`) | 22 de octubre |
| Las reglas de la base | 35 intentos de romperlas, en MariaDB 11.5 y MySQL 8.0 | 21 de octubre |
| La importación de Firebase | Con datos de prueba inventados que imitan las colecciones de Firebase (`tests/api/firebase/`) | 22 de octubre |
| Las pantallas | `npm run lint` y `npm run build` sin errores | 22 de octubre, en cada pull request |
| Los flujos de las pantallas | 47 pasos en Edge, a lo ancho de una computadora y de un teléfono (390 px), contra el sitio compilado y la API real | 22 de octubre |
| Las redirecciones de las direcciones anteriores | Apache 2.4, en la raíz y en una subcarpeta | 22 de octubre |
| El contraste de los colores | Todos cumplen AA (4.5:1) en los dos temas | 22 de octubre |

La prueba de los 47 pasos no está en el repositorio: se corrió con un script aparte, descrito en el pull request #5.

## Qué no se ha verificado

- **Una clase completa en el sitio publicado:** abrir una clase, registrar a un alumno desde un teléfono y cerrarla. Se hará al cargar los primeros datos reales, para no dejar datos de prueba en la base de producción.
- **La importación con los datos reales de Firebase.** No se han descargado: falta la autorización del instituto.
- **Las reglas de seguridad de Firebase.** Por cómo funciona la aplicación de Vercel (escribe sin iniciar sesión y compara las contraseñas en el navegador), lo más probable es que dejen leer y escribir a cualquiera.
- **Un lector de pantalla** y un **lector de credencial físico**.
- **Un teléfono real.** Se probó con el ancho de un teléfono en el navegador de la computadora.

## Limitaciones conocidas

- Nada impide que alguien registre la entrada de un compañero con su número de control. El código de clase (`exigir_codigo_de_clase`) lo dificulta, pero está apagado.
- Las listas en vivo se consultan cada pocos segundos; no llegan al instante.
- En el teléfono, el nombre del laboratorio sale cortado en el encabezado de la página de registro.
- Next.js está en 14.2.35; falta actualizarlo.
- No hay pruebas automáticas de las pantallas en el repositorio.

## Pendientes, en orden

1. **Lo pedido a Andrés y al instituto** (sección "Para probar con datos reales" de [preguntas-para-el-instituto.md](preguntas-para-el-instituto.md)):
   - quién usa la aplicación de Vercel;
   - acceso de lectura a Firebase;
   - la autorización por escrito para usar los datos de los alumnos;
   - la lista de alumnos en Excel, las materias con sus horarios y el personal;
   - un piloto y la fecha del cambio.
2. **El piloto:** cargar el periodo AGO-DIC 2026 (del 1 de agosto al 31 de diciembre), la cuenta del docente, los alumnos del grupo, la materia, el curso y las inscripciones, y probar una clase completa en el laboratorio.
3. **Simular la importación de Firebase** en cuanto haya autorización y acceso de lectura, para conocer los datos y lo que habrá que revisar a mano.
4. **El día del cambio:** importar los datos de Firebase y retirar la aplicación de Vercel ([cambio-de-firebase.md](cambio-de-firebase.md)).
5. **Quitar la autorización de MySQL remoto** en hPanel mientras no haga falta correr scripts desde la computadora.
6. **El resto de las respuestas del instituto:** carreras, permisos por rol, uso fuera de clase y si el registro debe exigir la inscripción.
7. **Arreglos menores:** el nombre del laboratorio cortado en el teléfono y actualizar Next.js.
8. **Pasar la prueba de las pantallas al repositorio**, para que corra en cada pull request.

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
