import { useState, type FormEvent } from "react"
import { KeyRound } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { api } from "../../lib/api"
import { alertas } from "../../lib/alertas"

/** El botón y la ventana para cambiar la contraseña propia, dando la actual. */
export function CambiarContrasena({ className = "" }: { className?: string }) {
  const [abierto, setAbierto] = useState(false)
  const [actual, setActual] = useState("")
  const [nueva, setNueva] = useState("")
  const [repetida, setRepetida] = useState("")

  const guardar = async (e: FormEvent) => {
    e.preventDefault()
    if (nueva !== repetida) {
      await alertas.aviso("Las contraseñas no coinciden", "Escribe la nueva contraseña igual las dos veces.")
      return
    }
    try {
      await api.sesion.cambiarContrasena(actual, nueva)
      setAbierto(false)
      setActual("")
      setNueva("")
      setRepetida("")
      await alertas.exito("Contraseña cambiada")
    } catch (error) {
      await alertas.error("No se pudo cambiar la contraseña", error)
    }
  }

  return (
    <>
      <Button type="button" variant="outline" onClick={() => setAbierto(true)} className={className}>
        <KeyRound className="w-4 h-4 mr-2" /> Cambiar contraseña
      </Button>
      <Dialog open={abierto} onOpenChange={setAbierto}>
        <DialogContent aria-describedby={undefined} className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Cambiar mi contraseña</DialogTitle>
          </DialogHeader>
          <form onSubmit={guardar} className="space-y-3">
            <div className="space-y-1">
              <Label htmlFor="contrasena-actual">Contraseña actual</Label>
              <Input id="contrasena-actual" type="password" value={actual} onChange={(e) => setActual(e.target.value)} autoComplete="current-password" required />
            </div>
            <div className="space-y-1">
              <Label htmlFor="contrasena-nueva">Nueva (al menos 8 caracteres)</Label>
              <Input id="contrasena-nueva" type="password" value={nueva} onChange={(e) => setNueva(e.target.value)} autoComplete="new-password" minLength={8} required />
            </div>
            <div className="space-y-1">
              <Label htmlFor="contrasena-repetida">Repite la nueva</Label>
              <Input id="contrasena-repetida" type="password" value={repetida} onChange={(e) => setRepetida(e.target.value)} autoComplete="new-password" required />
            </div>
            <Button type="submit" className="w-full bg-[#1d5631] hover:bg-[#153d23] text-white">
              Guardar
            </Button>
          </form>
        </DialogContent>
      </Dialog>
    </>
  )
}
