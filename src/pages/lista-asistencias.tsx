"use client"

import { useCallback, useEffect, useMemo, useState } from "react"
import { useRouter } from "next/navigation"
import { BookOpen, Calendar, CheckCircle2, ClipboardList, Clock, FileText, LogOut, User } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { api, type Clase, type Curso, type Laboratorio, type Practica } from "../lib/api"
import { alertas } from "../lib/alertas"
import { duracion, fecha } from "../lib/formato"
import { useSesion } from "../hooks/useSesion"
import { useTema } from "../hooks/useTema"
import { Cargando } from "../components/comunes/Cargando"
import { BotonDeTema } from "../components/comunes/BotonDeTema"
import { CambiarContrasena } from "../components/comunes/CambiarContrasena"
import { ClaseEnCurso } from "../components/clase/ClaseEnCurso"
import { PracticasDelDocente } from "../components/docente/PracticasDelDocente"
import { ClasesAnteriores } from "../components/docente/ClasesAnteriores"
import { ResumenDelCurso } from "../components/docente/ResumenDelCurso"

/**
 * El panel del docente: elige uno de sus cursos y una práctica, abre la clase en el
 * laboratorio donde inició sesión, ve quién entra y la cierra. Si cierra la pestaña
 * con la clase abierta, la clase se cierra sola.
 */
