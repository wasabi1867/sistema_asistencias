import { useCallback, useEffect, useState } from "react"
import { FileDown, Search } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { api, type EntradaDeBitacora } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { tablasExcel } from "../../lib/exportar"
import { fechaHora, hoy } from "../../lib/formato"
import type { PropsDeVista } from "./vista"

/** Las acciones que anota la API, en palabras. Las que vienen de Firebase se muestran tal cual. */
export const ACCIONES: Record<string, string> = {
  crear: "Alta",
  editar: "Cambio",
  eliminar: "Baja",
  iniciar_sesion: "Inicio de sesión",
  cerrar_sesion: "Cierre de sesión",
  sesion_fallida: "Intento fallido de iniciar sesión",
  registro_fallido: "Registro de entrada fallido",
  abrir_clase: "Clase abierta",
  cerrar_clase: "Clase cerrada",
  quitar_asistencia: "Entrada quitada",
  resolver_solicitud: "Solicitud resuelta",
  reportar_equipo: "Falla de equipo reportada",
  configurar_equipos: "Equipos configurados",
  carga_de_alumnos: "Carga de alumnos",
  cambio_masivo: "Cambio masivo de alumnos",
  inscribir: "Inscripción a un curso",
  dar_de_baja: "Baja de un curso",
  cambiar_contrasena: "Cambio de contraseña propia",
  restablecer_contrasena: "Contraseña restablecida",
}

const TABLAS: Record<string, string> = {
  alumnos: "Alumno",
  usuarios: "Cuenta",
  carreras: "Carrera",
  periodos: "Periodo",
  materias: "Materia",
  practicas: "Práctica",
  cursos: "Curso",
  horarios: "Horario",
  equipos: "Equipo",
  reportes_de_equipo: "Falla de equipo",
  clases: "Clase",
  asistencias: "Asistencia",
  solicitudes_ingreso: "Solicitud",
  eventos: "Evento",
  laboratorios: "Laboratorio",
}

const POR_PAGINA = 50

