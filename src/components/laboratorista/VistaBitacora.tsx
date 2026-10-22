import { useCallback, useEffect, useState } from "react"
import { FileSpreadsheet, ScrollText } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { api, type EntradaDeBitacora } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { tablasExcel } from "../../lib/exportar"
import { fechaHora, hoy } from "../../lib/formato"
import { Paginador } from "../comunes/Paginador"
import { CampoDeBusqueda, Listado, SinDatos } from "../comunes/Bloques"
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
export function VistaBitacora({ laboratorio }: PropsDeVista) {
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

  // Cualquier cambio de filtro regresa a la primera página.
  const filtrar = (cambio: () => void) => {
    cambio()
    setPagina(1)
  }

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

  return (
    <div className="space-y-4">
      <div className="flex justify-end">
        <Button type="button" variant="outline" onClick={exportar} disabled={!respuesta?.total}>
          <FileSpreadsheet /> Exportar a Excel
        </Button>
      </div>
      <Listado
        filtros={
          <>
            <label className="flex items-center gap-2 text-sm text-muted-foreground">
              Desde
              <Input type="date" value={desde} onChange={(e) => filtrar(() => setDesde(e.target.value))} className="w-40" aria-label="Desde" />
            </label>
            <label className="flex items-center gap-2 text-sm text-muted-foreground">
              hasta
              <Input type="date" value={hasta} onChange={(e) => filtrar(() => setHasta(e.target.value))} className="w-40" aria-label="Hasta" />
            </label>
            <Select value={accion} onValueChange={(v) => filtrar(() => setAccion(v))}>
              <SelectTrigger className="w-full sm:w-56" aria-label="Acción">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="todas">Todas las acciones</SelectItem>
                {Object.entries(ACCIONES).map(([valor, nombre]) => (
                  <SelectItem key={valor} value={valor}>
                    {nombre}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Select value={soloEsteLaboratorio ? "este" : "todos"} onValueChange={(v) => filtrar(() => setSoloEsteLaboratorio(v === "este"))}>
              <SelectTrigger className="w-full sm:w-52" aria-label="Laboratorio">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="todos">Todos los laboratorios</SelectItem>
                <SelectItem value="este">Solo {laboratorio.nombre}</SelectItem>
              </SelectContent>
            </Select>
            <CampoDeBusqueda etiqueta="Usuario o detalle" value={texto} onChange={(e) => filtrar(() => setTexto(e.target.value))} className="min-w-[12rem] flex-1" />
          </>
        }
        pie={
          <>
            <span className="tabular-nums">{respuesta ? `${respuesta.total} registros` : "Cargando…"}</span>
            {respuesta && <Paginador pagina={pagina} total={respuesta.total} porPagina={POR_PAGINA} alCambiar={setPagina} />}
          </>
        }
      >
        {respuesta?.entradas.length === 0 ? (
          <SinDatos icono={ScrollText} titulo="No hay registros con esos filtros" />
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Fecha</TableHead>
                <TableHead>Usuario</TableHead>
                <TableHead>Acción</TableHead>
                <TableHead>Detalles</TableHead>
                <TableHead className="hidden lg:table-cell">Dirección</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {(respuesta?.entradas ?? []).map((entrada) => (
                <TableRow key={entrada.id}>
                  <TableCell className="whitespace-nowrap tabular-nums text-muted-foreground">{fechaHora(entrada.fecha)}</TableCell>
                  <TableCell>
                    <span className="font-medium">{entrada.usuario}</span>
                    {entrada.rol && <span className="block text-xs text-muted-foreground">{entrada.rol}</span>}
                  </TableCell>
                  <TableCell>
                    {ACCIONES[entrada.accion] ?? entrada.accion}
                    {entrada.entidad && <span className="block text-xs text-muted-foreground">{TABLAS[entrada.entidad] ?? entrada.entidad}</span>}
                  </TableCell>
                  <TableCell className="max-w-md text-muted-foreground">{entrada.detalles}</TableCell>
                  <TableCell className="hidden font-mono text-xs text-muted-foreground lg:table-cell">{entrada.ip}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </Listado>
    </div>
  )
}
