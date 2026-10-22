import { useEffect, useState } from "react"
import { AlertTriangle, CheckCircle2, Monitor, Plus, Settings, Trash2, Wrench } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Textarea } from "@/components/ui/textarea"
import { api, type Equipo, type EstadoDeReporte, type ReporteDeEquipo } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { fechaHora, hora } from "../../lib/formato"
import { useConsultaPeriodica } from "../../hooks/useConsultaPeriodica"
import { nombreDeEquipo } from "../registro/FormularioDeEntrada"
import { encabezadoDeTarjeta, type PropsDeVista } from "./vista"

const ESTADOS_DE_REPORTE: Record<EstadoDeReporte, string> = { reportado: "Reportado", en_proceso: "En proceso", resuelto: "Resuelto" }

/** Las computadoras del laboratorio: su estado, sus fallas y cuántas hay. */
export function VistaEquipos({ laboratorio, usuario, oscuro, c }: PropsDeVista) {
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
  const cuenta = (estado: Equipo["estado"]) => lista.filter((e) => e.estado === estado).length

  const configurar = async () => {
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

  const agregar = async () => {
    if (!nueva.trim()) return
    try {
      await api.equipos.agregar(laboratorio.clave, nueva.trim())
      setNueva("")
      await actualizar()
    } catch (error) {
      await alertas.error("No se pudo agregar el equipo", error)
    }
  }

  const tarjeta = `${c.tarjeta} border-none shadow-lg`

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {[
          ["Total", lista.length, "text-[#1BB827]"],
          ["Libres", cuenta("libre"), "text-[#1BB827]"],
          ["En uso", cuenta("ocupado"), "text-blue-500"],
          ["Fuera de servicio", cuenta("fuera_de_servicio"), "text-red-500"],
        ].map(([titulo, valor, color]) => (
          <Card key={titulo as string} className={tarjeta}>
            <CardContent className="pt-6 text-center">
              <div className={`text-3xl font-bold ${color}`}>{valor}</div>
              <p className={`text-sm ${c.textoSuave}`}>{titulo}</p>
            </CardContent>
          </Card>
        ))}
      </div>

      <Card className={tarjeta}>
        <CardHeader className={encabezadoDeTarjeta(oscuro)}>
          <CardTitle className="text-lg">Equipos de {laboratorio.nombre}</CardTitle>
        </CardHeader>
        <CardContent className="pt-4 space-y-4">
          <div className="flex flex-wrap gap-2">
            <Input type="number" min={1} max={200} value={cantidad} onChange={(e) => setCantidad(e.target.value)} placeholder="Cantidad de equipos" aria-label="Cantidad de equipos" className="w-48" />
            <Button type="button" onClick={configurar} variant="outline">
              <Settings className="w-4 h-4 mr-2" /> Configurar PC-1 a PC-N
            </Button>
            {usuario.rol === "admin" && (
              <>
                <Input value={nueva} onChange={(e) => setNueva(e.target.value)} placeholder="Etiqueta (ej. PC-26)" aria-label="Etiqueta del equipo nuevo" className="w-48" />
                <Button type="button" onClick={agregar} variant="outline">
                  <Plus className="w-4 h-4 mr-2" /> Agregar
                </Button>
              </>
            )}
          </div>
          <div className="grid grid-cols-3 sm:grid-cols-5 md:grid-cols-8 gap-2">
            {lista.map((equipo) => (
              <button
                key={equipo.id}
                type="button"
                onClick={() => setElegido(equipo)}
                title={equipo.ocupadoPor ? `${equipo.ocupadoPor.nombre} desde las ${hora(equipo.ocupadoPor.desde)}` : equipo.notas ?? ""}
                className={`p-3 rounded-xl border-2 text-center transition-transform hover:scale-105 ${
                  equipo.estado === "fuera_de_servicio"
                    ? "border-red-400 bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300"
                    : equipo.estado === "ocupado"
                      ? "border-blue-400 bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300"
                      : "border-green-400 bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300"
                }`}
              >
                <Monitor className="w-6 h-6 mx-auto mb-1" />
                <span className="text-xs font-semibold">{equipo.etiqueta}</span>
                {equipo.reporteAbierto && <AlertTriangle className="w-3 h-3 mx-auto mt-1" />}
              </button>
            ))}
          </div>
        </CardContent>
      </Card>

      <Card className={tarjeta}>
        <CardHeader className={encabezadoDeTarjeta(oscuro)}>
          <CardTitle className="text-lg">Fallas reportadas</CardTitle>
        </CardHeader>
        <CardContent className="pt-4 space-y-3">
          <Tabs value={filtroDeReportes} onValueChange={(v) => setFiltroDeReportes(v as typeof filtroDeReportes)}>
            <TabsList>
              <TabsTrigger value="abiertos">Abiertas</TabsTrigger>
              <TabsTrigger value="reportado">Reportadas</TabsTrigger>
              <TabsTrigger value="en_proceso">En proceso</TabsTrigger>
              <TabsTrigger value="resuelto">Resueltas</TabsTrigger>
            </TabsList>
          </Tabs>
          {reportes?.length === 0 && <p className={c.textoSuave}>No hay fallas en esta lista.</p>}
          {(reportes ?? []).map((reporte) => (
            <SeguimientoDeFalla key={reporte.id} reporte={reporte} c={c} alCambiar={actualizar} />
          ))}
        </CardContent>
      </Card>

      {elegido && (
        <DetalleDeEquipo
          equipo={lista.find((e) => e.id === elegido.id) ?? elegido}
          esAdmin={usuario.rol === "admin"}
          c={c}
          alCerrar={() => setElegido(null)}
          alCambiar={actualizar}
        />
      )}
    </div>
  )
}

