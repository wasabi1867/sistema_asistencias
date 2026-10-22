import { useEffect, useState } from "react"
import { useRouter } from "next/navigation"
import { api, type Cuenta, type Rol } from "../lib/api"

/**
 * La cuenta con sesión, tal como la conoce la API. Si no hay sesión o su rol no es uno
 * de `roles`, regresa a la página principal: quien decide es el servidor, no lo que
 * haya guardado el navegador.
 */
export function useSesion(roles: Rol[]) {
  const router = useRouter()
  const [usuario, setUsuario] = useState<Cuenta | null>(null)
  const rolesClave = roles.join(",")

  useEffect(() => {
    let vigente = true
    api.sesion
      .actual()
      .then((cuenta) => {
        if (!vigente) return
        if (!cuenta || !rolesClave.split(",").includes(cuenta.rol)) {
          router.push("/")
          return
        }
        setUsuario(cuenta)
      })
      .catch(() => {
        if (vigente) router.push("/")
      })
    return () => {
      vigente = false
    }
  }, [router, rolesClave])

  return { usuario, cargando: usuario === null }
}
