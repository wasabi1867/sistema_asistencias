import { useCallback } from "react"
import { useRouter } from "next/router"

/**
 * La sección abierta de un panel, guardada en la dirección (`?seccion=alumnos`): así
 * recargar, usar "atrás" o compartir el enlace deja a la persona donde estaba.
 */
export function useSeccion<T extends string>(ids: readonly T[], porOmision: T) {
  const router = useRouter()
  const pedida = typeof router.query.seccion === "string" ? router.query.seccion : ""
  const seccion = (ids as readonly string[]).includes(pedida) ? (pedida as T) : porOmision

  const ir = useCallback(
    (nueva: T) => {
      if (nueva === seccion) return
      router.push({ pathname: router.pathname, query: nueva === porOmision ? {} : { seccion: nueva } }, undefined, { shallow: true })
    },
    [router, seccion, porOmision],
  )

  return [seccion, ir] as const
}