/** Una falla y el cambio de su estado. Al resolverse la última del equipo, vuelve a servicio. */
function SeguimientoDeFalla({ reporte, c, alCambiar }: { reporte: ReporteDeEquipo; c: PropsDeVista["c"]; alCambiar: () => void }) {
  const [estado, setEstado] = useState<EstadoDeReporte>(reporte.estado)
  const [comentario, setComentario] = useState(reporte.comentario ?? "")

  const guardar = async () => {
    try {
      await api.reportesDeEquipo.actualizar(reporte.id, { estado, comentario: comentario || null })
      await alertas.breve(estado === "resuelto" ? "Falla resuelta" : "Seguimiento guardado")
      alCambiar()
    } catch (error) {
      await alertas.error("No se pudo guardar el seguimiento", error)
    }
  }

  return (
    <div className={`p-3 rounded-xl ${c.panel} space-y-2`}>
      <div className="flex justify-between gap-2">
        <p className="font-medium">
          <Wrench className="inline w-4 h-4 mr-1" /> {nombreDeEquipo(reporte.equipo.etiqueta)}: {reporte.descripcion}
        </p>
        <span className="text-xs opacity-70 shrink-0">{ESTADOS_DE_REPORTE[reporte.estado]}</span>
      </div>
      <p className="text-xs opacity-70">
        Reportó {reporte.reportadoPor ?? "el sistema anterior"} el {fechaHora(reporte.reportadoEn)}
        {reporte.resueltoEn ? ` · resuelta el ${fechaHora(reporte.resueltoEn)}` : ""}
      </p>
      <div className="flex flex-wrap gap-2">
        <Select value={estado} onValueChange={(v) => setEstado(v as EstadoDeReporte)}>
          <SelectTrigger className="w-40">
            <SelectValue />
          </SelectTrigger>
          <SelectContent className={c.menu}>
            {Object.entries(ESTADOS_DE_REPORTE).map(([valor, nombre]) => (
              <SelectItem key={valor} value={valor}>
                {nombre}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Input value={comentario} onChange={(e) => setComentario(e.target.value)} placeholder="Comentario (qué se hizo, qué falta)" aria-label="Comentario (qué se hizo, qué falta)" className="flex-1 min-w-[12rem]" />
        <Button type="button" onClick={guardar} className={c.botonSecundario}>
          Guardar
        </Button>
      </div>
    </div>
  )
}

/** Un equipo: quién lo usa, sus notas, sacarlo de servicio o reportar una falla. */
function DetalleDeEquipo({ equipo, esAdmin, c, alCerrar, alCambiar }: { equipo: Equipo; esAdmin: boolean; c: PropsDeVista["c"]; alCerrar: () => void; alCambiar: () => void }) {
  const [notas, setNotas] = useState(equipo.notas ?? "")
  const [falla, setFalla] = useState("")

  useEffect(() => setNotas(equipo.notas ?? ""), [equipo.id, equipo.notas])

  const guardar = async (datos: { fueraDeServicio?: boolean; notas?: string | null }, mensaje: string) => {
    try {
      await api.equipos.editar(equipo.id, datos)
      await alertas.breve(mensaje)
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
      await alertas.breve("Falla reportada: el equipo quedó fuera de servicio")
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
      <DialogContent aria-describedby={undefined} className={`${c.tarjeta} max-w-lg`}>
        <DialogHeader>
          <DialogTitle className={c.titulo}>{nombreDeEquipo(equipo.etiqueta)}</DialogTitle>
        </DialogHeader>
        <div className="space-y-4">
          <p className="text-sm">
            {equipo.ocupadoPor
              ? `En uso por ${equipo.ocupadoPor.nombre}${equipo.ocupadoPor.matricula ? ` (${equipo.ocupadoPor.matricula})` : ""} desde las ${hora(equipo.ocupadoPor.desde)}`
              : fuera
                ? "Fuera de servicio"
                : "Libre"}
          </p>
          {equipo.reporteAbierto && (
            <p className="text-sm p-2 rounded-lg bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300">
              Falla {ESTADOS_DE_REPORTE[equipo.reporteAbierto.estado].toLowerCase()}: {equipo.reporteAbierto.descripcion}
            </p>
          )}
          <div className="space-y-1">
            <Label htmlFor="notas-equipo">Notas</Label>
            <Textarea id="notas-equipo" value={notas} onChange={(e) => setNotas(e.target.value)} rows={3} />
            <Button type="button" size="sm" variant="outline" onClick={() => guardar({ notas: notas || null }, "Notas guardadas")}>
              Guardar notas
            </Button>
          </div>
          <div className="space-y-1">
            <Label htmlFor="falla-equipo">Reportar una falla</Label>
            <div className="flex gap-2">
              <Input id="falla-equipo" value={falla} onChange={(e) => setFalla(e.target.value)} placeholder="Qué falla (no enciende, sin red...)" />
              <Button type="button" onClick={reportar} className="bg-red-500 hover:bg-red-600 text-white">
                <AlertTriangle className="w-4 h-4" />
              </Button>
            </div>
          </div>
          <div className="flex flex-wrap gap-2 pt-2">
            <Button type="button" onClick={() => guardar({ fueraDeServicio: !fuera }, fuera ? "De vuelta en servicio" : "Fuera de servicio")} className={fuera ? c.botonSecundario : "bg-gray-500 hover:bg-gray-600 text-white"}>
              {fuera ? <CheckCircle2 className="w-4 h-4 mr-2" /> : <AlertTriangle className="w-4 h-4 mr-2" />}
              {fuera ? "Poner en servicio" : "Sacar de servicio"}
            </Button>
            {esAdmin && (
              <Button type="button" variant="destructive" onClick={retirar}>
                <Trash2 className="w-4 h-4 mr-2" /> Retirar
              </Button>
            )}
          </div>
        </div>
      </DialogContent>
    </Dialog>
  )
}
