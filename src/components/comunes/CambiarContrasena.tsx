import { useState, type FormEvent } from "react"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { api } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { Campo } from "./Bloques"

/** La ventana para cambiar la contraseña propia, dando la actual. Se abre desde el menú de usuario. */
export function CambiarContrasena({ abierto, alCambiar }: { abierto: boolean; alCambiar: (abierto: boolean) => void }) {
  const [actual, setActual] = useState("")
  const [nueva, setNueva] = useState("")
  const [repetida, setRepetida] = useState("")
  const [guardando, setGuardando] = useState(false)

  const cerrar = (abrir: boolean) => {
    if (!abrir) {
      setActual("")
      setNueva("")
      setRepetida("")
    }
    alCambiar(abrir)
  }

  const guardar = async (e: FormEvent) => {
    e.preventDefault()
    if (nueva !== repetida) {
      await alertas.aviso("Las contraseñas no coinciden", "Escribe la nueva contraseña igual las dos veces.")
      return
    }
    setGuardando(true)
    try {
      await api.sesion.cambiarContrasena(actual, nueva)
      cerrar(false)
      alertas.breve("Contraseña cambiada")
    } catch (error) {
      await alertas.error("No se pudo cambiar la contraseña", error)
    } finally {
      setGuardando(false)
    }
  }

  return (
    <Dialog open={abierto} onOpenChange={cerrar}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Cambiar mi contraseña</DialogTitle>
          <DialogDescription>La nueva debe tener al menos 8 caracteres.</DialogDescription>
        </DialogHeader>
        <form onSubmit={guardar} className="space-y-4">
          <Campo etiqueta="Contraseña actual" htmlFor="contrasena-actual">
            <Input id="contrasena-actual" type="password" value={actual} onChange={(e) => setActual(e.target.value)} autoComplete="current-password" required />
          </Campo>
          <Campo etiqueta="Nueva contraseña" htmlFor="contrasena-nueva">
            <Input id="contrasena-nueva" type="password" value={nueva} onChange={(e) => setNueva(e.target.value)} autoComplete="new-password" minLength={8} required />
          </Campo>
          <Campo etiqueta="Repite la nueva" htmlFor="contrasena-repetida">
            <Input id="contrasena-repetida" type="password" value={repetida} onChange={(e) => setRepetida(e.target.value)} autoComplete="new-password" required />
          </Campo>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => cerrar(false)}>
              Cancelar
            </Button>
            <Button type="submit" disabled={guardando}>
              Guardar
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
