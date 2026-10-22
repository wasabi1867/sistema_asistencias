import { useCallback, useEffect, useState } from "react"
import { ChevronDown, FileSpreadsheet, FileText, History } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { cn } from "@/lib/utils"
import { api, type Clase } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { hojaDeClase, hojaDeRegistroExcel, hojaDeRegistroPdf } from "../../lib/exportar"
import { fecha, hora, hoy, inicioDeMes, nombreDeEquipo, turno } from "../../lib/formato"
import { Paginador } from "../comunes/Paginador"
import { CampoDeBusqueda, Listado, SinDatos } from "../comunes/Bloques"
import type { PropsDeVista } from "./vista"

const POR_PAGINA = 30

/** Todas las clases del laboratorio, de la más reciente a la más antigua, con quién asistió a cada una. */
export function VistaHistorial({ laboratorio }: PropsDeVista) {
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

  const visibles = (respuesta?.clases ?? []).filter((clase) =>
    [clase.materia, clase.practica?.titulo, clase.docente, clase.grupo].some((texto) => texto?.toLowerCase().includes(filtro.toLowerCase())),
  )

  return (
    <Listado
      filtros={
        <>
          <label className="flex items-center gap-2 text-sm text-muted-foreground">
            Desde
            <Input type="date" value={desde} onChange={(e) => setDesde(e.target.value)} className="w-40" aria-label="Desde" />
          </label>
          <label className="flex items-center gap-2 text-sm text-muted-foreground">
            hasta
            <Input type="date" value={hasta} onChange={(e) => setHasta(e.target.value)} className="w-40" aria-label="Hasta" />
          </label>
          <CampoDeBusqueda etiqueta="Buscar en esta página: materia, práctica, docente o grupo" value={filtro} onChange={(e) => setFiltro(e.target.value)} className="min-w-[14rem] flex-1" />
        </>
      }
      pie={
        <>
          <span className="tabular-nums">{respuesta ? `${respuesta.total} clases` : "Cargando…"}</span>
          {respuesta && <Paginador pagina={pagina} total={respuesta.total} porPagina={POR_PAGINA} alCambiar={setPagina} />}
        </>
      }
    >
      {respuesta && visibles.length === 0 ? (
        <SinDatos icono={History} titulo="No hay clases cerradas con esos filtros" />
      ) : (
        <ul className="divide-y">
          {visibles.map((clase) => {
            const expandida = abierta === clase.id
            return (
              <li key={clase.id}>
                <button
                  type="button"
                  onClick={() => setAbierta(expandida ? null : clase.id)}
                  aria-expanded={expandida}
                  className="flex w-full items-center gap-4 px-4 py-3 text-left transition-colors hover:bg-muted/40 focus-visible:bg-muted/60 focus-visible:outline-none sm:px-5"
                >
                  <div className="w-24 shrink-0 text-sm">
                    <p className="font-medium tabular-nums">{fecha(clase.abiertaEn)}</p>
                    <p className="text-xs text-muted-foreground tabular-nums">
                      {hora(clase.abiertaEn)}–{hora(clase.cerradaEn)}
                    </p>
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="truncate font-medium">
                      {clase.materia} {clase.grupo && <span className="font-normal text-muted-foreground">· {clase.grupo}</span>}
                      {clase.tipo === "invitado" && (
                        <Badge variant="info" className="ml-2 align-middle">
                          Invitado
                        </Badge>
                      )}
                    </p>
                    <p className="truncate text-xs text-muted-foreground">
                      {clase.docente ?? "Sin docente"} · {clase.practica?.titulo ?? "Sin práctica"}
                    </p>
                  </div>
                  <span className="shrink-0 text-sm tabular-nums text-muted-foreground">{clase.asistentes} asistentes</span>
                  <ChevronDown className={cn("h-4 w-4 shrink-0 text-muted-foreground transition-transform", expandida && "rotate-180")} aria-hidden />
                </button>
                {expandida && (
                  <div className="space-y-3 bg-muted/30 px-4 pb-4 pt-1 sm:px-5">
                    <div className="flex flex-wrap items-center gap-2">
                      <Button type="button" size="sm" variant="outline" onClick={() => hojaDeRegistroPdf(hojaDeClase(clase))}>
                        <FileText /> PDF
                      </Button>
                      <Button type="button" size="sm" variant="outline" onClick={() => hojaDeRegistroExcel(hojaDeClase(clase))}>
                        <FileSpreadsheet /> Excel
                      </Button>
                      <span className="text-xs text-muted-foreground">
                        Abrió {clase.abiertaPor}
                        {clase.cerradaPor ? `, cerró ${clase.cerradaPor}` : ""}
                        {clase.cierre === "automatico" ? " (se cerró sola al cerrar la pestaña)" : ""}
                      </span>
                    </div>
                    {(clase.personas ?? []).length === 0 ? (
                      <p className="text-sm text-muted-foreground">Nadie registró su entrada.</p>
                    ) : (
                      <ul className="grid gap-x-6 gap-y-1 text-sm md:grid-cols-2">
                        {(clase.personas ?? []).map((p) => (
                          <li key={p.id} className="flex justify-between gap-2 border-b border-dashed py-1.5">
                            <span className="min-w-0 truncate">
                              {p.nombre} {p.apellido} <span className="text-xs text-muted-foreground tabular-nums">{p.matricula}</span>
                            </span>
                            <span className="shrink-0 text-xs text-muted-foreground">
                              {p.equipo ? nombreDeEquipo(p.equipo) : "Propio"}
                              {p.grupo || p.semestre ? ` · ${p.semestre ?? ""}${p.grupo ?? ""} ${turno(p.turno)}` : ""}
                            </span>
                          </li>
                        ))}
                      </ul>
                    )}
                  </div>
                )}
              </li>
            )
          })}
        </ul>
      )}
    </Listado>
  )
}