/** Quién hizo qué, cuándo y desde dónde. Solo se consulta: nadie la puede editar. */
export function VistaBitacora({ laboratorio, c }: PropsDeVista) {
  const [desde, setDesde] = useState(hoy())
  const [hasta, setHasta] = useState(hoy())
  const [accion, setAccion] = useState("todas")
  const [texto, setTexto] = useState("")
  const [soloEsteLaboratorio, setSoloEsteLaboratorio] = useState(false)
  const [pagina, setPagina] = useState(1)
  const [respuesta, setRespuesta] = useState<{ entradas: EntradaDeBitacora[]; total: number } | null>(null)

  const filtros = useCallback(
    (extra: { pagina?: number; porPagina?: number } = {}) => ({
      desde,
      hasta,
      accion: accion === "todas" ? undefined : accion,
      q: texto || undefined,
      laboratorio: soloEsteLaboratorio ? laboratorio.clave : undefined,
      ...extra,
    }),
    [desde, hasta, accion, texto, soloEsteLaboratorio, laboratorio.clave],
  )

  const cargar = useCallback(async () => {
    try {
      setRespuesta(await api.bitacora.consultar(filtros({ pagina, porPagina: POR_PAGINA })))
    } catch (error) {
      await alertas.error("No se pudo consultar la bitácora", error)
    }
  }, [filtros, pagina])

  useEffect(() => {
    cargar()
  }, [cargar])

  const exportar = async () => {
    try {
      const todo = await api.bitacora.consultar(filtros({ porPagina: 1000 }))
      await tablasExcel(
        [
          {
            nombre: "Bitácora",
            filas: [
              ["Fecha", "Usuario", "Rol", "Acción", "Sobre", "Detalles", "Laboratorio", "Dirección"],
              ...todo.entradas.map((e) => [fechaHora(e.fecha), e.usuario, e.rol, ACCIONES[e.accion] ?? e.accion, e.entidad ? `${TABLAS[e.entidad] ?? e.entidad} ${e.entidadId ?? ""}` : "", e.detalles, e.laboratorio, e.ip]),
            ],
          },
        ],
        `bitacora_${desde}_a_${hasta}.xlsx`,
      )
    } catch (error) {
      await alertas.error("No se pudo exportar la bitácora", error)
    }
  }

  const paginas = respuesta ? Math.max(1, Math.ceil(respuesta.total / POR_PAGINA)) : 1

  return (
    <div className="space-y-4">
      <Card className={`${c.tarjeta} border-none shadow-lg`}>
        <CardContent className="pt-6 grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
          <div className="space-y-1">
            <Label htmlFor="desde-bitacora">Desde</Label>
            <Input id="desde-bitacora" type="date" value={desde} onChange={(e) => {
                setDesde(e.target.value)
                setPagina(1)
              }} />
          </div>
          <div className="space-y-1">
            <Label htmlFor="hasta-bitacora">Hasta</Label>
            <Input id="hasta-bitacora" type="date" value={hasta} onChange={(e) => {
                setHasta(e.target.value)
                setPagina(1)
              }} />
          </div>
          <div className="space-y-1">
            <Label>Acción</Label>
            <Select value={accion} onValueChange={(v) => {
                setAccion(v)
                setPagina(1)
              }}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent className={c.menu}>
                <SelectItem value="todas">Todas</SelectItem>
                {Object.entries(ACCIONES).map(([valor, nombre]) => (
                  <SelectItem key={valor} value={valor}>
                    {nombre}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1">
            <Label htmlFor="texto-bitacora">Buscar</Label>
            <div className="relative">
              <Search className="w-4 h-4 absolute left-2 top-3 opacity-50" />
              <Input id="texto-bitacora" value={texto} onChange={(e) => {
                setTexto(e.target.value)
                setPagina(1)
              }} placeholder="Usuario o detalle" className="pl-8" />
            </div>
          </div>
          <div className="flex gap-2">
            <Button type="button" variant={soloEsteLaboratorio ? "default" : "outline"} onClick={() => {
                setSoloEsteLaboratorio(!soloEsteLaboratorio)
                setPagina(1)
              }} className="flex-1">
              {soloEsteLaboratorio ? "Este laboratorio" : "Todos"}
            </Button>
            <Button type="button" onClick={exportar} className={c.botonSecundario} title="Exportar a Excel">
              <FileDown className="w-4 h-4" />
            </Button>
          </div>
        </CardContent>
      </Card>

      <p className={`text-sm ${c.textoSuave}`}>{respuesta ? `${respuesta.total} registros` : "Cargando..."}</p>

      <Card className={`${c.tarjeta} border-none shadow-lg`}>
        <CardContent className="p-0 overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Fecha</TableHead>
                <TableHead>Usuario</TableHead>
                <TableHead>Acción</TableHead>
                <TableHead>Detalles</TableHead>
                <TableHead>Dirección</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {(respuesta?.entradas ?? []).map((entrada) => (
                <TableRow key={entrada.id}>
                  <TableCell className="whitespace-nowrap">{fechaHora(entrada.fecha)}</TableCell>
                  <TableCell>
                    {entrada.usuario} {entrada.rol && <span className="opacity-60">({entrada.rol})</span>}
                  </TableCell>
                  <TableCell>
                    {ACCIONES[entrada.accion] ?? entrada.accion}
                    {entrada.entidad && <span className="opacity-60"> · {TABLAS[entrada.entidad] ?? entrada.entidad}</span>}
                  </TableCell>
                  <TableCell className="max-w-md">{entrada.detalles}</TableCell>
                  <TableCell className="opacity-70">{entrada.ip}</TableCell>
                </TableRow>
              ))}
              {respuesta?.entradas.length === 0 && (
                <TableRow>
                  <TableCell colSpan={5} className="text-center py-8 opacity-60">
                    No hay registros con esos filtros
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

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
