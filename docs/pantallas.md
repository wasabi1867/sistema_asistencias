# Pantallas

Las pantallas son un sitio de Next.js 16 que se exporta como archivos estáticos (`output: 'export'`): Hostinger solo sirve archivos y PHP, así que no hay servidor de Node. Todo lo que guarda o consulta datos pasa por la API en `/api` (ver [api.md](api.md)).

Stack: React 18, TypeScript, Tailwind, componentes de shadcn/ui (sobre Radix), lucide-react para los íconos y la letra Inter. Las hojas de registro y los reportes salen con jsPDF y SheetJS.

## Páginas

| Ruta | Quién | Qué hace |
|---|---|---|
| `/` | Alumnos | La computadora del laboratorio (y el teléfono del alumno): la clase abierta y el registro de entrada en dos pasos, número de control y computadora. El alumno no escribe ningún otro dato. El laboratorio se elige una vez y se recuerda en el navegador. |
| `/entrar` | Personal | Inicio de sesión de docentes, laboratoristas y administradores. Se llega con el botón **Personal** de `/`. |
| `/docente` | Docente | Abre la clase de uno de sus cursos, ve quién entra, la exporta y la cierra. Sus prácticas, sus clases anteriores y la asistencia del curso. |
| `/laboratorio` | Laboratorista y administrador | Lo que pasa en el laboratorio (con el mapa de equipos), historial, reportes, bitácora, equipos, horario, invitados y eventos. |
| `/clase-invitada` | Laboratorista y administrador | Una clase de maestro invitado, abierta desde `/laboratorio`. |
| `/admin` | Administrador | Tablero, alumnos (con carga desde Excel), cursos, materias y prácticas, cuentas del personal, carreras, periodos y laboratorios. |

La sección abierta de un panel va en la dirección (`/admin?seccion=alumnos`), con `useSeccion`: recargar, usar "atrás" o compartir el enlace deja a la persona donde estaba.

Cada página del personal revisa la sesión con `useSesion(roles)`: si no hay sesión o el rol no corresponde, lleva a `/entrar`. Esto solo evita mostrar una pantalla vacía; los permisos de verdad los revisa la API en cada petición.

Las direcciones de antes (`/AdminPanel`, `/lista-asistencias`, `/lista-asistenciasInvitado`, `/panel-laboratorista`) las redirige `public/.htaccess` a las nuevas.

## Dónde está cada cosa

| Carpeta | Contenido |
|---|---|
| `src/pages/` | Una página por ruta. Arman la pantalla con los componentes. |
| `src/components/registro/` | La página principal: elegir laboratorio, la clase abierta, el registro de entrada, la cuadrícula de computadoras, el reloj y el lector de credencial. |
| `src/components/docente/`, `clase/` | Las secciones del docente y la clase en curso, que también usa la clase de invitado. |
| `src/components/laboratorista/` | Una vista por sección del panel del laboratorio (`Vista*.tsx`) y el mapa de equipos con su detalle. |
| `src/components/admin/` | Una sección por archivo del panel del administrador; `alumnos/` tiene los diálogos de alumnos. |
| `src/components/comunes/` | Lo que usan todas: la estructura de los paneles, el menú de la cuenta, las piezas de pantalla (`Bloques.tsx`), el menú "⋯" de los renglones, alertas, marca, paginador. |
| `src/components/ui/` | Los componentes de shadcn/ui. Solo están los que se usan; si hace falta otro, se agrega con `npx shadcn@latest add` y se ajusta a los colores de abajo. |
| `src/lib/api.ts` | Todas las llamadas a la API y sus tipos. Ningún componente llama a `fetch` directamente. |
| `src/lib/alertas.ts` | Avisos y confirmaciones (ver abajo). |
| `src/lib/exportar.ts` | Hoja de registro y reportes en PDF y Excel, y lectura de hojas de cálculo. |
| `src/lib/formato.ts` | Fechas y horas en el formato de Hermosillo, y el nombre de cada equipo. |
| `src/lib/rutas.ts` | A qué panel va cada rol y el laboratorio de esta computadora. |
| `src/hooks/` | `useSesion`, `useSeccion`, `useTema`, `useDatos` (cargar una vez) y `useConsultaPeriodica` (mantener al día). |

