import { useEffect, useState } from "react"
import { aplicarTema, cambiarTema, temaGuardado, type Tema } from "../lib/tema"

/** El tema de la página, con la función para cambiarlo. */
export function useTema() {
  const [tema, setTema] = useState<Tema>("light")

  useEffect(() => {
    const guardado = temaGuardado()
    setTema(guardado)
    aplicarTema(guardado)
  }, [])

  const alternar = () => {
    const nuevo = cambiarTema()
    setTema(nuevo)
    aplicarTema(nuevo)
  }

  return { oscuro: tema === "dark", alternar }
}
