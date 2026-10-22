import { Button } from "@/components/ui/button"

/** Anterior / Página N de M / Siguiente. No se muestra si todo cabe en una página. */
export function Paginador({ pagina, total, porPagina, alCambiar }: { pagina: number; total: number; porPagina: number; alCambiar: (pagina: number) => void }) {
  const paginas = Math.max(1, Math.ceil(total / porPagina))
  if (paginas <= 1) return null
  return (
    <div className="flex items-center justify-center gap-3">
      <Button type="button" variant="outline" disabled={pagina <= 1} onClick={() => alCambiar(pagina - 1)}>
        Anterior
      </Button>
      <span className="text-sm">
        Página {pagina} de {paginas}
      </span>
      <Button type="button" variant="outline" disabled={pagina >= paginas} onClick={() => alCambiar(pagina + 1)}>
        Siguiente
      </Button>
    </div>
  )
}
