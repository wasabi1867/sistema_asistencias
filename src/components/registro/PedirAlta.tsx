import { useState, type FormEvent } from "react"
import { Loader2, Send } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { api, type ClaseAbierta } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { Campo } from "../comunes/Bloques"

/**
 * El alumno escribió un número de control que no está dado de alta: llegó después de que
 * se cargó la lista. Aquí pide su alta, y el administrador la aprueba (pregunta 3).
 * Si la pide desde una clase, al aprobarla queda inscrito en ese curso.
 */
export function PedirAlta({
  laboratorio,
  matricula,
  clase,
  alCerrar,
  alEnviar,
}: {
  laboratorio: string
  matricula: string
  clase: ClaseAbierta | null
  alCerrar: () => void
  alEnviar: () => void
}) {
  const [nombre, setNombre] = useState("")
  const [apellido, setApellido] = useState("")
  const [semestre, setSemestre] = useState("")
  const [grupo, setGrupo] = useState("")
  const [enviando, setEnviando] = useState(false)

  const enviar = async (e: FormEvent) => {
    e.preventDefault()
    setEnviando(true)
    try {
      await api.solicitudesDeAlta.crear({
        laboratorio,
        matricula,
        nombre,
        apellido,
        semestre: semestre ? Number(semestre) : null,
        grupo: grupo.trim() || null,
        claseId: clase?.id,
      })
      alEnviar()
    } catch (error) {
      await alertas.error("No se pudo enviar tu solicitud", error)
    } finally {
      setEnviando(false)
    }
  }

  return (
    <Dialog open onOpenChange={(abierto) => !abierto && alCerrar()}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Pedir mi alta</DialogTitle>
          <DialogDescription>
            El número de control <span className="font-semibold text-foreground tabular-nums">{matricula}</span> todavía no está en el sistema. Escribe tu nombre como aparece en tu credencial y el
            administrador revisará tu solicitud{clase ? ` para que quedes inscrito en ${clase.materia}` : ""}.
          </DialogDescription>
        </DialogHeader>
        <form id="pedir-alta" onSubmit={enviar} className="grid gap-4 sm:grid-cols-2">
          <Campo etiqueta="Nombre" htmlFor="alta-nombre">
            <Input id="alta-nombre" value={nombre} onChange={(e) => setNombre(e.target.value)} autoComplete="given-name" autoFocus required maxLength={100} />
          </Campo>
          <Campo etiqueta="Apellidos" htmlFor="alta-apellido">
            <Input id="alta-apellido" value={apellido} onChange={(e) => setApellido(e.target.value)} autoComplete="family-name" required maxLength={100} />
          </Campo>
          <Campo etiqueta="Semestre (opcional)" htmlFor="alta-semestre">
            <Input id="alta-semestre" value={semestre} onChange={(e) => setSemestre(e.target.value.replace(/\D/g, "").slice(0, 2))} inputMode="numeric" />
          </Campo>
          <Campo etiqueta="Grupo (opcional)" htmlFor="alta-grupo">
            <Input id="alta-grupo" value={grupo} onChange={(e) => setGrupo(e.target.value.toUpperCase())} maxLength={20} />
          </Campo>
        </form>
        <DialogFooter>
          <Button type="button" variant="outline" onClick={alCerrar}>
            Cancelar
          </Button>
          <Button type="submit" form="pedir-alta" disabled={enviando}>
            {enviando ? <Loader2 className="animate-spin" /> : <Send />} {enviando ? "Enviando…" : "Enviar solicitud"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
