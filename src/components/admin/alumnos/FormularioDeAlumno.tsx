import { useState, type FormEvent } from "react"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { api, type Alumno, type Carrera, type Turno } from "../../../lib/api"
import { alertas } from "../../../lib/alertas"

const SIN = "ninguno"

/** Alta o edición de un alumno. Con `alumno` edita; sin él, da de alta. */
export function FormularioDeAlumno({ alumno, carreras, alCerrar, alGuardar }: { alumno: Alumno | null; carreras: Carrera[]; alCerrar: () => void; alGuardar: () => void }) {
  const [datos, setDatos] = useState({
    matricula: alumno?.matricula ?? "",
    nombre: alumno?.nombre ?? "",
    apellido: alumno?.apellido ?? "",
    carrera: alumno?.carrera?.clave ?? SIN,
    semestre: alumno?.semestre ? String(alumno.semestre) : SIN,
    turno: alumno?.turno ?? SIN,
    grupo: alumno?.grupo ?? "",
  })

  const guardar = async (e: FormEvent) => {
    e.preventDefault()
    const cuerpo = {
      matricula: datos.matricula,
      nombre: datos.nombre,
      apellido: datos.apellido,
      carrera: datos.carrera === SIN ? null : datos.carrera,
      semestre: datos.semestre === SIN ? null : Number(datos.semestre),
      turno: datos.turno === SIN ? null : (datos.turno as Turno),
      grupo: datos.grupo || null,
    }
    try {
      if (alumno) {
        await api.alumnos.editar(alumno.matricula, cuerpo)
      } else {
        await api.alumnos.crear(cuerpo)
      }
      await alertas.breve(alumno ? "Alumno actualizado" : "Alumno dado de alta")
      alGuardar()
    } catch (error) {
      await alertas.error("No se pudo guardar el alumno", error)
    }
  }

  const texto = (clave: "matricula" | "nombre" | "apellido" | "grupo", etiqueta: string, obligatorio = true) => (
    <div className="space-y-1">
      <Label htmlFor={`alumno-${clave}`}>{etiqueta}</Label>
      <Input
        id={`alumno-${clave}`}
        value={datos[clave]}
        onChange={(e) => setDatos({ ...datos, [clave]: clave === "matricula" ? e.target.value.toUpperCase().trim() : e.target.value })}
        required={obligatorio}
      />
    </div>
  )

  return (
    <Dialog open onOpenChange={(abierto) => !abierto && alCerrar()}>
      <DialogContent aria-describedby={undefined} className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{alumno ? `Editar a ${alumno.nombre} ${alumno.apellido}` : "Nuevo alumno"}</DialogTitle>
        </DialogHeader>
        <form onSubmit={guardar} className="grid grid-cols-2 gap-3">
          {texto("matricula", "Número de control")}
          {texto("grupo", "Grupo", false)}
          {texto("nombre", "Nombre")}
          {texto("apellido", "Apellidos")}
          <div className="space-y-1 col-span-2">
            <Label>Carrera</Label>
            <Select value={datos.carrera} onValueChange={(v) => setDatos({ ...datos, carrera: v })}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={SIN}>Sin carrera</SelectItem>
                {carreras.map((carrera) => (
                  <SelectItem key={carrera.clave} value={carrera.clave}>
                    {carrera.nombre}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1">
            <Label>Semestre</Label>
            <Select value={datos.semestre} onValueChange={(v) => setDatos({ ...datos, semestre: v })}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={SIN}>Sin semestre</SelectItem>
                {Array.from({ length: 12 }, (_, i) => i + 1).map((s) => (
                  <SelectItem key={s} value={String(s)}>
                    {s}°
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1">
            <Label>Turno</Label>
            <Select value={datos.turno} onValueChange={(v) => setDatos({ ...datos, turno: v })}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={SIN}>Sin turno</SelectItem>
                <SelectItem value="matutino">Matutino</SelectItem>
                <SelectItem value="vespertino">Vespertino</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <Button type="submit" className="col-span-2 bg-[#1d5631] hover:bg-[#153d23] text-white">
            Guardar
          </Button>
        </form>
      </DialogContent>
    </Dialog>
  )
}
