import { useCallback, useEffect, useState } from "react"
import { ChevronDown, ChevronUp, FileDown, FileText } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { api, type Clase } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { hojaDeClase, hojaDeRegistroExcel, hojaDeRegistroPdf } from "../../lib/exportar"
import { fecha, hora, hoy, inicioDeMes, turno } from "../../lib/formato"
import { nombreDeEquipo } from "../registro/FormularioDeEntrada"
import type { PropsDeVista } from "./vista"

const POR_PAGINA = 30

/** Todas las clases del laboratorio, de la más reciente a la más antigua, con quién asistió a cada una. */
export function VistaHistorial({ laboratorio, c }: PropsDeVista) {
  const [desde, setDesde] = useState(inicioDeMes())
  const [hasta, setHasta] = useState(hoy())
  const [pagina, setPagina] = useState(1)
  const [respuesta, setRespuesta] = useState<{ clases: Clase[]; total: number } | null>(null)
  const [abierta, setAbierta] = useState<number | null>(null)
  const [filtro, setFiltro] = useState("")

  const cargar = useCallback(async () => {
    try {
      setRespuesta(await api.clases.listar({ laboratorio: laboratorio.clave, estado: "cerradas", desde, hasta, conAsistentes: true, pagina, porPagina: POR_PAGINA }))
    } catch (error) {
      await alertas.error("No se pudo cargar el historial", error)
    }
  }, [laboratorio.clave, desde, hasta, pagina])

  useEffect(() => {
    cargar()
  }, [cargar])

  useEffect(() => setPagina(1), [desde, hasta, laboratorio.clave])

  const paginas = respuesta ? Math.max(1, Math.ceil(respuesta.total / POR_PAGINA)) : 1
  const visibles = (respuesta?.clases ?? []).filter((clase) =>
    [clase.materia, clase.practica?.titulo, clase.docente, clase.grupo].some((texto) => texto?.toLowerCase().includes(filtro.toLowerCase())),
  )

  return (
    <div className="space-y-4">
      <Card className={`${c.tarjeta} border-none shadow-lg`}>
        <CardContent className="pt-6 grid grid-cols-1 md:grid-cols-3 gap-3">
          <div className="space-y-1">
            <Label htmlFor="desde-historial">Desde</Label>
            <Input id="desde-historial" type="date" value={desde} onChange={(e) => setDesde(e.target.value)} />
          </div>
          <div className="space-y-1">
            <Label htmlFor="hasta-historial">Hasta</Label>
            <Input id="hasta-historial" type="date" value={hasta} onChange={(e) => setHasta(e.target.value)} />
          </div>
          <div className="space-y-1">
            <Label htmlFor="buscar-historial">Buscar en esta página</Label>
            <Input id="buscar-historial" value={filtro} onChange={(e) => setFiltro(e.target.value)} placeholder="Materia, práctica, docente o grupo" />
          </div>
        </CardContent>
      </Card>

      <p className={`text-sm ${c.textoSuave}`}>{respuesta ? `${respuesta.total} clases` : "Cargando..."}</p>

      {visibles.map((clase) => (
        <Card key={clase.id} className={`${c.tarjeta} border-none shadow-lg`}>
          <CardContent className="pt-4">
            <button type="button" onClick={() => setAbierta(abierta === clase.id ? null : clase.id)} className="w-full flex justify-between items-center gap-2 text-left">
              <div>
                <p className={`font-bold ${c.titulo}`}>
                  {clase.materia} {clase.grupo ? `(${clase.grupo})` : ""} {clase.tipo === "invitado" ? "· maestro invitado" : ""}
                </p>
                <p className={`text-sm ${c.textoSuave}`}>
                  {fecha(clase.abiertaEn)}, {hora(clase.abiertaEn)}–{hora(clase.cerradaEn)} · {clase.docente ?? "Sin docente"} · {clase.practica?.titulo ?? "Sin práctica"} · {clase.asistentes} asistentes
                </p>
              </div>
              {abierta === clase.id ? <ChevronUp className="w-5 h-5 shrink-0" /> : <ChevronDown className="w-5 h-5 shrink-0" />}
            </button>
            {abierta === clase.id && (
              <div className="mt-4 space-y-3">
                <div className="flex gap-2">
                  <Button type="button" size="sm" onClick={() => hojaDeRegistroPdf(hojaDeClase(clase))} className={c.botonSecundario}>
                    <FileText className="w-4 h-4 mr-1" /> PDF
                  </Button>
                  <Button type="button" size="sm" onClick={() => hojaDeRegistroExcel(hojaDeClase(clase))} className={c.botonPrimario}>
                    <FileDown className="w-4 h-4 mr-1" /> Excel
                  </Button>
                  <span className={`text-xs self-center ${c.textoSuave}`}>
                    Abrió {clase.abiertaPor}
                    {clase.cerradaPor ? `, cerró ${clase.cerradaPor}` : ""}
                    {clase.cierre === "automatico" ? " (se cerró sola al cerrar la pestaña)" : ""}
                  </span>
                </div>
                <ul className="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                  {(clase.personas ?? []).map((p) => (
                    <li key={p.id} className={`p-2 rounded-lg ${c.panel}`}>
                      {p.nombre} {p.apellido} <span className="opacity-60">{p.matricula}</span> · {p.equipo ? nombreDeEquipo(p.equipo) : "Equipo propio"}
                      {p.grupo || p.semestre ? ` · ${p.semestre ?? ""}${p.grupo ?? ""} ${turno(p.turno)}` : ""}
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </CardContent>
        </Card>
      ))}

      {paginas > 1 && (
        <div className="flex items-center justify-center gap-3">
          <Button type="button" variant="outline" disabled={pagina <= 1} onClick={() => setPagina(pagina - 1)}>
            Anterior
          </Button>
          <span className="text-sm">
            Página {pagina} de {paginas}
          </span>
          <Button type="button" variant="outline" disabled={pagina >= paginas} onClick={() => setPagina(pagina + 1)}>
            Siguiente
          </Button>
        </div>
      )}
    </div>
  )
}
