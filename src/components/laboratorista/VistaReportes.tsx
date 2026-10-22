import { useCallback, useEffect, useMemo, useState } from "react"
import { BookOpen, CalendarDays, FileSpreadsheet, FileText, Presentation, RefreshCw, Settings2, Users } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { api, type Clase } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { hojaDeClase, hojaDeRegistroExcel, hojaDeRegistroPdf, reporteDeUsoExcel, reporteDeUsoPdf } from "../../lib/exportar"
import { fecha, hora, hoy, inicioDeMes } from "../../lib/formato"
import { CampoDeBusqueda, Campo, Cifra, Listado, Panel, SinDatos } from "../comunes/Bloques"
import { MenuDeAcciones } from "../comunes/MenuDeAcciones"
import type { PropsDeVista } from "./vista"

/** Las clases de un laboratorio en un rango de fechas, con quiénes asistieron. */
function useClases(laboratorio: string, desde: string, hasta: string) {
  const [clases, setClases] = useState<Clase[] | null>(null)
  const cargar = useCallback(async () => {
    setClases(null)
    try {
      setClases((await api.clases.listar({ laboratorio, desde, hasta, conAsistentes: true, porPagina: 1000 })).clases)
    } catch (error) {
      setClases([])
      await alertas.error("No se pudieron cargar las clases", error)
    }
  }, [laboratorio, desde, hasta])
  useEffect(() => {
    cargar()
  }, [cargar])
  return { clases, cargar }
}

/** Reportes del laboratorio: lo de hoy, y el reporte de uso de un periodo con el formato del ITSPP. */
export function VistaReportes(props: PropsDeVista) {
  return (
    <Tabs defaultValue="hoy">
      <TabsList>
        <TabsTrigger value="hoy">Hoy</TabsTrigger>
        <TabsTrigger value="periodo">Por periodo</TabsTrigger>
      </TabsList>
      <TabsContent value="hoy" className="mt-6">
        <ReporteDeHoy {...props} />
      </TabsContent>
      <TabsContent value="periodo" className="mt-6">
        <ReporteDePeriodo {...props} />
      </TabsContent>
    </Tabs>
  )
}

function ReporteDeHoy({ laboratorio }: PropsDeVista) {
  const dia = hoy()
  const { clases, cargar } = useClases(laboratorio.clave, dia, dia)
  const [filtro, setFiltro] = useState("")

  const visibles = (clases ?? []).filter((clase) =>
    [clase.materia, clase.practica?.titulo, clase.docente].some((texto) => texto?.toLowerCase().includes(filtro.toLowerCase())),
  )
  const personas = (clases ?? []).reduce((suma, clase) => suma + clase.asistentes, 0)

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <Cifra etiqueta="Clases hoy" valor={clases?.length} icono={Presentation} />
        <Cifra etiqueta="Materias" valor={clases ? new Set(clases.map((cl) => cl.materia)).size : undefined} icono={BookOpen} />
        <Cifra etiqueta="Personas atendidas" valor={clases ? personas : undefined} icono={Users} />
      </div>

      <Listado
        filtros={
          <>
            <CampoDeBusqueda etiqueta="Buscar por materia, práctica o docente" value={filtro} onChange={(e) => setFiltro(e.target.value)} className="min-w-[14rem] flex-1" />
            <Button type="button" variant="outline" onClick={cargar}>
              <RefreshCw /> Actualizar
            </Button>
          </>
        }
      >
        {clases && visibles.length === 0 ? (
          <SinDatos icono={CalendarDays} titulo={filtro ? "No hay clases que coincidan" : "No hay clases hoy en este laboratorio"} />
        ) : (
          <TablaDeClases clases={visibles} conFecha={false} />
        )}
      </Listado>
    </div>
  )
}

