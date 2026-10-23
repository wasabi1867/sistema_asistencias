# API

La API está en PHP, en la carpeta [`api/`](../api), y no usa paquetes de Composer: en Hostinger basta con subir los archivos. `npm run build` la copia a `out/api`, junto a las páginas.

## Cómo está organizada

Toda petición a `api/` la atiende [`api/index.php`](../api/index.php); el [`.htaccess`](../api/.htaccess) no deja servir ningún otro archivo. El código está en `api/src/`:

| Carpeta | Contenido |
|---|---|
| `Http/` | La petición, el enrutador, la lectura y validación de datos (`Entrada`), los errores con su estado HTTP |
| `Datos/` | La conexión a la base, las transacciones y la traducción de los rechazos de la base a mensajes claros |
| `Seguridad/` | La sesión, los roles, quién puede usar cada ruta (`Acceso`) y las contraseñas |
| `Auditoria/` | La bitácora y el registro de altas, cambios y bajas (`Cambios`) |
| `Modulos/` | Una clase por cosa que maneja el sistema (alumnos, cursos, clases...). Cada una registra sus rutas y las atiende |

Cosas que hace la base de la API para que ningún módulo tenga que acordarse:
- **Permisos:** cada ruta declara quién puede usarla al registrarse. Las reglas finas, como "el docente de ese curso", se revisan dentro de la ruta.
- **Transacciones:** lo que cambia datos corre en una transacción. Si algo falla a la mitad, no queda nada a medias.
- **Bitácora:** las altas, cambios y bajas hechas con `Cambios` quedan anotadas con quién, cuándo, desde qué dirección y qué campos cambiaron. Se guardan los nombres de los campos, no los valores, para que la bitácora nunca tenga contraseñas ni datos personales.
- **Errores:** un error inesperado responde 500 con un mensaje genérico y se anota completo en el registro de errores de PHP. Al cliente nunca le llega un detalle de la base.

Las cuentas de asistencia (lista de una clase, resumen de un curso) están en `Modulos/Clases/ListaDeAsistencia.php`, sin HTTP ni base de datos, y se prueban solas.

## Configuración

Los datos reales no van en git. `api/config.local.php` (copia de [`config.ejemplo.php`](../api/config.ejemplo.php)) o variables de entorno:

| Clave | Variable de entorno | Para qué |
|---|---|---|
| `db_host`, `db_port`, `db_name`, `db_user`, `db_pass` | `ASISTENCIAS_DB_HOST`... | Conexión a la base |
| `origenes_permitidos` | `ASISTENCIAS_ORIGENES_PERMITIDOS` | Sitios de otro dominio que pueden llamar a la API, separados por comas. Solo para desarrollo (`http://localhost:3000`) |
| `db_tls` | `ASISTENCIAS_DB_TLS` | Cifrar la conexión con la base. Hace falta al conectarse a la de Hostinger desde otra computadora (MySQL remoto); en el servidor, con `localhost`, no. Si se pide y la conexión no queda cifrada, no se conecta |
| `db_tls_ca` | `ASISTENCIAS_DB_TLS_CA` | Archivo de autoridades de certificados (por ejemplo, el `ca-bundle.crt` de Git) para comprobar el certificado del servidor. Sin él, la conexión va cifrada pero sin esa comprobación |
| `zona_horaria` | `ASISTENCIAS_ZONA_HORARIA` | Por omisión `America/Hermosillo` |
| `exigir_codigo_de_clase` | `ASISTENCIAS_EXIGIR_CODIGO_DE_CLASE` | Si el alumno tiene que escribir el código que se muestra en el aula al registrar su entrada. Por omisión, no |

## Convenciones

- **Rutas:** sustantivos en plural, como `/clases/12/cerrar`. Los alumnos se nombran por su matrícula (`/alumnos/23303080`) y los laboratorios por su clave (`/laboratorios/programacion/equipos`).
- **Datos:** JSON con nombres en camelCase (`horaEntrada`).
- **Fechas:**
  - Las fechas con hora salen con su desfase: `2026-10-21T14:05:00-07:00`.
  - Las fechas solas, como `2026-10-21`.
  - Las horas solas, como `14:05`.
- **Listas largas:** alumnos, asistencias, clases y bitácora se piden por páginas (`?pagina=2&porPagina=50`). Responden `{ alumnos: [...], total, pagina, porPagina }`.
- **Errores:** `{ "error": "mensaje en español" }`, que las pantallas muestran tal cual. Pueden traer además:
  - `campo`: el dato que hay que corregir, con 400.
  - `codigo`: un identificador fijo para que la pantalla reaccione, con 409 y otros. La tabla está más abajo.

