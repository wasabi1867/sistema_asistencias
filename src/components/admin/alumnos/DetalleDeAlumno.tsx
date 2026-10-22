import { useState } from "react"
import { BookOpen, Pencil, Trash2, UserPlus } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { api, type Alumno } from "../../../lib/api"
import { alertas } from "../../../lib/alertas"
import { fecha, turno } from "../../../lib/formato"
import { useDatos } from "../../../hooks/useDatos"
import { SinDatos } from "../../comunes/Bloques"

/** Un alumno con sus cursos: inscribirlo en otro o darlo de baja de uno. */
export function DetalleDeAlumno({ alumno, alCerrar, alEditar }: { alumno: Alumno; alCerrar: () => void; alEditar: () => void }) {
  const { datos: detalle, recargar } = useDatos(() => api.alumnos.uno(alumno.matricula), [alumno.matricula])
  const { datos: cursos } = useDatos(() => api.cursos.listar(), [])
  const [curso, setCurso] = useState("")

  const inscribir = async () => {
    if (!curso) return
    try {
      const r = await api.cursos.inscribir(Number(curso), [alumno.matricula])
      alertas.breve(r.inscritos.length ? "Inscrito" : r.yaInscritos.length ? "Ya estaba inscrito" : "No se pudo inscribir", r.inscritos.length || r.yaInscritos.length ? "success" : "error")
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
  const escolar = [alumno.carrera?.nombre, alumno.semestre ? `${alumno.semestre}° semestre` : null, alumno.grupo ? `grupo ${alumno.grupo}` : null, turno(alumno.turno)].filter(Boolean).join(" · ")

  return (
    <Dialog open onOpenChange={(abierto) => !abierto && alCerrar()}>
      <DialogContent className="sm:max-w-xl">
        <DialogHeader>
          <DialogTitle>
            {alumno.nombre} {alumno.apellido}
          </DialogTitle>
          <DialogDescription>
            <span className="font-mono tabular-nums">{alumno.matricula}</span> · {escolar || "Sin datos escolares"}
          </DialogDescription>
        </DialogHeader>

        <div className="flex gap-2">
          <Button type="button" variant="outline" size="sm" onClick={alEditar}>
            <Pencil /> Editar datos
          </Button>
          {!alumno.activo && <Badge>Desactivado</Badge>}
        </div>

        <section className="space-y-2">
          <h3 className="text-sm font-semibold">Cursos</h3>
          {detalle?.cursos.length === 0 ? (
            <div className="rounded-lg border">
              <SinDatos icono={BookOpen} titulo="No está inscrito en ningún curso" className="py-6" />
            </div>
          ) : (
            <ul className="divide-y rounded-lg border">
              {(detalle?.cursos ?? []).map((cu) => (
                <li key={cu.id} className="flex items-center justify-between gap-3 px-3 py-2.5 text-sm">
                  <div className="min-w-0">
                    <p className="font-medium">
                      {cu.materia} · {cu.grupo}
                    </p>
                    <p className="text-xs text-muted-foreground">
                      {cu.periodo} · {cu.docente ?? "sin docente"} · desde {fecha(cu.inscritoEn)}
                    </p>
                  </div>
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                    onClick={() => darDeBaja(cu.id, `${cu.materia} ${cu.grupo}`)}
                    aria-label={`Dar de baja de ${cu.materia} ${cu.grupo}`}
                    title="Dar de baja"
                  >
                    <Trash2 />
                  </Button>
                </li>
              ))}
            </ul>
          )}
        </section>

        <div className="flex gap-2">
          <Select value={curso} onValueChange={setCurso}>
            <SelectTrigger aria-label="Curso para inscribirlo">
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
          <Button type="button" onClick={inscribir} disabled={!curso}>
            <UserPlus /> Inscribir
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}
