import { useState, type FormEvent } from "react"
import { Monitor, Plus, Settings2, Wrench } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { api, type Equipo, type EstadoDeReporte, type ReporteDeEquipo } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { fechaHora, nombreDeEquipo } from "../../lib/formato"
import { useConsultaPeriodica } from "../../hooks/useConsultaPeriodica"
import { Panel, SinDatos } from "../comunes/Bloques"
import { DetalleDeEquipo, ESTADOS_DE_REPORTE, MapaDeEquipos } from "./MapaDeEquipos"
import type { PropsDeVista } from "./vista"

const VARIANTE_DEL_ESTADO = { reportado: "destructive", en_proceso: "warning", resuelto: "success" } as const

/** Las computadoras del laboratorio: su estado, sus fallas y cuántas hay. */
export function VistaEquipos({ laboratorio, usuario }: PropsDeVista) {
  const { datos: equipos, recargar } = useConsultaPeriodica(() => api.equipos.deLaboratorio(laboratorio.clave), 5000, [laboratorio.clave])
  const [filtroDeReportes, setFiltroDeReportes] = useState<"abiertos" | EstadoDeReporte>("abiertos")
  const { datos: reportes, recargar: recargarReportes } = useConsultaPeriodica(
    () => api.reportesDeEquipo.listar({ laboratorio: laboratorio.clave, estado: filtroDeReportes }),
    15000,
    [laboratorio.clave, filtroDeReportes],
  )
  const [elegido, setElegido] = useState<Equipo | null>(null)
  const [cantidad, setCantidad] = useState("")
  const [nueva, setNueva] = useState("")

  const actualizar = () => Promise.all([recargar(), recargarReportes()])
  const lista = equipos ?? []

  const configurar = async (e: FormEvent) => {
    e.preventDefault()
    const n = Number(cantidad)
    if (!n) return
    if (!(await alertas.confirmar(`¿Dejar ${n} equipos?`, `El laboratorio quedará con PC-1 a PC-${n}. Los demás se retiran (los que tienen historial solo se desactivan).`, { aceptar: "Configurar" }))) return
    try {
      const r = await api.equipos.configurar(laboratorio.clave, n)
      await alertas.exito("Equipos configurados", `${r.agregados} agregados, ${r.retirados} retirados.`)
      setCantidad("")
      await actualizar()
    } catch (error) {
      await alertas.error("No se pudieron configurar los equipos", error)
    }
  }

  const agregar = async (e: FormEvent) => {
    e.preventDefault()
    if (!nueva.trim()) return
    try {
      await api.equipos.agregar(laboratorio.clave, nueva.trim())
      alertas.breve(`${nueva.trim()} agregado`)
      setNueva("")
      await actualizar()
    } catch (error) {
      await alertas.error("No se pudo agregar el equipo", error)
    }
  }

  return (
    <div className="space-y-6">
      <Panel titulo={`Equipos de ${laboratorio.nombre}`} descripcion={`${lista.length} computadoras. Elige una para ver su detalle.`}>
        {equipos && lista.length === 0 ? (
          <SinDatos icono={Monitor} titulo="El laboratorio no tiene equipos">
            Escribe cuántas computadoras hay abajo y se crean de PC-1 en adelante.
          </SinDatos>
        ) : (
          <MapaDeEquipos equipos={lista} alElegir={setElegido} />
        )}
        <div className="mt-5 flex flex-wrap gap-x-6 gap-y-3 border-t pt-4">
          <form onSubmit={configurar} className="flex items-end gap-2">
            <div className="space-y-1.5">
              <label htmlFor="cantidad-equipos" className="text-sm font-medium">
                Cantidad de equipos
              </label>
              <Input id="cantidad-equipos" type="number" min={1} max={200} value={cantidad} onChange={(e) => setCantidad(e.target.value)} className="w-28" />
            </div>
            <Button type="submit" variant="outline" disabled={!cantidad}>
              <Settings2 /> Configurar PC-1 a PC-N
            </Button>
          </form>
          {usuario.rol === "admin" && (
            <form onSubmit={agregar} className="flex items-end gap-2">
              <div className="space-y-1.5">
                <label htmlFor="etiqueta-equipo" className="text-sm font-medium">
                  Agregar uno
                </label>
                <Input id="etiqueta-equipo" value={nueva} onChange={(e) => setNueva(e.target.value)} placeholder="Ej. PC-26" className="w-32" />
              </div>
              <Button type="submit" variant="outline" disabled={!nueva.trim()}>
                <Plus /> Agregar
              </Button>
            </form>
          )}
        </div>
      </Panel>

      <Panel
        titulo="Fallas reportadas"
        acciones={
          <Tabs value={filtroDeReportes} onValueChange={(v) => setFiltroDeReportes(v as typeof filtroDeReportes)}>
            <TabsList>
              <TabsTrigger value="abiertos">Abiertas</TabsTrigger>
              <TabsTrigger value="reportado">Reportadas</TabsTrigger>
              <TabsTrigger value="en_proceso">En proceso</TabsTrigger>
              <TabsTrigger value="resuelto">Resueltas</TabsTrigger>
            </TabsList>
          </Tabs>
        }
        sinRelleno
      >
        {reportes?.length === 0 ? (
          <SinDatos icono={Wrench} titulo="No hay fallas en esta lista" />
        ) : (
          <ul className="divide-y">
            {(reportes ?? []).map((reporte) => (
              <SeguimientoDeFalla key={reporte.id} reporte={reporte} alCambiar={actualizar} />
            ))}
          </ul>
        )}
      </Panel>

      {elegido && <DetalleDeEquipo equipo={lista.find((e) => e.id === elegido.id) ?? elegido} esAdmin={usuario.rol === "admin"} alCerrar={() => setElegido(null)} alCambiar={actualizar} />}
    </div>
  )
}

