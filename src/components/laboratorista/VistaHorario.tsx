import { useEffect, useMemo, useState, type FormEvent } from "react"
import { Plus, Trash2 } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { api, type Curso, type Horario } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { DIAS } from "../../lib/formato"
import { encabezadoDeTarjeta, type PropsDeVista } from "./vista"

const HORAS = Array.from({ length: 14 }, (_, i) => 7 + i) // 7:00 a 20:00
const aHora = (h: number) => `${String(h).padStart(2, "0")}:00`

/**
 * El horario semanal del laboratorio en el periodo actual: qué curso lo ocupa cada día
 * y a qué hora. Lo arma el personal; la API no deja encimar el laboratorio, el curso
 * ni el docente.
 */
export function VistaHorario({ laboratorio, oscuro, c }: PropsDeVista) {
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

  return (
    <div className="space-y-6">
      <Card className={`${c.tarjeta} border-none shadow-lg`}>
        <CardHeader className={encabezadoDeTarjeta(oscuro)}>
          <CardTitle className="text-lg">Agregar al horario</CardTitle>
        </CardHeader>
        <CardContent className="pt-4">
          {cursos.length === 0 ? (
            <p className={c.textoSuave}>No hay cursos en el periodo actual. Los crea el administrador.</p>
          ) : (
            <form onSubmit={agregar} className="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
              <div className="space-y-1 md:col-span-2">
                <Label>Curso</Label>
                <Select value={cursoId} onValueChange={setCursoId}>
                  <SelectTrigger>
                    <SelectValue placeholder="Elige un curso" />
                  </SelectTrigger>
                  <SelectContent className={c.menu}>
                    {cursos.map((cu) => (
                      <SelectItem key={cu.id} value={String(cu.id)}>
                        {cu.materia.nombre} {cu.grupo} {cu.docente ? `— ${cu.docente.nombre} ${cu.docente.apellido}` : ""}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1">
                <Label>Día</Label>
                <Select value={dia} onValueChange={setDia}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent className={c.menu}>
                    {[1, 2, 3, 4, 5, 6].map((d) => (
                      <SelectItem key={d} value={String(d)}>
                        {DIAS[d]}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1">
                <Label>De / a</Label>
                <div className="flex gap-1">
                  <Select value={inicio} onValueChange={setInicio}>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent className={c.menu}>
                      {HORAS.map((h) => (
                        <SelectItem key={h} value={String(h)}>
                          {aHora(h)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <Select value={fin} onValueChange={setFin}>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent className={c.menu}>
                      {HORAS.map((h) => h + 1).map((h) => (
                        <SelectItem key={h} value={String(h)}>
                          {aHora(h)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>
              <Button type="submit" disabled={!cursoId} className={c.botonSecundario}>
                <Plus className="w-4 h-4 mr-2" /> Agregar
              </Button>
            </form>
          )}
        </CardContent>
      </Card>

      <Card className={`${c.tarjeta} border-none shadow-lg`}>
        <CardContent className="p-0 overflow-x-auto">
          <table className="w-full text-sm border-collapse">
            <thead>
              <tr className={oscuro ? "bg-[#153731]" : "bg-[#e6ffe9]"}>
                <th className="p-2 w-20">Hora</th>
                {dias.map((d) => (
                  <th key={d} className="p-2">
                    {DIAS[d]}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {HORAS.map((h) => (
                <tr key={h} className={`border-t ${c.borde}`}>
                  <td className="p-2 text-center opacity-70">
                    {aHora(h)}–{aHora(h + 1)}
                  </td>
                  {dias.map((d) => {
                    const horario = enLaCelda(d, h)
                    const empieza = horario && Number(horario.horaInicio.slice(0, 2)) === h
                    return (
                      <td key={d} className="p-1 align-top">
                        {horario && (
                          <div className={`p-2 rounded-lg text-xs ${empieza ? "" : "opacity-60"} ${oscuro ? "bg-[#1d5631]/50" : "bg-[#800040]/10"}`}>
                            {empieza ? (
                              <>
                                <div className="flex justify-between gap-1">
                                  <span className="font-semibold">
                                    {horario.curso.materia} {horario.curso.grupo}
                                  </span>
                                  <button type="button" onClick={() => quitar(horario)} title="Quitar" className="text-red-500">
                                    <Trash2 className="w-3 h-3" />
                                  </button>
                                </div>
                                <div className="opacity-80">{horario.curso.docente}</div>
                              </>
                            ) : (
                              <span>{horario.curso.materia}</span>
                            )}
                          </div>
                        )}
                      </td>
                    )
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </CardContent>
      </Card>
    </div>
  )
}