| Estado | Cuándo |
|---|---|
| 200, 201, 204 | Bien: con datos, creado, o sin nada que devolver |
| 400 | Un dato no sirve. Trae `campo` |
| 401 | Sin sesión, o usuario y contraseña incorrectos |
| 403 | Con sesión, pero sin permiso |
| 404 | No existe |
| 409 | Choca con el estado actual: duplicado, equipo ocupado, clase cerrada, o un borrado del que dependen otros registros |
| 429 | Demasiados intentos fallidos desde la misma dirección |

## Sesión

El personal inicia sesión con `POST /sesion` `{usuario, contrasena, rol}`, donde `usuario` es el correo o la matrícula. PHP guarda la sesión en el servidor; el navegador solo lleva la cookie `asistencias_sesion`, que JavaScript no puede leer.
- La cookie es `SameSite=Lax`, así que no viaja en peticiones que vengan de otro sitio.
- La sesión vence tras 4 horas sin usarse.
- Una cuenta desactivada pierde la sesión de inmediato.
- Después de 10 intentos fallidos desde una dirección hay que esperar 15 minutos.

El alumno no inicia sesión ni da otros datos: registra su entrada con su número de control (`POST /registro/entrada`), y su nombre y su curso los busca la API. Después de 30 números de control o códigos de clase equivocados desde una dirección en 10 minutos, hay que esperar. El límite es alto porque todo un laboratorio sale a internet con la misma dirección.

## Rutas

Quién puede usar cada una. La tabla completa está fijada en [`tests/api/PermisosTest.php`](../tests/api/PermisosTest.php): si se cambia un permiso se cambia ahí también, y una ruta nueva que no esté en esa prueba la hace fallar.

| Valor | Significa |
|---|---|
| Pública | Sin sesión |
| Sesión | Administrador, laboratorista o docente |
| Personal | Administrador o laboratorista |
| Admin | Solo el administrador |

El reparto es provisional mientras el instituto no defina los permisos (pregunta 7 de [preguntas-para-el-instituto.md](preguntas-para-el-instituto.md)). Se usa el más restrictivo que deja operar.

### Sesión y catálogos

| Ruta | Quién | Qué hace |
|---|---|---|
| `POST /sesion` | Pública | Inicia sesión |
| `GET /sesion` | Sesión | La cuenta con sesión (401 si no hay) |
| `DELETE /sesion` | Pública | Cierra la sesión |
| `PUT /sesion/contrasena` | Sesión | Cambia la contraseña propia, dando la actual |
| `GET /laboratorios` | Pública | Los laboratorios activos (`?todos=1`, el administrador ve también los desactivados) |
| `POST /laboratorios`, `PUT /laboratorios/{id}` | Admin | Crea o edita (o desactiva) un laboratorio |
| `GET /carreras` | Sesión | Las carreras activas (`?todas=1` también las desactivadas) |
| `POST /carreras`, `PUT`, `DELETE /carreras/{id}` | Admin | Crea, edita o borra (si nadie la usa) |
| `GET /periodos`, `GET /periodos/actual` | Sesión | Los periodos, y el que contiene hoy |
| `POST /periodos`, `PUT`, `DELETE /periodos/{id}` | Admin | No se permiten periodos encimados |

### Personas

| Ruta | Quién | Qué hace |
|---|---|---|
| `GET /usuarios` | Sesión | El administrador ve todas las cuentas (`?rol=`, `?activo=`); los demás, los docentes y laboratoristas activos |
| `GET /usuarios/{id}` | Admin | Una cuenta |
| `POST /usuarios`, `PUT /usuarios/{id}` | Admin | Crea o edita (o desactiva) una cuenta. Nadie se desactiva a sí mismo y siempre queda un administrador |
| `PUT /usuarios/{id}/contrasena` | Admin | Restablece la contraseña de otro |
| `DELETE /usuarios/{id}` | Admin | Borra una cuenta sin historial |
| `GET /alumnos` | Sesión | Búsqueda por matrícula o nombre, con filtros (`carrera`, `semestre`, `grupo`, `turno`, `activo`), por páginas |
| `GET /alumnos/{matricula}` | Sesión | Un alumno y sus cursos |
| `POST /alumnos`, `PUT /alumnos/{matricula}` | Personal | Alta o edición (o desactivación) |
| `POST /alumnos/lote` | Personal | Carga hasta 2000: crea a los nuevos y actualiza a los existentes. Si un renglón tiene error no guarda ninguno, y dice cuáles fallaron |
| `POST /alumnos/cambio-masivo` | Personal | Cambia semestre, grupo o turno de los alumnos de una carrera y semestre |
| `DELETE /alumnos/{matricula}` | Admin | Borra un alumno sin historial |

