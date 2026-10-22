import type { ComponentType } from "react"
import { useRouter } from "next/navigation"
import { BookOpen, FolderCog, GraduationCap, LayoutDashboard, Monitor, UserCog, Users } from "lucide-react"
import { api } from "../lib/api"
import { alertas } from "../lib/alertas"
import { PANEL_DE } from "../lib/rutas"
import { useSesion } from "../hooks/useSesion"
import { useSeccion } from "../hooks/useSeccion"
import { useConsultaPeriodica } from "../hooks/useConsultaPeriodica"
import { Cargando } from "../components/comunes/Cargando"
import { EstructuraDelPanel, type SeccionDelMenu } from "../components/comunes/EstructuraDelPanel"
import type { IdDeSeccion, PropsDeSeccion } from "../components/admin/seccion"
import { Tablero } from "../components/admin/Tablero"
import { Alumnos } from "../components/admin/Alumnos"
import { Cursos } from "../components/admin/Cursos"
import { Materias } from "../components/admin/Materias"
import { Cuentas } from "../components/admin/Cuentas"
import { Catalogos } from "../components/admin/Catalogos"

const SECCIONES: (SeccionDelMenu<IdDeSeccion> & { componente: ComponentType<PropsDeSeccion>; descripcion: string })[] = [
  { id: "tablero", nombre: "Tablero", icono: LayoutDashboard, componente: Tablero, descripcion: "Lo que pasa ahora en todos los laboratorios" },
  { id: "alumnos", nombre: "Alumnos", icono: GraduationCap, grupo: "Escuela", componente: Alumnos, descripcion: "Alta, cambios y carga desde Excel" },
  { id: "cursos", nombre: "Cursos", icono: Users, grupo: "Escuela", componente: Cursos, descripcion: "Materia, grupo y docente de cada periodo" },
  { id: "materias", nombre: "Materias y prácticas", icono: BookOpen, grupo: "Escuela", componente: Materias, descripcion: "El catálogo de materias y sus prácticas comunes" },
  { id: "cuentas", nombre: "Cuentas del personal", icono: UserCog, grupo: "Configuración", componente: Cuentas, descripcion: "Docentes, laboratoristas y administradores" },
  { id: "catalogos", nombre: "Carreras, periodos y laboratorios", icono: FolderCog, grupo: "Configuración", componente: Catalogos, descripcion: "Los catálogos de la escuela" },
]
const IDS = SECCIONES.map((s) => s.id)

/** El panel del administrador: lo de todos los laboratorios y los datos de la escuela. */
export default function PanelDelAdministrador() {
  const router = useRouter()
  const { usuario } = useSesion(["admin"])
  const [seccion, irA] = useSeccion<IdDeSeccion>(IDS, "tablero")
  const { datos: estadisticas } = useConsultaPeriodica(() => (usuario ? api.estadisticas() : undefined), 30000, [usuario?.id])

  const cerrarSesion = async () => {
    if (!(await alertas.confirmar("¿Cerrar sesión?", "Saldrás del panel del administrador.", { aceptar: "Cerrar sesión" }))) return
    await api.sesion.cerrar().catch(() => undefined)
    router.push("/")
  }

  if (!usuario) {
    return <Cargando />
  }

  const actual = SECCIONES.find((s) => s.id === seccion) ?? SECCIONES[0]
  const Seccion = actual.componente
  const secciones = SECCIONES.map((s) => (s.id === "tablero" ? { ...s, pendientes: estadisticas?.solicitudesPendientes } : s))

  return (
    <EstructuraDelPanel
      panel="Administración"
      usuario={usuario}
      secciones={secciones}
      seccion={seccion}
      alElegir={irA}
      // El administrador también puede atender un laboratorio: equipos, horario, reportes y bitácora.
      enlaces={[{ texto: "Panel del laboratorio", href: PANEL_DE.laboratorista, icono: Monitor }]}
      titulo={actual.nombre}
      subtitulo={actual.descripcion}
      alCerrarSesion={cerrarSesion}
    >
      <Seccion key={actual.id} usuario={usuario} irA={irA} />
    </EstructuraDelPanel>
  )
}
