# Guía para asistentes de desarrollo

Contexto y reglas de trabajo para Claude Code u otro agente en este repositorio. Lo que explica el sistema está en `README.md` y en `docs/`; aquí solo va lo que un agente necesita saber antes de tocar algo.

## El proyecto

- **Qué es:** el control de asistencias a los laboratorios de cómputo del Instituto Tecnológico Superior de Puerto Peñasco (ITSPP). El docente abre la clase; el alumno registra su entrada **solo con su número de control** (sin cuenta ni datos personales) y dice qué computadora ocupa; al cerrar la clase se registra la salida de todos.
- **Repositorio:** `wasabi1867/sistema_asistencias`. El dueño es Andrés Cárdenas (wasabi1867), el contacto con el instituto.
- **Hospedaje:** Hostinger, en la cuenta de Daniel Alonso Osuna Talamantes. David entra como colaborador y publica; no puede comprar ni cambiar de plan. Cómo es el plan y cómo se publica: [docs/cambio-de-firebase.md](docs/cambio-de-firebase.md).
- **Quién decide lo técnico:** David Ramírez (Ozelot12100), colaborador con permiso de escritura. Lleva la base, la API y las pantallas.
- **Repositorio anterior:** `Ozelot12100/Sistema_asignacion_academica_ITSPP` (API en .NET) ya no se usa. De ahí se tomó el diseño de la base, pero su código y su `CLAUDE.md` no aplican aquí.
- **Estado:** [docs/estado-actual.md](docs/estado-actual.md) dice qué funciona, qué se verificó y qué falta.

## Tecnologías

| Parte | Qué | Dónde |
|---|---|---|
| Pantallas | Next.js 14 con export estático, React 18, TypeScript, Tailwind 3, shadcn/ui | `src/` |
| API | PHP 8.1+ sin framework ni paquetes | `api/` |
| Base | MariaDB 11.8 en producción (Hostinger). El SQL debe funcionar también en MySQL 8: se prueba en los dos | `db/` |
| Pruebas | PHPUnit contra una base real | `tests/api/` |

Hostinger compartido no corre Node ni .NET: por eso las pantallas son archivos estáticos y la API es PHP.

## Comandos

```bash
composer install && vendor/bin/phpunit   # API; ASISTENCIAS_PRUEBAS_DB_HOST/_PORT/_USER/_PASS para otra base
npm ci
npm run lint
npm run build                            # compila, revisa tipos y lint, y copia api/ a out/api
php -S localhost:8000 api/index.php      # API local, con api/config.local.php
npm run dev                              # pantallas en http://localhost:3000 (NEXT_PUBLIC_API_URL en .env.local)
```

## Reglas

- **Nada directo a `main`.** Cada cambio va en una rama de este repositorio (remoto `origin`) con un pull request. David lo acepta cuando pasan las pruebas y la integración continua (`.github/workflows/`).
- **Antes de subir:** `vendor/bin/phpunit`, `npm run lint` y `npm run build` sin errores.
- **Commits** en español, en infinitivo, describiendo el cambio: `Detectar traslapes entre clases`. Terminan con la línea `Co-Authored-By` del agente.
- **Secretos:** nunca escribir contraseñas ni datos de conexión en archivos versionados. Van en `api/config.local.php` (ignorado por git) o en variables de entorno. La conexión a la base de Hostinger va en un archivo fuera del repositorio (ver la guía de publicación).
- **La base de producción:** antes de escribir en ella, confirmar con David cada paso. Las demás bases de la cuenta de Hostinger no se tocan.
- **Datos de Firebase:** son datos personales de alumnos. No descargarlos ni importarlos sin permiso explícito de David; cuando lo haya, primero con `--simular`, y el respaldo fuera del repositorio.
- **Código:** vocabulario del dominio en español, como lo nombra el instituto. Los comentarios explican el porqué.
- **Cambios a la base:** `db/esquema.sql` y, para bases ya instaladas, un archivo en `db/migraciones/` (ver [docs/api.md](docs/api.md#cambios-a-la-base)), con sus pruebas, y `docs/modelo-de-datos.md`.
- **Cambios a una ruta:** su permiso en `tests/api/PermisosTest.php`, su tipo en `src/lib/api.ts` y su renglón en `docs/api.md`.
- **Permisos que el instituto no ha definido:** el más restrictivo que deja operar, anotado como provisional.
- **Documentación:** cada cambio actualiza lo que le toque de `docs/`, en especial `docs/estado-actual.md`. Lo que no se verificó se dice como no verificado.
- **Preguntas para el instituto:** van en `docs/preguntas-para-el-instituto.md`, con lo que se supone mientras tanto; la respuesta se anota ahí con su fecha.

## Documentación

| Documento | Contenido |
|---|---|
| [README.md](README.md) | Qué es, cómo correrlo y cómo publicarlo |
| [docs/estado-actual.md](docs/estado-actual.md) | Qué funciona, qué se verificó, qué falta e historial de cambios |
| [docs/api.md](docs/api.md) | Organización de la API, configuración, las 84 rutas con sus permisos, pruebas y cambios a la base |
| [docs/modelo-de-datos.md](docs/modelo-de-datos.md) | Las 19 tablas, las reglas que garantiza la base y la conversión desde Firebase |
| [docs/pantallas.md](docs/pantallas.md) | Las páginas, dónde está cada cosa en `src/`, diseño y convenciones |
| [docs/cambio-de-firebase.md](docs/cambio-de-firebase.md) | Publicar esta versión en Hostinger y traer los datos de Firebase |
| [docs/preguntas-para-el-instituto.md](docs/preguntas-para-el-instituto.md) | Lo que falta que defina el instituto |
