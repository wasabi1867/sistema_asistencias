import { Fragment } from "react"
import { MoreHorizontal, type LucideIcon } from "lucide-react"
import { Button } from "@/components/ui/button"
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from "@/components/ui/dropdown-menu"

export interface Accion {
  texto: string
  icono: LucideIcon
  alElegir: () => void
  /** Va al final, separada y en rojo. */
  peligro?: boolean
  oculta?: boolean
}

/**
 * El botón "⋯" de un renglón con lo que se puede hacer con él. Junta en un menú lo que
 * antes eran cuatro íconos sueltos, y cada opción dice con palabras qué hace.
 */
export function MenuDeAcciones({ acciones, etiqueta }: { acciones: Accion[]; etiqueta: string }) {
  const visibles = acciones.filter((a) => !a.oculta)
  const normales = visibles.filter((a) => !a.peligro)
  const peligrosas = visibles.filter((a) => a.peligro)
  if (visibles.length === 0) return null
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button type="button" variant="ghost" size="icon-sm" aria-label={etiqueta} title={etiqueta} onClick={(e) => e.stopPropagation()}>
          <MoreHorizontal />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent onClick={(e) => e.stopPropagation()}>
        {normales.map(({ texto, icono: Icono, alElegir }) => (
          <DropdownMenuItem key={texto} onSelect={alElegir}>
            <Icono /> {texto}
          </DropdownMenuItem>
        ))}
        {peligrosas.map(({ texto, icono: Icono, alElegir }, i) => (
          <Fragment key={texto}>
            {i === 0 && normales.length > 0 && <DropdownMenuSeparator />}
            <DropdownMenuItem peligro onSelect={alElegir}>
              <Icono /> {texto}
            </DropdownMenuItem>
          </Fragment>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
