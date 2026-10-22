"use client"

import { useState, type ComponentType } from "react"
import Link from "next/link"
import { useRouter } from "next/navigation"
import { BookOpen, FolderCog, GraduationCap, LayoutDashboard, LogOut, Menu, Monitor, UserCog, Users, X } from "lucide-react"
import { Button } from "@/components/ui/button"
import { api } from "../lib/api"
import { alertas } from "../lib/alertas"
import { useSesion } from "../hooks/useSesion"
import { useTema } from "../hooks/useTema"
import { Cargando } from "../components/comunes/Cargando"
import { BotonDeTema } from "../components/comunes/BotonDeTema"
import { CambiarContrasena } from "../components/comunes/CambiarContrasena"
import type { PropsDeSeccion } from "../components/admin/seccion"
import { Tablero } from "../components/admin/Tablero"
import { Alumnos } from "../components/admin/Alumnos"
import { Cursos } from "../components/admin/Cursos"
import { Materias } from "../components/admin/Materias"
import { Cuentas } from "../components/admin/Cuentas"
import { Catalogos } from "../components/admin/Catalogos"

const SECCIONES: { id: string; nombre: string; icono: typeof Monitor; componente: ComponentType<PropsDeSeccion> }[] = [
  { id: "tablero", nombre: "Tablero", icono: LayoutDashboard, componente: Tablero },
  { id: "alumnos", nombre: "Alumnos", icono: GraduationCap, componente: Alumnos },
  { id: "cursos", nombre: "Cursos", icono: Users, componente: Cursos },
  { id: "materias", nombre: "Materias y prácticas", icono: BookOpen, componente: Materias },
  { id: "cuentas", nombre: "Cuentas del personal", icono: UserCog, componente: Cuentas },
  { id: "catalogos", nombre: "Carreras, periodos y laboratorios", icono: FolderCog, componente: Catalogos },
]

/** El panel del administrador: lo de todos los laboratorios y los datos de la escuela. */
export default function PanelDelAdministrador() {
  const router = useRouter()
  const { usuario } = useSesion(["admin"])
  const { oscuro, c, alternar } = useTema()
  const [seccion, setSeccion] = useState(SECCIONES[0].id)
  const [menuAbierto, setMenuAbierto] = useState(false)

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

  const menu = (
    <div className="flex flex-col h-full">
      <div className={`p-4 ${c.encabezado}`}>
        <p className="text-white/80 text-xs">Panel del administrador</p>
        <p className="text-white font-bold mt-1">
          {usuario.nombre} {usuario.apellido}
        </p>
      </div>
      <nav className="flex-1 p-2 space-y-1">
        {SECCIONES.map(({ id, nombre, icono: Icono }) => (
          <button
            key={id}
            type="button"
            onClick={() => {
              setSeccion(id)
              setMenuAbierto(false)
            }}
            className={`w-full flex items-center gap-3 px-3 py-2 rounded-lg text-left transition-colors ${
              seccion === id ? (oscuro ? "bg-[#1d5631] text-white" : "bg-[#800040] text-white") : oscuro ? "text-gray-300 hover:bg-[#153731]" : "text-gray-700 hover:bg-[#fff0f5]"
            }`}
          >
            <Icono className="w-5 h-5" /> {nombre}
          </button>
        ))}
        {/* El administrador también puede atender un laboratorio: equipos, horario, reportes y bitácora. */}
        <Link href="/panel-laboratorista" className={`w-full flex items-center gap-3 px-3 py-2 rounded-lg ${oscuro ? "text-gray-300 hover:bg-[#153731]" : "text-gray-700 hover:bg-[#fff0f5]"}`}>
          <Monitor className="w-5 h-5" /> Panel del laboratorio
        </Link>
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
        </header>
        <Seccion usuario={usuario} oscuro={oscuro} c={c} />
      </main>
    </div>
  )
}
