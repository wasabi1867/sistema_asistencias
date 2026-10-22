"use client"

import { useEffect, useState, type ComponentType } from "react"
import { useRouter } from "next/navigation"
import { BarChart3, Calendar, History, LogOut, Menu, Monitor, ScrollText, UserCheck, Users, X, XCircle } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { api, type Clase, type Laboratorio } from "../lib/api"
import { alertas } from "../lib/alertas"
import { useSesion } from "../hooks/useSesion"
import { useTema } from "../hooks/useTema"
import { useConsultaPeriodica } from "../hooks/useConsultaPeriodica"
import { Cargando } from "../components/comunes/Cargando"
import { BotonDeTema } from "../components/comunes/BotonDeTema"
import { CambiarContrasena } from "../components/comunes/CambiarContrasena"
import type { PropsDeVista } from "../components/laboratorista/vista"
import { VistaReportes } from "../components/laboratorista/VistaReportes"
import { VistaHistorial } from "../components/laboratorista/VistaHistorial"
import { VistaEnElLaboratorio } from "../components/laboratorista/VistaEnElLaboratorio"
import { VistaEquipos } from "../components/laboratorista/VistaEquipos"
import { VistaHorario } from "../components/laboratorista/VistaHorario"
import { VistaInvitadosYEventos } from "../components/laboratorista/VistaInvitadosYEventos"
import { VistaBitacora } from "../components/laboratorista/VistaBitacora"

const VISTAS: { id: string; nombre: string; icono: typeof Monitor; componente: ComponentType<PropsDeVista> }[] = [
  { id: "laboratorio", nombre: "En el laboratorio", icono: Users, componente: VistaEnElLaboratorio },
  { id: "reportes", nombre: "Reportes", icono: BarChart3, componente: VistaReportes },
  { id: "historial", nombre: "Historial de clases", icono: History, componente: VistaHistorial },
  { id: "equipos", nombre: "Equipos", icono: Monitor, componente: VistaEquipos },
  { id: "horario", nombre: "Horario", icono: Calendar, componente: VistaHorario },
  { id: "invitado", nombre: "Maestro invitado y eventos", icono: UserCheck, componente: VistaInvitadosYEventos },
  { id: "bitacora", nombre: "Bitácora", icono: ScrollText, componente: VistaBitacora },
]

/** El panel de quien atiende el laboratorio: lo que pasa ahora, reportes, equipos, horario y bitácora. */
export default function PanelDelLaboratorista() {
  const router = useRouter()
  const { usuario } = useSesion(["laboratorista", "admin"])
  const { oscuro, c, alternar } = useTema()
  const [laboratorios, setLaboratorios] = useState<Laboratorio[]>([])
  const [clave, setClave] = useState("")
  const [vista, setVista] = useState(VISTAS[0].id)
  const [menuAbierto, setMenuAbierto] = useState(false)

  useEffect(() => {
    if (!usuario) return
    api.laboratorios
      .listar()
      .then((lista) => {
        setLaboratorios(lista)
        const guardada = localStorage.getItem("laboratorio")
        setClave(lista.some((l) => l.clave === guardada) ? (guardada as string) : (lista[0]?.clave ?? ""))
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
    localStorage.setItem("laboratorio", nueva)
  }

  const cerrarDeEmergencia = async (clase: Clase) => {
    if (!(await alertas.confirmar(`¿Finalizar la clase de ${clase.materia}?`, `${clase.docente ?? "Sin docente"}. Se registrará la salida de todos los que siguen dentro.`, { aceptar: "Finalizar", peligro: true }))) return
    try {
      await api.clases.cerrar(clase.id)
      await alertas.breve("Clase finalizada")
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

  const menu = (
    <div className="flex flex-col h-full">
      <div className={`p-4 ${c.encabezado}`}>
        <p className="text-white/80 text-xs">Panel del laboratorio</p>
        <Select value={clave} onValueChange={elegirLaboratorio}>
          <SelectTrigger className="mt-1 bg-white/20 border-none text-white font-bold">
            <SelectValue />
          </SelectTrigger>
          <SelectContent className={c.menu}>
            {laboratorios.map((l) => (
              <SelectItem key={l.clave} value={l.clave}>
                {l.nombre}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <p className="text-white/80 text-xs mt-2">
          {usuario.nombre} {usuario.apellido}
        </p>
      </div>
      <nav className="flex-1 p-2 space-y-1">
        {VISTAS.map(({ id, nombre, icono: Icono }) => (
          <button
            key={id}
            type="button"
            onClick={() => {
              setVista(id)
              setMenuAbierto(false)
            }}
            className={`w-full flex items-center gap-3 px-3 py-2 rounded-lg text-left transition-colors ${
              vista === id ? (oscuro ? "bg-[#1d5631] text-white" : "bg-[#800040] text-white") : oscuro ? "text-gray-300 hover:bg-[#153731]" : "text-gray-700 hover:bg-[#fff0f5]"
            }`}
          >
            <Icono className="w-5 h-5" /> {nombre}
          </button>
        ))}
      </nav>
      <div className="p-3 space-y-2">
        <CambiarContrasena className="w-full" />
        <Button type="button" onClick={cerrarSesion} className="w-full bg-red-500 hover:bg-red-600 text-white">
          <LogOut className="w-4 h-4 mr-2" /> Cerrar sesión
        </Button>
      </div>
    </div>
  )

  return (
    <div className={`min-h-screen flex ${oscuro ? "bg-[#0c1f1a]" : "bg-[#f0fff4]"} transition-colors duration-300`}>
      <aside className={`hidden lg:block w-72 shrink-0 ${c.tarjeta} shadow-lg min-h-screen`}>{menu}</aside>
      {menuAbierto && (
        <div className="lg:hidden fixed inset-0 z-50 flex">
          <div className={`w-72 ${c.tarjeta} shadow-xl`}>{menu}</div>
          <button type="button" className="flex-1 bg-black/50" onClick={() => setMenuAbierto(false)} aria-label="Cerrar el menú" />
        </div>
      )}

      <main className="flex-1 min-w-0 p-4 sm:p-6 space-y-6">
        <BotonDeTema oscuro={oscuro} alternar={alternar} />
        <header className="flex items-center gap-3 pr-16">
          <button type="button" className={`lg:hidden p-2 rounded-lg ${c.tarjeta}`} onClick={() => setMenuAbierto(true)} aria-label="Abrir el menú">
            {menuAbierto ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
          </button>
          <h1 className={`text-2xl font-bold ${c.titulo}`}>{actual.nombre}</h1>
          <div className="ml-auto flex flex-wrap gap-2 justify-end">
            {(abiertas?.clases ?? []).map((clase) => (
              <Button key={clase.id} type="button" onClick={() => cerrarDeEmergencia(clase)} className="bg-red-500 hover:bg-red-600 text-white rounded-full" title="Finalizar la clase">
                <XCircle className="w-4 h-4 mr-1" /> Finalizar {clase.tipo === "invitado" ? "clase de invitado" : clase.materia}
              </Button>
            ))}
          </div>
        </header>
        <Vista laboratorio={laboratorio} usuario={usuario} oscuro={oscuro} c={c} />
      </main>
    </div>
  )
}
