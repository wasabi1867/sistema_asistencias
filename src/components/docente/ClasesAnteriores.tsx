import { useCallback, useEffect, useState } from "react"
import { FileDown, FileText, RefreshCw } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { api, type Clase } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { hojaDeClase, hojaDeRegistroExcel, hojaDeRegistroPdf } from "../../lib/exportar"
import { fecha, hora } from "../../lib/formato"

/** Las clases ya cerradas de un curso, con quiénes asistieron, para volver a sacar su hoja. */
export function ClasesAnteriores({ cursoId }: { cursoId: number | null }) {
  const [clases, setClases] = useState<Clase[]>([])
  const [desde, setDesde] = useState("")
  const [hasta, setHasta] = useState("")
  const [cargando, setCargando] = useState(false)

  const cargar = useCallback(async () => {
    if (!cursoId) return setClases([])
    setCargando(true)
    try {
      const respuesta = await api.clases.listar({ curso: cursoId, estado: "cerradas", desde: desde || undefined, hasta: hasta || undefined, conAsistentes: true, porPagina: 200 })
      setClases(respuesta.clases)
    } catch (error) {
      await alertas.error("No se pudieron cargar las clases", error)
    } finally {
      setCargando(false)
    }
  }, [cursoId, desde, hasta])

  useEffect(() => {
    cargar()
  }, [cargar])

  if (!cursoId) {
    return <p className="text-sm text-gray-500">Elige un curso para ver sus clases anteriores.</p>
  }

  return (
    <div className="space-y-3">
      <div className="grid grid-cols-2 gap-2">
        <div className="space-y-1">
          <Label htmlFor="desde-clases">Desde</Label>
          <Input id="desde-clases" type="date" value={desde} onChange={(e) => setDesde(e.target.value)} />
        </div>
        <div className="space-y-1">
          <Label htmlFor="hasta-clases">Hasta</Label>
          <Input id="hasta-clases" type="date" value={hasta} onChange={(e) => setHasta(e.target.value)} />
        </div>
      </div>
      <Button type="button" variant="outline" onClick={cargar} className="w-full" disabled={cargando}>
        <RefreshCw className={`w-4 h-4 mr-2 ${cargando ? "animate-spin" : ""}`} /> Actualizar
      </Button>

      {clases.length === 0 && !cargando && <p className="text-sm text-gray-500">No hay clases cerradas en esas fechas.</p>}
      <ul className="space-y-2">
        {clases.map((clase) => (
          <li key={clase.id} className="p-3 rounded-xl bg-[#e6ffe9] dark:bg-[#153731]/60">
            <p className="font-medium">
              {fecha(clase.abiertaEn)}, {hora(clase.abiertaEn)} a {hora(clase.cerradaEn)}
            </p>
            <p className="text-xs opacity-70">
              {clase.practica?.titulo ?? "Sin práctica"} · {clase.asistentes} asistentes · {clase.laboratorio.nombre}
            </p>
            <div className="flex gap-2 mt-2">
              <Button type="button" size="sm" onClick={() => hojaDeRegistroPdf(hojaDeClase(clase), `lista_${clase.abiertaEn.slice(0, 10)}.pdf`)} className="bg-[#800040] hover:bg-[#5c002e] text-white">
                <FileText className="w-4 h-4 mr-1" /> PDF
              </Button>
              <Button type="button" size="sm" onClick={() => hojaDeRegistroExcel(hojaDeClase(clase), `lista_${clase.abiertaEn.slice(0, 10)}.xlsx`)} className="bg-[#1d5631] hover:bg-[#153d23] text-white">
                <FileDown className="w-4 h-4 mr-1" /> Excel
              </Button>
            </div>
          </li>
        ))}
      </ul>
    </div>
  )
}