## Diseño

- **Colores:** están definidos una sola vez, como variables, en `src/styles/globals.css`, para el tema claro y el oscuro. Los componentes usan sus nombres (`bg-primary`, `text-muted-foreground`, `border-success/40`) y nunca un color fijo, así que el tema oscuro sale solo.
  - `primary`: el guinda del Tecnológico, en los dos temas (más claro en el oscuro). Botón principal, sección elegida, foco.
  - `success`: lo que está bien: libre, dentro, en curso, listo.
  - `warning`: lo que pide atención: solicitudes por resolver, fallas en proceso.
  - `destructive`: lo que no tiene vuelta atrás: eliminar, finalizar, fuera de servicio.
  - `info`: lo demás que conviene distinguir: en uso, equipo propio, maestro invitado.
- **Piezas** (`components/comunes/Bloques.tsx`): `Panel` (un bloque con título y botones), `Cifra` (un número con su etiqueta), `Listado` (filtros, tabla y pie), `SinDatos` (qué pasa cuando no hay nada y qué hacer), `Campo` (etiqueta, control y ayuda), `CampoDeBusqueda` y `PuntoEnVivo`.
- **Paneles:** `EstructuraDelPanel` arma el menú lateral (en el teléfono se abre con un botón), el título de la sección y el menú de la cuenta (cambiar contraseña, tema y cerrar sesión).
- **Tablas:** lo que se puede hacer con un renglón va en su menú "⋯" (`MenuDeAcciones`), con palabras y lo peligroso al final en rojo. Si el renglón tiene un detalle, un clic en él lo abre. En pantallas angostas se esconden columnas y su dato pasa bajo el nombre.
- **Formularios:** en un diálogo, con los botones abajo a la derecha: Cancelar y la acción.
- **Teléfono:** todo debe caber a 390 px de ancho sin moverse hacia los lados. Los botones que se tocan miden al menos 40 px.
- **Movimiento:** solo transiciones cortas de `tailwindcss-animate`. Quien pidió menos movimiento en su sistema no las ve (`globals.css`).

## Convenciones

- **Nombres en español**, como en la API y la base: `Alumnos`, `alCerrar`, `recargar`.
- **Datos:** `useDatos` para lo que se carga al abrir una sección (avisa si falla); `useConsultaPeriodica` para lo que debe verse al día, como la lista de una clase abierta. La consulta periódica se detiene mientras la pestaña está oculta.
- **Errores de la API:** llegan como `ErrorDeApi`, con el mensaje en español, el `codigo` (por ejemplo `no_inscrito` o `equipo_ocupado`) y el `campo` con problema. Las pantallas reaccionan al `codigo`, no al texto del mensaje.
- **Alertas:** siempre con `alertas` de `lib/alertas.ts`:
  - `exito`, `info`, `aviso`, `error`;
  - `confirmar` devuelve `true` o `false`;
  - `pedirTexto` devuelve el texto, o `null` si se canceló;
  - `breve` muestra un aviso abajo a la derecha que se quita solo y no detiene a quien lo pide. Es lo que se usa para confirmar algo que salió bien.

  Son diálogos de Radix, igual que los demás, y los muestra `components/comunes/Alertas.tsx`, montado en `_app`. Antes eran de SweetAlert2. Al combinarse con los diálogos de Radix, la página quedaba oculta para los lectores de pantalla después de cerrar ambos, y el foco se quedaba en el diálogo de abajo.
