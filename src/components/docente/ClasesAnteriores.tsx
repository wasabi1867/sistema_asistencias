import { useCallback, useEffect, useState } from "react"
import { FileSpreadsheet, FileText, History } from "lucide-react"
import { Input } from "@/components/ui/input"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { api, type Clase } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { hojaDeClase, hojaDeRegistroExcel, hojaDeRegistroPdf } from "../../lib/exportar"
import { fecha, hora } from "../../lib/formato"
import { Campo, Panel, SinDatos } from "../comunes/Bloques"
import { MenuDeAcciones } from "../comunes/MenuDeAcciones"

/** Las clases ya cerradas de un curso, con quiénes asistieron, para volver a sacar su hoja. */
export function ClasesAnteriores({ cursoId }: { cursoId: number | null }) {
  const [clases, setClases] = useState<Clase[] | null>(null)
  const [desde, setDesde] = useState("")
  const [hasta, setHasta] = useState("")

  const cargar = useCallback(async () => {
    if (!cursoId) return setClases([])
    setClases(null)
    try {
      const respuesta = await api.clases.listar({ curso: cursoId, estado: "cerradas", desde: desde || undefined, hasta: hasta || undefined, conAsistentes: true, porPagina: 200 })
      setClases(respuesta.clases)
    } catch (error) {
      setClases([])
      await alertas.error("No se pudieron cargar las clases", error)
    }
  }, [cursoId, desde, hasta])

  useEffect(() => {
    cargar()
  }, [cargar])

  return (
    <Panel
      titulo="Clases anteriores"
      descripcion="Vuelve a descargar la hoja de registro de una clase ya cerrada."
      acciones={
        <div className="flex gap-2">
          <Campo etiqueta="Desde" htmlFor="desde-clases" className="space-y-0.5">
            <Input id="desde-clases" type="date" value={desde} onChange={(e) => setDesde(e.target.value)} className="h-9 w-40" />
          </Campo>
          <Campo etiqueta="Hasta" htmlFor="hasta-clases" className="space-y-0.5">
            <Input id="hasta-clases" type="date" value={hasta} onChange={(e) => setHasta(e.target.value)} className="h-9 w-40" />
          </Campo>
        </div>
      }
      sinRelleno
    >
      {clases === null ? (
        <p className="p-5 text-sm text-muted-foreground">Cargando…</p>
      ) : clases.length === 0 ? (
        <SinDatos icono={History} titulo={cursoId ? "No hay clases cerradas en esas fechas" : "Elige un curso"} />
      ) : (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Fecha</TableHead>
              <TableHead>Horario</TableHead>
              <TableHead>Práctica</TableHead>
              <TableHead className="text-right">Asistentes</TableHead>
              <TableHead className="hidden md:table-cell">Laboratorio</TableHead>
              <TableHead className="w-12">
                <span className="sr-only">Acciones</span>
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {clases.map((clase) => {
              const dia = clase.abiertaEn.slice(0, 10)
              return (
                <TableRow key={clase.id}>
                  <TableCell className="whitespace-nowrap font-medium">{fecha(clase.abiertaEn)}</TableCell>
                  <TableCell className="whitespace-nowrap tabular-nums text-muted-foreground">
                    {hora(clase.abiertaEn)} – {hora(clase.cerradaEn)}
                  </TableCell>
                  <TableCell>{clase.practica?.titulo ?? <span className="text-muted-foreground">Sin práctica</span>}</TableCell>
                  <TableCell className="text-right tabular-nums">{clase.asistentes}</TableCell>
                  <TableCell className="hidden text-muted-foreground md:table-cell">{clase.laboratorio.nombre}</TableCell>
                  <TableCell>
                    <MenuDeAcciones
                      etiqueta={`Descargar la clase del ${fecha(clase.abiertaEn)}`}
                      acciones={[
                        { texto: "Hoja en PDF", icono: FileText, alElegir: () => hojaDeRegistroPdf(hojaDeClase(clase), `lista_${dia}.pdf`) },
                        { texto: "Hoja en Excel", icono: FileSpreadsheet, alElegir: () => hojaDeRegistroExcel(hojaDeClase(clase), `lista_${dia}.xlsx`) },
                      ]}
                    />
                  </TableCell>
                </TableRow>
              )
            })}
          </TableBody>
        </Table>
      )}
    </Panel>
  )
}
