import type { ReactNode } from "react"
import { CalendarClock, ClipboardList, UserRound, type LucideIcon } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import type { ClaseAbierta } from "../../lib/api"
import { hora } from "../../lib/formato"
import { PuntoEnVivo, SinDatos } from "../comunes/Bloques"

/** La clase abierta en el laboratorio, para que el alumno sepa a dónde está entrando. */
export function ClaseActual({ clases }: { clases: ClaseAbierta[] | undefined }) {
  if (!clases) {
    return <div className="h-48 animate-pulse rounded-xl border bg-card" aria-hidden />
  }

  if (clases.length === 0) {
    return (
      <section className="flex items-center rounded-xl border bg-card shadow-sm" aria-label="Clase abierta">
        <SinDatos icono={CalendarClock} titulo="No hay ninguna clase abierta">
          Cuando el docente abra su clase aparecerá aquí y podrás registrar tu entrada. Esta página se actualiza sola.
        </SinDatos>
      </section>
    )
  }

  return (
    <section className="space-y-4" aria-label={clases.length > 1 ? "Clases abiertas" : "Clase abierta"}>
      {clases.map((clase) => (
        <article key={clase.id} className="overflow-hidden rounded-xl border bg-card shadow-sm">
          <div className="h-1 bg-primary" aria-hidden />
          <div className="p-5 sm:p-6">
            <div className="flex flex-wrap items-center gap-2">
              <Badge variant="success">
                <PuntoEnVivo /> En curso desde las {hora(clase.abiertaEn)}
              </Badge>
              {clase.tipo === "invitado" && <Badge variant="info">Maestro invitado</Badge>}
            </div>
            <h2 className="mt-3 text-2xl font-semibold tracking-tight sm:text-3xl">{clase.materia}</h2>
            {clase.grupo && <p className="mt-0.5 text-muted-foreground">Grupo {clase.grupo}</p>}
            <dl className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
              <Dato icono={UserRound} etiqueta="Docente">
                {clase.docente ?? "Sin docente"}
              </Dato>
              <Dato icono={ClipboardList} etiqueta="Práctica">
                {clase.practica ?? "Sin práctica"}
              </Dato>
            </dl>
          </div>
        </article>
      ))}
    </section>
  )
}

function Dato({ icono: Icono, etiqueta, children }: { icono: LucideIcon; etiqueta: string; children: ReactNode }) {
  return (
    <div className="flex gap-3">
      <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-muted">
        <Icono className="h-4 w-4 text-muted-foreground" aria-hidden />
      </div>
      <div className="min-w-0">
        <dt className="text-xs text-muted-foreground">{etiqueta}</dt>
        <dd className="font-medium">{children}</dd>
      </div>
    </div>
  )
}
