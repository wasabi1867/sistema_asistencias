import { useState, type FormEvent } from "react"
import { useRouter } from "next/navigation"
import { ChevronRight, Loader2 } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { api, ErrorDeApi, type Rol } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import type { Colores } from "../../lib/tema"

/** A dónde va cada rol después de iniciar sesión. */
export const PANEL_DE: Record<Rol, string> = { docente: "/lista-asistencias", laboratorista: "/panel-laboratorista", admin: "/AdminPanel" }

/** Inicia sesión y lleva a su panel. Devuelve false si no se pudo (ya avisó por qué). */
function useIniciarSesion() {
  const router = useRouter()
  const [enviando, setEnviando] = useState(false)

  const iniciar = async (usuario: string, contrasena: string, rol: Rol) => {
    setEnviando(true)
    try {
      const cuenta = await api.sesion.iniciar(usuario, contrasena, rol)
      await alertas.breve(`¡Bienvenido, ${cuenta.nombre}!`)
      router.push(PANEL_DE[rol])
      return true
    } catch (error) {
      // Un solo mensaje para usuario y contraseña: así no se puede averiguar qué cuentas existen.
      await alertas.error(error instanceof ErrorDeApi && error.estado === 401 ? "Usuario o contraseña incorrectos" : "No se pudo iniciar sesión", error instanceof ErrorDeApi && error.estado === 401 ? undefined : error)
      return false
    } finally {
      setEnviando(false)
    }
  }
  return { iniciar, enviando }
}

/** La pestaña de docentes y laboratoristas de la página principal. */
export function InicioDeSesionDelPersonal({ c }: { c: Colores }) {
  const [rol, setRol] = useState<"docente" | "laboratorista">("docente")
  const [usuario, setUsuario] = useState("")
  const [contrasena, setContrasena] = useState("")
  const { iniciar, enviando } = useIniciarSesion()
  const entrada = `${c.entrada} rounded-xl border-2 focus:ring-[#1BB827] focus:border-[#1BB827] transition-all duration-300`

  const enviar = (e: FormEvent) => {
    e.preventDefault()
    iniciar(usuario, contrasena, rol)
  }

  return (
    <form onSubmit={enviar} className="space-y-5">
      <div className="space-y-3">
        <Label className={`text-sm sm:text-base font-medium ${c.titulo}`}>Tipo de usuario</Label>
        <Select value={rol} onValueChange={(valor) => setRol(valor as typeof rol)}>
          <SelectTrigger className={entrada}>
            <SelectValue />
          </SelectTrigger>
          <SelectContent className={c.menu}>
            <SelectItem value="docente">Maestro</SelectItem>
            <SelectItem value="laboratorista">Laboratorista</SelectItem>
          </SelectContent>
        </Select>
      </div>
      <div className="space-y-3">
        <Label htmlFor="usuario" className={`text-sm sm:text-base font-medium ${c.titulo}`}>
          Número de empleado o correo
        </Label>
        <Input id="usuario" value={usuario} onChange={(e) => setUsuario(e.target.value)} autoComplete="username" className={entrada} required />
      </div>
      <div className="space-y-3">
        <Label htmlFor="contrasena" className={`text-sm sm:text-base font-medium ${c.titulo}`}>
          Contraseña
        </Label>
        <Input id="contrasena" type="password" value={contrasena} onChange={(e) => setContrasena(e.target.value)} autoComplete="current-password" className={entrada} required />
      </div>
      <Button type="submit" disabled={enviando} className={`w-full py-6 rounded-xl text-base sm:text-lg font-medium flex items-center justify-center gap-2 ${c.botonSecundario}`}>
        {enviando ? <Loader2 className="w-5 h-5 animate-spin" /> : null}
        Iniciar sesión
        {!enviando && <ChevronRight className="w-5 h-5" />}
      </Button>
    </form>
  )
}

/** La ventana de acceso del administrador, desde el menú de la página principal. */
export function AccesoDelAdministrador({ abierto, alCambiar, oscuro, c }: { abierto: boolean; alCambiar: (abierto: boolean) => void; oscuro: boolean; c: Colores }) {
  const [usuario, setUsuario] = useState("")
  const [contrasena, setContrasena] = useState("")
  const { iniciar, enviando } = useIniciarSesion()

  const enviar = async (e: FormEvent) => {
    e.preventDefault()
    if (await iniciar(usuario, contrasena, "admin")) {
      alCambiar(false)
    }
  }

  return (
    <Dialog open={abierto} onOpenChange={alCambiar}>
      <DialogContent className={`sm:max-w-md ${oscuro ? "bg-[#1C4A3F] text-white" : "bg-white"} border-none shadow-lg rounded-2xl`}>
        <DialogHeader>
          <DialogTitle className={`text-center text-xl ${oscuro ? "text-white" : "text-[#1C4A3F]"}`}>Acceso administrador</DialogTitle>
          <DialogDescription className={`text-center ${oscuro ? "text-gray-300" : "text-gray-600"}`}>Ingresa tus credenciales de administrador</DialogDescription>
        </DialogHeader>
        <form onSubmit={enviar} className="space-y-5">
          <div className="space-y-3">
            <Label htmlFor="usuarioAdmin">Correo o matrícula</Label>
            <Input id="usuarioAdmin" value={usuario} onChange={(e) => setUsuario(e.target.value)} autoComplete="username" className={`${c.entrada} rounded-xl border-2`} required />
          </div>
          <div className="space-y-3">
            <Label htmlFor="contrasenaAdmin">Contraseña</Label>
            <Input id="contrasenaAdmin" type="password" value={contrasena} onChange={(e) => setContrasena(e.target.value)} autoComplete="current-password" className={`${c.entrada} rounded-xl border-2`} required />
          </div>
          <Button type="submit" disabled={enviando} className={`w-full py-6 rounded-xl text-base sm:text-lg font-medium ${c.botonSecundario}`}>
            {enviando && <Loader2 className="w-5 h-5 animate-spin mr-2" />}
            Iniciar sesión
          </Button>
        </form>
      </DialogContent>
    </Dialog>
  )
}
