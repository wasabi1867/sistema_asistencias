import { Building2, ChevronRight, Monitor, Server, Wifi, type LucideIcon } from "lucide-react"
import type { Laboratorio } from "../../lib/api"
import { Logo } from "../comunes/Marca"
import { SinDatos } from "../comunes/Bloques"

// Un ícono por laboratorio conocido; los que se agreguen después usan el de monitor.
const ICONOS: Record<string, LucideIcon> = { programacion: Monitor, redes: Wifi, laboratorio_a: Building2, laboratorio_c: Server }

export function iconoDeLaboratorio(clave: string): LucideIcon {
  return ICONOS[clave] ?? Monitor
}

/** La primera pantalla de la computadora del laboratorio: en cuál está. Se recuerda en el navegador. */
export function SeleccionDeLaboratorio({ laboratorios, alElegir }: { laboratorios: Laboratorio[]; alElegir: (clave: string) => void }) {
  return (
    <div className="mx-auto w-full max-w-2xl">
      <div className="mb-8 flex flex-col items-center text-center">
        <Logo conTecnm className="h-14" />
        <h1 className="mt-6 text-2xl font-semibold tracking-tight sm:text-3xl">Control de asistencias</h1>
        <p className="mt-2 text-muted-foreground">¿En qué laboratorio está esta computadora? Se recuerda para la próxima vez.</p>
      </div>

      {laboratorios.length === 0 ? (
        <div className="rounded-xl border bg-card shadow-sm">
          <SinDatos icono={Monitor} titulo="No hay laboratorios">
            No se pudo obtener la lista. Revisa la conexión a internet y vuelve a cargar la página.
          </SinDatos>
        </div>
      ) : (
        <ul className="grid gap-3 sm:grid-cols-2">
          {laboratorios.map((laboratorio) => {
            const Icono = iconoDeLaboratorio(laboratorio.clave)
            return (
              <li key={laboratorio.clave}>
                <button
                  type="button"
                  onClick={() => alElegir(laboratorio.clave)}
                  className="group flex w-full items-center gap-4 rounded-xl border bg-card p-4 text-left shadow-sm transition-colors hover:border-primary/50 hover:bg-accent/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                  <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-accent text-accent-foreground">
                    <Icono className="h-5 w-5" aria-hidden />
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="block font-semibold">{laboratorio.nombre}</span>
                    {laboratorio.ubicacion && <span className="block truncate text-sm text-muted-foreground">{laboratorio.ubicacion}</span>}
                  </span>
                  <ChevronRight className="h-5 w-5 text-muted-foreground transition-transform group-hover:translate-x-0.5" aria-hidden />
                </button>
              </li>
            )
          })}
        </ul>
      )}
    </div>
  )
}
