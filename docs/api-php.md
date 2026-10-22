# API en PHP (`public/api/`)

La API corre en el mismo hosting que el frontend (Hostinger) y la publica el mismo build: lo que está en `public/api/` queda en `/laboratorios/api/`.

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

**Para el frontend:** si se sirve desde el mismo dominio que la API, `fetch` ya manda la cookie y no hay que cambiar nada. Si se sirve desde otro dominio (por ejemplo `npm run dev` en `localhost:3000` contra la API en otro lado), hay que agregar `credentials: 'include'` en `fetchAPI` y poner ese dominio en `origenes_permitidos` de `config.local.php`.

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
| `POST asistencias.php?action=entrada` | ✓² | ✓ | ✓ | ✓ |
| `POST solicitudes.php` | ✓ | ✓ | ✓ | ✓ |
| Consultar alumnos, docentes, laboratoristas, materias, asistencias, histórico, solicitudes, estadísticas | | ✓ | ✓ | ✓ |
| Registrar salida, quitar una asistencia, crear una notificación | | ✓ | ✓ | ✓ |
| Crear o borrar prácticas | | ✓³ | | ✓ |
| Dar de alta o editar alumnos, cambiar el estado de un equipo | | | ✓ | ✓ |
| Borrar alumnos; guardar o borrar docentes, laboratoristas y materias | | | | ✓ |
| Resolver solicitudes; consultar y marcar notificaciones | | | | ✓ |

1. Sin sesión se ve si un equipo está libre, pero no quién lo ocupa ni sus notas. Solo el personal inicializa los equipos de un laboratorio nuevo.
2. Los datos del alumno o docente salen de la base, no de lo que mande el navegador. El equipo tiene que existir; ya no se crea al registrarse.
3. Solo en las materias que imparte.

## Pruebas

Corren contra una base MariaDB real: cada corrida crea una base nueva con `schema.sql`, levanta la API con el servidor de PHP y la borra al terminar.

```bash
composer install
vendor/bin/phpunit
```

Por omisión se conectan a `127.0.0.1:3306` como `root` sin contraseña. Se cambia con `ASISTENCIAS_PRUEBAS_DB_HOST`, `_PORT`, `_USER` y `_PASS`. En GitHub corren solas con cada cambio a la API (`.github/workflows/api-php.yml`).

Para correr la API en local: crear la base con `schema.sql`, hacer `config.local.php` y `php -S localhost:8000 -t public`. La API queda en `http://localhost:8000/api/`.

## Lo que falta

**El frontend todavía no usa esta API.** Todas las pantallas leen y escriben directo en Firebase (Firestore). `src/lib/api.ts` existe, pero ningún componente lo llama. Para dejar Firebase, la API tiene que cubrir lo que hoy está en estas colecciones:

| Colección de Firestore | En la API PHP |
|---|---|
| `Alumnos`, `Docentes`, `Laboratoristas`, `Materias`, `SolicitudesIngreso`, `NotificacionesAdmin` | Hay ruta |
| `Asistencias`, `HistoricalAttendance` | `asistencias.php` (activas e histórico) |
| `Numero de equipos` | `equipos.php` |
| `Administrador` | Tabla `administradores`, sin ruta para administrarla |
| `logs` | Tabla `logs`, sin ruta ni registro automático |
| `EstadoClase`, `EstadoClaseInvitado` | **Falta.** Abrir y cerrar la clase de un laboratorio, con su materia, docente y práctica |
| `AsistenciasInvitado` | Parcial: `tipo_usuario = 'invitado'` en `asistencias` |
| `ClassInformation`, `EventosLaboratorio` | **Falta.** Por revisar qué guardan |

**Reglas que hoy solo revisa el navegador** y la API debería hacer cumplir (siguiente cambio):

- Solo se registra entrada si hay una clase abierta en ese laboratorio, y el alumno está inscrito en su materia.
- Un alumno no se registra dos veces ni en dos laboratorios a la vez.
- Un equipo ocupado no se puede volver a asignar.
- Entrada y salida tocan varias tablas sin transacción: si algo falla a la mitad, quedan a medias.
- Límite de intentos de inicio de sesión.
