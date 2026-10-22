import { useCallback, useEffect, useRef, useState } from "react"
import { alertas } from "../lib/alertas"

/**
 * Pide datos a la API una vez (y cada vez que cambian `dependencias`), con `recargar`
 * para pedirlos de nuevo después de un cambio. Si falla, avisa con `queFallo`.
 */
export function useDatos<T>(cargar: () => Promise<T>, dependencias: unknown[], queFallo = "No se pudieron cargar los datos") {
  const [datos, setDatos] = useState<T | undefined>(undefined)
  const cargarRef = useRef(cargar)
  cargarRef.current = cargar

  const recargar = useCallback(async () => {
    try {
      const nuevos = await cargarRef.current()
      setDatos(nuevos)
      return nuevos
    } catch (error) {
      await alertas.error(queFallo, error)
    }
  }, [queFallo])

  useEffect(() => {
    recargar()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, dependencias)

  return { datos, recargar }
}
