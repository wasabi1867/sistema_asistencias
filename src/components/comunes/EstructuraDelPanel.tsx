import { useState, type ReactNode } from "react"
import Link from "next/link"
import { Menu, type LucideIcon } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Sheet, SheetContent, SheetDescription, SheetTitle } from "@/components/ui/sheet"
import { cn } from "@/lib/utils"
import type { Cuenta } from "../../lib/api"
import { Marca } from "./Marca"
import { MenuDeUsuario } from "./MenuDeUsuario"

export interface SeccionDelMenu<T extends string = string> {
  id: T
  nombre: string
  icono: LucideIcon
  /** Las secciones con el mismo grupo van juntas bajo ese título. */
  grupo?: string
  /** Un número junto al nombre: lo pendiente en esa sección. */
  pendientes?: number
}

export interface EnlaceDelMenu {
  texto: string
  href: string
  icono: LucideIcon
}

/**
 * La estructura de los tres paneles (administrador, laboratorio y docente): el menú de
 * secciones a la izquierda (en el teléfono se abre con un botón), y arriba el título de
 * la sección con el menú de la cuenta. Sin `secciones`, la página va sin menú lateral.
 */
export function EstructuraDelPanel<T extends string>({
  panel,
  usuario,
  secciones,
  seccion,
  alElegir,
  arribaDelMenu,
  enlaces,
  titulo,
  subtitulo,
  acciones,
  alCerrarSesion,
  salidaBloqueada,
  children,
}: {
  panel: string
  usuario: Cuenta
  secciones?: SeccionDelMenu<T>[]
  seccion?: T
  alElegir?: (id: T) => void
  arribaDelMenu?: ReactNode
  enlaces?: EnlaceDelMenu[]
  titulo: ReactNode
  subtitulo?: ReactNode
  acciones?: ReactNode
  alCerrarSesion: () => void
  salidaBloqueada?: string
  children: ReactNode
}) {
  const [menuAbierto, setMenuAbierto] = useState(false)
  const conMenu = Boolean(secciones?.length)

  const menu = (
    <div className="flex h-full flex-col">
      <div className="flex h-16 shrink-0 items-center border-b px-4">
        <Marca subtitulo={panel} />
      </div>
      {arribaDelMenu && <div className="border-b p-3">{arribaDelMenu}</div>}
      <nav className="flex-1 overflow-y-auto p-3" aria-label="Secciones">
        {agrupar(secciones ?? []).map(([grupo, delGrupo]) => (
          <div key={grupo || "general"} className="mb-3">
            {grupo && <p className="px-2.5 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground/80">{grupo}</p>}
            <ul className="space-y-0.5">
              {delGrupo.map(({ id, nombre, icono: Icono, pendientes }) => {
                const activa = id === seccion
                return (
                  <li key={id}>
                    <button
                      type="button"
                      aria-current={activa ? "page" : undefined}
                      onClick={() => {
                        alElegir?.(id)
                        setMenuAbierto(false)
                      }}
                      className={cn(
                        "flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-left text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
                        activa ? "bg-accent text-accent-foreground" : "text-muted-foreground hover:bg-muted hover:text-foreground",
                      )}
                    >
                      <Icono className="h-[18px] w-[18px] shrink-0" aria-hidden />
                      <span className="flex-1">{nombre}</span>
                      {pendientes ? (
                        <span className="rounded-full bg-primary px-1.5 py-0.5 text-[11px] font-semibold leading-none text-primary-foreground tabular-nums">{pendientes}</span>
                      ) : null}
                    </button>
                  </li>
                )
              })}
            </ul>
          </div>
        ))}
      </nav>
      {enlaces?.length ? (
        <div className="space-y-0.5 border-t p-3">
          {enlaces.map(({ texto, href, icono: Icono }) => (
            <Link key={href} href={href} className="flex items-center gap-3 rounded-lg px-2.5 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground">
              <Icono className="h-[18px] w-[18px]" aria-hidden /> {texto}
            </Link>
          ))}
        </div>
      ) : null}
    </div>
  )

  return (
    <div className="min-h-dvh bg-background">
      {conMenu && <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 border-r bg-card lg:block">{menu}</aside>}
      {conMenu && (
        <Sheet open={menuAbierto} onOpenChange={setMenuAbierto}>
          <SheetContent side="left" className="w-72 p-0">
            <SheetTitle className="sr-only">Menú</SheetTitle>
            <SheetDescription className="sr-only">Secciones del {panel.toLowerCase()}</SheetDescription>
            {menu}
          </SheetContent>
        </Sheet>
      )}

      <div className={conMenu ? "lg:pl-64" : ""}>
        <header className="sticky top-0 z-20 flex h-16 items-center gap-3 border-b bg-background/85 px-4 backdrop-blur supports-[backdrop-filter]:bg-background/70 sm:px-6">
          {conMenu ? (
            <Button type="button" variant="ghost" size="icon" className="-ml-2 lg:hidden" onClick={() => setMenuAbierto(true)} aria-label="Abrir el menú">
              <Menu />
            </Button>
          ) : (
            <Marca subtitulo={panel} className="mr-2 hidden sm:flex" />
          )}
          <div className="min-w-0 flex-1">
            <h1 className="truncate text-base font-semibold sm:text-lg">{titulo}</h1>
            {subtitulo && <p className="truncate text-xs text-muted-foreground sm:text-sm">{subtitulo}</p>}
          </div>
          {acciones && <div className="flex items-center gap-2">{acciones}</div>}
          <MenuDeUsuario usuario={usuario} alCerrarSesion={alCerrarSesion} salidaBloqueada={salidaBloqueada} />
        </header>
        <main className="mx-auto w-full max-w-7xl p-4 sm:p-6">{children}</main>
      </div>
    </div>
  )
}

/** Las secciones en el orden en que llegan, juntas por grupo. */
function agrupar<T extends string>(secciones: SeccionDelMenu<T>[]): [string, SeccionDelMenu<T>[]][] {
  const grupos = new Map<string, SeccionDelMenu<T>[]>()
  for (const s of secciones) {
    const grupo = s.grupo ?? ""
    grupos.set(grupo, [...(grupos.get(grupo) ?? []), s])
  }
  return [...grupos.entries()]
}
