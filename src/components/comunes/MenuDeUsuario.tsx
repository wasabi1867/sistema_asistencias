import { useState } from "react"
import { ChevronDown, KeyRound, LogOut, Moon, Sun } from "lucide-react"
import { Button } from "@/components/ui/button"
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from "@/components/ui/dropdown-menu"
import type { Cuenta } from "../../lib/api"
import { NOMBRE_DEL_ROL } from "../../lib/rutas"
import { useTema } from "../../hooks/useTema"
import { CambiarContrasena } from "./CambiarContrasena"

/**
 * La cuenta con sesión, arriba a la derecha: cambiar la contraseña, el tema y cerrar
 * sesión. `salidaBloqueada` explica por qué no se puede salir todavía (una clase abierta).
 */
export function MenuDeUsuario({ usuario, alCerrarSesion, salidaBloqueada }: { usuario: Cuenta; alCerrarSesion: () => void; salidaBloqueada?: string }) {
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
              <span className="block text-xs font-normal text-muted-foreground">{NOMBRE_DEL_ROL[usuario.rol]}</span>
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
