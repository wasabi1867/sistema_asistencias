import { useEffect, useState } from "react"
import { useRouter } from "next/navigation"
import { API, type Rol, type UsuarioEnSesion } from "../lib/api"

/**
 * La sesión del personal, tal como la conoce la API. Si no hay sesión o el rol no
 * es uno de `roles`, regresa a la página principal.
 *
 * Antes cada pantalla confiaba en lo que hubiera en localStorage (maestroId, adminId),
 * que cualquiera podía escribir a mano; ahora quien decide es el servidor.
 */
export function useSesion(roles: Rol[]) {
  const router = useRouter()
  const [usuario, setUsuario] = useState<UsuarioEnSesion | null>(null)
  const [cargando, setCargando] = useState(true)
  const rolesClave = roles.join(",")

  useEffect(() => {
    let vigente = true
    API.sesion()
      .then((u) => {
        if (!vigente) return
        if (!u || !rolesClave.split(",").includes(u.rol)) {
          router.push("/")
          return
        }
        setUsuario(u)
        setCargando(false)
      })
      .catch(() => {
        if (vigente) router.push("/")
      })
    return () => {
      vigente = false
    }
  }, [router, rolesClave])

  return { usuario, cargando }
}
