import { useState, type FormEvent } from "react"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { api, type Alumno, type Carrera, type Turno } from "../../../lib/api"
import { alertas } from "../../../lib/alertas"
import { Campo } from "../../comunes/Bloques"

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
      alertas.breve(alumno ? "Alumno actualizado" : "Alumno dado de alta")
      alGuardar()
    } catch (error) {
      await alertas.error("No se pudo guardar el alumno", error)
    }
  }

  const texto = (clave: "matricula" | "nombre" | "apellido" | "grupo", etiqueta: string, obligatorio = true) => (
    <Campo etiqueta={etiqueta} htmlFor={`alumno-${clave}`}>
      <Input
        id={`alumno-${clave}`}
        value={datos[clave]}
        onChange={(e) => setDatos({ ...datos, [clave]: clave === "matricula" ? e.target.value.toUpperCase().trim() : e.target.value })}
        required={obligatorio}
      />
    </Campo>
  )

  return (
    <Dialog open onOpenChange={(abierto) => !abierto && alCerrar()}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{alumno ? `Editar a ${alumno.nombre} ${alumno.apellido}` : "Nuevo alumno"}</DialogTitle>
          <DialogDescription>Los datos escolares se pueden dejar vacíos y completar después.</DialogDescription>
        </DialogHeader>
        <form onSubmit={guardar} className="grid grid-cols-2 gap-4">
          {texto("matricula", "Número de control")}
          {texto("grupo", "Grupo", false)}
          {texto("nombre", "Nombre")}
          {texto("apellido", "Apellidos")}
          <Campo etiqueta="Carrera" htmlFor="alumno-carrera" className="col-span-2">
            <Select value={datos.carrera} onValueChange={(v) => setDatos({ ...datos, carrera: v })}>
              <SelectTrigger id="alumno-carrera">
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
          </Campo>
          <Campo etiqueta="Semestre" htmlFor="alumno-semestre">
            <Select value={datos.semestre} onValueChange={(v) => setDatos({ ...datos, semestre: v })}>
              <SelectTrigger id="alumno-semestre">
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
          </Campo>
          <Campo etiqueta="Turno" htmlFor="alumno-turno">
            <Select value={datos.turno} onValueChange={(v) => setDatos({ ...datos, turno: v })}>
              <SelectTrigger id="alumno-turno">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={SIN}>Sin turno</SelectItem>
                <SelectItem value="matutino">Matutino</SelectItem>
                <SelectItem value="vespertino">Vespertino</SelectItem>
              </SelectContent>
            </Select>
          </Campo>
          <DialogFooter className="col-span-2">
            <Button type="button" variant="outline" onClick={alCerrar}>
              Cancelar
            </Button>
            <Button type="submit">Guardar</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
