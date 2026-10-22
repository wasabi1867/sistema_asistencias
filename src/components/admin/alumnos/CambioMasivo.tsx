import { useState, type FormEvent } from "react"
import { ArrowDown } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { api, type Carrera, type Turno } from "../../../lib/api"
import { alertas } from "../../../lib/alertas"
import { Campo } from "../../comunes/Bloques"

const IGUAL = "igual"

/** Cambiar a la vez el semestre, el grupo o el turno de un grupo de alumnos, por ejemplo al pasar de semestre. */
export function CambioMasivo({ carreras, alCerrar, alCambiar }: { carreras: Carrera[]; alCerrar: () => void; alCambiar: () => void }) {
  const [carrera, setCarrera] = useState(carreras[0]?.clave ?? "")
  const [semestre, setSemestre] = useState("1")
  const [grupo, setGrupo] = useState("")
  const [nuevoSemestre, setNuevoSemestre] = useState(IGUAL)
  const [nuevoGrupo, setNuevoGrupo] = useState("")
  const [nuevoTurno, setNuevoTurno] = useState(IGUAL)

  const aplicar = async (e: FormEvent) => {
    e.preventDefault()
    const cambios = {
      nuevoSemestre: nuevoSemestre === IGUAL ? undefined : Number(nuevoSemestre),
      nuevoGrupo: nuevoGrupo || undefined,
      nuevoTurno: nuevoTurno === IGUAL ? undefined : (nuevoTurno as Turno),
    }
    const descripcion = [cambios.nuevoSemestre && `semestre ${cambios.nuevoSemestre}`, cambios.nuevoGrupo && `grupo ${cambios.nuevoGrupo}`, cambios.nuevoTurno && `turno ${cambios.nuevoTurno}`]
      .filter(Boolean)
      .join(", ")
    if (!descripcion) {
      await alertas.aviso("Nada que cambiar", "Indica el semestre, el grupo o el turno nuevos.")
      return
    }
    if (!(await alertas.confirmar("¿Aplicar el cambio?", `Los alumnos activos de ${carrera}, semestre ${semestre}${grupo ? `, grupo ${grupo}` : ""} pasarán a ${descripcion}.`, { aceptar: "Aplicar" }))) return
    try {
      const r = await api.alumnos.cambioMasivo({ carrera, semestre: Number(semestre), grupo: grupo || undefined, ...cambios })
      await alertas.exito("Cambio aplicado", `${r.actualizados} alumnos actualizados.`)
      alCambiar()
    } catch (error) {
      await alertas.error("No se pudo aplicar el cambio", error)
    }
  }

  const semestres = Array.from({ length: 12 }, (_, i) => i + 1)

  return (
    <Dialog open onOpenChange={(abierto) => !abierto && alCerrar()}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Cambio masivo</DialogTitle>
          <DialogDescription>Cambia a todos los alumnos activos de una carrera y semestre (y, si se indica, de un grupo). Sirve para pasarlos de semestre.</DialogDescription>
        </DialogHeader>
        <form onSubmit={aplicar} className="space-y-4">
          <fieldset className="grid grid-cols-2 gap-4 rounded-lg border p-4">
            <legend className="px-1 text-sm font-semibold">Quiénes</legend>
            <Campo etiqueta="Carrera" htmlFor="masivo-carrera" className="col-span-2">
              <Select value={carrera} onValueChange={setCarrera}>
                <SelectTrigger id="masivo-carrera">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {carreras.map((c) => (
                    <SelectItem key={c.clave} value={c.clave}>
                      {c.nombre}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Campo>
            <Campo etiqueta="Semestre" htmlFor="masivo-semestre">
              <Select value={semestre} onValueChange={setSemestre}>
                <SelectTrigger id="masivo-semestre">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {semestres.map((s) => (
                    <SelectItem key={s} value={String(s)}>
                      {s}°
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Campo>
            <Campo etiqueta="Grupo (opcional)" htmlFor="grupo-masivo">
              <Input id="grupo-masivo" value={grupo} onChange={(e) => setGrupo(e.target.value)} />
            </Campo>
          </fieldset>
          <ArrowDown className="mx-auto h-4 w-4 text-muted-foreground" aria-hidden />
          <fieldset className="grid grid-cols-3 gap-4 rounded-lg border p-4">
            <legend className="px-1 text-sm font-semibold">Pasan a</legend>
            <Campo etiqueta="Semestre" htmlFor="masivo-nuevo-semestre">
              <Select value={nuevoSemestre} onValueChange={setNuevoSemestre}>
                <SelectTrigger id="masivo-nuevo-semestre">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={IGUAL}>Sin cambio</SelectItem>
                  {semestres.map((s) => (
                    <SelectItem key={s} value={String(s)}>
                      {s}°
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Campo>
            <Campo etiqueta="Grupo" htmlFor="nuevo-grupo">
              <Input id="nuevo-grupo" value={nuevoGrupo} onChange={(e) => setNuevoGrupo(e.target.value)} placeholder="Sin cambio" />
            </Campo>
            <Campo etiqueta="Turno" htmlFor="masivo-nuevo-turno">
              <Select value={nuevoTurno} onValueChange={setNuevoTurno}>
                <SelectTrigger id="masivo-nuevo-turno">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={IGUAL}>Sin cambio</SelectItem>
                  <SelectItem value="matutino">Matutino</SelectItem>
                  <SelectItem value="vespertino">Vespertino</SelectItem>
                </SelectContent>
              </Select>
            </Campo>
          </fieldset>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={alCerrar}>
              Cancelar
            </Button>
            <Button type="submit">Aplicar</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
