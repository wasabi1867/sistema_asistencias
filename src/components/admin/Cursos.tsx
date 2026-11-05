import { useEffect, useState, type FormEvent } from "react"
import { BarChart3, BookOpen, Pencil, Plus, Trash2, UserMinus, Users } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Textarea } from "@/components/ui/textarea"
import { api, type Curso, type DatosDeAlumno, type ResultadoDeInscripcion, type Turno } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { leerHojaDeCalculo } from "../../lib/exportar"
import { alumnoSinNombre, COLUMNAS_QUE_FALTAN, leerListaDeAlumnos } from "../../lib/listasDeAlumnos"
import { RevisionDeNombres } from "./alumnos/RevisionDeNombres"
import { SelectorDeArchivo } from "./alumnos/CargaDesdeArchivo"
import { fecha, turno as textoDeTurno } from "../../lib/formato"
import { useDatos } from "../../hooks/useDatos"
import { ResumenDelCurso } from "../docente/ResumenDelCurso"
import { Campo, Listado, Panel, SinDatos } from "../comunes/Bloques"
import { MenuDeAcciones } from "../comunes/MenuDeAcciones"
import type { PropsDeSeccion } from "./seccion"

const NINGUNO = "ninguno"

/** Los cursos de cada periodo: una materia que un docente imparte a un grupo. */
export function Cursos({ irA }: PropsDeSeccion) {
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
    return (
      <Panel>
        <SinDatos icono={BookOpen} titulo="Todavía no hay periodos escolares">
          Los cursos son de un periodo. Da de alta el primero en{" "}
          <button type="button" className="font-medium text-primary underline-offset-4 hover:underline" onClick={() => irA("catalogos")}>
            Carreras, periodos y laboratorios
          </button>
          .
        </SinDatos>
      </Panel>
    )
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <Select value={periodo} onValueChange={setPeriodo}>
          <SelectTrigger className="w-full sm:w-64" aria-label="Periodo">
            <SelectValue placeholder="Periodo" />
          </SelectTrigger>
          <SelectContent>
            {(periodos ?? []).map((p) => (
              <SelectItem key={p.id} value={String(p.id)}>
                {p.nombre} {p.actual ? "(actual)" : ""}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Button type="button" onClick={() => setEditando("nuevo")}>
          <Plus /> Nuevo curso
        </Button>
      </div>

      <Listado pie={cursos ? <span className="tabular-nums">{cursos.length} cursos en el periodo</span> : undefined}>
        {cursos?.length === 0 ? (
          <SinDatos icono={Users} titulo="No hay cursos en este periodo">
            Un curso junta una materia, un grupo y un docente.
          </SinDatos>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Materia</TableHead>
                <TableHead className="hidden sm:table-cell">Docente</TableHead>
                <TableHead className="hidden md:table-cell">Laboratorio</TableHead>
                <TableHead className="hidden sm:table-cell">Turno</TableHead>
                <TableHead className="text-right">Inscritos</TableHead>
                <TableHead className="w-12">
                  <span className="sr-only">Acciones</span>
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {(cursos ?? []).map((curso) => (
                <TableRow key={curso.id} className="cursor-pointer" onClick={() => setInscritos(curso)}>
                  <TableCell>
                    <span className="font-medium">{curso.materia.nombre}</span> <span className="text-muted-foreground">· {curso.grupo}</span>
                    <span className="block text-xs text-muted-foreground sm:hidden">{curso.docente ? `${curso.docente.nombre} ${curso.docente.apellido}` : "Sin docente"}</span>
                  </TableCell>
                  <TableCell className="hidden sm:table-cell">{curso.docente ? `${curso.docente.nombre} ${curso.docente.apellido}` : <Badge variant="warning">Sin docente</Badge>}</TableCell>
                  <TableCell className="hidden text-muted-foreground md:table-cell">{curso.laboratorio?.nombre ?? "Cualquiera"}</TableCell>
                  <TableCell className="hidden text-muted-foreground sm:table-cell">{textoDeTurno(curso.turno)}</TableCell>
                  <TableCell className="text-right tabular-nums">{curso.inscritos}</TableCell>
                  <TableCell>
                    <MenuDeAcciones
                      etiqueta={`Acciones para ${curso.materia.nombre} ${curso.grupo}`}
                      acciones={[
                        { texto: "Alumnos inscritos", icono: Users, alElegir: () => setInscritos(curso) },
                        { texto: "Asistencia", icono: BarChart3, alElegir: () => setResumen(curso) },
                        { texto: "Editar", icono: Pencil, alElegir: () => setEditando(curso) },
                        { texto: "Eliminar", icono: Trash2, peligro: true, alElegir: () => eliminar(curso) },
                      ]}
                    />
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </Listado>

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
          <DialogContent className="gap-0 p-0 sm:max-w-2xl">
            <DialogHeader className="p-6 pb-4">
              <DialogTitle>
                Asistencia de {resumen.materia.nombre} {resumen.grupo}
              </DialogTitle>
              <DialogDescription>Sobre las clases ya cerradas del curso.</DialogDescription>
            </DialogHeader>
            <div className="border-t">
              <ResumenDelCurso cursoId={resumen.id} nombre={`${resumen.materia.nombre} ${resumen.grupo}`} />
            </div>
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
      alertas.breve(curso ? "Curso actualizado" : "Curso creado")
      alGuardar()
    } catch (error) {
      await alertas.error("No se pudo guardar el curso", error)
    }
  }

  const elegir = (clave: keyof typeof datos, etiqueta: string, opciones: { valor: string; texto: string }[], conNinguno?: string) => (
    <Campo etiqueta={etiqueta} htmlFor={`curso-${clave}`}>
      <Select value={datos[clave]} onValueChange={(v) => setDatos({ ...datos, [clave]: v })}>
        <SelectTrigger id={`curso-${clave}`}>
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
    </Campo>
  )

  return (
    <Dialog open onOpenChange={(abierto) => !abierto && alCerrar()}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{curso ? "Editar curso" : "Nuevo curso"}</DialogTitle>
          <DialogDescription>Una materia con su grupo y su docente en un periodo.</DialogDescription>
        </DialogHeader>
        <form onSubmit={guardar} className="space-y-4">
          {elegir("materiaId", "Materia", (materias ?? []).map((m) => ({ valor: String(m.id), texto: `${m.nombre}${m.carrera ? ` (${m.carrera.clave})` : ""}` })))}
          <div className="grid grid-cols-2 gap-4">
            {elegir("periodoId", "Periodo", (periodos ?? []).map((p) => ({ valor: String(p.id), texto: p.nombre })))}
            <Campo etiqueta="Grupo" htmlFor="grupo-curso">
              <Input id="grupo-curso" value={datos.grupo} onChange={(e) => setDatos({ ...datos, grupo: e.target.value.toUpperCase() })} placeholder="Ej. 5A" required />
            </Campo>
          </div>
          {elegir("docenteId", "Docente", (docentes ?? []).map((d) => ({ valor: String(d.id), texto: `${d.nombre} ${d.apellido}` })), "Sin docente por ahora")}
          <div className="grid grid-cols-2 gap-4">
            {elegir("laboratorio", "Laboratorio", (laboratorios ?? []).map((l) => ({ valor: l.clave, texto: l.nombre })), "Cualquiera")}
            {elegir("turno", "Turno", [{ valor: "matutino", texto: "Matutino" }, { valor: "vespertino", texto: "Vespertino" }], "Sin turno")}
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={alCerrar}>
              Cancelar
            </Button>
            <Button type="submit" disabled={!datos.materiaId}>
              Guardar
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

const inscritosEnTexto = (n: number) => (n === 1 ? "1 inscrito" : `${n} inscritos`)

/** Lo que pasó al inscribir una lista, en una oración: "3 inscritos. 1 ya lo estaba...". */
function resumenDeInscripcion(r: ResultadoDeInscripcion) {
  return [
    inscritosEnTexto(r.inscritos.length),
    r.yaInscritos.length ? (r.yaInscritos.length === 1 ? "1 ya lo estaba" : `${r.yaInscritos.length} ya lo estaban`) : "",
    r.noEncontrados.length ? `no existen: ${r.noEncontrados.join(", ")}` : "",
    r.inactivos.length ? `desactivados: ${r.inactivos.join(", ")}` : "",
  ]
    .filter(Boolean)
    .join(". ")
}

/**
 * Los inscritos de un curso: pegar una lista de matrículas o subir la lista de la materia
 * (la que se baja de Mindbox) para inscribirlos, o dar de baja a uno.
 */
function InscritosDelCurso({ curso, alCerrar }: { curso: Curso; alCerrar: () => void }) {
  const { datos: inscritos, recargar } = useDatos(() => api.cursos.inscritos(curso.id), [curso.id])
  const [lista, setLista] = useState("")
  const [archivo, setArchivo] = useState("")
  // Los de la lista de la materia que no están dados de alta: se revisan sus nombres antes de darlos de alta.
  const [nuevos, setNuevos] = useState<{ alumnos: DatosDeAlumno[]; dudosos: boolean[] } | null>(null)
  const [enviando, setEnviando] = useState(false)

  /**
   * Inscribe a los que ya están dados de alta. A los que no, los deja para revisar sus
   * nombres: así una lista con nombres mal separados no cambia a los alumnos que ya existían.
   */
  const subirLista = async (file: File | undefined) => {
    if (!file) return
    setNuevos(null)
    try {
      const leida = leerListaDeAlumnos(await leerHojaDeCalculo(file))
      if (!leida || leida.alumnos.length === 0) {
        await alertas.aviso(leida ? "La lista está vacía" : "Faltan columnas", leida ? "No se encontró ninguna matrícula en el archivo." : COLUMNAS_QUE_FALTAN)
        return
      }
      setArchivo(file.name)
      const r = await api.cursos.inscribir(
        curso.id,
        leida.alumnos.map((a) => a.matricula),
      )
      await recargar()
      const faltan = new Set(r.noEncontrados.map((m) => m.toUpperCase()))
      const indices = leida.alumnos.map((a, i) => (faltan.has(a.matricula) ? i : -1)).filter((i) => i >= 0)
      if (indices.length === 0) {
        await (r.inactivos.length ? alertas.aviso("Inscripción hecha a medias", resumenDeInscripcion(r)) : alertas.exito("Lista de la materia inscrita", resumenDeInscripcion(r)))
        return
      }
      alertas.breve(`${inscritosEnTexto(r.inscritos.length)}. ${indices.length === 1 ? "Falta 1 que no está dado de alta" : `Faltan ${indices.length} que no están dados de alta`}`)
      setNuevos({ alumnos: indices.map((i) => leida.alumnos[i]), dudosos: indices.map((i) => leida.dudosos[i]) })
    } catch (error) {
      await alertas.error("No se pudo inscribir la lista", error)
    }
  }

  const corregir = (i: number, campo: "nombre" | "apellido", valor: string) =>
    setNuevos((n) => n && { ...n, alumnos: n.alumnos.map((a, j) => (j === i ? { ...a, [campo]: valor } : a)) })

  const darDeAltaEInscribir = async () => {
    if (!nuevos) return
    const sinNombre = alumnoSinNombre(nuevos.alumnos)
    if (sinNombre) {
      await alertas.aviso("Falta un nombre", `El alumno ${sinNombre.matricula} quedó sin nombre. Escríbelo en la lista.`)
      return
    }
    setEnviando(true)
    try {
      await api.alumnos.cargarLote(nuevos.alumnos)
      const r = await api.cursos.inscribir(
        curso.id,
        nuevos.alumnos.map((a) => a.matricula),
      )
      setNuevos(null)
      await recargar()
      await alertas.exito("Alumnos dados de alta e inscritos", resumenDeInscripcion(r))
    } catch (error) {
      await alertas.error("No se pudieron dar de alta", error)
    } finally {
      setEnviando(false)
    }
  }

  const inscribir = async (e: FormEvent) => {
    e.preventDefault()
    const matriculas = lista
      .split(/[\s,;]+/)
      .map((m) => m.trim().toUpperCase())
      .filter(Boolean)
    if (matriculas.length === 0) return
    try {
      const r = await api.cursos.inscribir(curso.id, matriculas)
      await (r.noEncontrados.length || r.inactivos.length ? alertas.aviso("Inscripción hecha a medias", resumenDeInscripcion(r)) : alertas.exito("Alumnos inscritos", resumenDeInscripcion(r)))
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
      <DialogContent className="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>
            {curso.materia.nombre} · {curso.grupo}
          </DialogTitle>
          <DialogDescription>
            {inscritos?.length ?? curso.inscritos} inscritos · {curso.docente ? `${curso.docente.nombre} ${curso.docente.apellido}` : "sin docente"}
          </DialogDescription>
        </DialogHeader>
        <form onSubmit={inscribir} className="space-y-2">
          <Campo etiqueta="Inscribir: pega los números de control" htmlFor="lista-matriculas" ayuda="Uno por renglón, o separados por comas o espacios.">
            <Textarea id="lista-matriculas" value={lista} onChange={(e) => setLista(e.target.value)} rows={3} />
          </Campo>
          <Button type="submit" disabled={!lista.trim()}>
            Inscribir
          </Button>
        </form>
        <div className="space-y-2">
          <p className="text-sm font-medium">O sube la lista de la materia</p>
          <p className="text-xs text-muted-foreground">
            El Excel que bajas de Mindbox, o cualquier lista con Matrícula y el nombre. Se inscriben los que ya están dados de alta; los demás los revisas y los das de alta aquí mismo.
          </p>
          <SelectorDeArchivo archivo={archivo} alElegir={subirLista} compacto />
        </div>
        {nuevos && (
          <div className="space-y-2">
            <RevisionDeNombres
              alumnos={nuevos.alumnos}
              dudosos={nuevos.dudosos}
              alCorregir={corregir}
              titulo={`${nuevos.alumnos.length === 1 ? "Este alumno no está dado de alta" : `Estos ${nuevos.alumnos.length} alumnos no están dados de alta`}. Revisa su nombre y dalos de alta para inscribirlos. La carrera, el semestre y el grupo se completan después en Alumnos.`}
            />
            <div className="flex flex-wrap justify-end gap-2">
              <Button type="button" variant="outline" onClick={() => setNuevos(null)}>
                Omitirlos
              </Button>
              <Button type="button" onClick={darDeAltaEInscribir} disabled={enviando}>
                {enviando ? "Dando de alta…" : "Dar de alta e inscribir"}
              </Button>
            </div>
          </div>
        )}
        {inscritos?.length === 0 ? (
          <div className="rounded-lg border">
            <SinDatos icono={Users} titulo="Nadie inscrito todavía" className="py-6" />
          </div>
        ) : (
          <ul className="max-h-80 divide-y overflow-y-auto rounded-lg border">
            {(inscritos ?? []).map((a) => (
              <li key={a.id} className="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                <span className="min-w-0">
                  <span className="font-medium">
                    {a.apellido} {a.nombre}
                  </span>{" "}
                  <span className="text-xs text-muted-foreground tabular-nums">
                    {a.matricula} · desde {fecha(a.inscritoEn)}
                  </span>
                </span>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-sm"
                  className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                  onClick={() => darDeBaja(a.matricula, `${a.nombre} ${a.apellido}`)}
                  aria-label={`Dar de baja a ${a.nombre} ${a.apellido}`}
                  title="Dar de baja"
                >
                  <UserMinus />
                </Button>
              </li>
            ))}
          </ul>
        )}
      </DialogContent>
    </Dialog>
  )
}
