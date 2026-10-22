import { useEffect, useState, type FormEvent } from "react"
import Link from "next/link"
import { useRouter } from "next/navigation"
import { ArrowLeft, Eye, EyeOff, Loader2, Monitor } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { cn } from "@/lib/utils"
import { api, ErrorDeApi, type Rol } from "../lib/api"
import { alertas } from "../lib/alertas"
import { NOMBRE_DEL_ROL, PANEL_DE, laboratorioDeEstaComputadora } from "../lib/rutas"
import { useTema } from "../hooks/useTema"
import { BotonDeTema } from "../components/comunes/BotonDeTema"
import { Campo } from "../components/comunes/Bloques"
import { Logo } from "../components/comunes/Marca"

const ROLES: Rol[] = ["docente", "laboratorista", "admin"]
// El último rol con que se entró en esta computadora: casi siempre es el mismo.
const ROL_GUARDADO = "rolDeAcceso"

/**
 * El inicio de sesión del personal: docentes, laboratoristas y administradores. El rol
 * se elige aparte porque una misma persona puede tener cuenta en dos roles con el mismo
 * correo.
 */
export default function Entrar() {
  const router = useRouter()
  const { oscuro, alternar } = useTema()
  const [rol, setRol] = useState<Rol>("docente")
  const [usuario, setUsuario] = useState("")
  const [contrasena, setContrasena] = useState("")
  const [verContrasena, setVerContrasena] = useState(false)
  const [enviando, setEnviando] = useState(false)
  const [laboratorio, setLaboratorio] = useState<string | null>(null)

  useEffect(() => {
    try {
      const guardado = localStorage.getItem(ROL_GUARDADO)
      if (ROLES.includes(guardado as Rol)) setRol(guardado as Rol)
    } catch {
      // Sin almacenamiento: se queda en docente.
    }
    const clave = laboratorioDeEstaComputadora()
    if (clave) {
      api.laboratorios
        .listar()
        .then((lista) => setLaboratorio(lista.find((l) => l.clave === clave)?.nombre ?? null))
        .catch(() => setLaboratorio(null))
    }
  }, [])

  const enviar = async (e: FormEvent) => {
    e.preventDefault()
    setEnviando(true)
    try {
      const cuenta = await api.sesion.iniciar(usuario, contrasena, rol)
      try {
        localStorage.setItem(ROL_GUARDADO, rol)
      } catch {
        // No pasa nada si no se recuerda.
      }
      alertas.breve(`¡Bienvenido, ${cuenta.nombre}!`)
      router.push(PANEL_DE[rol])
    } catch (error) {
      // Un solo mensaje para usuario y contraseña: así no se puede averiguar qué cuentas existen.
      const credenciales = error instanceof ErrorDeApi && error.estado === 401
      await alertas.error(credenciales ? "Usuario o contraseña incorrectos" : "No se pudo iniciar sesión", credenciales ? undefined : error)
      setEnviando(false)
    }
  }

  return (
    <div className="flex min-h-dvh flex-col bg-background">
      <div className="flex items-center justify-between p-3 sm:p-4">
        <Button asChild variant="ghost">
          <Link href="/">
            <ArrowLeft /> Registro de alumnos
          </Link>
        </Button>
        <BotonDeTema oscuro={oscuro} alternar={alternar} />
      </div>

      <main className="flex flex-1 items-start justify-center px-4 pb-16 pt-4 sm:items-center sm:pt-0">
        <div className="w-full max-w-sm">
          <div className="mb-6 flex flex-col items-center text-center">
            <Logo className="h-14" />
            <h1 className="mt-5 text-2xl font-semibold tracking-tight">Acceso del personal</h1>
            <p className="mt-1 text-sm text-muted-foreground">Docentes, laboratoristas y administración</p>
          </div>

          <form onSubmit={enviar} className="space-y-5 rounded-xl border bg-card p-5 shadow-sm sm:p-6">
            <fieldset>
              <legend className="mb-1.5 text-sm font-medium">Entro como</legend>
              <div className="grid grid-cols-3 gap-1 rounded-lg bg-muted p-1">
                {ROLES.map((r) => (
                  <button
                    key={r}
                    type="button"
                    aria-pressed={rol === r}
                    onClick={() => setRol(r)}
                    className={cn(
                      "rounded-md px-2 py-2 text-[13px] font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
                      rol === r ? "bg-card text-foreground shadow-sm" : "text-muted-foreground hover:text-foreground",
                    )}
                  >
                    {NOMBRE_DEL_ROL[r]}
                  </button>
                ))}
              </div>
            </fieldset>

            <Campo etiqueta={rol === "admin" ? "Correo o matrícula" : "Número de empleado o correo"} htmlFor="usuario">
              <Input id="usuario" value={usuario} onChange={(e) => setUsuario(e.target.value)} autoComplete="username" autoCapitalize="none" spellCheck={false} required autoFocus />
            </Campo>

            <Campo etiqueta="Contraseña" htmlFor="contrasena">
              <div className="relative">
                <Input id="contrasena" type={verContrasena ? "text" : "password"} value={contrasena} onChange={(e) => setContrasena(e.target.value)} autoComplete="current-password" className="pr-10" required />
                <button
                  type="button"
                  onClick={() => setVerContrasena(!verContrasena)}
                  className="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-r-lg text-muted-foreground hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                  aria-label={verContrasena ? "Ocultar la contraseña" : "Mostrar la contraseña"}
                  title={verContrasena ? "Ocultar la contraseña" : "Mostrar la contraseña"}
                >
                  {verContrasena ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </button>
              </div>
            </Campo>

            <Button type="submit" size="lg" className="w-full" disabled={enviando}>
              {enviando && <Loader2 className="animate-spin" />}
              Iniciar sesión
            </Button>
          </form>

          {rol === "docente" && (
            <p className="mt-4 flex items-start gap-2 px-1 text-sm text-muted-foreground">
              <Monitor className="mt-0.5 h-4 w-4 shrink-0" aria-hidden />
              {laboratorio ? (
                <span>
                  Tu clase se abrirá en <span className="font-medium text-foreground">{laboratorio}</span>, el laboratorio de esta computadora.
                </span>
              ) : (
                <span>Para abrir una clase, primero elige el laboratorio de esta computadora en la página de registro.</span>
              )}
            </p>
          )}
        </div>
      </main>
    </div>
  )
}