export default function PanelDelDocente() {
  const router = useRouter()
  const { usuario } = useSesion(["docente"])
  const { oscuro, c, alternar } = useTema()
  const [laboratorio, setLaboratorio] = useState<Laboratorio | null>(null)
  const [cursos, setCursos] = useState<Curso[] | null>(null)
  const [cursoId, setCursoId] = useState<number | null>(null)
  const [practicas, setPracticas] = useState<Practica[]>([])
  const [practicaId, setPracticaId] = useState<number | null>(null)
  const [claseAbierta, setClaseAbierta] = useState<number | null>(null)
  const [abriendo, setAbriendo] = useState(false)

  // Lo inicial: el laboratorio de esta computadora, los cursos del docente y, si dejó
  // una clase abierta (por ejemplo, se cerró el navegador), la retoma.
  useEffect(() => {
    if (!usuario) return
    const clave = localStorage.getItem("laboratorio") ?? ""
    Promise.all([api.laboratorios.listar(), api.cursos.listar({ docente: "yo" }), api.clases.listar({ estado: "abiertas", docente: "yo", tipo: "regular" })])
      .then(([laboratorios, suyos, abiertas]) => {
        setLaboratorio(laboratorios.find((l) => l.clave === clave) ?? null)
        setCursos(suyos)
        const abierta = abiertas.clases[0]
        if (abierta) {
          setClaseAbierta(abierta.id)
          setCursoId(abierta.cursoId)
        } else if (suyos.length === 1) {
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
    setPracticaId(null)
    cargarPracticas()
  }, [cargarPracticas])

  // Si cierra la pestaña con la clase abierta, se cierra sola y se registra la salida de todos.
  useEffect(() => {
    if (!claseAbierta) return
    const alSalir = () => api.clases.cerrarAlSalir(claseAbierta)
    window.addEventListener("beforeunload", alSalir)
    return () => window.removeEventListener("beforeunload", alSalir)
  }, [claseAbierta])

  const practica = practicas.find((p) => p.id === practicaId) ?? null

  const abrir = async () => {
    if (!curso) return
    setAbriendo(true)
    try {
      const clase = await api.clases.abrir({ cursoId: curso.id, laboratorio: laboratorio?.clave, practicaId })
      setClaseAbierta(clase.id)
      await alertas.exito("Clase iniciada", `Los alumnos ya pueden registrar su entrada${clase.practica ? ` para la práctica "${clase.practica.titulo}"` : ""}.`)
    } catch (error) {
      await alertas.error("No se pudo iniciar la clase", error)
    } finally {
      setAbriendo(false)
    }
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

  const tarjeta = `${c.tarjeta} border-none shadow-lg`
  const entrada = `${c.entrada} rounded-xl border-2 text-base`

  return (
    <div className={`flex min-h-screen w-full flex-col lg:flex-row ${oscuro ? "bg-[#0c1f1a]" : "bg-[#f0fff4]"} transition-colors duration-300`}>
      <aside className={`lg:w-96 shrink-0 p-4 lg:min-h-screen ${c.tarjeta} shadow-lg order-last lg:order-first`}>
        <h2 className={`text-lg font-bold mb-3 ${c.titulo}`}>{curso ? `${curso.materia.nombre} ${curso.grupo}` : "Mis cursos"}</h2>
        <Tabs defaultValue="practicas">
          <TabsList className="grid grid-cols-3 w-full mb-3">
            <TabsTrigger value="practicas">Prácticas</TabsTrigger>
            <TabsTrigger value="clases">Clases</TabsTrigger>
            <TabsTrigger value="asistencia">Asistencia</TabsTrigger>
          </TabsList>
          <TabsContent value="practicas">
            <PracticasDelDocente materiaId={curso?.materia.id ?? null} docenteId={usuario.id} alCambiar={cargarPracticas} />
          </TabsContent>
          <TabsContent value="clases">
            <ClasesAnteriores cursoId={cursoId} />
          </TabsContent>
          <TabsContent value="asistencia">
            <ResumenDelCurso cursoId={cursoId} nombre={curso ? `${curso.materia.nombre} ${curso.grupo}` : ""} />
          </TabsContent>
        </Tabs>
      </aside>

      <main className="flex-1 flex flex-col p-4 sm:p-6 gap-6 overflow-hidden">
        <BotonDeTema oscuro={oscuro} alternar={alternar} />
        <Card className={`${c.encabezado} border-none shadow-lg`}>
          <CardHeader className="flex flex-row items-center justify-between pb-4">
            <div className="flex items-center space-x-4">
              <div className="p-2 rounded-full bg-white/20">
                <User className="h-8 w-8 text-white" />
              </div>
              <div>
                <CardTitle className="text-2xl font-bold text-white">¡Bienvenido, {usuario.nombre} {usuario.apellido}!</CardTitle>
                <CardDescription className="text-white/80">{laboratorio ? laboratorio.nombre : "Elige el laboratorio en la página principal antes de iniciar sesión"}</CardDescription>
              </div>
            </div>
            <div className="flex items-center gap-2 mr-14">
              <div className="hidden sm:block bg-white/20 text-white text-sm px-3 py-1 rounded-full">
                <Clock className="inline-block w-4 h-4 mr-1" />
                {fecha(new Date().toISOString())}
              </div>
              <CambiarContrasena className="bg-white/20 hover:bg-white/30 text-white hover:text-white border-none rounded-full" />
              <Button type="button" onClick={cerrarSesion} disabled={claseAbierta !== null} className="bg-white/20 hover:bg-white/30 text-white rounded-full" title={claseAbierta ? "Finaliza la clase antes de salir" : "Cerrar sesión"}>
                <LogOut className="w-4 h-4" />
              </Button>
            </div>
          </CardHeader>
        </Card>

        {claseAbierta ? (
          <ClaseEnCurso claseId={claseAbierta} oscuro={oscuro} c={c} alCerrarse={alCerrarse} />
        ) : (
          <Card className={`max-w-2xl ${tarjeta}`}>
            <CardHeader className={`pb-2 ${oscuro ? "border-b border-gray-700" : "border-b"}`}>
              <CardTitle className={`flex items-center ${c.titulo}`}>
                <BookOpen className="w-5 h-5 mr-2" /> Iniciar una clase
              </CardTitle>
              <CardDescription className={c.descripcion}>Elige el curso y la práctica</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4 pt-4">
              {cursos.length === 0 ? (
                <p className={c.texto}>No tienes cursos en el periodo actual. Pide al administrador que te asigne los tuyos.</p>
              ) : (
                <>
                  <div className="space-y-2">
                    <Label htmlFor="curso" className={c.texto}>
                      <BookOpen className="inline-block w-4 h-4 mr-2" /> Curso
                    </Label>
                    <Select value={cursoId ? String(cursoId) : ""} onValueChange={(valor) => setCursoId(Number(valor))}>
                      <SelectTrigger id="curso" className={entrada}>
                        <SelectValue placeholder="Elige un curso" />
                      </SelectTrigger>
                      <SelectContent className={c.menu}>
                        {cursos.map((cu) => (
                          <SelectItem key={cu.id} value={String(cu.id)}>
                            {cu.materia.nombre} — {cu.grupo}
                            {cu.laboratorio && cu.laboratorio.clave !== laboratorio?.clave ? ` (normalmente en ${cu.laboratorio.nombre})` : ""}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="practica" className={c.texto}>
                      <ClipboardList className="inline-block w-4 h-4 mr-2" /> Práctica
                    </Label>
                    <Select value={practicaId ? String(practicaId) : ""} onValueChange={(valor) => setPracticaId(Number(valor))} disabled={!curso}>
                      <SelectTrigger id="practica" className={entrada}>
                        <SelectValue placeholder={practicas.length ? "Elige una práctica" : "El curso no tiene prácticas (puedes iniciar sin una)"} />
                      </SelectTrigger>
                      <SelectContent className={c.menu}>
                        {practicas.map((p) => (
                          <SelectItem key={p.id} value={String(p.id)}>
                            {p.numero}. {p.titulo}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  {practica && (
                    <div className={`p-4 rounded-xl ${c.panel} space-y-2 text-sm`}>
                      {practica.fecha && (
                        <p className="flex items-start">
                          <Calendar className="w-4 h-4 mt-0.5 mr-2 text-[#1BB827]" />
                          <span className="font-medium mr-1">Fecha:</span> {fecha(practica.fecha)}
                        </p>
                      )}
                      {practica.duracionMinutos && (
                        <p className="flex items-start">
                          <Clock className="w-4 h-4 mt-0.5 mr-2 text-[#1BB827]" />
                          <span className="font-medium mr-1">Duración:</span> {duracion(practica.duracionMinutos)}
                        </p>
                      )}
                      {practica.descripcion && (
                        <p className="flex items-start">
                          <FileText className="w-4 h-4 mt-0.5 mr-2 text-[#1BB827]" />
                          <span>{practica.descripcion}</span>
                        </p>
                      )}
                    </div>
                  )}
                  <Button type="button" onClick={abrir} disabled={!curso || abriendo || !laboratorio} className={`w-full py-5 rounded-xl text-base font-medium ${c.botonPrimario}`}>
                    <CheckCircle2 className="w-5 h-5 mr-2" /> Iniciar clase {laboratorio ? `en ${laboratorio.nombre}` : ""}
                  </Button>
                  {curso && <p className={`text-xs ${c.textoSuave}`}>Inscritos en el curso: {curso.inscritos}</p>}
                </>
              )}
            </CardContent>
          </Card>
        )}
      </main>
    </div>
  )
}
