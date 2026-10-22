import { ChevronLeft, ChevronRight } from "lucide-react"
import { Button } from "@/components/ui/button"

/** Anterior / Página N de M / Siguiente. No se muestra si todo cabe en una página. */
export function Paginador({ pagina, total, porPagina, alCambiar }: { pagina: number; total: number; porPagina: number; alCambiar: (pagina: number) => void }) {
  const paginas = Math.max(1, Math.ceil(total / porPagina))
  if (paginas <= 1) return null
  return (
    <nav className="flex items-center justify-end gap-2" aria-label="Páginas">
      <span className="mr-2 text-sm text-muted-foreground tabular-nums">
        Página {pagina} de {paginas}
      </span>
      <Button type="button" variant="outline" size="sm" disabled={pagina <= 1} onClick={() => alCambiar(pagina - 1)}>
        <ChevronLeft /> Anterior
      </Button>
      <Button type="button" variant="outline" size="sm" disabled={pagina >= paginas} onClick={() => alCambiar(pagina + 1)}>
        Siguiente <ChevronRight />
      </Button>
    </nav>
  )
}
