import { useEffect, useState } from "react"
import { cn } from "@/lib/utils"
import { hora } from "../../lib/formato"

/**
 * La hora y la fecha de hoy. Se pinta hasta que la página ya está en el navegador: la
 * página se compila de antemano y la hora de la compilación no serviría.
 */
export function Reloj({ className }: { className?: string }) {
  const [ahora, setAhora] = useState<Date | null>(null)

  useEffect(() => {
    setAhora(new Date())
    const intervalo = setInterval(() => setAhora(new Date()), 15000)
    return () => clearInterval(intervalo)
  }, [])

  if (!ahora) return null
  const dia = ahora.toLocaleDateString("es-MX", { weekday: "long", day: "numeric", month: "long", timeZone: "America/Hermosillo" })
  return (
    <div className={cn("text-right leading-tight", className)}>
      <p className="text-lg font-semibold tabular-nums">{hora(ahora.toISOString())}</p>
      <p className="text-xs text-muted-foreground first-letter:uppercase">{dia}</p>
    </div>
  )
}
