# API en PHP (`public/api/`)

La API corre en el mismo hosting que el frontend (Hostinger) y la publica el mismo build: lo que está en `public/api/` queda en la carpeta `api/` junto a las páginas.

## Configuración

Los datos de conexión **no van en git**. `config.php` los lee de variables de entorno o de `public/api/config.local.php`, que git ignora.

En Hostinger, una sola vez:

1. Copiar `config.ejemplo.php` como `config.local.php` en la carpeta `api/` del sitio, con el administrador de archivos, y llenar los datos de la base.
2. Al publicar una versión nueva, no borrar `config.local.php`. Si se sube la carpeta completa, se vuelve a copiar.

> La contraseña anterior de la base estuvo publicada en el repositorio, así que hay que cambiarla en el panel de Hostinger. Quitarla del código no la borra del historial de git.

## Sesiones

El personal inicia sesión con `POST auth.php` (mismo cuerpo y misma respuesta que antes). PHP guarda la sesión en el servidor y el navegador solo lleva una cookie `asistencias_sesion`, que JavaScript no puede leer.

| Petición | Qué hace |
|---|---|
| `POST auth.php` `{email, password, role}` | Inicia sesión. `email` puede ser el correo o la matrícula (docente y laboratorista) |
| `GET auth.php` | Quién tiene la sesión abierta (401 si nadie) |
| `DELETE auth.php` | Cierra la sesión |

La sesión vence tras 4 horas sin usarse. El alumno no inicia sesión.

**Para el frontend:** `src/lib/api.ts` manda la cookie en cada petición. Si las páginas se sirven desde otro origen que la API (por ejemplo `npm run dev` en `localhost:3000` y la API en `localhost:8000`), ese origen va en `origenes_permitidos` de `config.local.php` y la dirección de la API en `NEXT_PUBLIC_API_URL`.

**Contraseñas:** se guardan cifradas (`password_hash`). Una cuenta que todavía tenga la contraseña en texto plano puede entrar una vez; en ese momento se cifra. Una cuenta nueva necesita contraseña de al menos 8 caracteres, y editar una cuenta sin mandar contraseña conserva la que tenía. Antes se ponía `123456`.

**Primer administrador:** el hash que trae `schema.sql` no corresponde a `Admin123!`, como dice el comentario, así que con esos datos nadie entra. Para crear un administrador o cambiarle la contraseña:

```bash
php scripts/crear-admin.php admin@puertopenasco.tecnm.mx "Administrador General"
php scripts/crear-admin.php --solo-hash   # solo imprime el hash, para pegarlo en phpMyAdmin
```

## Quién puede usar cada ruta

Provisional, mientras el instituto no defina los permisos. Se usa el reparto más restrictivo que deja operar: todos consultan, el laboratorista además edita alumnos y equipos, y solo el administrador maneja cuentas y materias y borra. `tests/api/PermisosTest.php` fija esta tabla: si se cambia un permiso, se cambia ahí también.

| Ruta | Sin sesión | Docente | Laboratorista | Admin |
|---|:-:|:-:|:-:|:-:|
| `GET equipos.php` | ✓¹ | ✓ | ✓ | ✓ |
| `GET clases.php?abiertas=1` (qué clase hay en el laboratorio) | ✓ | ✓ | ✓ | ✓ |
| `POST asistencias.php?action=entrada` | ✓² | ✓ | ✓ | ✓ |
| `POST solicitudes.php` | ✓ | ✓ | ✓ | ✓ |
| Consultar alumnos, docentes, laboratoristas, materias, clases, asistencias, histórico, solicitudes, eventos, estadísticas | | ✓ | ✓ | ✓ |
| Registrar salida, quitar una asistencia, crear una notificación | | ✓ | ✓ | ✓ |
| Cerrar una clase | | ✓³ | ✓ | ✓ |
| Abrir una clase regular; crear, editar o borrar prácticas | | ✓³ | | ✓ |
| Abrir una clase de maestro invitado; guardar o borrar eventos | | | ✓ | ✓ |
| Dar de alta o editar alumnos, cambio masivo de semestre o grupo, cambiar el estado de un equipo | | | ✓ | ✓ |
| Borrar alumnos; guardar o borrar docentes, laboratoristas, administradores y materias; agregar o quitar equipos | | | | ✓ |
| Consultar la bitácora y los avisos de equipos; configurar los equipos del laboratorio | | | ✓ | ✓ |
| Resolver solicitudes; consultar y marcar los demás avisos | | | | ✓ |

1. Sin sesión se ve si un equipo está libre, pero no quién lo ocupa ni sus notas. Solo el personal inicializa los equipos de un laboratorio nuevo.
2. Los datos del alumno o docente salen de la base, no de lo que mande el navegador. Ver las reglas abajo.
3. Solo las de sus materias.

## Clases y registro de entrada

Una **clase** (`clases.php`, tabla `clases`) es lo que antes eran `EstadoClase`, `EstadoClaseInvitado` y `ClassInformation` en Firebase. Hay dos tipos:

- **Regular:** la abre el docente con una de sus materias y una práctica. Solo entran los alumnos inscritos en esa materia (`alumnos.materias`).
- **De maestro invitado:** la abre el laboratorista o el administrador escribiendo docente, materia y práctica. Entra cualquiera, incluso gente de fuera con su nombre.

Por laboratorio hay a lo más una clase abierta de cada tipo; si están las dos, el registro va a la de invitado, como hacía la página. Al cerrar la clase se registra la salida de todos, se liberan sus equipos y se guarda el total. El cierre queda como `normal`, `externo` (la cerró el personal, no el docente: su pantalla lo consulta para enterarse) o `automatico` (el navegador del docente la cerró al cerrar la pestaña).

**Reglas del registro de entrada**, que antes solo revisaba el navegador:

- Tiene que haber una clase abierta en el laboratorio.
- En la clase regular, el alumno debe estar inscrito en la materia. Si no lo está, la respuesta trae `codigo: "no_inscrito"` para que la página le ofrezca pedir ingreso.
- Una persona solo está en una clase a la vez (`codigo: "ya_registrado"`). La base lo garantiza con un índice único aunque lleguen dos registros al mismo tiempo.
- El equipo debe existir, estar en servicio y libre (`codigo: "equipo_ocupado"`). Muchos pueden usar `personal`, su equipo propio.
- Entrada, salida y cierre de clase son transacciones: si algo falla a la mitad, no queda nada a medias.

**Solicitudes de ingreso:** el alumno no inscrito la manda desde la clase abierta. El administrador la aprueba por esa vez (`aprobado_temporal`: queda registrado en la clase con el equipo que eligió, o con el suyo propio si ya se ocupó), inscribiéndolo en la materia (`aprobado_permanente`) o la rechaza.

**Bitácora:** la llena el servidor, con quién, desde qué dirección y en qué laboratorio: inicio y cierre de sesión, intentos fallidos, abrir y cerrar clases, solicitudes resueltas, borrados, cambios masivos y equipos. Después de 10 intentos fallidos de inicio de sesión desde una misma dirección hay que esperar 15 minutos.

**Zona horaria:** las horas se guardan en la de Puerto Peñasco (`America/Hermosillo`, UTC-7 sin horario de verano), aunque el servidor esté en otra. Se cambia con `zona_horaria` en `config.local.php`.

## Pruebas

Corren contra una base MariaDB real: cada corrida crea una base nueva con `schema.sql` y las migraciones, levanta la API con el servidor de PHP y la borra al terminar.

```bash
composer install
vendor/bin/phpunit
```

Por omisión se conectan a `127.0.0.1:3306` como `root` sin contraseña. Se cambia con `ASISTENCIAS_PRUEBAS_DB_HOST`, `_PORT`, `_USER` y `_PASS`. En GitHub corren solas con cada cambio a la API (`.github/workflows/api-php.yml`).

Para correr la API en local: crear la base con `schema.sql`, hacer `config.local.php`, aplicar las migraciones (`php scripts/migrar.php`) y `php -S localhost:8000 -t public`. La API queda en `http://localhost:8000/api/`.

## Cambios a la base de datos (migraciones)

`schema.sql` es la base tal como se creó en Hostinger. Cada cambio posterior va en un archivo de `db/migraciones/`, numerado, que se aplica una sola vez y en orden:

```bash
php scripts/migrar.php   # aplica las que falten y las anota en la tabla `migraciones`
```

Sin terminal en el servidor, se pega cada archivo pendiente en phpMyAdmin. Una base nueva se crea con `schema.sql` y luego todas las migraciones.

| Migración | Qué agrega |
|---|---|
| `001-clases-solicitudes-y-bitacora` | Tablas `clases` y `eventos_laboratorio`. Columnas: `clase_id` en asistencias, histórico y solicitudes; nombre y apellido en el histórico; materia y equipo en solicitudes; laboratorio e IP en la bitácora; matrícula y apellido en administradores; turno en materias. Una persona solo puede estar en una asistencia activa |
| `002-practicas-con-fecha` | Fecha de cada práctica, que el docente captura al crearla |

## El frontend y Firebase

Todas las pantallas usan esta API a través de `src/lib/api.ts`, que devuelve los datos con los mismos nombres de campo que tenían los documentos de Firestore. Firebase ya no se usa. Los pasos para publicar el cambio y copiar los datos de Firebase están en [cambio-de-firebase.md](cambio-de-firebase.md) (`scripts/importar-de-firebase.php`).

| Colección de Firestore | En la API PHP |
|---|---|
| `Alumnos`, `Docentes`, `Laboratoristas`, `Administrador`, `Materias` (y sus `Practicas`) | `alumnos.php`, `docentes.php`, `laboratoristas.php`, `administradores.php`, `materias.php` |
| `EstadoClase`, `EstadoClaseInvitado`, `ClassInformation` | `clases.php` |
| `Asistencias`, `AsistenciasInvitado`, `HistoricalAttendance` | `asistencias.php` (activas e histórico) |
| `Numero de equipos` | `equipos.php` |
| `SolicitudesIngreso`, `NotificacionesAdmin` | `solicitudes.php`, `notificaciones.php` |
| `EventosLaboratorio` | `eventos.php` |
| `logs` | `bitacora.php` (se llena sola) |
