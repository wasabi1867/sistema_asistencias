import { Badge } from "@/components/ui/badge"
import { Input } from "@/components/ui/input"
import type { DatosDeAlumno } from "../../../lib/api"

/**
 * Los nombres separados de una lista que los traía completos en una columna, para
 * corregirlos antes de guardar. Los de tres palabras se marcan con "Revisa".
 */
export function RevisionDeNombres({
  alumnos,
  dudosos,
  alCorregir,
  titulo = "El nombre venía completo en una columna y se separó así. Si alguno quedó mal, corrígelo aquí antes de cargar.",
}: {
  alumnos: DatosDeAlumno[]
  dudosos: boolean[]
  alCorregir: (i: number, campo: "nombre" | "apellido", valor: string) => void
  titulo?: string
}) {
  return (
    <div className="max-h-72 overflow-y-auto rounded-lg border p-3 text-sm">
      <p className="font-medium">{titulo}</p>
      {dudosos.some(Boolean) && (
        <p className="mb-2 text-muted-foreground">
          Los marcados con <span className="font-medium text-foreground">Revisa</span> tienen tres palabras: pueden ser dos apellidos y un nombre, o un apellido y dos nombres.
        </p>
      )}
      <table className="mt-1 w-full">
        <thead className="text-left text-muted-foreground">
          <tr>
            <th className="pb-1 font-normal">Matrícula</th>
            <th className="pb-1 font-normal">Apellidos</th>
            <th className="pb-1 font-normal">Nombre</th>
          </tr>
        </thead>
        <tbody>
          {alumnos.map((a, i) => (
            <tr key={`${a.matricula}-${i}`}>
              <td className="whitespace-nowrap pr-2 tabular-nums">
                {a.matricula}
                {dudosos[i] && (
                  <Badge variant="warning" className="ml-1.5">
                    Revisa
                  </Badge>
                )}
              </td>
              <td className="py-0.5 pr-2">
                <Input value={a.apellido} onChange={(e) => alCorregir(i, "apellido", e.target.value)} aria-label={`Apellidos de ${a.matricula}`} className="h-8" />
              </td>
              <td className="py-0.5">
                <Input value={a.nombre} onChange={(e) => alCorregir(i, "nombre", e.target.value)} aria-label={`Nombre de ${a.matricula}`} className="h-8" />
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