### Materias, cursos y horario

| Ruta | Quién | Qué hace |
|---|---|---|
| `GET /materias`, `GET /materias/{id}` | Sesión | El catálogo de materias |
| `POST /materias`, `PUT`, `DELETE /materias/{id}` | Admin | Crea, edita o borra (si no tiene cursos) |
| `GET /materias/{id}/practicas` | Sesión | Las prácticas comunes y las del docente (el docente ve las suyas; `?docente=id`) |
| `POST /materias/{id}/practicas` | Admin, o un docente que imparta la materia | El administrador captura las comunes; el docente, las suyas |
| `PUT`, `DELETE /practicas/{id}` | Admin, o el docente si es suya | Una práctica ya usada en clase se desactiva en lugar de borrarse |
| `GET /cursos` | Sesión | Los del periodo actual (`?periodo=id`, `?periodo=todos`, `?docente=yo`, `?materia=`, `?laboratorio=`) |
| `GET /cursos/{id}` | Sesión | Un curso |
| `POST /cursos`, `PUT`, `DELETE /cursos/{id}` | Admin | Crea, edita o borra (si no tiene clases) |
| `GET /cursos/{id}/alumnos` | Sesión | Los inscritos |
| `POST /cursos/{id}/alumnos` | Personal o su docente | Inscribe una lista de hasta 500 matrículas y dice qué pasó con cada una |
| `DELETE /cursos/{id}/alumnos/{matricula}` | Personal o su docente | Da de baja del curso |
| `GET /cursos/{id}/asistencia` | Sesión | Asistencias, faltas y porcentaje de cada inscrito. No cuenta como falta lo anterior a su inscripción |
| `GET /horarios` | Sesión | El horario del periodo actual (`?laboratorio=`, `?docente=yo`, `?curso=`) |
| `POST /horarios`, `PUT`, `DELETE /horarios/{id}` | Personal | Arma el horario. No deja encimar el laboratorio, el curso ni el docente |

### Equipos

| Ruta | Quién | Qué hace |
|---|---|---|
| `GET /laboratorios/{clave}/equipos` | Pública | Cada equipo con su estado: `libre`, `ocupado` o `fuera_de_servicio`. Con sesión, además quién lo ocupa, sus notas y su reporte abierto |
| `POST /laboratorios/{clave}/equipos` | Admin | Agrega un equipo |
| `POST /laboratorios/{clave}/equipos/configurar` | Personal | Deja el laboratorio con PC-1 a PC-N; no retira un equipo en uso |
| `PUT /equipos/{id}` | Personal | Fuera de servicio, notas; la etiqueta solo el administrador |
| `DELETE /equipos/{id}` | Admin | Lo retira: se borra si nunca se usó y, si no, se desactiva |
| `GET /reportes-de-equipo` | Sesión | Las fallas (`?laboratorio=`, `?estado=abiertos`, `?equipo=`) |
| `POST /equipos/{id}/reportes` | Sesión | Reporta una falla y, salvo que se diga lo contrario, saca al equipo de servicio |
| `PUT /reportes-de-equipo/{id}` | Personal | Atiende la falla. Al resolverse la última, el equipo vuelve a servicio |

### Clases y asistencias

| Ruta | Quién | Qué hace |
|---|---|---|
| `GET /clases/abiertas?laboratorio=` | Pública | Qué clase hay en el laboratorio y si pide código (sin el código) |
| `GET /clases` | Sesión | Con filtros (`laboratorio`, `estado`, `tipo`, `desde`, `hasta`, `docente=yo`, `curso`) y `?conAsistentes=1` para los reportes |
| `GET /clases/{id}` | Sesión | La clase, quiénes entraron y, si es regular, la lista con las faltas. El código solo lo ve quien la lleva |
| `POST /clases` | Admin o su docente (regular); Personal (de invitado) | Abre una clase |
| `POST /clases/{id}/cerrar` | Quien lleva la clase | La cierra y registra la salida de todos. `{automatico: true}` cuando la cierra la pestaña del docente |
| `POST /clases/{id}/asistencias` | Quien lleva la clase | Registra a mano a un inscrito o, en una clase de invitado, a alguien de fuera |
| `POST /registro/entrada` | Pública | El alumno registra su entrada |
| `GET /asistencias/activas?laboratorio=` | Sesión | Quién está dentro ahora |
| `GET /asistencias` | Sesión | El historial, por páginas (`laboratorio`, `desde`, `hasta`, `alumno`, `carrera`, `curso`, `clase`, `fueraDeClase`) |
| `POST /asistencias` | Personal | Uso del laboratorio fuera de clase |
| `POST /asistencias/{id}/salida` | Quien lleva la clase (fuera de clase, el personal) | Registra la salida de alguien |
| `DELETE /asistencias/{id}` | Quien lleva la clase (fuera de clase, el personal) | Quita una entrada registrada por error. Queda en la bitácora |

