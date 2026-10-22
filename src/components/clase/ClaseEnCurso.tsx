import { useEffect, useRef, useState, type FormEvent } from "react"
import { CheckCircle2, ChevronDown, Download, Eye, EyeOff, FileSpreadsheet, FileText, Square, Trash2, UserPlus, Users, X } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { api, ErrorDeApi, type Clase, type Equipo } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { hojaDeClase, hojaDeRegistroExcel, hojaDeRegistroPdf } from "../../lib/exportar"
import { hora, nombreDeEquipo, turno } from "../../lib/formato"
import { useConsultaPeriodica } from "../../hooks/useConsultaPeriodica"
import { Campo, Panel, PuntoEnVivo, SinDatos } from "../comunes/Bloques"
import { MenuDeAcciones } from "../comunes/MenuDeAcciones"

const PROPIO = "propio"

/**
 * Una clase abierta, como la ve quien la lleva: quiénes han entrado (al día cada pocos
 * segundos), quién falta, el código para el aula, y los botones para registrar a
 * alguien a mano, quitar una entrada equivocada, exportar la hoja y cerrar la clase.
 *
 * `alCerrarse` avisa cuando la clase se cerró: aquí (`propia`) o desde otra pantalla.
 */
export function ClaseEnCurso({ claseId, alCerrarse }: { claseId: number; alCerrarse: (clase: Clase, propia: boolean) => void }) {
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
    return <div className="h-48 animate-pulse rounded-xl border bg-card" aria-label="Cargando la clase" />
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

  return (
    <div className="space-y-6">
      <section className="overflow-hidden rounded-xl border bg-card shadow-sm" aria-label="Clase en curso">
        <div className="h-1 bg-success" aria-hidden />
        <div className="flex flex-col gap-6 p-5 sm:p-6 lg:flex-row lg:items-center">
          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              <Badge variant="success">
                <PuntoEnVivo /> En curso desde las {hora(clase.abiertaEn)}
              </Badge>
              {clase.tipo === "invitado" && <Badge variant="info">Maestro invitado</Badge>}
              <Badge>{clase.laboratorio.nombre}</Badge>
            </div>
            <h2 className="mt-3 text-2xl font-semibold tracking-tight">
              {clase.materia}
              {clase.grupo && <span className="font-normal text-muted-foreground"> · {clase.grupo}</span>}
            </h2>
            <p className="mt-1 text-sm text-muted-foreground">
              {clase.practica ? `Práctica ${clase.practica.numero ? `${clase.practica.numero}. ` : ""}${clase.practica.titulo}` : "Sin práctica"}
              {clase.docente ? ` · ${clase.docente}` : ""}
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-6 sm:gap-8">
            {clase.codigo && (
              <div>
                <p className="text-xs text-muted-foreground">Código para el aula</p>
                <div className="mt-1 flex items-center gap-1">
                  <span className="font-mono text-2xl font-semibold tracking-[0.3em] tabular-nums">{mostrarCodigo ? clase.codigo : "••••"}</span>
                  <Button type="button" variant="ghost" size="icon-sm" onClick={() => setMostrarCodigo(!mostrarCodigo)} aria-label={mostrarCodigo ? "Ocultar el código" : "Mostrar el código"}>
                    {mostrarCodigo ? <EyeOff /> : <Eye />}
                  </Button>
                </div>
              </div>
            )}
            <Contador dentro={personas.length} inscritos={clase.lista?.inscritos} />
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-2 border-t bg-muted/40 px-5 py-3 sm:px-6">
          <Button type="button" variant="outline" onClick={() => setRegistrando(!registrando)} aria-expanded={registrando}>
            <UserPlus /> Registrar a alguien a mano
          </Button>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button type="button" variant="outline" disabled={personas.length === 0}>
                <Download /> Exportar <ChevronDown className="text-muted-foreground" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
              <DropdownMenuItem onSelect={() => hojaDeRegistroPdf(hojaDeClase(clase))}>
                <FileText /> Hoja de registro en PDF
              </DropdownMenuItem>
              <DropdownMenuItem onSelect={() => hojaDeRegistroExcel(hojaDeClase(clase))}>
                <FileSpreadsheet /> Hoja de registro en Excel
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
          <Button type="button" variant="destructive" className="sm:ml-auto" onClick={cerrar}>
            <Square className="fill-current" /> Finalizar clase
          </Button>
        </div>
      </section>

      {registrando && <RegistroAMano clase={clase} alCerrar={() => setRegistrando(false)} alRegistrar={recargar} />}

      <div className="grid items-start gap-6 xl:grid-cols-3">
        <Panel
          titulo="Quiénes han entrado"
          descripcion="Se actualiza sola cada pocos segundos."
          acciones={<Badge variant="primary">{personas.length}</Badge>}
          sinRelleno
          className={clase.lista ? "xl:col-span-2" : "xl:col-span-3"}
        >
          {personas.length === 0 ? (
            <SinDatos icono={Users} titulo="Todavía no ha entrado nadie">
              Los alumnos registran su entrada en la página principal del laboratorio o desde su teléfono.
            </SinDatos>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-10">#</TableHead>
                  <TableHead>Persona</TableHead>
                  <TableHead>Computadora</TableHead>
                  <TableHead>Entrada</TableHead>
                  <TableHead className="w-12">
                    <span className="sr-only">Acciones</span>
                  </TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {personas.map((persona, i) => {
                  const nombre = `${persona.nombre} ${persona.apellido ?? ""}`.trim()
                  const escolar = [persona.carrera?.clave, persona.semestre || persona.grupo ? `${persona.semestre ?? ""}${persona.semestre ? "°" : ""}${persona.grupo ?? ""}` : null, turno(persona.turno)].filter(Boolean).join(" · ")
                  return (
                    <TableRow key={persona.id} className="animate-in fade-in-0">
                      <TableCell className="text-muted-foreground">{i + 1}</TableCell>
                      <TableCell>
                        <p className="font-medium">{nombre}</p>
                        <p className="text-xs text-muted-foreground">
                          {persona.matricula ?? (persona.tipo === "invitado" ? "De fuera" : "")}
                          {escolar ? ` · ${escolar}` : ""}
                        </p>
                      </TableCell>
                      <TableCell>{persona.equipo ? <Badge variant="success">{nombreDeEquipo(persona.equipo)}</Badge> : <Badge variant="info">Propio</Badge>}</TableCell>
                      <TableCell className="whitespace-nowrap tabular-nums">{hora(persona.horaEntrada)}</TableCell>
                      <TableCell>
                        <MenuDeAcciones etiqueta={`Acciones para ${nombre}`} acciones={[{ texto: "Quitar entrada", icono: Trash2, peligro: true, alElegir: () => quitar(persona.id, nombre) }]} />
                      </TableCell>
                    </TableRow>
                  )
                })}
              </TableBody>
            </Table>
          )}
        </Panel>

        {clase.lista && (
          <Panel titulo="Faltan" acciones={<Badge variant={faltan.length ? "warning" : "success"}>{faltan.length}</Badge>} sinRelleno>
            {faltan.length === 0 ? (
              <SinDatos icono={CheckCircle2} titulo="Ya llegaron todos los inscritos" />
            ) : (
              <ul className="max-h-[28rem] divide-y overflow-y-auto">
                {faltan.map((alumno) => (
                  <li key={alumno.alumnoId} className="flex items-center justify-between gap-3 px-4 py-2.5 text-sm sm:px-5">
                    <span className="min-w-0 truncate">
                      {alumno.apellido} {alumno.nombre}
                    </span>
                    <span className="shrink-0 text-xs text-muted-foreground tabular-nums">{alumno.matricula}</span>
                  </li>
                ))}
              </ul>
            )}
          </Panel>
        )}
      </div>
    </div>
  )
}

