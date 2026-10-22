# Sistema de control de asistencias a laboratorios — ITSPP

Registra las entradas de los alumnos a los laboratorios de cómputo del Instituto Tecnológico Superior de Puerto Peñasco y el equipo que ocupa cada uno:

- El docente abre la clase de su materia.
- El alumno registra su entrada con su número de control, sin iniciar sesión, e indica su computadora o que trae la suya.
- Al cerrar la clase se registra la salida de todos.

El sistema lleva además las prácticas por materia, las clases de maestro invitado, las solicitudes de ingreso de alumnos no inscritos, el estado de los equipos y una bitácora.

Lo usan cuatro tipos de persona: alumno (sin cuenta), docente, laboratorista y administrador.

## Cómo está hecho

| Parte | Tecnología | Carpeta |
|---|---|---|
| Pantallas | Next.js 14 (export estático), React 18, TypeScript, Tailwind, shadcn/ui | `src/` |
| API | PHP 8.1 o más nuevo, sin framework ni paquetes | `api/` |
| Base de datos | MySQL 8 o MariaDB 10.6 o más nuevo | `db/` |
| Pruebas de la API | PHPUnit, contra una base real | `tests/api/` |
| Scripts | Instalar la base, aplicar cambios, primer administrador, importar de Firebase, exportar datos | `scripts/` |

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
npm run build        # compila las pantallas y revisa tipos y lint
```

En GitHub corren solas en cada pull request (`.github/workflows/`). Las de la API corren en MariaDB 10.6 y 11.8 y en MySQL 8.0.

## Publicar en Hostinger

```bash
npm ci
npm run build
```

Se sube el contenido de `out/` a la carpeta del sitio. **No hay que borrar `api/config.local.php` del servidor.** La primera vez hay que seguir [docs/cambio-de-firebase.md](docs/cambio-de-firebase.md), que también explica cómo copiar los datos que había en Firebase.

## Documentación

- [docs/api.md](docs/api.md):
  - Cómo está organizada la API, configuración y convenciones.
  - Todas las rutas y quién puede usar cada una.
  - Pruebas y cambios a la base.
- [docs/pantallas.md](docs/pantallas.md): las páginas, dónde está cada cosa en `src/` y las convenciones del frontend.
- [docs/modelo-de-datos.md](docs/modelo-de-datos.md): las tablas, las reglas que garantiza la base y por qué tiene esta forma.
- [docs/cambio-de-firebase.md](docs/cambio-de-firebase.md): pasos para publicar esta versión y traer los datos de Firebase.
- [docs/preguntas-para-el-instituto.md](docs/preguntas-para-el-instituto.md): lo que falta que defina el instituto, con lo que se supone mientras tanto.
