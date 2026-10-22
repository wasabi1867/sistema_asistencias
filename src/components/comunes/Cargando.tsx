import { Loader2 } from "lucide-react"

/** La pantalla completa mientras se confirma la sesión o llegan los primeros datos. */
export function Cargando({ texto = "Cargando…" }: { texto?: string }) {
  return (
    <div className="flex min-h-dvh flex-col items-center justify-center gap-3 bg-background text-muted-foreground" role="status">
      <Loader2 className="h-7 w-7 animate-spin text-primary" aria-hidden />
      <span className="text-sm">{texto}</span>
    </div>
  )
}
