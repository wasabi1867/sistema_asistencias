import { useEffect, useState, type FormEvent } from "react"
import { BarChart3, Edit, Plus, Trash2, Users } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Textarea } from "@/components/ui/textarea"
import { api, type Curso, type Turno } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { fecha, turno as textoDeTurno } from "../../lib/formato"
import { useDatos } from "../../hooks/useDatos"
import { ResumenDelCurso } from "../docente/ResumenDelCurso"
import type { PropsDeSeccion } from "./seccion"

const NINGUNO = "ninguno"

/** Los cursos de cada periodo: una materia que un docente imparte a un grupo. */
export function Cursos({ c }: PropsDeSeccion) {
  const { datos: periodos } = useDatos(() => api.periodos.listar(), [])
  const [periodo, setPeriodo] = useState<string>("")
  const { datos: cursos, recargar } = useDatos(() => (periodo ? api.cursos.listar({ periodo: Number(periodo) }) : Promise.resolve([] as Curso[])), [periodo], "No se pudieron cargar los cursos")
  const [editando, setEditando] = useState<Curso | "nuevo" | null>(null)
  const [inscritos, setInscritos] = useState<Curso | null>(null)
  const [resumen, setResumen] = useState<Curso | null>(null)

  // Al empezar, el periodo actual (o el más reciente).
  useEffect(() => {
    if (periodos?.length && !periodo) {
      setPeriodo(String((periodos.find((p) => p.actual) ?? periodos[0]).id))
    }
  }, [periodos, periodo])

  const eliminar = async (curso: Curso) => {
    if (!(await alertas.confirmar(`¿Eliminar ${curso.materia.nombre} ${curso.grupo}?`, "Solo se puede si todavía no tiene clases. Sus inscripciones y su horario se borran con él.", { aceptar: "Eliminar", peligro: true }))) return
    try {
      await api.cursos.eliminar(curso.id)
      await recargar()
    } catch (error) {
      await alertas.error("No se pudo eliminar el curso", error)
    }
  }

  if (periodos && periodos.length === 0) {
    return <p className={c.texto}>Primero da de alta un periodo escolar en Catálogos.</p>
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap gap-2">
        <Select value={periodo} onValueChange={setPeriodo}>
          <SelectTrigger className="w-64">
            <SelectValue placeholder="Periodo" />
          </SelectTrigger>
          <SelectContent className={c.menu}>
            {(periodos ?? []).map((p) => (
              <SelectItem key={p.id} value={String(p.id)}>
                {p.nombre} {p.actual ? "(actual)" : ""}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Button type="button" onClick={() => setEditando("nuevo")} className={c.botonSecundario}>
          <Plus className="w-4 h-4 mr-2" /> Nuevo curso
        </Button>
      </div>

      <Card className={`${c.tarjeta} border-none shadow-lg`}>
        <CardContent className="p-0 overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Materia</TableHead>
                <TableHead>Grupo</TableHead>
                <TableHead>Docente</TableHead>
                <TableHead>Laboratorio</TableHead>
                <TableHead>Turno</TableHead>
                <TableHead>Inscritos</TableHead>
                <TableHead />
              </TableRow>
            </TableHeader>
            <TableBody>
              {(cursos ?? []).map((curso) => (
                <TableRow key={curso.id}>
                  <TableCell>{curso.materia.nombre}</TableCell>
                  <TableCell>{curso.grupo}</TableCell>
                  <TableCell>{curso.docente ? `${curso.docente.nombre} ${curso.docente.apellido}` : <span className="text-orange-600">Sin docente</span>}</TableCell>
                  <TableCell>{curso.laboratorio?.nombre ?? "Cualquiera"}</TableCell>
                  <TableCell>{textoDeTurno(curso.turno)}</TableCell>
                  <TableCell>{curso.inscritos}</TableCell>
                  <TableCell className="text-right whitespace-nowrap">
                    <Button type="button" size="sm" variant="ghost" onClick={() => setInscritos(curso)} title="Alumnos inscritos">
                      <Users className="w-4 h-4" />
                    </Button>
                    <Button type="button" size="sm" variant="ghost" onClick={() => setResumen(curso)} title="Asistencia">
                      <BarChart3 className="w-4 h-4" />
                    </Button>
                    <Button type="button" size="sm" variant="ghost" onClick={() => setEditando(curso)} title="Editar">
                      <Edit className="w-4 h-4" />
                    </Button>
                    <Button type="button" size="sm" variant="ghost" onClick={() => eliminar(curso)} title="Eliminar" className="text-red-500">
                      <Trash2 className="w-4 h-4" />
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
              {cursos?.length === 0 && (
                <TableRow>
                  <TableCell colSpan={7} className="text-center py-8 opacity-60">
                    No hay cursos en este periodo
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      {editando && (
        <FormularioDeCurso
          curso={editando === "nuevo" ? null : editando}
          periodoId={Number(periodo)}
          alCerrar={() => setEditando(null)}
          alGuardar={() => {
            setEditando(null)
            recargar()
          }}
        />
      )}
      {inscritos && (
        <InscritosDelCurso
          curso={inscritos}
          alCerrar={() => {
            setInscritos(null)
            recargar()
          }}
        />
      )}
      {resumen && (
        <Dialog open onOpenChange={(abierto) => !abierto && setResumen(null)}>
          <DialogContent aria-describedby={undefined} className="sm:max-w-lg max-h-[85vh] overflow-y-auto">
            <DialogHeader>
              <DialogTitle>
                Asistencia de {resumen.materia.nombre} {resumen.grupo}
              </DialogTitle>
            </DialogHeader>
            <ResumenDelCurso cursoId={resumen.id} nombre={`${resumen.materia.nombre} ${resumen.grupo}`} />
          </DialogContent>
        </Dialog>
      )}
    </div>
  )
}

function FormularioDeCurso({ curso, periodoId, alCerrar, alGuardar }: { curso: Curso | null; periodoId: number; alCerrar: () => void; alGuardar: () => void }) {
  const { datos: materias } = useDatos(() => api.materias.listar(), [])
  const { datos: docentes } = useDatos(() => api.usuarios.listar({ rol: "docente", activo: true }), [])
  const { datos: laboratorios } = useDatos(() => api.laboratorios.listar(), [])
  const { datos: periodos } = useDatos(() => api.periodos.listar(), [])
  const [datos, setDatos] = useState({
    materiaId: curso ? String(curso.materia.id) : "",
    periodoId: String(curso?.periodo.id ?? periodoId),
    grupo: curso?.grupo ?? "",
    turno: curso?.turno ?? NINGUNO,
    docenteId: curso?.docente ? String(curso.docente.id) : NINGUNO,
    laboratorio: curso?.laboratorio?.clave ?? NINGUNO,
  })

  const guardar = async (e: FormEvent) => {
    e.preventDefault()
    const cuerpo = {
      materiaId: Number(datos.materiaId),
      periodoId: Number(datos.periodoId),
      grupo: datos.grupo,
      turno: datos.turno === NINGUNO ? null : (datos.turno as Turno),
      docenteId: datos.docenteId === NINGUNO ? null : Number(datos.docenteId),
      laboratorio: datos.laboratorio === NINGUNO ? null : datos.laboratorio,
    }
    try {
      if (curso) {
        await api.cursos.editar(curso.id, cuerpo)
      } else {
        await api.cursos.crear(cuerpo)
      }
      alGuardar()
    } catch (error) {
      await alertas.error("No se pudo guardar el curso", error)
    }
  }

  const elegir = (clave: keyof typeof datos, etiqueta: string, opciones: { valor: string; texto: string }[], conNinguno?: string) => (
    <div className="space-y-1">
      <Label>{etiqueta}</Label>
      <Select value={datos[clave]} onValueChange={(v) => setDatos({ ...datos, [clave]: v })}>
        <SelectTrigger>
          <SelectValue placeholder={`Elige ${etiqueta.toLowerCase()}`} />
        </SelectTrigger>
        <SelectContent>
          {conNinguno && <SelectItem value={NINGUNO}>{conNinguno}</SelectItem>}
          {opciones.map((o) => (
            <SelectItem key={o.valor} value={o.valor}>
              {o.texto}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  )

  return (
    <Dialog open onOpenChange={(abierto) => !abierto && alCerrar()}>
      <DialogContent aria-describedby={undefined} className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{curso ? "Editar curso" : "Nuevo curso"}</DialogTitle>
        </DialogHeader>
        <form onSubmit={guardar} className="space-y-3">
          {elegir("materiaId", "Materia", (materias ?? []).map((m) => ({ valor: String(m.id), texto: `${m.nombre}${m.carrera ? ` (${m.carrera.clave})` : ""}` })))}
          <div className="grid grid-cols-2 gap-3">
            {elegir("periodoId", "Periodo", (periodos ?? []).map((p) => ({ valor: String(p.id), texto: p.nombre })))}
            <div className="space-y-1">
              <Label htmlFor="grupo-curso">Grupo</Label>
              <Input id="grupo-curso" value={datos.grupo} onChange={(e) => setDatos({ ...datos, grupo: e.target.value.toUpperCase() })} placeholder="Ej. 5A" required />
            </div>
          </div>
          {elegir("docenteId", "Docente", (docentes ?? []).map((d) => ({ valor: String(d.id), texto: `${d.nombre} ${d.apellido}` })), "Sin docente por ahora")}
          <div className="grid grid-cols-2 gap-3">
            {elegir("laboratorio", "Laboratorio", (laboratorios ?? []).map((l) => ({ valor: l.clave, texto: l.nombre })), "Cualquiera")}
            {elegir("turno", "Turno", [{ valor: "matutino", texto: "Matutino" }, { valor: "vespertino", texto: "Vespertino" }], "Sin turno")}
          </div>
          <Button type="submit" disabled={!datos.materiaId} className="w-full bg-[#1d5631] hover:bg-[#153d23] text-white">
            Guardar
          </Button>
        </form>
      </DialogContent>
    </Dialog>
  )
}

/** Los inscritos de un curso: pegar una lista de matrículas para inscribirlos, o dar de baja a uno. */
function InscritosDelCurso({ curso, alCerrar }: { curso: Curso; alCerrar: () => void }) {
  const { datos: inscritos, recargar } = useDatos(() => api.cursos.inscritos(curso.id), [curso.id])
  const [lista, setLista] = useState("")

  const inscribir = async () => {
    const matriculas = lista
      .split(/[\s,;]+/)
      .map((m) => m.trim().toUpperCase())
      .filter(Boolean)
    if (matriculas.length === 0) return
    try {
      const r = await api.cursos.inscribir(curso.id, matriculas)
      const partes = [
        `${r.inscritos.length} inscritos`,
        r.yaInscritos.length ? `${r.yaInscritos.length} ya lo estaban` : "",
        r.noEncontrados.length ? `no existen: ${r.noEncontrados.join(", ")}` : "",
        r.inactivos.length ? `desactivados: ${r.inactivos.join(", ")}` : "",
      ].filter(Boolean)
      await (r.noEncontrados.length || r.inactivos.length ? alertas.aviso("Inscripción hecha a medias", partes.join(". ")) : alertas.exito("Alumnos inscritos", partes.join(". ")))
      setLista("")
      await recargar()
    } catch (error) {
      await alertas.error("No se pudo inscribir", error)
    }
  }

  const darDeBaja = async (matricula: string, nombre: string) => {
    if (!(await alertas.confirmar("¿Dar de baja del curso?", nombre, { aceptar: "Dar de baja", peligro: true }))) return
    try {
      await api.cursos.darDeBaja(curso.id, matricula)
      await recargar()
    } catch (error) {
      await alertas.error("No se pudo dar de baja", error)
    }
  }

  return (
    <Dialog open onOpenChange={(abierto) => !abierto && alCerrar()}>
      <DialogContent aria-describedby={undefined} className="sm:max-w-2xl max-h-[85vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>
            {curso.materia.nombre} {curso.grupo}: {inscritos?.length ?? curso.inscritos} inscritos
          </DialogTitle>
        </DialogHeader>
        <div className="space-y-2">
          <Label htmlFor="lista-matriculas">Inscribir: pega los números de control</Label>
          <Textarea id="lista-matriculas" value={lista} onChange={(e) => setLista(e.target.value)} rows={3} placeholder="Uno por renglón, o separados por comas" />
          <Button type="button" onClick={inscribir} className="bg-[#1d5631] hover:bg-[#153d23] text-white">
            Inscribir
          </Button>
        </div>
        <ul className="space-y-1 text-sm">
          {(inscritos ?? []).map((a) => (
            <li key={a.id} className="flex justify-between items-center p-2 rounded-lg bg-[#e6ffe9] dark:bg-[#153731]/60">
              <span>
                <span className="font-mono">{a.matricula}</span> {a.apellido} {a.nombre} <span className="opacity-60">desde {fecha(a.inscritoEn)}</span>
              </span>
              <button type="button" onClick={() => darDeBaja(a.matricula, `${a.nombre} ${a.apellido}`)} className="text-red-500" title="Dar de baja">
                <Trash2 className="w-4 h-4" />
              </button>
            </li>
          ))}
        </ul>
      </DialogContent>
    </Dialog>
  )
}
