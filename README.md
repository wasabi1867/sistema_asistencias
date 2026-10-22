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
| Pantallas | Next.js 14 (export estático), React, Tailwind, shadcn/ui | `src/` |
| API | PHP con PDO, sin framework | `public/api/` |
| Base de datos | MySQL o MariaDB | `schema.sql` y `db/migraciones/` |
| Pruebas de la API | PHPUnit, contra una base real | `tests/api/` |
| Scripts | Migraciones, primer administrador, importación desde Firebase | `scripts/` |

Todo se publica junto en Hostinger: `npm run build` deja en `out/` las páginas y la carpeta `api/`.

## Correrlo en una computadora

Se necesita:
- Node 20 o más nuevo.
- PHP 8.3 (la versión con la que corren las pruebas) con `pdo_mysql`.
- Composer.
- MySQL o MariaDB.

1. **Base de datos.** Crear una base vacía, cargar `schema.sql` y aplicar las migraciones:

   ```bash
   php scripts/migrar.php
   ```

2. **Configuración de la API.** Copiar `public/api/config.ejemplo.php` como `public/api/config.local.php` y llenar los datos de la base. Git ignora este archivo. Para usar la API desde `npm run dev`, poner `http://localhost:3000` en `origenes_permitidos`.

3. **Primer administrador.** El script pide la contraseña:

   ```bash
   php scripts/crear-admin.php admin@puertopenasco.tecnm.mx "Administrador General"
   ```

4. **API y pantallas.** Cada una corre en su propia terminal:

   ```bash
   php -S localhost:8000 -t public
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
vendor/bin/phpunit   # API; docs/api-php.md explica cómo apuntar a otra base
npm run build        # comprueba que las pantallas compilen
```

En GitHub corren solas en cada pull request (`.github/workflows/`).

## Publicar en Hostinger

```bash
npm ci
npm run build
```

Se sube el contenido de `out/` a la carpeta del sitio. **No hay que borrar `api/config.local.php` del servidor.** La primera vez hay que seguir [docs/cambio-de-firebase.md](docs/cambio-de-firebase.md), que también explica cómo copiar los datos que había en Firebase.

## Documentación

- [docs/api-php.md](docs/api-php.md):
  - Configuración y sesiones.
  - Quién puede usar cada ruta y las reglas del registro.
  - Pruebas y migraciones.
- [docs/cambio-de-firebase.md](docs/cambio-de-firebase.md): pasos para publicar esta versión y traer los datos de Firebase.
