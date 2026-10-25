# Sistema de control de asistencias a laboratorios — ITSPP

Registra las entradas de los alumnos a los laboratorios de cómputo del Instituto Tecnológico Superior de Puerto Peñasco y el equipo que ocupa cada uno:

- El docente abre la clase de su materia.
- El alumno registra su entrada con su número de control, sin iniciar sesión, e indica su computadora o que trae la suya.
- Al cerrar la clase se registra la salida de todos.

El sistema lleva además las prácticas por materia, las clases de maestro invitado, las solicitudes de ingreso de alumnos no inscritos, el estado de los equipos y una bitácora.

Lo usan cuatro tipos de persona: alumno (sin cuenta), docente, laboratorista y administrador.

**Estado:** publicado en https://laboratorios.soycorrecaminos.xyz desde el 22 de octubre de 2026. Falta cargar los datos reales para empezar a usarlo. En el instituto se sigue usando la versión anterior (https://asistencialaboratorios.vercel.app, con Firebase) hasta el día del cambio. Qué funciona, qué se verificó y qué falta: [docs/estado-actual.md](docs/estado-actual.md).

## Cómo está hecho

| Parte | Tecnología | Carpeta |
|---|---|---|
| Pantallas | Next.js 16 (export estático, compilado con webpack), React 18, TypeScript, Tailwind, shadcn/ui | `src/` |
| API | PHP 8.1 o más nuevo, sin framework ni paquetes | `api/` |
| Base de datos | MariaDB 11.8 en producción; también funciona en MySQL 8 y MariaDB 10.6 o más nuevo | `db/` |
| Pruebas de la API | PHPUnit, contra una base real | `tests/api/` |
| Scripts | Instalar la base, aplicar cambios, primer administrador, importar de Firebase, respaldar y restaurar | `scripts/` |

Todo se publica junto en Hostinger: `npm run build` deja en `out/` las páginas y la carpeta `api/`.

## Correrlo en una computadora

Se necesita:
- Node 20 o más nuevo.
- PHP 8.3 (la versión con la que corren las pruebas) con `pdo_mysql`.
- Composer.
- MySQL o MariaDB.

1. **Configuración de la API.** Copiar `api/config.ejemplo.php` como `api/config.local.php` y llenar los datos de una base vacía. Git ignora este archivo. Para usar la API desde `npm run dev`, poner `http://localhost:3000` en `origenes_permitidos`.

2. **Base de datos y primer administrador.** El segundo script pide la contraseña:

   ```bash
   php scripts/instalar.php
   php scripts/crear-admin.php admin@puertopenasco.tecnm.mx "Nombre" "Apellido"
   ```

3. **API y pantallas.** Cada una corre en su propia terminal:

   ```bash
   php -S localhost:8000 api/index.php
   ```

   ```bash
   npm install
   echo NEXT_PUBLIC_API_URL=http://localhost:8000/api > .env.local
   npm run dev
   ```

   El sitio queda en http://localhost:3000.

## Pruebas

```bash
composer install
vendor/bin/phpunit   # API; docs/api.md explica cómo apuntar a otra base
npm run lint         # pantallas
npm run build        # compila las pantallas y revisa los tipos (el lint va aparte)
npm run prueba:pantallas   # recorre el sitio compilado en un navegador; ver docs/pantallas.md
```

En GitHub corren solas en cada pull request (`.github/workflows/`). Las de la API corren en MariaDB 10.6 y 11.8 (esta con el aislamiento READ COMMITTED, como en Hostinger) y en MySQL 8.0. La de las pantallas corre en Chrome, con MariaDB 11.8. Además, cada hora se revisa que el sitio publicado responda (`.github/workflows/vigilancia.yml`).

## Publicar en Hostinger

```bash
npm ci
npm run build
```

Se sube `out/` por FTP cifrado con `scripts/publicar.php`, que antes respalda el sitio publicado y nunca pisa el `api/config.local.php` del servidor. Después, `npm run prueba:produccion` revisa el sitio publicado. Los pasos, la conexión y cómo se trabaja con la base desde la computadora (no hay SSH) están en [docs/cambio-de-firebase.md](docs/cambio-de-firebase.md), pasos 6 y 7.

## Forma de trabajo

- Ningún cambio va directo a `main`: cada uno va en una rama con su pull request, que se acepta cuando pasan las pruebas de GitHub.
- Commits en español, en infinitivo, describiendo el cambio: `Detectar traslapes entre clases`.
- Contraseñas y datos de conexión nunca en archivos del repositorio: van en `api/config.local.php`.
- Cada cambio actualiza la documentación que le toque, en especial [docs/estado-actual.md](docs/estado-actual.md).

[CLAUDE.md](CLAUDE.md) reúne estas reglas y el contexto que necesita un asistente de programación (Claude Code u otro).

## Documentación

- [docs/estado-actual.md](docs/estado-actual.md): qué funciona, qué se verificó, qué falta y el historial de cambios.
- [docs/decisiones.md](docs/decisiones.md): las decisiones que dan forma al sistema, con su porqué.
- [docs/guia-del-administrador.md](docs/guia-del-administrador.md): para quien administra el sistema: el periodo, las cuentas, los alumnos desde Excel ([plantilla](docs/plantilla-alumnos.xlsx)), las materias, los cursos y la primera clase.
- [docs/api.md](docs/api.md):
  - Cómo está organizada la API, configuración y convenciones.
  - Todas las rutas y quién puede usar cada una.
  - Pruebas y cambios a la base.
- [docs/pantallas.md](docs/pantallas.md): las páginas, dónde está cada cosa en `src/` y las convenciones del frontend.
- [docs/modelo-de-datos.md](docs/modelo-de-datos.md): las tablas, las reglas que garantiza la base y por qué tiene esta forma.
- [docs/cambio-de-firebase.md](docs/cambio-de-firebase.md): pasos para publicar esta versión y traer los datos de Firebase, antes o después de publicar.
- [docs/preguntas-para-el-instituto.md](docs/preguntas-para-el-instituto.md): lo que falta que defina el instituto, con lo que se supone mientras tanto.
