import { useEffect, useMemo, useState, type FormEvent } from "react"
import { Plus, X } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { api, type Curso, type Horario } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { DIAS } from "../../lib/formato"
import { Campo, Panel } from "../comunes/Bloques"
import type { PropsDeVista } from "./vista"

const HORAS = Array.from({ length: 14 }, (_, i) => 7 + i) // 7:00 a 20:00
const aHora = (h: number) => `${String(h).padStart(2, "0")}:00`

/**
 * El horario semanal del laboratorio en el periodo actual: qué curso lo ocupa cada día
 * y a qué hora. Lo arma el personal; la API no deja encimar el laboratorio, el curso
 * ni el docente.
 */
export function VistaHorario({ laboratorio }: PropsDeVista) {
  const [horarios, setHorarios] = useState<Horario[]>([])
  const [cursos, setCursos] = useState<Curso[]>([])
  const [cursoId, setCursoId] = useState("")
  const [dia, setDia] = useState("1")
  const [inicio, setInicio] = useState("7")
  const [fin, setFin] = useState("9")

  const cargar = async () => {
    try {
      setHorarios(await api.horarios.listar({ laboratorio: laboratorio.clave }))
    } catch (error) {
      await alertas.error("No se pudo cargar el horario", error)
    }
  }

  useEffect(() => {
    cargar()
    api.cursos.listar().then(setCursos).catch(() => setCursos([]))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [laboratorio.clave])

  // De lunes a viernes; el sábado solo si hay algo ese día.
  const dias = useMemo(() => (horarios.some((h) => h.dia === 6) ? [1, 2, 3, 4, 5, 6] : [1, 2, 3, 4, 5]), [horarios])

  const enLaCelda = (d: number, h: number) => horarios.find((x) => x.dia === d && Number(x.horaInicio.slice(0, 2)) <= h && Number(x.horaFin.slice(0, 2)) > h)

  const agregar = async (e: FormEvent) => {
    e.preventDefault()
    try {
      await api.horarios.crear({ cursoId: Number(cursoId), laboratorio: laboratorio.clave, dia: Number(dia), horaInicio: aHora(Number(inicio)), horaFin: aHora(Number(fin)) })
      alertas.breve("Agregado al horario")
      await cargar()
    } catch (error) {
      await alertas.error("No se pudo agregar al horario", error)
    }
  }

  const quitar = async (horario: Horario) => {
    if (!(await alertas.confirmar("¿Quitar del horario?", `${horario.curso.materia} ${horario.curso.grupo}, ${DIAS[horario.dia]} de ${horario.horaInicio} a ${horario.horaFin}.`, { aceptar: "Quitar", peligro: true }))) return
    try {
      await api.horarios.eliminar(horario.id)
      await cargar()
    } catch (error) {
      await alertas.error("No se pudo quitar", error)
    }
  }

  const seleccion = (valor: string, cambiar: (v: string) => void, opciones: { valor: string; texto: string }[], id: string, placeholder?: string) => (
    <Select value={valor} onValueChange={cambiar}>
      <SelectTrigger id={id}>
        <SelectValue placeholder={placeholder} />
      </SelectTrigger>
      <SelectContent>
        {opciones.map((o) => (
          <SelectItem key={o.valor} value={o.valor}>
            {o.texto}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )

  return (
    <div className="space-y-6">
      <Panel titulo="Agregar al horario" descripcion="Un curso del periodo actual, un día y un rango de horas.">
        {cursos.length === 0 ? (
          <p className="text-sm text-muted-foreground">No hay cursos en el periodo actual. Los crea el administrador.</p>
        ) : (
          <form onSubmit={agregar} className="grid items-end gap-4 md:grid-cols-[2fr_1fr_1fr_1fr_auto]">
            <Campo etiqueta="Curso" htmlFor="horario-curso">
              {seleccion(
                cursoId,
                setCursoId,
                cursos.map((cu) => ({ valor: String(cu.id), texto: `${cu.materia.nombre} ${cu.grupo}${cu.docente ? ` — ${cu.docente.nombre} ${cu.docente.apellido}` : ""}` })),
                "horario-curso",
                "Elige un curso",
              )}
            </Campo>
            <Campo etiqueta="Día" htmlFor="horario-dia">
              {seleccion(dia, setDia, [1, 2, 3, 4, 5, 6].map((d) => ({ valor: String(d), texto: DIAS[d] })), "horario-dia")}
            </Campo>
            <Campo etiqueta="De" htmlFor="horario-inicio">
              {seleccion(inicio, setInicio, HORAS.map((h) => ({ valor: String(h), texto: aHora(h) })), "horario-inicio")}
            </Campo>
            <Campo etiqueta="A" htmlFor="horario-fin">
              {seleccion(fin, setFin, HORAS.map((h) => h + 1).map((h) => ({ valor: String(h), texto: aHora(h) })), "horario-fin")}
            </Campo>
            <Button type="submit" disabled={!cursoId}>
              <Plus /> Agregar
            </Button>
          </form>
        )}
      </Panel>

      <Panel titulo={`Semana de ${laboratorio.nombre}`} sinRelleno>
        <div className="overflow-x-auto">
          <table className="w-full min-w-[40rem] border-collapse text-sm">
            <thead>
              <tr className="bg-muted/60 text-xs text-muted-foreground">
                <th className="w-24 px-3 py-2 text-left font-medium">Hora</th>
                {dias.map((d) => (
                  <th key={d} className="px-2 py-2 text-left font-medium">
                    {DIAS[d]}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {HORAS.map((h) => (
                <tr key={h} className="border-t">
                  <td className="whitespace-nowrap px-3 py-1.5 align-top text-xs text-muted-foreground tabular-nums">{aHora(h)}</td>
                  {dias.map((d) => {
                    const horario = enLaCelda(d, h)
                    if (!horario) return <td key={d} className="p-1" />
                    const desde = Number(horario.horaInicio.slice(0, 2))
                    // La clase es una sola celda que abarca todas sus horas; las de abajo no se pintan.
                    if (desde !== h) return null
                    const horas = Math.max(1, Math.min(Number(horario.horaFin.slice(0, 2)), HORAS[HORAS.length - 1] + 1) - desde)
                    return (
                      <td key={d} rowSpan={horas} className="h-px p-1 align-top">
                        <div className="group relative h-full rounded-md border-l-4 border-primary bg-accent px-2 py-1.5 text-xs text-accent-foreground">
                          <p className="pr-5 font-semibold">
                            {horario.curso.materia} {horario.curso.grupo}
                          </p>
                          <p className="opacity-80">{horario.curso.docente}</p>
                          <p className="opacity-70 tabular-nums">
                            {horario.horaInicio.slice(0, 5)}–{horario.horaFin.slice(0, 5)}
                          </p>
                          <button
                            type="button"
                            onClick={() => quitar(horario)}
                            className="absolute right-1 top-1 rounded p-0.5 opacity-60 hover:bg-destructive/15 hover:text-destructive hover:opacity-100 focus-visible:opacity-100"
                            aria-label={`Quitar ${horario.curso.materia} ${horario.curso.grupo} del ${DIAS[horario.dia]}`}
                            title="Quitar del horario"
                          >
                            <X className="h-3.5 w-3.5" />
                          </button>
                        </div>
                      </td>
                    )
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Panel>
    </div>
  )
}
