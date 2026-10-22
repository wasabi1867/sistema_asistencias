import { useCallback, useEffect, useMemo, useState } from "react"
import { useRouter } from "next/navigation"
import { AlertTriangle, BarChart3, BookOpen, Check, ClipboardList, History, Loader2, PlayCircle, Presentation } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { cn } from "@/lib/utils"
import { api, type Clase, type Curso, type Laboratorio, type Practica } from "../lib/api"
import { alertas } from "../lib/alertas"
import { duracion, fecha } from "../lib/formato"
import { laboratorioDeEstaComputadora } from "../lib/rutas"
import { useSesion } from "../hooks/useSesion"
import { useSeccion } from "../hooks/useSeccion"
import { Cargando } from "../components/comunes/Cargando"
import { EstructuraDelPanel, type SeccionDelMenu } from "../components/comunes/EstructuraDelPanel"
import { Panel, SinDatos } from "../components/comunes/Bloques"
import { ClaseEnCurso } from "../components/clase/ClaseEnCurso"
import { PracticasDelDocente } from "../components/docente/PracticasDelDocente"
import { ClasesAnteriores } from "../components/docente/ClasesAnteriores"
import { ResumenDelCurso } from "../components/docente/ResumenDelCurso"

const SECCIONES = [
  { id: "clase", nombre: "Clase", icono: Presentation },
  { id: "practicas", nombre: "Prácticas", icono: ClipboardList },
  { id: "anteriores", nombre: "Clases anteriores", icono: History },
  { id: "asistencia", nombre: "Asistencia del curso", icono: BarChart3 },
] as const satisfies readonly SeccionDelMenu[]

type IdDeSeccion = (typeof SECCIONES)[number]["id"]
const IDS = SECCIONES.map((s) => s.id)

/**
 * El panel del docente: elige uno de sus cursos y una práctica, abre la clase en el
 * laboratorio donde inició sesión, ve quién entra y la cierra. Si cierra la pestaña
 * con la clase abierta, la clase se cierra sola.
 */