- **Menús desplegables:** no son modales (`components/ui/dropdown-menu.tsx`). Muchas opciones abren un diálogo, y un menú modal que se cierra a la vez que se abre el diálogo puede dejar la página sin recibir clics.
- **Accesibilidad:** cada diálogo lleva `DialogTitle` y `DialogDescription` (oculta con `sr-only` si no hace falta verla). Los campos llevan etiqueta (`Campo` o `aria-label`), y los botones que solo tienen ícono llevan `aria-label`.
- **Exportar:** jsPDF y SheetJS se cargan la primera vez que alguien exporta, no al abrir la página.
- **Tema:** la cookie `theme` la lee `_document.tsx` antes de pintar, para que la página no parpadee en blanco en modo oscuro. Es el único script en línea, y la CSP del `.htaccess` lo permite por su hash: si se cambia, hay que poner el hash nuevo (decisión D18).
- **Se compila con webpack, no con Turbopack** (`next build --webpack`): Turbopack, el de omisión en Next.js 16, mete un script en línea distinto en cada página y la CSP lo bloquearía (D19).
- **Lint:** `npm run lint` corre ESLint 9 con `eslint.config.mjs`. Las reglas del compilador de React que llegaron con Next.js 16 están apagadas; las de los hooks de siempre siguen.
- **Nada de scripts de otros sitios ni `eval`:** la CSP solo deja ejecutar lo del propio sitio. Una librería que los necesite no funcionaría en producción.
- **Letra:** Inter, desde el paquete `@fontsource-variable/inter`. Se publica junto con el sitio; al abrir la página no se pide nada a otro servicio.

## Comprobar antes de subir

```bash
npm run lint
npm run build   # compila con webpack y revisa los tipos; falla si hay errores
```

## Prueba de las pantallas

`tests/pantallas/` recorre el sistema en un navegador de verdad, contra el sitio compilado y la API real, en 48 pasos. Cubre:
- **Laboratorio y alumno:** elegir el laboratorio; el docente abre la clase; un alumno se registra desde el teléfono; uno no inscrito pide permiso y el administrador lo aprueba.
- **Docente:** registra a alguien a mano, descarga la hoja en PDF y en Excel, quita una entrada y cierra la clase.
- **Administrador:** sus secciones, la carga de alumnos desde Excel y desde una lista de grupo (título arriba y el nombre completo en una columna), crear cuentas y carreras, y el tema oscuro.
- **Laboratorista:** sus vistas, reportar una falla, una clase de maestro invitado y la bitácora.

En cada paso revisa además que no haya errores en la consola, respuestas 5xx de la API ni desborde horizontal en el teléfono. Deja capturas en `tests/pantallas/capturas/`, que git ignora.

```bash
npm run build
npm run prueba:pantallas
```

El servidor de la prueba manda las mismas cabeceras de seguridad que el `.htaccess` (las lee de ahí). Si la política de contenido (CSP) bloqueara un script o un estilo, sale un error en la consola y la prueba falla.

`tests/pantallas/correr.js` hace todo lo demás:
1. Crea una base nueva con la conexión de las pruebas de la API (`ASISTENCIAS_PRUEBAS_DB_*`) y la instala.
2. Crea el administrador de la prueba y sirve `out/` con el servidor de PHP (`tests/pantallas/servidor.php` imita el `.htaccess`).
3. Carga datos inventados por la API (`datos-de-ejemplo.php`), con un periodo alrededor de la fecha del día.
4. Al terminar, borra la base.

No descarga navegadores: usa Edge en Windows y Chrome en lo demás (variable `NAVEGADOR`). Si PHP no está en el PATH, se indica con la variable `PHP`. En GitHub corre en cada pull request que toca las pantallas, la API o la base (`.github/workflows/pantallas.yml`), con MariaDB 11.8 en READ COMMITTED como producción, y guarda las capturas.

Si cambia un texto, un botón o un flujo de una pantalla, se actualiza el paso que lo usa en `tests/pantallas/prueba.js`.