/** Cuántos han entrado y, en una clase con inscritos, de cuántos, con una barra. */
function Contador({ dentro, inscritos }: { dentro: number; inscritos?: number }) {
  const porcentaje = inscritos ? Math.min(100, Math.round((dentro / inscritos) * 100)) : 0
  return (
    <div className="min-w-[9rem]">
      <p className="text-xs text-muted-foreground">{inscritos !== undefined ? "Han entrado" : "Personas dentro"}</p>
      <p className="mt-0.5 text-3xl font-semibold tabular-nums tracking-tight">
        {dentro}
        {inscritos !== undefined && <span className="text-lg font-normal text-muted-foreground"> / {inscritos}</span>}
      </p>
      {inscritos !== undefined && inscritos > 0 && (
        <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-muted" role="progressbar" aria-valuenow={porcentaje} aria-valuemin={0} aria-valuemax={100} aria-label="Inscritos que han entrado">
          <div className="h-full rounded-full bg-success transition-all" style={{ width: `${porcentaje}%` }} />
        </div>
      )}
    </div>
  )
}

/** Registrar a mano: un alumno inscrito o, en una clase de invitado, alguien de fuera. */
function RegistroAMano({ clase, alCerrar, alRegistrar }: { clase: Clase; alCerrar: () => void; alRegistrar: () => void }) {
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
      alertas.breve("Entrada registrada")
      alRegistrar()
      api.equipos.deLaboratorio(clase.laboratorio.clave).then(setEquipos).catch(() => undefined)
    } catch (error) {
      await alertas.error(error instanceof ErrorDeApi && error.codigo === "no_inscrito" ? "El alumno no está inscrito" : "No se pudo registrar la entrada", error)
    }
  }

  return (
    <Panel
      titulo="Registrar a alguien a mano"
      descripcion={deInvitado ? "Un alumno con su número de control, o alguien de fuera con su nombre." : "Solo alumnos inscritos en el curso."}
      acciones={
        <Button type="button" variant="ghost" size="icon-sm" onClick={alCerrar} aria-label="Cerrar el registro a mano">
          <X />
        </Button>
      }
    >
      <form onSubmit={registrar} className="grid items-end gap-3 md:grid-cols-[1fr_1fr_auto]">
        <Campo etiqueta="Número de control" htmlFor="matricula-a-mano">
          <Input id="matricula-a-mano" value={matricula} onChange={(e) => setMatricula(e.target.value.toUpperCase().trim())} placeholder={deInvitado ? "Vacío si es de fuera" : "Ej. 23303080"} required={!deInvitado} />
        </Campo>
        {deInvitado && !matricula && (
          <>
            <Campo etiqueta="Nombre" htmlFor="nombre-a-mano">
              <Input id="nombre-a-mano" value={nombre} onChange={(e) => setNombre(e.target.value)} required />
            </Campo>
            <Campo etiqueta="Apellidos" htmlFor="apellido-a-mano" className="md:col-start-1">
              <Input id="apellido-a-mano" value={apellido} onChange={(e) => setApellido(e.target.value)} required />
            </Campo>
          </>
        )}
        <Campo etiqueta="Computadora" htmlFor="equipo-a-mano">
          <Select value={equipo} onValueChange={setEquipo}>
            <SelectTrigger id="equipo-a-mano">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
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
        </Campo>
        <Button type="submit">Registrar</Button>
      </form>
    </Panel>
  )
}
