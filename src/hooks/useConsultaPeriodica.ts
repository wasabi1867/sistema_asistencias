import { useCallback, useEffect, useRef, useState } from "react"

/**
 * Pide datos a la API cada `cadaMs` milisegundos y los mantiene al día. Sustituye a
 * onSnapshot de Firebase: en lugar de que el servidor avise de cada cambio, la página
 * pregunta seguido. Mientras la pestaña está oculta no pregunta, para no gastar.
 *
 * `consultar` puede devolver undefined para no hacer nada (por ejemplo, si todavía
 * no hay laboratorio elegido). `recargar` pide los datos en ese momento.
 */
export function useConsultaPeriodica<T>(consultar: () => Promise<T> | undefined, cadaMs: number, dependencias: unknown[]) {
  const [datos, setDatos] = useState<T | undefined>(undefined)
  const [error, setError] = useState<unknown>(null)
  const consultarRef = useRef(consultar)
  consultarRef.current = consultar

  const recargar = useCallback(async () => {
    const promesa = consultarRef.current()
    if (!promesa) return
    try {
      const resultado = await promesa
      setDatos(resultado)
      setError(null)
      return resultado
    } catch (e) {
      setError(e)
    }
  }, [])

  useEffect(() => {
    // Al cambiar lo que se consulta (otro laboratorio, otra clase) se olvida lo
    // anterior, para no mostrar un momento los datos que ya no corresponden.
    setDatos(undefined)
    recargar()
    const intervalo = setInterval(() => {
      if (typeof document === "undefined" || document.visibilityState === "visible") {
        recargar()
      }
    }, cadaMs)
    const alVolver = () => {
      if (document.visibilityState === "visible") recargar()
    }
    document.addEventListener("visibilitychange", alVolver)
    return () => {
      clearInterval(intervalo)
      document.removeEventListener("visibilitychange", alVolver)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [cadaMs, recargar, ...dependencias])

  return { datos, error, recargar }
}
