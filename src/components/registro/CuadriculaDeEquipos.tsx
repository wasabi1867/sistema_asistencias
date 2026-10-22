import { Laptop, Monitor, Wrench } from "lucide-react"
import { cn } from "@/lib/utils"
import type { Equipo } from "../../lib/api"
import { nombreDeEquipo } from "../../lib/formato"

export const PROPIO = "propio"

/**
 * Las computadoras del laboratorio como botones: las libres se pueden elegir, las que
 * están en uso o fuera de servicio se ven pero no se eligen. Debajo, "Traigo mi propio
 * equipo". `valor` es la etiqueta elegida, PROPIO, o "" si aún no se elige.
 */
export function CuadriculaDeEquipos({ equipos, valor, alElegir }: { equipos: Equipo[]; valor: string; alElegir: (valor: string) => void }) {
  const libres = equipos.filter((e) => e.estado === "libre").length
  return (
    <fieldset className="space-y-3">
      <legend className="sr-only">Elige la computadora que vas a usar</legend>
      {equipos.length > 0 && (
        <>
          <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
            <span>
              {libres} de {equipos.length} libres
            </span>
            <span className="flex items-center gap-3">
              <Muestra className="border-success/50 bg-success/10" /> Libre
              <Muestra className="border-border bg-muted" /> En uso
              <Muestra className="border-dashed border-destructive/50 bg-transparent" /> Fuera de servicio
            </span>
          </div>
          <div className="grid grid-cols-4 gap-2 min-[400px]:grid-cols-5 sm:grid-cols-6 lg:grid-cols-7">
            {equipos.map((equipo) => {
              const libre = equipo.estado === "libre"
              const elegido = valor === equipo.etiqueta
              const estado = equipo.estado === "ocupado" ? "en uso" : equipo.estado === "fuera_de_servicio" ? "fuera de servicio" : "libre"
              return (
                <button
                  key={equipo.id}
                  type="button"
                  aria-pressed={elegido}
                  aria-label={`${nombreDeEquipo(equipo.etiqueta)}, ${estado}`}
                  disabled={!libre}
                  onClick={() => alElegir(equipo.etiqueta)}
                  className={cn(
                    "flex min-h-[3.5rem] flex-col items-center justify-center gap-0.5 rounded-lg border-2 px-1 py-2 text-xs font-semibold transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-card",
                    elegido && "border-primary bg-primary text-primary-foreground shadow-md",
                    !elegido && libre && "border-success/40 bg-success/5 text-foreground hover:border-success hover:bg-success/10",
                    equipo.estado === "ocupado" && "cursor-not-allowed border-transparent bg-muted text-muted-foreground/70",
                    equipo.estado === "fuera_de_servicio" && "cursor-not-allowed border-dashed border-destructive/40 text-muted-foreground/70",
                  )}
                >
                  {equipo.estado === "fuera_de_servicio" ? <Wrench className="h-4 w-4" aria-hidden /> : <Monitor className="h-4 w-4" aria-hidden />}
                  <span className="truncate">{equipo.etiqueta}</span>
                </button>
              )
            })}
          </div>
        </>
      )}
      <button
        type="button"
        aria-pressed={valor === PROPIO}
        onClick={() => alElegir(PROPIO)}
        className={cn(
          "flex w-full items-center gap-3 rounded-lg border-2 px-4 py-3 text-left text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-card",
          valor === PROPIO ? "border-primary bg-primary text-primary-foreground" : "border-input hover:bg-accent",
        )}
      >
        <Laptop className="h-5 w-5 shrink-0" aria-hidden />
        Traigo mi propio equipo
      </button>
    </fieldset>
  )
}

function Muestra({ className }: { className: string }) {
  return <span className={cn("inline-block h-3 w-3 rounded-sm border-2", className)} aria-hidden />
}
