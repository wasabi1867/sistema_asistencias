import { useEffect, useState } from "react"
import { FileSpreadsheet, Users } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { cn } from "@/lib/utils"
import { api, type ResumenDeAsistencia } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { tablasExcel } from "../../lib/exportar"
import { SinDatos } from "../comunes/Bloques"

// Debajo de este porcentaje, la asistencia se marca en rojo.
const MINIMO = 80

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
    return <SinDatos icono={Users} titulo="Elige un curso" />
  }
  if (!resumen) {
    return <p className="p-5 text-sm text-muted-foreground">Cargando…</p>
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
    <div>
      <div className="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3 sm:px-5">
        <p className="text-sm text-muted-foreground">
          {resumen.clases} {resumen.clases === 1 ? "clase cerrada" : "clases cerradas"} · en rojo, menos de {MINIMO}%
        </p>
        <Button type="button" variant="outline" size="sm" onClick={exportar} disabled={resumen.alumnos.length === 0}>
          <FileSpreadsheet /> Exportar a Excel
        </Button>
      </div>
      {resumen.alumnos.length === 0 ? (
        <SinDatos icono={Users} titulo="El curso todavía no tiene alumnos inscritos" />
      ) : (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Alumno</TableHead>
              <TableHead className="text-right">Asistencias</TableHead>
              <TableHead className="text-right">Faltas</TableHead>
              <TableHead className="w-40">Porcentaje</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {resumen.alumnos.map((alumno) => {
              const bajo = alumno.porcentaje !== null && alumno.porcentaje < MINIMO
              return (
                <TableRow key={alumno.alumnoId}>
                  <TableCell>
                    <p className="font-medium">
                      {alumno.apellido} {alumno.nombre}
                    </p>
                    <p className="text-xs text-muted-foreground tabular-nums">{alumno.matricula}</p>
                  </TableCell>
                  <TableCell className="text-right tabular-nums">
                    {alumno.asistencias} <span className="text-muted-foreground">/ {alumno.clases}</span>
                  </TableCell>
                  <TableCell className={cn("text-right tabular-nums", alumno.faltas > 0 && bajo && "font-medium text-destructive")}>{alumno.faltas}</TableCell>
                  <TableCell>
                    {alumno.porcentaje === null ? (
                      <span className="text-muted-foreground">Sin clases</span>
                    ) : (
                      <div className="flex items-center gap-2">
                        <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted" aria-hidden>
                          <div className={cn("h-full rounded-full", bajo ? "bg-destructive" : "bg-success")} style={{ width: `${alumno.porcentaje}%` }} />
                        </div>
                        <span className={cn("w-10 text-right text-sm font-medium tabular-nums", bajo && "text-destructive")}>{alumno.porcentaje}%</span>
                      </div>
                    )}
                  </TableCell>
                </TableRow>
              )
            })}
          </TableBody>
        </Table>
      )}
    </div>
  )
}
