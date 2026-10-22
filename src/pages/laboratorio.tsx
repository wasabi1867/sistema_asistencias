import { useEffect, useState, type ComponentType } from "react"
import { useRouter } from "next/navigation"
import { BarChart3, Calendar, ChevronDown, History, LayoutDashboard, Monitor, ScrollText, Square, UserCheck, Users } from "lucide-react"
import { Button } from "@/components/ui/button"
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from "@/components/ui/dropdown-menu"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { api, type Clase, type Laboratorio } from "../lib/api"
import { alertas } from "../lib/alertas"
import { hora } from "../lib/formato"
import { PANEL_DE, laboratorioDeEstaComputadora, recordarLaboratorio } from "../lib/rutas"
import { useSesion } from "../hooks/useSesion"
import { useSeccion } from "../hooks/useSeccion"
import { useConsultaPeriodica } from "../hooks/useConsultaPeriodica"
import { Cargando } from "../components/comunes/Cargando"
import { EstructuraDelPanel, type SeccionDelMenu } from "../components/comunes/EstructuraDelPanel"
import { PuntoEnVivo } from "../components/comunes/Bloques"
import type { PropsDeVista } from "../components/laboratorista/vista"
import { VistaReportes } from "../components/laboratorista/VistaReportes"
import { VistaHistorial } from "../components/laboratorista/VistaHistorial"
import { VistaEnElLaboratorio } from "../components/laboratorista/VistaEnElLaboratorio"
import { VistaEquipos } from "../components/laboratorista/VistaEquipos"
import { VistaHorario } from "../components/laboratorista/VistaHorario"
import { VistaInvitadosYEventos } from "../components/laboratorista/VistaInvitadosYEventos"
import { VistaBitacora } from "../components/laboratorista/VistaBitacora"

type IdDeVista = "laboratorio" | "historial" | "reportes" | "bitacora" | "equipos" | "horario" | "invitado"

const VISTAS: (SeccionDelMenu<IdDeVista> & { componente: ComponentType<PropsDeVista> })[] = [
  { id: "laboratorio", nombre: "En el laboratorio", icono: Users, componente: VistaEnElLaboratorio },
  { id: "historial", nombre: "Historial de clases", icono: History, grupo: "Registros", componente: VistaHistorial },
  { id: "reportes", nombre: "Reportes", icono: BarChart3, grupo: "Registros", componente: VistaReportes },
  { id: "bitacora", nombre: "Bitácora", icono: ScrollText, grupo: "Registros", componente: VistaBitacora },
  { id: "equipos", nombre: "Equipos", icono: Monitor, grupo: "Laboratorio", componente: VistaEquipos },
  { id: "horario", nombre: "Horario", icono: Calendar, grupo: "Laboratorio", componente: VistaHorario },
  { id: "invitado", nombre: "Maestro invitado y eventos", icono: UserCheck, grupo: "Laboratorio", componente: VistaInvitadosYEventos },
]
const IDS = VISTAS.map((v) => v.id)