/** Una falla y el cambio de su estado. Al resolverse la última del equipo, vuelve a servicio. */
function SeguimientoDeFalla({ reporte, alCambiar }: { reporte: ReporteDeEquipo; alCambiar: () => void }) {
  const [estado, setEstado] = useState<EstadoDeReporte>(reporte.estado)
  const [comentario, setComentario] = useState(reporte.comentario ?? "")

  const guardar = async (e: FormEvent) => {
    e.preventDefault()
    try {
      await api.reportesDeEquipo.actualizar(reporte.id, { estado, comentario: comentario || null })
      alertas.breve(estado === "resuelto" ? "Falla resuelta" : "Seguimiento guardado")
      alCambiar()
    } catch (error) {
      await alertas.error("No se pudo guardar el seguimiento", error)
    }
  }

  return (
    <li className="space-y-3 px-4 py-4 sm:px-5">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="font-medium">
            {nombreDeEquipo(reporte.equipo.etiqueta)}: {reporte.descripcion}
          </p>
          <p className="text-xs text-muted-foreground">
            Reportó {reporte.reportadoPor ?? "el sistema anterior"} el {fechaHora(reporte.reportadoEn)}
            {reporte.resueltoEn ? ` · resuelta el ${fechaHora(reporte.resueltoEn)}` : ""}
          </p>
        </div>
        <Badge variant={VARIANTE_DEL_ESTADO[reporte.estado]}>{ESTADOS_DE_REPORTE[reporte.estado]}</Badge>
      </div>
      <form onSubmit={guardar} className="flex flex-wrap gap-2">
        <Select value={estado} onValueChange={(v) => setEstado(v as EstadoDeReporte)}>
          <SelectTrigger className="w-40" aria-label="Estado de la falla">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {Object.entries(ESTADOS_DE_REPORTE).map(([valor, nombre]) => (
              <SelectItem key={valor} value={valor}>
                {nombre}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Input value={comentario} onChange={(e) => setComentario(e.target.value)} placeholder="Comentario (qué se hizo, qué falta)" aria-label="Comentario (qué se hizo, qué falta)" className="min-w-[12rem] flex-1" />
        <Button type="submit" variant="outline">
          Guardar
        </Button>
      </form>
    </li>
  )
}
