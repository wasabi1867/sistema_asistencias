import { useEffect, useState, type ReactNode } from "react"
import { AlertTriangle, CheckCircle2, Monitor, Trash2, Wrench } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { cn } from "@/lib/utils"
import { api, type Equipo, type EstadoDeReporte } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { hora, nombreDeEquipo } from "../../lib/formato"
import { Campo } from "../comunes/Bloques"

export const ESTADOS_DE_REPORTE: Record<EstadoDeReporte, string> = { reportado: "Reportada", en_proceso: "En proceso", resuelto: "Resuelta" }

const ESTILO: Record<Equipo["estado"], string> = {
  libre: "border-success/35 bg-success/5 hover:border-success",
  ocupado: "border-info/40 bg-info/10 hover:border-info",
  fuera_de_servicio: "border-dashed border-destructive/50 bg-destructive/5 hover:border-destructive",
}

/**
 * Las computadoras del laboratorio como en el salón: cada una dice si está libre, quién
 * la usa y desde qué hora, o si está fuera de servicio. Al elegir una se abre su detalle.
 */
export function MapaDeEquipos({ equipos, alElegir }: { equipos: Equipo[]; alElegir: (equipo: Equipo) => void }) {
  const cuenta = (estado: Equipo["estado"]) => equipos.filter((e) => e.estado === estado).length
  return (
    <div className="space-y-3">
      <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
        <Leyenda className="border-success/50 bg-success/10">{cuenta("libre")} libres</Leyenda>
        <Leyenda className="border-info/50 bg-info/15">{cuenta("ocupado")} en uso</Leyenda>
        <Leyenda className="border-dashed border-destructive/60 bg-destructive/5">{cuenta("fuera_de_servicio")} fuera de servicio</Leyenda>
      </div>
      <ul className="grid grid-cols-2 gap-2 min-[480px]:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-8">
        {equipos.map((equipo) => (
          <li key={equipo.id}>
            <button
              type="button"
              onClick={() => alElegir(equipo)}
              className={cn(
                "flex h-full w-full flex-col items-start gap-1 rounded-lg border-2 p-2.5 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
                ESTILO[equipo.estado],
              )}
            >
              <span className="flex w-full items-center justify-between gap-1">
                <span className="flex items-center gap-1.5 text-sm font-semibold">
                  {equipo.estado === "fuera_de_servicio" ? <Wrench className="h-3.5 w-3.5 text-destructive" aria-hidden /> : <Monitor className="h-3.5 w-3.5 text-muted-foreground" aria-hidden />}
                  {equipo.etiqueta}
                </span>
                {equipo.reporteAbierto && <AlertTriangle className="h-3.5 w-3.5 text-warning" aria-label="Con falla reportada" />}
              </span>
              <span className="w-full truncate text-xs text-muted-foreground">
                {equipo.ocupadoPor ? (
                  <>
                    <span className="font-medium text-foreground">{equipo.ocupadoPor.nombre}</span> · {hora(equipo.ocupadoPor.desde)}
                  </>
                ) : equipo.estado === "fuera_de_servicio" ? (
                  "Fuera de servicio"
                ) : (
                  "Libre"
                )}
              </span>
            </button>
          </li>
        ))}
      </ul>
    </div>
  )
}

function Leyenda({ className, children }: { className: string; children: ReactNode }) {
  return (
    <span className="flex items-center gap-1.5">
      <span className={cn("inline-block h-3 w-3 rounded-sm border-2", className)} aria-hidden />
      {children}
    </span>
  )
}

