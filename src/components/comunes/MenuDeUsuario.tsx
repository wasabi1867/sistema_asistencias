import { useState } from "react"
import { usePathname, useRouter } from "next/navigation"
import { ArrowRightLeft, ChevronDown, KeyRound, LogOut, Moon, Sun } from "lucide-react"
import { Button } from "@/components/ui/button"
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from "@/components/ui/dropdown-menu"
import type { Cuenta } from "../../lib/api"
import { nombreDeLosRoles, panelesDe } from "../../lib/rutas"
import { useTema } from "../../hooks/useTema"
import { CambiarContrasena } from "./CambiarContrasena"

/**
 * La cuenta con sesión, arriba a la derecha: ir a los otros paneles de sus roles,
 * cambiar la contraseña, el tema y cerrar sesión. `salidaBloqueada` explica por qué no
 * se puede salir todavía (una clase abierta); tampoco se cambia de panel entonces.
 */
export function MenuDeUsuario({ usuario, alCerrarSesion, salidaBloqueada }: { usuario: Cuenta; alCerrarSesion: () => void; salidaBloqueada?: string }) {
  const router = useRouter()
  const ruta = usePathname()?.replace(/\/$/, "")
  const otrosPaneles = panelesDe(usuario).filter((p) => p.href !== ruta)
  const { oscuro, alternar } = useTema()
  const [contrasena, setContrasena] = useState(false)
  const iniciales = `${usuario.nombre.charAt(0)}${usuario.apellido.charAt(0)}`.toUpperCase()

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="ghost" className="h-10 gap-2 px-1.5 sm:px-2" aria-label={`Cuenta de ${usuario.nombre}`}>
            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground" aria-hidden>
              {iniciales}
            </span>
            <span className="hidden text-left md:block">
              <span className="block max-w-[10rem] truncate text-sm font-medium leading-tight">
                {usuario.nombre} {usuario.apellido}
              </span>
              <span className="block max-w-[10rem] truncate text-xs font-normal text-muted-foreground">{nombreDeLosRoles(usuario.roles)}</span>
            </span>
            <ChevronDown className="hidden text-muted-foreground md:block" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent className="w-64">
          <DropdownMenuLabel className="font-normal">
            <span className="block font-medium">
              {usuario.nombre} {usuario.apellido}
            </span>
            <span className="block truncate text-xs text-muted-foreground">{usuario.email ?? usuario.matricula}</span>
          </DropdownMenuLabel>
          <DropdownMenuSeparator />
          {otrosPaneles.length > 0 && (
            <>
              {otrosPaneles.map((p) => (
                <DropdownMenuItem key={p.href} disabled={Boolean(salidaBloqueada)} onSelect={() => router.push(p.href)}>
                  <ArrowRightLeft /> Ir al {p.nombre.charAt(0).toLowerCase() + p.nombre.slice(1)}
                </DropdownMenuItem>
              ))}
              <DropdownMenuSeparator />
            </>
          )}
          <DropdownMenuItem onSelect={() => setContrasena(true)}>
            <KeyRound /> Cambiar contraseña
          </DropdownMenuItem>
          {/* El menú se queda abierto al cambiar el tema, para ver el cambio sin perderlo. */}
          <DropdownMenuItem
            onSelect={(e) => {
              e.preventDefault()
              alternar()
            }}
          >
            {oscuro ? <Sun /> : <Moon />} {oscuro ? "Tema claro" : "Tema oscuro"}
          </DropdownMenuItem>
          <DropdownMenuSeparator />
          <DropdownMenuItem peligro disabled={Boolean(salidaBloqueada)} onSelect={alCerrarSesion}>
            <LogOut /> Cerrar sesión
          </DropdownMenuItem>
          {salidaBloqueada && <p className="px-2 pb-1.5 text-xs text-muted-foreground">{salidaBloqueada}</p>}
        </DropdownMenuContent>
      </DropdownMenu>
      <CambiarContrasena abierto={contrasena} alCambiar={setContrasena} />
    </>
  )
}
