# Pantallas

Las pantallas son un sitio de Next.js 14 que se exporta como archivos estáticos (`output: 'export'`): Hostinger solo sirve archivos y PHP, así que no hay servidor de Node. Todo lo que guarda o consulta datos pasa por la API en `/api` (ver [api.md](api.md)).

Stack: React 18, TypeScript, Tailwind, componentes de shadcn/ui (sobre Radix), lucide-react para los íconos y framer-motion para algunas animaciones. Las hojas de registro y los reportes salen con jsPDF y SheetJS.

## Páginas

| Ruta | Quién | Qué hace |
|---|---|---|
| `/` | Todos | La computadora del laboratorio: el alumno registra su entrada, el personal inicia sesión. El laboratorio se elige una vez y se recuerda en el navegador. |
| `/lista-asistencias` | Docente | Abre la clase de uno de sus cursos, ve quién entra, la exporta y la cierra. Sus prácticas, sus clases anteriores y la asistencia del curso. |
| `/lista-asistenciasInvitado` | Personal | Una clase de maestro invitado. |
| `/panel-laboratorista` | Laboratorista y administrador | Lo que pasa en el laboratorio, reportes, historial, equipos, horario, invitados y eventos, bitácora. |
| `/AdminPanel` | Administrador | Tablero, alumnos (con carga desde Excel), cursos, materias y prácticas, cuentas del personal, carreras, periodos y laboratorios. |

Cada página revisa la sesión con `useSesion(roles)`: si no hay sesión o el rol no corresponde, regresa a `/`. Esto solo evita mostrar una pantalla vacía; los permisos de verdad los revisa la API en cada petición.

## Dónde está cada cosa

| Carpeta | Contenido |
|---|---|
| `src/pages/` | Una página por ruta. Arman la pantalla con los componentes y no tienen lógica de datos propia. |
| `src/components/registro/` | La página principal: elegir laboratorio, registrar la entrada, iniciar sesión, lector de credencial, menú. |
| `src/components/docente/`, `clase/` | El panel del docente y la clase en curso, que también usan el laboratorista y la clase de invitado. |
| `src/components/laboratorista/` | Una vista por sección del panel del laboratorio (`Vista*.tsx`). |
| `src/components/admin/` | Una sección por archivo del panel del administrador; `alumnos/` tiene los diálogos de alumnos. |
| `src/components/comunes/` | Lo que usan todas: alertas, cargando, botón de tema, cambiar contraseña, paginador. |
| `src/components/ui/` | Los componentes de shadcn/ui. Solo están los que se usan; si hace falta otro, se agrega con `npx shadcn@latest add`. |
| `src/lib/api.ts` | Todas las llamadas a la API y sus tipos. Ningún componente llama a `fetch` directamente. |
| `src/lib/alertas.ts` | Avisos y confirmaciones (ver abajo). |
| `src/lib/exportar.ts` | Hoja de registro y reportes en PDF y Excel, y lectura de hojas de cálculo. |
| `src/lib/formato.ts`, `tema.ts` | Fechas y horas en el formato de Hermosillo; tema claro u oscuro y sus colores. |
| `src/hooks/` | `useSesion`, `useTema`, `useDatos` (cargar una vez) y `useConsultaPeriodica` (mantener al día). |

## Convenciones

- **Nombres en español**, como en la API y la base: `Alumnos`, `alCerrar`, `recargar`.
- **Datos:** `useDatos` para lo que se carga al abrir una sección (avisa si falla); `useConsultaPeriodica` para lo que debe verse al día, como la lista de una clase abierta. La consulta periódica se detiene mientras la pestaña está oculta.
- **Errores de la API:** llegan como `ErrorDeApi`, con el mensaje en español, el `codigo` (por ejemplo `no_inscrito` o `equipo_ocupado`) y el `campo` con problema. Las pantallas reaccionan al `codigo`, no al texto del mensaje.
- **Alertas:** siempre con `alertas` de `lib/alertas.ts`:
  - `exito`, `info`, `aviso`, `error`;
  - `confirmar` devuelve `true` o `false`;
  - `pedirTexto` devuelve el texto, o `null` si se canceló;
  - `breve` muestra un aviso que se quita solo y no detiene a quien lo pide.

  Son diálogos de Radix, igual que los demás, y los muestra `components/comunes/Alertas.tsx`, montado en `_app`. Antes eran de SweetAlert2. Al combinarse con los diálogos de Radix, la página quedaba oculta para los lectores de pantalla después de cerrar ambos, y el foco se quedaba en el diálogo de abajo.
- **Diálogos:** cada `DialogContent` lleva `DialogTitle`. Si no tiene `DialogDescription`, se le pasa `aria-describedby={undefined}`. Los campos sin `<Label>` llevan `aria-label`, y los botones que solo tienen ícono llevan `title` o `aria-label`.
- **Exportar:** jsPDF y SheetJS se cargan la primera vez que alguien exporta, no al abrir la página. Así las pantallas del personal pesan unos 200 kB en lugar de 500 kB.
- **Tema:** la cookie `theme` la lee `_document.tsx` antes de pintar, para que la página no parpadee en blanco en modo oscuro.

## Comprobar antes de subir

```bash
npm run lint
npm run build   # también revisa los tipos y el lint; falla si hay errores
```

No hay pruebas automáticas de las pantallas en el repositorio. Los flujos principales se probaron en Edge contra el sitio compilado en `out/` y la API real con una base de ejemplo; el detalle está en la descripción del pull request.
