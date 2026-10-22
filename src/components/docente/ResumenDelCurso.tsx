import { useEffect, useState } from "react"
import { FileDown } from "lucide-react"
import { Button } from "@/components/ui/button"
import { api, type ResumenDeAsistencia } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { tablasExcel } from "../../lib/exportar"

/**
 * Asistencias, faltas y porcentaje de cada alumno del curso, sobre las clases ya
 * cerradas. A cada alumno le cuentan las clases desde que se inscribió.
 */
export function ResumenDelCurso({ cursoId, nombre }: { cursoId: number | null; nombre: string }) {
  const [resumen, setResumen] = useState<ResumenDeAsistencia | null>(null)

  useEffect(() => {
    setResumen(null)
    if (!cursoId) return
    api.cursos
      .asistencia(cursoId)
      .then(setResumen)
      .catch((error) => alertas.error("No se pudo cargar el resumen", error))
  }, [cursoId])

  if (!cursoId) {
    return <p className="text-sm text-gray-500">Elige un curso para ver la asistencia de sus alumnos.</p>
  }
  if (!resumen) {
    return <p className="text-sm text-gray-500">Cargando...</p>
  }

  const exportar = () =>
    tablasExcel(
      [
        {
          nombre: "Asistencia",
          filas: [
            [`Asistencia de ${nombre}`],
            [`${resumen.clases} clases`],
            [],
            ["Matrícula", "Apellido", "Nombre", "Clases", "Asistencias", "Faltas", "Porcentaje"],
            ...resumen.alumnos.map((a) => [a.matricula, a.apellido, a.nombre, a.clases, a.asistencias, a.faltas, a.porcentaje ?? ""]),
          ],
        },
      ],
      `asistencia_${nombre.replace(/\s+/g, "_")}.xlsx`,
    )

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <p className="text-sm">{resumen.clases} clases cerradas</p>
        <Button type="button" size="sm" onClick={exportar} disabled={resumen.alumnos.length === 0} className="bg-[#1d5631] hover:bg-[#153d23] text-white">
          <FileDown className="w-4 h-4 mr-1" /> Excel
        </Button>
      </div>
      {resumen.alumnos.length === 0 && <p className="text-sm text-gray-500">El curso todavía no tiene alumnos inscritos.</p>}
      <ul className="space-y-1">
        {resumen.alumnos.map((alumno) => (
          <li key={alumno.alumnoId} className="flex justify-between items-center p-2 rounded-lg bg-[#e6ffe9] dark:bg-[#153731]/60 text-sm">
            <span className="min-w-0 truncate">
              {alumno.apellido} {alumno.nombre}
            </span>
            <span className={`shrink-0 font-semibold ${alumno.porcentaje !== null && alumno.porcentaje < 80 ? "text-red-600" : "text-[#1d5631] dark:text-[#1BB827]"}`}>
              {alumno.porcentaje === null ? "—" : `${alumno.porcentaje}%`} <span className="font-normal opacity-70">({alumno.faltas} faltas)</span>
            </span>
          </li>
        ))}
      </ul>
    </div>
  )
}
