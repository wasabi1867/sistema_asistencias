import { useState, type FormEvent } from "react"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { api, type Carrera, type Turno } from "../../../lib/api"
import { alertas } from "../../../lib/alertas"

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
          <DialogDescription>Cambia a todos los alumnos activos de una carrera y semestre (y, si se indica, de un grupo).</DialogDescription>
        </DialogHeader>
        <form onSubmit={aplicar} className="grid grid-cols-2 gap-3">
          <p className="col-span-2 font-medium">Quiénes</p>
          <div className="space-y-1 col-span-2">
            <Label>Carrera</Label>
            <Select value={carrera} onValueChange={setCarrera}>
              <SelectTrigger>
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
          </div>
          <div className="space-y-1">
            <Label>Semestre</Label>
            <Select value={semestre} onValueChange={setSemestre}>
              <SelectTrigger>
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
          </div>
          <div className="space-y-1">
            <Label htmlFor="grupo-masivo">Grupo (opcional)</Label>
            <Input id="grupo-masivo" value={grupo} onChange={(e) => setGrupo(e.target.value)} />
          </div>
          <p className="col-span-2 font-medium pt-2">Pasan a</p>
          <div className="space-y-1">
            <Label>Semestre</Label>
            <Select value={nuevoSemestre} onValueChange={setNuevoSemestre}>
              <SelectTrigger>
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
          </div>
          <div className="space-y-1">
            <Label htmlFor="nuevo-grupo">Grupo</Label>
            <Input id="nuevo-grupo" value={nuevoGrupo} onChange={(e) => setNuevoGrupo(e.target.value)} placeholder="Sin cambio" />
          </div>
          <div className="space-y-1">
            <Label>Turno</Label>
            <Select value={nuevoTurno} onValueChange={setNuevoTurno}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={IGUAL}>Sin cambio</SelectItem>
                <SelectItem value="matutino">Matutino</SelectItem>
                <SelectItem value="vespertino">Vespertino</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <Button type="submit" className="col-span-2 bg-[#800040] hover:bg-[#5c002e] text-white">
            Aplicar
          </Button>
        </form>
      </DialogContent>
    </Dialog>
  )
}
