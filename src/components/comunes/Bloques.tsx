import type { InputHTMLAttributes, ReactNode } from "react"
import { Search, type LucideIcon } from "lucide-react"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { cn } from "@/lib/utils"

/**
 * Las piezas con que se arman las pantallas, para que todas se vean igual: un panel con
 * título, una cifra, el aviso de que no hay nada y un campo con su etiqueta.
 */

/** Un bloque con título y, a la derecha, sus botones. `sinRelleno` es para tablas de orilla a orilla. */
export function Panel({
  titulo,
  descripcion,
  acciones,
  sinRelleno = false,
  className,
  children,
}: {
  titulo?: ReactNode
  descripcion?: ReactNode
  acciones?: ReactNode
  sinRelleno?: boolean
  className?: string
  children: ReactNode
}) {
  return (
    <section className={cn("rounded-xl border bg-card text-card-foreground shadow-sm", className)}>
      {(titulo || acciones) && (
        <header className="flex flex-wrap items-start justify-between gap-x-4 gap-y-2 border-b px-4 py-3 sm:px-5">
          <div className="min-w-0">
            {titulo && <h2 className="text-[15px] font-semibold leading-tight">{titulo}</h2>}
            {descripcion && <p className="mt-0.5 text-sm text-muted-foreground">{descripcion}</p>}
          </div>
          {acciones && <div className="flex flex-wrap items-center gap-2">{acciones}</div>}
        </header>
      )}
      <div className={sinRelleno ? "" : "p-4 sm:p-5"}>{children}</div>
    </section>
  )
}

const TONOS = {
  neutral: "text-foreground",
  primary: "text-primary",
  success: "text-success",
  warning: "text-warning",
  destructive: "text-destructive",
  info: "text-info",
} as const

export type Tono = keyof typeof TONOS

/** Un número con su etiqueta. Con `alElegir` se vuelve botón y lleva a donde está el detalle. */
export function Cifra({
  etiqueta,
  valor,
  icono: Icono,
  tono = "neutral",
  detalle,
  alElegir,
}: {
  etiqueta: string
  valor: ReactNode
  icono?: LucideIcon
  tono?: Tono
  detalle?: ReactNode
  alElegir?: () => void
}) {
  const contenido = (
    <>
      <div className="flex items-center justify-between gap-2 text-sm text-muted-foreground">
        <span className="truncate">{etiqueta}</span>
        {Icono && <Icono className="h-4 w-4 shrink-0" aria-hidden />}
      </div>
      <div className={cn("mt-1 text-2xl font-semibold tabular-nums tracking-tight", TONOS[tono])}>{valor ?? "–"}</div>
      {detalle && <div className="mt-0.5 truncate text-xs text-muted-foreground">{detalle}</div>}
    </>
  )
  const clase = "flex flex-col justify-start rounded-xl border bg-card p-4 text-left shadow-sm"
  return alElegir ? (
    <button type="button" onClick={alElegir} className={cn(clase, "transition-colors hover:border-primary/40 hover:bg-accent/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring")}>
      {contenido}
    </button>
  ) : (
    <div className={clase}>{contenido}</div>
  )
}

/** Lo que se muestra cuando una lista está vacía: qué pasa y, si aplica, qué hacer. */
export function SinDatos({ icono: Icono, titulo, children, className }: { icono?: LucideIcon; titulo: string; children?: ReactNode; className?: string }) {
  return (
    <div className={cn("flex flex-col items-center justify-center gap-2 px-4 py-10 text-center", className)}>
      {Icono && (
        <div className="mb-1 flex h-11 w-11 items-center justify-center rounded-full bg-muted">
          <Icono className="h-5 w-5 text-muted-foreground" aria-hidden />
        </div>
      )}
      <p className="font-medium">{titulo}</p>
      {children && <div className="max-w-sm text-sm text-muted-foreground">{children}</div>}
    </div>
  )
}

/** Una etiqueta con su campo y, debajo, una ayuda. */
export function Campo({ etiqueta, htmlFor, ayuda, className, children }: { etiqueta: ReactNode; htmlFor?: string; ayuda?: ReactNode; className?: string; children: ReactNode }) {
  return (
    <div className={cn("space-y-1.5", className)}>
      <Label htmlFor={htmlFor}>{etiqueta}</Label>
      {children}
      {ayuda && <p className="text-xs text-muted-foreground">{ayuda}</p>}
    </div>
  )
}

/** Un campo de búsqueda con su lupa. La etiqueta es para lectores de pantalla y es también el texto de ejemplo. */
export function CampoDeBusqueda({ etiqueta, className, ...props }: { etiqueta: string; className?: string } & Omit<InputHTMLAttributes<HTMLInputElement>, "className">) {
  return (
    <div className={cn("relative", className)}>
      <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" aria-hidden />
      <Input type="search" placeholder={etiqueta} aria-label={etiqueta} className="pl-9" {...props} />
    </div>
  )
}

/** Un listado: arriba los filtros, en medio la tabla y abajo el pie (cuántos hay, páginas). */
export function Listado({ filtros, pie, children }: { filtros?: ReactNode; pie?: ReactNode; children: ReactNode }) {
  return (
    <section className="overflow-hidden rounded-xl border bg-card text-card-foreground shadow-sm">
      {filtros && <div className="flex flex-wrap items-center gap-2 border-b p-3 sm:p-4">{filtros}</div>}
      {children}
      {pie && <div className="flex flex-wrap items-center justify-between gap-2 border-t px-4 py-3 text-sm text-muted-foreground">{pie}</div>}
    </section>
  )
}

/** Un punto que late: algo está pasando ahora (una clase abierta, alguien dentro). */
export function PuntoEnVivo({ className }: { className?: string }) {
  return (
    <span className={cn("relative flex h-2 w-2", className)} aria-hidden>
      <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-success opacity-60" />
      <span className="relative inline-flex h-2 w-2 rounded-full bg-success" />
    </span>
  )
}