export default function PanelDelDocente() {
  const router = useRouter()
  const { usuario } = useSesion(["docente"])
  const [seccion, irA] = useSeccion<IdDeSeccion>(IDS, "clase")
  const [laboratorio, setLaboratorio] = useState<Laboratorio | null>(null)
  const [cursos, setCursos] = useState<Curso[] | null>(null)
  const [cursoId, setCursoId] = useState<number | null>(null)
  const [practicas, setPracticas] = useState<Practica[]>([])
  const [claseAbierta, setClaseAbierta] = useState<number | null>(null)

  // Lo inicial: el laboratorio de esta computadora, los cursos del docente y, si dejó
  // una clase abierta (por ejemplo, se cerró el navegador), la retoma.
  useEffect(() => {
    if (!usuario) return
    const clave = laboratorioDeEstaComputadora()
    Promise.all([api.laboratorios.listar(), api.cursos.listar({ docente: "yo" }), api.clases.listar({ estado: "abiertas", docente: "yo", tipo: "regular" })])
      .then(([laboratorios, suyos, abiertas]) => {
        setLaboratorio(laboratorios.find((l) => l.clave === clave) ?? null)
        setCursos(suyos)
        const abierta = abiertas.clases[0]
        if (abierta) {
          setClaseAbierta(abierta.id)
          setCursoId(abierta.cursoId)
        } else if (suyos.length > 0) {
          setCursoId(suyos[0].id)
        }
      })
      .catch((error) => alertas.error("No se pudieron cargar tus cursos", error))
  }, [usuario])

  const curso = useMemo(() => cursos?.find((cu) => cu.id === cursoId) ?? null, [cursos, cursoId])

  const cargarPracticas = useCallback(async () => {
    if (!curso) return setPracticas([])
    try {
      setPracticas(await api.practicas.deMateria(curso.materia.id))
    } catch (error) {
      await alertas.error("No se pudieron cargar las prácticas", error)
    }
  }, [curso])

  useEffect(() => {
    cargarPracticas()
  }, [cargarPracticas])

  // Si cierra la pestaña con la clase abierta, se cierra sola y se registra la salida de todos.
  useEffect(() => {
    if (!claseAbierta) return
    const alSalir = () => api.clases.cerrarAlSalir(claseAbierta)
    window.addEventListener("beforeunload", alSalir)
    return () => window.removeEventListener("beforeunload", alSalir)
  }, [claseAbierta])

  const alAbrir = (clase: Clase) => {
    setClaseAbierta(clase.id)
    alertas.breve("Clase abierta: los alumnos ya pueden registrar su entrada")
  }

  const alCerrarse = useCallback(
    async (clase: Clase, propia: boolean) => {
      setClaseAbierta(null)
      if (propia) {
        await alertas.exito("Clase finalizada", `Se registró la salida de todos. Asistieron ${clase.asistentes}.`)
        return
      }
      // La cerró el laboratorista o el administrador: la sesión del docente termina.
      await alertas.info("Clase finalizada", "Un administrador o laboratorista finalizó la clase. Tu sesión se cerrará.")
      await api.sesion.cerrar().catch(() => undefined)
      router.push("/")
    },
    [router],
  )

  const cerrarSesion = async () => {
    if (!(await alertas.confirmar("¿Cerrar sesión?", "Tendrás que volver a iniciar sesión para abrir una clase.", { aceptar: "Cerrar sesión" }))) return
    await api.sesion.cerrar().catch(() => undefined)
    router.push("/")
  }

  if (!usuario || cursos === null) {
    return <Cargando />
  }

  const actual = SECCIONES.find((s) => s.id === seccion) ?? SECCIONES[0]
  const nombreDelCurso = curso ? `${curso.materia.nombre} ${curso.grupo}` : ""

  return (
    <EstructuraDelPanel
      panel="Panel del docente"
      usuario={usuario}
      secciones={[...SECCIONES]}
      seccion={seccion}
      alElegir={irA}
      titulo={actual.nombre}
      subtitulo={laboratorio ? `En ${laboratorio.nombre}` : "Sin laboratorio elegido en esta computadora"}
      alCerrarSesion={cerrarSesion}
      salidaBloqueada={claseAbierta ? "Finaliza la clase antes de salir." : undefined}
    >
      <div className="space-y-6">
        {cursos.length > 0 && !(seccion === "clase" && claseAbierta) && (
          <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
            <label htmlFor="curso" className="text-sm font-medium">
              Curso
            </label>
            <Select value={cursoId ? String(cursoId) : ""} onValueChange={(valor) => setCursoId(Number(valor))} disabled={claseAbierta !== null}>
              <SelectTrigger id="curso" className="w-full sm:w-80">
                <SelectValue placeholder="Elige un curso" />
              </SelectTrigger>
              <SelectContent>
                {cursos.map((cu) => (
                  <SelectItem key={cu.id} value={String(cu.id)}>
                    {cu.materia.nombre} · {cu.grupo}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {curso && (
              <span className="text-sm text-muted-foreground">
                {curso.inscritos} inscritos · {curso.periodo.nombre}
                {curso.laboratorio && curso.laboratorio.clave !== laboratorio?.clave ? ` · normalmente en ${curso.laboratorio.nombre}` : ""}
              </span>
            )}
          </div>
        )}

        {seccion === "clase" &&
          (claseAbierta ? (
            <ClaseEnCurso claseId={claseAbierta} alCerrarse={alCerrarse} />
          ) : cursos.length === 0 ? (
            <Panel>
              <SinDatos icono={BookOpen} titulo="No tienes cursos en el periodo actual">
                Pide al administrador que te asigne los tuyos.
              </SinDatos>
            </Panel>
          ) : (
            <PrepararClase curso={curso} practicas={practicas} laboratorio={laboratorio} alAbrir={alAbrir} />
          ))}
        {seccion === "practicas" && <PracticasDelDocente materia={curso?.materia ?? null} docenteId={usuario.id} alCambiar={cargarPracticas} />}
        {seccion === "anteriores" && <ClasesAnteriores cursoId={cursoId} />}
        {seccion === "asistencia" && (
          <Panel titulo={nombreDelCurso ? `Asistencia de ${nombreDelCurso}` : "Asistencia del curso"} sinRelleno>
            <ResumenDelCurso cursoId={cursoId} nombre={nombreDelCurso} />
          </Panel>
        )}
      </div>
    </EstructuraDelPanel>
  )
}

/** Elegir la práctica y abrir la clase del curso en el laboratorio de esta computadora. */
function PrepararClase({ curso, practicas, laboratorio, alAbrir }: { curso: Curso | null; practicas: Practica[]; laboratorio: Laboratorio | null; alAbrir: (clase: Clase) => void }) {
  const [practicaId, setPracticaId] = useState<number | null>(null)
  const [abriendo, setAbriendo] = useState(false)

  useEffect(() => setPracticaId(null), [curso?.id])

  const abrir = async () => {
    if (!curso) return
    setAbriendo(true)
    try {
      alAbrir(await api.clases.abrir({ cursoId: curso.id, laboratorio: laboratorio?.clave, practicaId }))
    } catch (error) {
      await alertas.error("No se pudo iniciar la clase", error)
    } finally {
      setAbriendo(false)
    }
  }

  const opciones: { id: number | null; titulo: string; detalle: string; numero?: number }[] = [
    ...practicas.map((p) => ({ id: p.id, numero: p.numero, titulo: p.titulo, detalle: [p.comun ? "Común" : "Tuya", duracion(p.duracionMinutos), fecha(p.fecha)].filter(Boolean).join(" · ") })),
    { id: null, titulo: "Sin práctica", detalle: "Una clase sin práctica registrada" },
  ]

  return (
    <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
      <Panel titulo="¿Qué práctica toca hoy?" descripcion={curso ? `${curso.materia.nombre} · ${curso.grupo}` : undefined} sinRelleno>
        <fieldset>
          <legend className="sr-only">Práctica</legend>
          <ul className="max-h-[30rem] divide-y overflow-y-auto">
            {opciones.map((opcion) => {
              const elegida = practicaId === opcion.id
              return (
                <li key={opcion.id ?? "ninguna"}>
                  <button
                    type="button"
                    aria-pressed={elegida}
                    onClick={() => setPracticaId(opcion.id)}
                    className={cn("flex w-full items-center gap-4 px-4 py-3 text-left transition-colors focus-visible:bg-accent focus-visible:outline-none sm:px-5", elegida ? "bg-accent" : "hover:bg-muted/50")}
                  >
                    <span
                      className={cn(
                        "flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold tabular-nums",
                        elegida ? "bg-primary text-primary-foreground" : "bg-muted text-muted-foreground",
                      )}
                    >
                      {elegida ? <Check className="h-4 w-4" /> : (opcion.numero ?? "–")}
                    </span>
                    <span className="min-w-0 flex-1">
                      <span className={cn("block font-medium", elegida && "text-accent-foreground")}>{opcion.titulo}</span>
                      <span className="block truncate text-xs text-muted-foreground">{opcion.detalle}</span>
                    </span>
                  </button>
                </li>
              )
            })}
          </ul>
        </fieldset>
      </Panel>

      <div className="space-y-4 lg:sticky lg:top-24">
        {!laboratorio && (
          <div className="flex gap-3 rounded-xl border border-warning/40 bg-warning/10 p-4 text-sm">
            <AlertTriangle className="h-5 w-5 shrink-0 text-warning" aria-hidden />
            <p>Esta computadora no tiene laboratorio elegido. Cierra sesión y elígelo en la página de registro antes de abrir la clase.</p>
          </div>
        )}
        <Panel>
          <dl className="space-y-3 text-sm">
            <div>
              <dt className="text-muted-foreground">Curso</dt>
              <dd className="font-medium">{curso ? `${curso.materia.nombre} · ${curso.grupo}` : "—"}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Práctica</dt>
              <dd className="font-medium">{practicas.find((p) => p.id === practicaId)?.titulo ?? "Sin práctica"}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Laboratorio</dt>
              <dd className="font-medium">{laboratorio?.nombre ?? "—"}</dd>
            </div>
          </dl>
          <Button type="button" size="xl" className="mt-5 w-full" onClick={abrir} disabled={!curso || abriendo || !laboratorio}>
            {abriendo ? <Loader2 className="animate-spin" /> : <PlayCircle />} Abrir la clase
          </Button>
          {curso && <p className="mt-3 text-center text-xs text-muted-foreground">{curso.inscritos} alumnos inscritos podrán registrar su entrada.</p>}
        </Panel>
      </div>
    </div>
  )
}