/** Las clases con su horario, asistentes y la descarga de su hoja de registro. */
function TablaDeClases({ clases, conFecha }: { clases: Clase[]; conFecha: boolean }) {
  return (
    <Table>
      <TableHeader>
        <TableRow>
          {conFecha && <TableHead>Fecha</TableHead>}
          <TableHead>Materia</TableHead>
          <TableHead className="hidden md:table-cell">Docente</TableHead>
          <TableHead>Horario</TableHead>
          <TableHead className="text-right">Asistentes</TableHead>
          <TableHead className="w-12">
            <span className="sr-only">Acciones</span>
          </TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {clases.map((clase) => (
          <TableRow key={clase.id}>
            {conFecha && <TableCell className="whitespace-nowrap">{fecha(clase.abiertaEn)}</TableCell>}
            <TableCell>
              <p className="font-medium">
                {clase.materia} {clase.grupo && <span className="font-normal text-muted-foreground">· {clase.grupo}</span>}
              </p>
              <p className="text-xs text-muted-foreground">{clase.practica?.titulo ?? "Sin práctica"}</p>
            </TableCell>
            <TableCell className="hidden text-muted-foreground md:table-cell">{clase.docente ?? "Sin docente"}</TableCell>
            <TableCell className="whitespace-nowrap tabular-nums">
              {clase.abierta ? (
                <Badge variant="success">En curso</Badge>
              ) : (
                <>
                  {hora(clase.abiertaEn)} – {hora(clase.cerradaEn)}
                </>
              )}
            </TableCell>
            <TableCell className="text-right tabular-nums">{clase.asistentes}</TableCell>
            <TableCell>
              <MenuDeAcciones
                etiqueta={`Descargar la hoja de ${clase.materia}`}
                acciones={[
                  { texto: "Hoja en PDF", icono: FileText, alElegir: () => hojaDeRegistroPdf(hojaDeClase(clase)) },
                  { texto: "Hoja en Excel", icono: FileSpreadsheet, alElegir: () => hojaDeRegistroExcel(hojaDeClase(clase)) },
                ]}
              />
            </TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )
}

/** Restar meses a una fecha "AAAA-MM-DD" y devolver el primer día de ese mes. */
function mesAnterior(dia: string, meses: number): string {
  const [anio, mes] = dia.split("-").map(Number)
  const d = new Date(anio, mes - 1 - meses, 1)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-01`
}

function finDeMes(primero: string): string {
  const [anio, mes] = primero.split("-").map(Number)
  return `${primero.slice(0, 8)}${String(new Date(anio, mes, 0).getDate()).padStart(2, "0")}`
}

const RANGOS: Record<string, () => [string, string]> = {
  "Este mes": () => [inicioDeMes(), hoy()],
  "Mes anterior": () => {
    const primero = mesAnterior(hoy(), 1)
    return [primero, finDeMes(primero)]
  },
  "Últimos 3 meses": () => [mesAnterior(hoy(), 2), hoy()],
}

function ReporteDePeriodo({ laboratorio }: PropsDeVista) {
  const [desde, setDesde] = useState(inicioDeMes())
  const [hasta, setHasta] = useState(hoy())
  const { clases } = useClases(laboratorio.clave, desde, hasta)
  const [docente, setDocente] = useState("todos")
  const [materia, setMateria] = useState("todas")
  const [configurar, setConfigurar] = useState(false)
  const [encabezado, setEncabezado] = useState({
    division: "DIVISIÓN DE INGENIERÍA EN SISTEMAS COMPUTACIONALES",
    subdireccion: "SUBDIRECCIÓN ACADÉMICA",
    responsable: "",
  })

  // Lo que la persona escribió en el encabezado se recuerda en esta computadora.
  useEffect(() => {
    try {
      const guardado = localStorage.getItem("encabezadoDeReporte")
      if (guardado) setEncabezado((actual) => ({ ...actual, ...JSON.parse(guardado) }))
    } catch {
      // Sin almacenamiento o con un valor dañado: se queda el de siempre.
    }
  }, [])
  const cambiarEncabezado = (cambios: Partial<typeof encabezado>) => {
    const nuevo = { ...encabezado, ...cambios }
    setEncabezado(nuevo)
    try {
      localStorage.setItem("encabezadoDeReporte", JSON.stringify(nuevo))
    } catch {
      // No pasa nada si no se recuerda.
    }
  }

  const cerradas = useMemo(() => (clases ?? []).filter((clase) => !clase.abierta), [clases])
  const docentes = [...new Set(cerradas.map((cl) => cl.docente).filter(Boolean))] as string[]
  const materias = [...new Set(cerradas.map((cl) => cl.materia))]
  const filtradas = cerradas.filter((cl) => (docente === "todos" || cl.docente === docente) && (materia === "todas" || cl.materia === materia)).reverse()
  const total = filtradas.reduce((suma, cl) => suma + cl.asistentes, 0)
  const datos = { ...encabezado, laboratorio: laboratorio.nombre, periodo: `${fecha(desde)} al ${fecha(hasta)}` }

  const porMateria = Object.entries(
    filtradas.reduce<Record<string, number>>((acc, cl) => ({ ...acc, [cl.materia]: (acc[cl.materia] ?? 0) + 1 }), {}),
  ).sort((a, b) => b[1] - a[1])

  return (
    <div className="space-y-6">
      <Panel
        titulo="Reporte de uso"
        descripcion="Con el formato del ITSPP: las prácticas del periodo y cuántos alumnos atendió el laboratorio."
        acciones={
          <>
            <Button type="button" variant="ghost" size="sm" onClick={() => setConfigurar(!configurar)} aria-expanded={configurar}>
              <Settings2 /> Encabezado
            </Button>
            <Button type="button" variant="outline" size="sm" onClick={() => reporteDeUsoExcel(datos, filtradas)} disabled={filtradas.length === 0}>
              <FileSpreadsheet /> Excel
            </Button>
            <Button type="button" size="sm" onClick={() => reporteDeUsoPdf(datos, filtradas)} disabled={filtradas.length === 0}>
              <FileText /> PDF
            </Button>
          </>
        }
      >
        <div className="space-y-4">
          <div className="flex flex-wrap gap-2">
            {Object.entries(RANGOS).map(([nombre, rango]) => (
              <Button
                key={nombre}
                type="button"
                variant="secondary"
                size="sm"
                onClick={() => {
                  const [d, h] = rango()
                  setDesde(d)
                  setHasta(h)
                }}
              >
                {nombre}
              </Button>
            ))}
          </div>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Campo etiqueta="Desde" htmlFor="desde-reporte">
              <Input id="desde-reporte" type="date" value={desde} onChange={(e) => setDesde(e.target.value)} />
            </Campo>
            <Campo etiqueta="Hasta" htmlFor="hasta-reporte">
              <Input id="hasta-reporte" type="date" value={hasta} onChange={(e) => setHasta(e.target.value)} />
            </Campo>
            <Campo etiqueta="Docente" htmlFor="docente-reporte">
              <Select value={docente} onValueChange={setDocente}>
                <SelectTrigger id="docente-reporte">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="todos">Todos</SelectItem>
                  {docentes.map((d) => (
                    <SelectItem key={d} value={d}>
                      {d}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Campo>
            <Campo etiqueta="Materia" htmlFor="materia-reporte">
              <Select value={materia} onValueChange={setMateria}>
                <SelectTrigger id="materia-reporte">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="todas">Todas</SelectItem>
                  {materias.map((m) => (
                    <SelectItem key={m} value={m}>
                      {m}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Campo>
          </div>
          {configurar && (
            <div className="grid gap-4 rounded-lg border bg-muted/40 p-4 md:grid-cols-3">
              <Campo etiqueta="División" htmlFor="division-reporte">
                <Input id="division-reporte" value={encabezado.division} onChange={(e) => cambiarEncabezado({ division: e.target.value })} />
              </Campo>
              <Campo etiqueta="Subdirección" htmlFor="subdireccion-reporte">
                <Input id="subdireccion-reporte" value={encabezado.subdireccion} onChange={(e) => cambiarEncabezado({ subdireccion: e.target.value })} />
              </Campo>
              <Campo etiqueta="Responsable del laboratorio" htmlFor="responsable-reporte">
                <Input id="responsable-reporte" value={encabezado.responsable} onChange={(e) => cambiarEncabezado({ responsable: e.target.value })} />
              </Campo>
            </div>
          )}
        </div>
      </Panel>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <Cifra etiqueta="Prácticas" valor={clases ? filtradas.length : undefined} icono={Presentation} />
        <Cifra etiqueta="Alumnos atendidos" valor={clases ? total : undefined} icono={Users} />
        <Cifra etiqueta="Promedio por práctica" valor={clases ? (filtradas.length ? (total / filtradas.length).toFixed(1) : "0") : undefined} detalle={porMateria[0] ? `Más prácticas: ${porMateria[0][0]}` : undefined} />
      </div>

      <Listado pie={porMateria.length > 1 ? <span>Por materia: {porMateria.slice(0, 5).map(([m, n]) => `${m} (${n})`).join(", ")}</span> : undefined}>
        {clases && filtradas.length === 0 ? <SinDatos icono={CalendarDays} titulo="No hay clases cerradas en ese periodo" /> : <TablaDeClases clases={filtradas} conFecha />}
      </Listado>
    </div>
  )
}
