# Estado actual

Qué funciona, qué se verificó y qué falta. Se actualiza con cada cambio.

Última actualización: 22 de octubre de 2026.

## En resumen

La versión nueva está completa en `main`, pero **no se ha publicado**. El sitio de Hostinger sigue siendo la versión anterior, que guarda todo en Firebase, así que hasta publicar no se pierde ningún dato.

Antes de publicar falta decidir qué pasa con los datos de Firebase: traerlos primero, o publicar sin ellos y traerlos después. Las dos formas, y lo que implica cada una, están en [cambio-de-firebase.md](cambio-de-firebase.md).

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
| La API | 238 pruebas de PHPUnit contra una base real, en MariaDB 10.6, 11.5 y 11.8 y en MySQL 8.0 | 22 de octubre, en cada pull request |
| Las reglas de la base | 35 intentos de romperlas, en MariaDB 11.5 y MySQL 8.0 | 21 de octubre |
| La importación de Firebase | Con datos de prueba inventados que imitan las colecciones de Firebase (`tests/api/firebase/`) | 22 de octubre |
| Las pantallas | `npm run lint` y `npm run build` sin errores | 22 de octubre, en cada pull request |
| Los flujos de las pantallas | 47 pasos en Edge, a lo ancho de una computadora y de un teléfono (390 px), contra el sitio compilado y la API real | 22 de octubre |
| Las redirecciones de las direcciones anteriores | Apache 2.4, en la raíz y en una subcarpeta | 22 de octubre |
| El contraste de los colores | Todos cumplen AA (4.5:1) en los dos temas | 22 de octubre |

La prueba de los 47 pasos no está en el repositorio: se corrió con un script aparte, descrito en el pull request #5.

## Qué no se ha verificado

- **Hostinger:** qué versión de PHP y qué motor de base da (MySQL o MariaDB), y si su servidor (LiteSpeed) aplica el `.htaccess` igual que Apache.
- **La importación con los datos reales de Firebase.** No se han descargado.
- **Las reglas de seguridad de Firebase.**
- **Un lector de pantalla** y un **lector de credencial físico**.
- **Un teléfono real.** Se probó con el ancho de un teléfono en el navegador de la computadora.

## Limitaciones conocidas

- Nada impide que alguien registre la entrada de un compañero con su número de control. El código de clase (`exigir_codigo_de_clase`) lo dificulta, pero está apagado.
- Las listas en vivo se consultan cada pocos segundos; no llegan al instante.
- En el teléfono, el nombre del laboratorio sale cortado en el encabezado de la página de registro.
- Next.js está en 14.2.35; falta actualizarlo.
- No hay pruebas automáticas de las pantallas en el repositorio.

## Pendientes, en orden

1. **Decidir qué pasa con los datos de Firebase y publicar** en Hostinger. Lo publica Andrés, con [cambio-de-firebase.md](cambio-de-firebase.md).
2. **Cambiar la contraseña de la base anterior** en el panel de Hostinger. Estuvo en el código y sigue en el historial de git.
3. **Importar los datos de Firebase**, si no se hizo al publicar.
4. **Respuestas del instituto** ([preguntas-para-el-instituto.md](preguntas-para-el-instituto.md)): carreras, periodos, de dónde sale la lista de alumnos, permisos por rol y si el registro debe exigir que el alumno esté inscrito.
5. **Arreglos menores:** el nombre del laboratorio cortado en el teléfono y actualizar Next.js.
6. **Pasar la prueba de las pantallas al repositorio**, para que corra en cada pull request.

## Historial

| Fecha | Pull request | Qué cambió |
|---|---|---|
| 21 de octubre de 2026 | — | Andrés sube el sistema original: pantallas en Next.js que guardaban todo en Firebase, y una API en PHP sin usar |
| 22 de octubre de 2026 | #1 | La API en PHP pide sesión y revisa el rol; la contraseña de la base sale del código |
| 22 de octubre de 2026 | #2 | Clases en la API; el servidor hace cumplir las reglas del registro |
| 22 de octubre de 2026 | #3 | Las pantallas dejan Firebase y usan la API; script de importación de Firebase |
| 22 de octubre de 2026 | #4 | Base rediseñada (de 12 a 19 tablas), API reescrita con 84 rutas y 238 pruebas, panel nuevo del administrador. Incluye #1 a #3 |
| 22 de octubre de 2026 | #5 | Rediseño de las pantallas: colores, registro en dos pasos, `/entrar` y direcciones nuevas |
| 22 de octubre de 2026 | #6 | Documentación al día: este documento y la guía para asistentes (`CLAUDE.md`) |