/** El panel de quien atiende el laboratorio: lo que pasa ahora, reportes, equipos, horario y bitácora. */
export default function PanelDelLaboratorista() {
  const router = useRouter()
  const { usuario } = useSesion(["laboratorista", "admin"])
  const [vista, irA] = useSeccion<IdDeVista>(IDS, "laboratorio")
  const [laboratorios, setLaboratorios] = useState<Laboratorio[]>([])
  const [clave, setClave] = useState("")

  useEffect(() => {
    if (!usuario) return
    api.laboratorios
      .listar()
      .then((lista) => {
        setLaboratorios(lista)
        const guardada = laboratorioDeEstaComputadora()
        setClave(lista.some((l) => l.clave === guardada) ? guardada : (lista[0]?.clave ?? ""))
      })
      .catch((error) => alertas.error("No se pudieron cargar los laboratorios", error))
  }, [usuario])

  const laboratorio = laboratorios.find((l) => l.clave === clave) ?? null
  // Las clases abiertas, para poder cerrarlas en una emergencia (el docente se fue sin cerrar).
  const { datos: abiertas, recargar: recargarAbiertas } = useConsultaPeriodica(
    () => (laboratorio ? api.clases.listar({ laboratorio: laboratorio.clave, estado: "abiertas" }) : undefined),
    5000,
    [laboratorio?.clave],
  )

  const elegirLaboratorio = (nueva: string) => {
    setClave(nueva)
    recordarLaboratorio(nueva)
  }

  const cerrarDeEmergencia = async (clase: Clase) => {
    if (!(await alertas.confirmar(`¿Finalizar la clase de ${clase.materia}?`, `${clase.docente ?? "Sin docente"}. Se registrará la salida de todos los que siguen dentro.`, { aceptar: "Finalizar", peligro: true }))) return
    try {
      await api.clases.cerrar(clase.id)
      alertas.breve("Clase finalizada")
      await recargarAbiertas()
    } catch (error) {
      await alertas.error("No se pudo finalizar la clase", error)
    }
  }

  const cerrarSesion = async () => {
    if (!(await alertas.confirmar("¿Cerrar sesión?", "Saldrás del panel del laboratorio.", { aceptar: "Cerrar sesión" }))) return
    await api.sesion.cerrar().catch(() => undefined)
    router.push("/")
  }

  if (!usuario || !laboratorio) {
    return <Cargando />
  }

  const actual = VISTAS.find((v) => v.id === vista) ?? VISTAS[0]
  const Vista = actual.componente
  const clasesAbiertas = abiertas?.clases ?? []

  return (
    <EstructuraDelPanel
      panel="Panel del laboratorio"
      usuario={usuario}
      secciones={VISTAS}
      seccion={vista}
      alElegir={irA}
      arribaDelMenu={
        <div className="space-y-1.5">
          <label htmlFor="laboratorio-del-panel" className="px-0.5 text-xs font-medium text-muted-foreground">
            Laboratorio
          </label>
          <Select value={clave} onValueChange={elegirLaboratorio}>
            <SelectTrigger id="laboratorio-del-panel" className="font-medium">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {laboratorios.map((l) => (
                <SelectItem key={l.clave} value={l.clave}>
                  {l.nombre}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      }
      enlaces={usuario.rol === "admin" ? [{ texto: "Panel del administrador", href: PANEL_DE.admin, icono: LayoutDashboard }] : undefined}
      titulo={actual.nombre}
      subtitulo={laboratorio.nombre}
      acciones={
        clasesAbiertas.length > 0 && (
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button type="button" variant="outline" className="gap-2">
                <PuntoEnVivo />
                <span className="hidden sm:inline">{clasesAbiertas.length === 1 ? "1 clase abierta" : `${clasesAbiertas.length} clases abiertas`}</span>
                <span className="sm:hidden tabular-nums">{clasesAbiertas.length}</span>
                <ChevronDown className="text-muted-foreground" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-72">
              <DropdownMenuLabel className="text-xs font-medium text-muted-foreground">Finalizar en una emergencia</DropdownMenuLabel>
              <DropdownMenuSeparator />
              {clasesAbiertas.map((clase) => (
                <DropdownMenuItem key={clase.id} peligro onSelect={() => cerrarDeEmergencia(clase)}>
                  <Square />
                  <span className="min-w-0">
                    <span className="block truncate">Finalizar {clase.tipo === "invitado" ? "clase de invitado" : clase.materia}</span>
                    <span className="block text-xs font-normal text-muted-foreground">
                      {clase.docente ?? "Sin docente"} · desde las {hora(clase.abiertaEn)}
                    </span>
                  </span>
                </DropdownMenuItem>
              ))}
            </DropdownMenuContent>
          </DropdownMenu>
        )
      }
      alCerrarSesion={cerrarSesion}
    >
      <Vista key={`${actual.id}-${laboratorio.clave}`} laboratorio={laboratorio} usuario={usuario} />
    </EstructuraDelPanel>
  )
}
