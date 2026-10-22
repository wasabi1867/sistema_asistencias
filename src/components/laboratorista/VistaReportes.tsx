import { useCallback, useEffect, useMemo, useState } from "react"
import { FileDown, FileText, RefreshCw, Settings } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { api, type Clase } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { hojaDeClase, hojaDeRegistroExcel, hojaDeRegistroPdf, reporteDeUsoExcel, reporteDeUsoPdf } from "../../lib/exportar"
import { fecha, hora, hoy, inicioDeMes } from "../../lib/formato"
import { encabezadoDeTarjeta, type PropsDeVista } from "./vista"

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
      <TabsList className="mb-4">
        <TabsTrigger value="hoy">Hoy</TabsTrigger>
        <TabsTrigger value="periodo">Por periodo</TabsTrigger>
      </TabsList>
      <TabsContent value="hoy">
        <ReporteDeHoy {...props} />
      </TabsContent>
      <TabsContent value="periodo">
        <ReporteDePeriodo {...props} />
      </TabsContent>
    </Tabs>
  )
}

function ReporteDeHoy({ laboratorio, oscuro, c }: PropsDeVista) {
  const dia = hoy()
  const { clases, cargar } = useClases(laboratorio.clave, dia, dia)
  const [filtro, setFiltro] = useState("")

  const visibles = (clases ?? []).filter((clase) =>
    [clase.materia, clase.practica?.titulo, clase.docente].some((texto) => texto?.toLowerCase().includes(filtro.toLowerCase())),
  )
  const personas = (clases ?? []).reduce((suma, clase) => suma + clase.asistentes, 0)

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        {[
          ["Clases hoy", clases?.length ?? 0],
          ["Materias", new Set((clases ?? []).map((cl) => cl.materia)).size],
          ["Personas atendidas", personas],
        ].map(([titulo, valor]) => (
          <Card key={titulo} className={`${c.tarjeta} border-none shadow-lg`}>
            <CardHeader className={`${encabezadoDeTarjeta(oscuro)} py-3`}>
              <CardTitle className="text-sm font-medium">{titulo}</CardTitle>
            </CardHeader>
            <CardContent className="pt-4 text-3xl font-bold text-[#1BB827]">{valor}</CardContent>
          </Card>
        ))}
      </div>

      <div className="flex gap-2">
        <Input value={filtro} onChange={(e) => setFiltro(e.target.value)} placeholder="Buscar por materia, práctica o docente" aria-label="Buscar por materia, práctica o docente" />
        <Button type="button" variant="outline" onClick={cargar} title="Actualizar">
          <RefreshCw className="w-4 h-4" />
        </Button>
      </div>

      {clases && visibles.length === 0 && <p className={c.textoSuave}>No hay clases {filtro ? "que coincidan" : "hoy en este laboratorio"}.</p>}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {visibles.map((clase) => (
          <Card key={clase.id} className={`${c.tarjeta} border-none shadow-lg`}>
            <CardContent className="pt-6 space-y-2">
              <div className="flex justify-between gap-2">
                <div>
                  <p className={`font-bold ${c.titulo}`}>
                    {clase.materia} {clase.grupo ? `(${clase.grupo})` : ""}
                  </p>
                  <p className={`text-sm ${c.textoSuave}`}>
                    {clase.practica?.titulo ?? "Sin práctica"} · {clase.docente ?? "Sin docente"}
                  </p>
                </div>
                <span className={`h-fit px-2 py-1 rounded-full text-xs ${clase.abierta ? "bg-green-100 text-green-700" : "bg-gray-100 text-gray-600"}`}>
                  {clase.abierta ? "En curso" : `${hora(clase.abiertaEn)}–${hora(clase.cerradaEn)}`}
                </span>
              </div>
              <p className="text-sm">{clase.asistentes} asistentes</p>
              <div className="flex gap-2">
                <Button type="button" size="sm" onClick={() => hojaDeRegistroPdf(hojaDeClase(clase))} className={c.botonSecundario}>
                  <FileText className="w-4 h-4 mr-1" /> PDF
                </Button>
                <Button type="button" size="sm" onClick={() => hojaDeRegistroExcel(hojaDeClase(clase))} className={c.botonPrimario}>
                  <FileDown className="w-4 h-4 mr-1" /> Excel
                </Button>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
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

function ReporteDePeriodo({ laboratorio, oscuro, c }: PropsDeVista) {
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
    const guardado = localStorage.getItem("encabezadoDeReporte")
    if (guardado) setEncabezado((actual) => ({ ...actual, ...JSON.parse(guardado) }))
  }, [])
  const cambiarEncabezado = (cambios: Partial<typeof encabezado>) => {
    const nuevo = { ...encabezado, ...cambios }
    setEncabezado(nuevo)
    localStorage.setItem("encabezadoDeReporte", JSON.stringify(nuevo))
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
      <Card className={`${c.tarjeta} border-none shadow-lg`}>
        <CardContent className="pt-6 space-y-4">
          <div className="flex flex-wrap gap-2">
            {Object.entries(RANGOS).map(([nombre, rango]) => (
              <Button
                key={nombre}
                type="button"
                variant="outline"
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
          <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div className="space-y-1">
              <Label htmlFor="desde-reporte">Desde</Label>
              <Input id="desde-reporte" type="date" value={desde} onChange={(e) => setDesde(e.target.value)} />
            </div>
            <div className="space-y-1">
              <Label htmlFor="hasta-reporte">Hasta</Label>
              <Input id="hasta-reporte" type="date" value={hasta} onChange={(e) => setHasta(e.target.value)} />
            </div>
            <div className="space-y-1">
              <Label>Docente</Label>
              <Select value={docente} onValueChange={setDocente}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent className={c.menu}>
                  <SelectItem value="todos">Todos</SelectItem>
                  {docentes.map((d) => (
                    <SelectItem key={d} value={d}>
                      {d}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label>Materia</Label>
              <Select value={materia} onValueChange={setMateria}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent className={c.menu}>
                  <SelectItem value="todas">Todas</SelectItem>
                  {materias.map((m) => (
                    <SelectItem key={m} value={m}>
                      {m}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button type="button" onClick={() => reporteDeUsoPdf(datos, filtradas)} disabled={filtradas.length === 0} className={c.botonSecundario}>
              <FileText className="w-4 h-4 mr-2" /> Reporte en PDF
            </Button>
            <Button type="button" onClick={() => reporteDeUsoExcel(datos, filtradas)} disabled={filtradas.length === 0} className={c.botonPrimario}>
              <FileDown className="w-4 h-4 mr-2" /> Reporte en Excel
            </Button>
            <Button type="button" variant="outline" onClick={() => setConfigurar(!configurar)}>
              <Settings className="w-4 h-4 mr-2" /> Encabezado del reporte
            </Button>
          </div>
          {configurar && (
            <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
              <Input value={encabezado.division} onChange={(e) => cambiarEncabezado({ division: e.target.value })} placeholder="División" aria-label="División" />
              <Input value={encabezado.subdireccion} onChange={(e) => cambiarEncabezado({ subdireccion: e.target.value })} placeholder="Subdirección" aria-label="Subdirección" />
              <Input value={encabezado.responsable} onChange={(e) => cambiarEncabezado({ responsable: e.target.value })} placeholder="Responsable del laboratorio" aria-label="Responsable del laboratorio" />
            </div>
          )}
        </CardContent>
      </Card>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        {[
          ["Prácticas", filtradas.length],
          ["Alumnos atendidos", total],
          ["Promedio por práctica", filtradas.length ? (total / filtradas.length).toFixed(1) : "0"],
        ].map(([titulo, valor]) => (
          <Card key={titulo} className={`${c.tarjeta} border-none shadow-lg`}>
            <CardHeader className={`${encabezadoDeTarjeta(oscuro)} py-3`}>
              <CardTitle className="text-sm font-medium">{titulo}</CardTitle>
            </CardHeader>
            <CardContent className="pt-4 text-3xl font-bold text-[#1BB827]">{valor}</CardContent>
          </Card>
        ))}
      </div>

      {porMateria.length > 0 && (
        <p className={`text-sm ${c.textoSuave}`}>
          Más prácticas: {porMateria.slice(0, 5).map(([m, n]) => `${m} (${n})`).join(", ")}
        </p>
      )}

      <Card className={`${c.tarjeta} border-none shadow-lg`}>
        <CardContent className="p-0 overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Fecha</TableHead>
                <TableHead>Docente</TableHead>
                <TableHead>Materia</TableHead>
                <TableHead>Práctica</TableHead>
                <TableHead>Grupo</TableHead>
                <TableHead className="text-right">Alumnos</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {filtradas.map((clase) => (
                <TableRow key={clase.id}>
                  <TableCell>{fecha(clase.abiertaEn)}</TableCell>
                  <TableCell>{clase.docente}</TableCell>
                  <TableCell>{clase.materia}</TableCell>
                  <TableCell>{clase.practica?.titulo}</TableCell>
                  <TableCell>{clase.grupo}</TableCell>
                  <TableCell className="text-right">{clase.asistentes}</TableCell>
                </TableRow>
              ))}
              {clases && filtradas.length === 0 && (
                <TableRow>
                  <TableCell colSpan={6} className="text-center py-8 opacity-60">
                    No hay clases cerradas en ese periodo
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  )
}
