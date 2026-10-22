import { Moon, Sun } from "lucide-react"
import { Button } from "@/components/ui/button"

/** El botón que cambia entre tema claro y oscuro, para las páginas sin menú de usuario. */
export function BotonDeTema({ oscuro, alternar }: { oscuro: boolean; alternar: () => void }) {
  const texto = oscuro ? "Usar tema claro" : "Usar tema oscuro"
  return (
    <Button type="button" variant="ghost" size="icon" onClick={alternar} title={texto} aria-label={texto}>
      {oscuro ? <Sun /> : <Moon />}
    </Button>
  )
}
