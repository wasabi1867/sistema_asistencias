import { useEffect, useRef, useState, type FormEvent } from "react"
import { AnimatePresence, motion } from "framer-motion"
import { AlertCircle, BookOpen, ClipboardList, Clock, Eye, EyeOff, FilePlus2, FileText, KeyRound, Trash2, UserPlus, Users, XCircle } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { api, ErrorDeApi, type Clase, type Equipo } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { hojaDeClase, hojaDeRegistroExcel, hojaDeRegistroPdf } from "../../lib/exportar"
import { hora, turno } from "../../lib/formato"
import type { Colores } from "../../lib/tema"
import { useConsultaPeriodica } from "../../hooks/useConsultaPeriodica"
import { nombreDeEquipo } from "../registro/FormularioDeEntrada"

const PROPIO = "propio"

/**
 * Una clase abierta, como la ve quien la lleva: quiénes han entrado (al día cada pocos
 * segundos), quién falta, el código para el aula, y los botones para registrar a
 * alguien a mano, quitar una entrada equivocada, exportar la hoja y cerrar la clase.
 *
 * `alCerrarse` avisa cuando la clase se cerró: aquí (`propia`) o desde otra pantalla.
 */
export function ClaseEnCurso({ claseId, oscuro, c, alCerrarse }: { claseId: number; oscuro: boolean; c: Colores; alCerrarse: (clase: Clase, propia: boolean) => void }) {
  const { datos: clase, recargar } = useConsultaPeriodica(() => api.clases.una(claseId), 3000, [claseId])
  const [mostrarCodigo, setMostrarCodigo] = useState(false)
  const [registrando, setRegistrando] = useState(false)
  const cerrandoAqui = useRef(false)
  const avisado = useRef(false)

  // Si la cerró otra persona (el laboratorista, el administrador), se avisa una vez.
  useEffect(() => {
    if (clase && !clase.abierta && !cerrandoAqui.current && !avisado.current) {
      avisado.current = true
      alCerrarse(clase, false)
    }
  }, [clase, alCerrarse])

  if (!clase) {
    return <p className={c.texto}>Cargando la clase...</p>
  }

  const personas = clase.personas ?? []
  const faltan = clase.lista?.alumnos.filter((a) => a.inscrito && !a.presente) ?? []

  const cerrar = async () => {
    if (!(await alertas.confirmar("¿Finalizar la clase?", "Se registrará la salida de todos los que siguen dentro.", { aceptar: "Finalizar" }))) return
    cerrandoAqui.current = true
    try {
      const cerrada = await api.clases.cerrar(clase.id)
      alCerrarse(cerrada, true)
    } catch (error) {
      cerrandoAqui.current = false
      await alertas.error("No se pudo finalizar la clase", error)
    }
  }

  const quitar = async (id: number, nombre: string) => {
    if (!(await alertas.confirmar("¿Quitar esta entrada?", `${nombre}. Úsalo solo si se registró por error; queda anotado en la bitácora.`, { aceptar: "Quitar", peligro: true }))) return
    try {
      await api.asistencias.quitar(id)
      await recargar()
    } catch (error) {
      await alertas.error("No se pudo quitar la entrada", error)
    }
  }

  const tarjeta = `${c.tarjeta} border-none shadow-lg`
  const linea = `pb-2 ${oscuro ? "border-b border-gray-700" : "border-b"}`

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <Card className={`lg:col-span-2 ${tarjeta}`}>
          <CardHeader className={linea}>
            <div className="flex justify-between items-center gap-2">
              <CardTitle className={`flex items-center ${c.titulo}`}>
                <Users className="w-5 h-5 mr-2" /> Clase en progreso
              </CardTitle>
              <div className={`flex items-center px-3 py-1 rounded-full ${oscuro ? "bg-green-900/50 text-green-400" : "bg-green-100 text-green-700"}`}>
                <div className="w-2 h-2 rounded-full mr-2 bg-green-500 animate-pulse" />
                <span className="text-sm font-medium">Desde las {hora(clase.abiertaEn)}</span>
              </div>
            </div>
            <CardDescription className={c.descripcion}>{clase.laboratorio.nombre}</CardDescription>
          </CardHeader>
          <CardContent className="pt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className={`p-4 rounded-xl ${c.panel} space-y-2 text-sm`}>
              <p className="flex items-center">
                <BookOpen className="w-4 h-4 mr-2 text-[#1BB827]" />
                <span className="font-medium mr-1">Materia:</span> {clase.materia} {clase.grupo ? `(${clase.grupo})` : ""}
              </p>
              <p className="flex items-center">
                <ClipboardList className="w-4 h-4 mr-2 text-[#1BB827]" />
                <span className="font-medium mr-1">Práctica:</span> {clase.practica?.titulo ?? "Sin práctica"}
              </p>
              {clase.docente && (
                <p className="flex items-center">
                  <Clock className="w-4 h-4 mr-2 text-[#1BB827]" />
                  <span className="font-medium mr-1">Docente:</span> {clase.docente}
                </p>
              )}
              {clase.codigo && (
                <div className="flex items-center gap-2 pt-2">
                  <KeyRound className="w-4 h-4 text-[#1BB827]" />
                  <span className="font-medium">Código:</span>
                  <span className="font-mono text-2xl tracking-[0.3em]">{mostrarCodigo ? clase.codigo : "••••"}</span>
                  <button type="button" onClick={() => setMostrarCodigo(!mostrarCodigo)} title={mostrarCodigo ? "Ocultar" : "Mostrar para el aula"}>
                    {mostrarCodigo ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                  </button>
                </div>
              )}
            </div>
            <div className={`p-4 rounded-xl ${c.panel} text-center`}>
              <div className="text-4xl font-bold text-[#1BB827]">{personas.length}</div>
              <p className="text-sm mt-1">{personas.length === 1 ? "persona registrada" : "personas registradas"}</p>
              {clase.lista && (
                <p className="text-sm mt-3">
                  {clase.lista.presentes} de {clase.lista.inscritos} inscritos ·{" "}
                  <span className={clase.lista.faltas > 0 ? "font-semibold" : ""}>{clase.lista.faltas} faltan</span>
                </p>
              )}
            </div>
          </CardContent>
        </Card>

        <Card className={tarjeta}>
          <CardHeader className={linea}>
            <CardTitle className={`flex items-center ${c.titulo}`}>
              <FileText className="w-5 h-5 mr-2" /> Acciones
            </CardTitle>
          </CardHeader>
          <CardContent className="pt-4 space-y-3">
            <Button type="button" onClick={() => setRegistrando(!registrando)} variant="outline" className="w-full rounded-xl">
              <UserPlus className="w-4 h-4 mr-2" /> Registrar a alguien a mano
            </Button>
            <div className="grid grid-cols-2 gap-2">
              <Button type="button" onClick={() => hojaDeRegistroPdf(hojaDeClase(clase))} disabled={personas.length === 0} className={`rounded-xl ${c.botonSecundario}`}>
                <FileText className="w-4 h-4 mr-1" /> PDF
              </Button>
              <Button type="button" onClick={() => hojaDeRegistroExcel(hojaDeClase(clase))} disabled={personas.length === 0} className={`rounded-xl ${c.botonPrimario}`}>
                <FilePlus2 className="w-4 h-4 mr-1" /> Excel
              </Button>
            </div>
            <Button type="button" onClick={cerrar} className="w-full py-5 rounded-xl bg-red-500 hover:bg-red-600 text-white">
              <XCircle className="w-5 h-5 mr-2" /> Finalizar clase
            </Button>
          </CardContent>
        </Card>
      </div>

      {registrando && <RegistroAMano clase={clase} c={c} alRegistrar={recargar} />}

      <Card className={tarjeta}>
        <CardHeader className={linea}>
          <div className="flex justify-between items-center">
            <CardTitle className={`flex items-center ${c.titulo}`}>
              <Users className="w-5 h-5 mr-2" /> Quiénes han entrado
            </CardTitle>
            <div className={`px-3 py-1 rounded-full ${oscuro ? "bg-[#1BB827]/20" : "bg-[#e6ffe9]"} text-[#1BB827]`}>Total: {personas.length}</div>
          </div>
        </CardHeader>
        <CardContent className="p-0">
          <div className="max-h-[60vh] min-h-[200px] overflow-auto">
            <Table className="w-full">
              <TableHeader className="sticky top-0 z-10">
                <TableRow className={oscuro ? "bg-[#153731]" : "bg-[#e6ffe9]"}>
                  <TableHead>#</TableHead>
                  <TableHead>Matrícula</TableHead>
                  <TableHead>Nombre</TableHead>
                  <TableHead>Carrera</TableHead>
                  <TableHead>Semestre</TableHead>
                  <TableHead>Grupo</TableHead>
                  <TableHead>Turno</TableHead>
                  <TableHead>Equipo</TableHead>
                  <TableHead>Entrada</TableHead>
                  <TableHead />
                </TableRow>
              </TableHeader>
              <TableBody>
                <AnimatePresence>
                  {personas.map((persona, i) => (
                    <motion.tr
                      key={persona.id}
                      initial={{ opacity: 0, y: 10 }}
                      animate={{ opacity: 1, y: 0 }}
                      exit={{ opacity: 0, y: -10 }}
                      className={oscuro ? "hover:bg-[#153731] border-b border-gray-700/50" : "hover:bg-[#e6ffe9]/50 border-b"}
                    >
                      <TableCell className="font-medium">{i + 1}</TableCell>
                      <TableCell>{persona.matricula ?? (persona.tipo === "invitado" ? "Invitado" : "")}</TableCell>
                      <TableCell>
                        {persona.nombre} {persona.apellido}
                      </TableCell>
                      <TableCell>{persona.carrera?.nombre ?? ""}</TableCell>
                      <TableCell>{persona.semestre ?? ""}</TableCell>
                      <TableCell>{persona.grupo ?? ""}</TableCell>
                      <TableCell>{turno(persona.turno)}</TableCell>
                      <TableCell>
                        <span className={`px-2 py-1 rounded-full text-xs ${persona.equipo ? "bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-400" : "bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-400"}`}>
                          {persona.equipo ? nombreDeEquipo(persona.equipo) : "Propio"}
                        </span>
                      </TableCell>
                      <TableCell>{hora(persona.horaEntrada)}</TableCell>
                      <TableCell>
                        <Button type="button" onClick={() => quitar(persona.id, `${persona.nombre} ${persona.apellido ?? ""}`)} variant="destructive" size="sm" title="Quitar entrada">
                          <Trash2 className="w-4 h-4" />
                        </Button>
                      </TableCell>
                    </motion.tr>
                  ))}
                </AnimatePresence>
                {personas.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={10} className="h-32 text-center">
                      <AlertCircle className="w-8 h-8 mb-2 mx-auto text-gray-400" />
                      <p className="text-gray-500">Todavía no ha entrado nadie</p>
                      <p className="text-sm text-gray-400">Los alumnos registran su entrada desde la página principal del laboratorio</p>
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>

      {faltan.length > 0 && (
        <Card className={tarjeta}>
          <CardHeader className={linea}>
            <CardTitle className={`flex items-center ${c.titulo}`}>
              <AlertCircle className="w-5 h-5 mr-2" /> Faltan ({faltan.length})
            </CardTitle>
          </CardHeader>
          <CardContent className="pt-4">
            <ul className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 text-sm">
              {faltan.map((alumno) => (
                <li key={alumno.alumnoId} className={`p-2 rounded-lg ${c.panel}`}>
                  {alumno.apellido} {alumno.nombre} <span className="opacity-60">({alumno.matricula})</span>
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
      )}
    </div>
  )
}

/** Registrar a mano: un alumno inscrito o, en una clase de invitado, alguien de fuera. */
function RegistroAMano({ clase, c, alRegistrar }: { clase: Clase; c: Colores; alRegistrar: () => void }) {
  const [equipos, setEquipos] = useState<Equipo[]>([])
  const [matricula, setMatricula] = useState("")
  const [nombre, setNombre] = useState("")
  const [apellido, setApellido] = useState("")
  const [equipo, setEquipo] = useState(PROPIO)
  const deInvitado = clase.tipo === "invitado"

  useEffect(() => {
    api.equipos.deLaboratorio(clase.laboratorio.clave).then(setEquipos).catch(() => setEquipos([]))
  }, [clase.laboratorio.clave])

  const registrar = async (e: FormEvent) => {
    e.preventDefault()
    try {
      await api.clases.registrarAMano(clase.id, {
        ...(matricula ? { matricula } : { nombre, apellido }),
        ...(equipo === PROPIO ? { equipoPropio: true } : { equipo }),
      })
      setMatricula("")
      setNombre("")
      setApellido("")
      setEquipo(PROPIO)
      await alertas.breve("Entrada registrada")
      alRegistrar()
    } catch (error) {
      await alertas.error(error instanceof ErrorDeApi && error.codigo === "no_inscrito" ? "El alumno no está inscrito" : "No se pudo registrar la entrada", error)
    }
  }

  return (
    <Card className={`${c.tarjeta} border-none shadow-lg`}>
      <CardContent className="pt-6">
        <form onSubmit={registrar} className="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
          <div className="space-y-1">
            <label className="text-sm font-medium" htmlFor="matricula-a-mano">
              Número de control
            </label>
            <Input id="matricula-a-mano" value={matricula} onChange={(e) => setMatricula(e.target.value.toUpperCase().trim())} placeholder={deInvitado ? "Vacío si es de fuera" : "Ej. 23303080"} required={!deInvitado} />
          </div>
          {deInvitado && !matricula && (
            <>
              <Input value={nombre} onChange={(e) => setNombre(e.target.value)} placeholder="Nombre" aria-label="Nombre" required />
              <Input value={apellido} onChange={(e) => setApellido(e.target.value)} placeholder="Apellido" aria-label="Apellido" required />
            </>
          )}
          <Select value={equipo} onValueChange={setEquipo}>
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent className={c.menu}>
              <SelectItem value={PROPIO}>Su propio equipo</SelectItem>
              {equipos
                .filter((e) => e.estado === "libre")
                .map((e) => (
                  <SelectItem key={e.id} value={e.etiqueta}>
                    {nombreDeEquipo(e.etiqueta)}
                  </SelectItem>
                ))}
            </SelectContent>
          </Select>
          <Button type="submit" className={`rounded-xl ${c.botonSecundario}`}>
            Registrar
          </Button>
        </form>
      </CardContent>
    </Card>
  )
}
