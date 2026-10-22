import { useState } from "react"
import { Trash2, UserPlus } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { api, type Alumno } from "../../../lib/api"
import { alertas } from "../../../lib/alertas"
import { fecha, turno } from "../../../lib/formato"
import { useDatos } from "../../../hooks/useDatos"

/** Un alumno con sus cursos: inscribirlo en otro o darlo de baja de uno. */
export function DetalleDeAlumno({ alumno, alCerrar }: { alumno: Alumno; alCerrar: () => void }) {
  const { datos: detalle, recargar } = useDatos(() => api.alumnos.uno(alumno.matricula), [alumno.matricula])
  const { datos: cursos } = useDatos(() => api.cursos.listar(), [])
  const [curso, setCurso] = useState("")

  const inscribir = async () => {
    if (!curso) return
    try {
      const r = await api.cursos.inscribir(Number(curso), [alumno.matricula])
      await alertas.breve(r.inscritos.length ? "Inscrito" : r.yaInscritos.length ? "Ya estaba inscrito" : "No se pudo inscribir", r.inscritos.length || r.yaInscritos.length ? "success" : "error")
      setCurso("")
      await recargar()
    } catch (error) {
      await alertas.error("No se pudo inscribir", error)
    }
  }

  const darDeBaja = async (cursoId: number, nombre: string) => {
    if (!(await alertas.confirmar("¿Darlo de baja del curso?", nombre, { aceptar: "Dar de baja", peligro: true }))) return
    try {
      await api.cursos.darDeBaja(cursoId, alumno.matricula)
      await recargar()
    } catch (error) {
      await alertas.error("No se pudo dar de baja", error)
    }
  }

  const suyos = new Set((detalle?.cursos ?? []).map((cu) => cu.id))

  return (
    <Dialog open onOpenChange={(abierto) => !abierto && alCerrar()}>
      <DialogContent aria-describedby={undefined} className="sm:max-w-xl">
        <DialogHeader>
          <DialogTitle>
            {alumno.nombre} {alumno.apellido} <span className="opacity-60 font-normal">({alumno.matricula})</span>
          </DialogTitle>
        </DialogHeader>
        <p className="text-sm">
          {[alumno.carrera?.nombre, alumno.semestre ? `${alumno.semestre}° semestre` : null, alumno.grupo ? `grupo ${alumno.grupo}` : null, turno(alumno.turno)].filter(Boolean).join(" · ") || "Sin datos escolares"}
        </p>
        <div className="space-y-2">
          <p className="font-medium">Cursos</p>
          {detalle?.cursos.length === 0 && <p className="text-sm opacity-70">No está inscrito en ningún curso.</p>}
          {(detalle?.cursos ?? []).map((cu) => (
            <div key={cu.id} className="flex justify-between items-center gap-2 p-2 rounded-lg bg-[#e6ffe9] dark:bg-[#153731]/60 text-sm">
              <span>
                {cu.materia} {cu.grupo} · {cu.periodo} · {cu.docente ?? "sin docente"} <span className="opacity-60">desde {fecha(cu.inscritoEn)}</span>
              </span>
              <button type="button" onClick={() => darDeBaja(cu.id, `${cu.materia} ${cu.grupo}`)} className="text-red-500" title="Dar de baja">
                <Trash2 className="w-4 h-4" />
              </button>
            </div>
          ))}
        </div>
        <div className="flex gap-2">
          <Select value={curso} onValueChange={setCurso}>
            <SelectTrigger>
              <SelectValue placeholder="Inscribir en un curso del periodo actual" />
            </SelectTrigger>
            <SelectContent>
              {(cursos ?? [])
                .filter((cu) => !suyos.has(cu.id))
                .map((cu) => (
                  <SelectItem key={cu.id} value={String(cu.id)}>
                    {cu.materia.nombre} {cu.grupo} {cu.docente ? `— ${cu.docente.nombre} ${cu.docente.apellido}` : ""}
                  </SelectItem>
                ))}
            </SelectContent>
          </Select>
          <Button type="button" onClick={inscribir} disabled={!curso} className="bg-[#1d5631] hover:bg-[#153d23] text-white">
            <UserPlus className="w-4 h-4" />
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}