/** Un equipo: quién lo usa, sus notas, sacarlo de servicio o reportar una falla. */
export function DetalleDeEquipo({ equipo, esAdmin, alCerrar, alCambiar }: { equipo: Equipo; esAdmin: boolean; alCerrar: () => void; alCambiar: () => void }) {
  const [notas, setNotas] = useState(equipo.notas ?? "")
  const [falla, setFalla] = useState("")

  useEffect(() => setNotas(equipo.notas ?? ""), [equipo.id, equipo.notas])

  const guardar = async (datos: { fueraDeServicio?: boolean; notas?: string | null }, mensaje: string) => {
    try {
      await api.equipos.editar(equipo.id, datos)
      alertas.breve(mensaje)
      alCambiar()
    } catch (error) {
      await alertas.error("No se pudo guardar el cambio", error)
    }
  }

  const reportar = async () => {
    if (!falla.trim()) return
    try {
      await api.reportesDeEquipo.reportar(equipo.id, falla.trim())
      setFalla("")
      alertas.breve("Falla reportada: el equipo quedó fuera de servicio")
      alCambiar()
    } catch (error) {
      await alertas.error("No se pudo reportar la falla", error)
    }
  }

  const retirar = async () => {
    if (!(await alertas.confirmar(`¿Retirar ${equipo.etiqueta}?`, "Deja de aparecer en el laboratorio. Si tiene historial, se conserva.", { aceptar: "Retirar", peligro: true }))) return
    try {
      await api.equipos.retirar(equipo.id)
      alCerrar()
      alCambiar()
    } catch (error) {
      await alertas.error("No se pudo retirar el equipo", error)
    }
  }

  const fuera = equipo.estado === "fuera_de_servicio"

  return (
    <Dialog open onOpenChange={(abierto) => !abierto && alCerrar()}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            {nombreDeEquipo(equipo.etiqueta)}
            {equipo.estado === "libre" && <Badge variant="success">Libre</Badge>}
            {equipo.estado === "ocupado" && <Badge variant="info">En uso</Badge>}
            {fuera && <Badge variant="destructive">Fuera de servicio</Badge>}
          </DialogTitle>
          <DialogDescription>
            {equipo.ocupadoPor
              ? `Lo usa ${equipo.ocupadoPor.nombre}${equipo.ocupadoPor.matricula ? ` (${equipo.ocupadoPor.matricula})` : ""} desde las ${hora(equipo.ocupadoPor.desde)}.`
              : fuera
                ? "No se puede elegir al registrar la entrada."
                : "Nadie lo está usando."}
          </DialogDescription>
        </DialogHeader>
        {equipo.reporteAbierto && (
          <p className="flex gap-2 rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm">
            <AlertTriangle className="h-4 w-4 shrink-0 text-warning" aria-hidden />
            <span>
              Falla {ESTADOS_DE_REPORTE[equipo.reporteAbierto.estado].toLowerCase()}: {equipo.reporteAbierto.descripcion}
            </span>
          </p>
        )}
        <Campo etiqueta="Notas" htmlFor="notas-equipo">
          <Textarea id="notas-equipo" value={notas} onChange={(e) => setNotas(e.target.value)} rows={3} />
          <Button type="button" size="sm" variant="outline" onClick={() => guardar({ notas: notas || null }, "Notas guardadas")} disabled={notas === (equipo.notas ?? "")}>
            Guardar notas
          </Button>
        </Campo>
        <Campo etiqueta="Reportar una falla" htmlFor="falla-equipo" ayuda="El equipo queda fuera de servicio hasta que se resuelva.">
          <div className="flex gap-2">
            <Input id="falla-equipo" value={falla} onChange={(e) => setFalla(e.target.value)} placeholder="Qué falla (no enciende, sin red...)" />
            <Button type="button" variant="outline-destructive" onClick={reportar} disabled={!falla.trim()}>
              <AlertTriangle /> Reportar
            </Button>
          </div>
        </Campo>
        <div className="flex flex-wrap gap-2 border-t pt-4">
          <Button type="button" variant="outline" onClick={() => guardar({ fueraDeServicio: !fuera }, fuera ? "De vuelta en servicio" : "Fuera de servicio")}>
            {fuera ? <CheckCircle2 /> : <Wrench />}
            {fuera ? "Poner en servicio" : "Sacar de servicio"}
          </Button>
          {esAdmin && (
            <Button type="button" variant="ghost" className="text-destructive hover:bg-destructive/10 hover:text-destructive" onClick={retirar}>
              <Trash2 /> Retirar del laboratorio
            </Button>
          )}
        </div>
      </DialogContent>
    </Dialog>
  )
}
