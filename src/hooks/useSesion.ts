import { useEffect, useState } from "react"
import { useRouter } from "next/navigation"
import { api, type Cuenta, type Rol } from "../lib/api"
import { recordarPanelActual } from "../lib/rutas"

/**
 * La cuenta con sesión, tal como la conoce la API. Si no hay sesión o la cuenta no
 * tiene ninguno de `roles`, lleva al inicio de sesión: quien decide es el servidor, no
 * lo que haya guardado el navegador.
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
        if (!cuenta || !cuenta.roles.some((r) => rolesClave.split(",").includes(r))) {
          router.push("/entrar")
          return
        }
        recordarPanelActual()
        setUsuario(cuenta)
      })
      .catch(() => {
        if (vigente) router.push("/entrar")
      })
    return () => {
      vigente = false
    }
  }, [router, rolesClave])

  return { usuario, cargando: usuario === null }
}