"Quien lleva la clase" es el administrador, el laboratorista o, en una clase regular, el docente del curso.

### Solicitudes, avisos y consultas

| Ruta | Quién | Qué hace |
|---|---|---|
| `POST /solicitudes` | Pública | El alumno pide entrar a una clase (o usar el laboratorio fuera de clase) |
| `GET /solicitudes` | Sesión | Con filtros (`laboratorio`, `estado`, `clase`) |
| `POST /solicitudes/{id}/resolver` | Admin (de una clase); Personal (fuera de clase) | `aprobada_temporal` (entra ya), `aprobada_permanente` (entra y queda inscrito) o `rechazada` |
| `PUT /solicitudes/{id}` | Personal | Marca leída |
| `GET /avisos` | Personal | Los avisos, con el estado actual de lo que avisan |
| `PUT /avisos/{id}`, `POST /avisos/leidos` | Personal | Marca uno, o todos, como leídos |
| `GET /eventos` | Sesión | Talleres y actividades |
| `POST /eventos`, `PUT`, `DELETE /eventos/{id}` | Personal | |
| `GET /bitacora` | Personal | Con filtros (`desde`, `hasta`, `accion`, `entidad`, `usuario`, `laboratorio`, `q`), por páginas |
| `GET /estadisticas?laboratorio=` | Sesión | Los números de los tableros |

## Códigos para las pantallas

| `codigo` | Cuándo |
|---|---|
| `matricula_no_encontrada` | El número de control no existe (404) |
| `no_inscrito` | El alumno no está inscrito en el curso de la clase. Trae `claseId` y `materia` para ofrecerle pedir ingreso |
| `ya_registrado` | Ya está dentro de esta u otra clase |
| `equipo_ocupado`, `equipo_fuera_de_servicio` | La computadora elegida no se puede usar |
| `sin_clase`, `clase_cerrada` | No hay clase abierta, o la que se indicó ya se cerró |
| `solo_alumnos` | Alguien de fuera quiso entrar a una clase regular |
| `codigo_incorrecto` | El código de la clase no coincide (403) |
| `ya_salio`, `ya_resuelta` | La salida ya estaba registrada, o la solicitud ya se había resuelto |
| `laboratorio_ocupado`, `curso_abierto`, `docente_con_clase_abierta`, `periodo_terminado` | No se puede abrir la clase |
| `horario_encimado` | El horario choca con otro |

## Pruebas

Son 245 y corren contra una base real: cada corrida crea una base nueva igual que en producción (`db/esquema.sql` y `db/datos-iniciales.sql`), levanta la API con el servidor de PHP y la borra al terminar.

```bash
composer install
vendor/bin/phpunit
```

Por omisión se conectan a `127.0.0.1:3306` como `root` sin contraseña; se cambia con `ASISTENCIAS_PRUEBAS_DB_HOST`, `_PORT`, `_USER` y `_PASS`. En GitHub corren en MariaDB 10.6 y 11.8 y en MySQL 8.0. La 11.8 corre con el aislamiento READ COMMITTED, como la base de Hostinger; las otras dos, con REPEATABLE READ, el de siempre.

Para correr la API en una computadora:
1. Instalar la base con `php scripts/instalar.php`.
2. Crear el primer administrador con `php scripts/crear-admin.php`.
3. Levantar la API con `php -S localhost:8000 api/index.php`. Queda en `http://localhost:8000/api/`.

## Cambios a la base

[`db/esquema.sql`](../db/esquema.sql) siempre describe la base completa; [docs/modelo-de-datos.md](modelo-de-datos.md) explica cada tabla.

Un cambio a una base ya instalada va en un archivo nuevo de `db/migraciones/` y, en el mismo commit, también en `db/esquema.sql`. Los archivos se numeran en orden: el primero será `001-lo-que-cambia.sql`. Todavía no hay ninguno; la carpeta se crea con el primero. Como la base de producción ya está instalada (22 de octubre de 2026), **todo cambio a la base lleva su migración**. `php scripts/migrar.php` aplica los pendientes y anota cada uno en la tabla `migraciones`; `instalar.php` los da por aplicados porque el esquema ya los incluye.

Antes de aplicar una migración en producción se respalda la base. Un respaldo solo se restaura sobre el esquema de su misma versión: `scripts/restaurar.php` lo revisa. Ver "Respaldos" en [cambio-de-firebase.md](cambio-de-firebase.md#respaldos).
