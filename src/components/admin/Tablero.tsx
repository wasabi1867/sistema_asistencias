import { useState } from "react"
import { AlertTriangle, BookOpen, Check, CheckCheck, ClipboardCheck, GraduationCap, Mail, MailOpen, Monitor, UserCheck, UserPlus, Users, X, XCircle } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { api, type Aviso, type Clase, type Solicitud, type TipoDeAviso } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { fechaHora, hora } from "../../lib/formato"
import { useConsultaPeriodica } from "../../hooks/useConsultaPeriodica"
import { nombreDeEquipo } from "../registro/FormularioDeEntrada"
import { encabezadoDeTarjeta } from "../laboratorista/vista"
import type { PropsDeSeccion } from "./seccion"

const ESTADO_DE_SOLICITUD = { pendiente: "Pendiente", aprobada_temporal: "Aprobada por esa vez", aprobada_permanente: "Aprobada e inscrito", rechazada: "Rechazada" }
const ESTADO_DE_REPORTE = { reportado: "Reportada", en_proceso: "En proceso", resuelto: "Resuelta" }
const ICONO_DE_AVISO: Record<TipoDeAviso, typeof Mail> = { solicitud: UserPlus, equipo: AlertTriangle, clase: UserCheck, sistema: Monitor }

/** Lo que el administrador revisa a diario: números, clases abiertas, solicitudes y avisos. */
export function Tablero({ oscuro, c }: PropsDeSeccion) {
  const { datos: estadisticas } = useConsultaPeriodica(() => api.estadisticas(), 30000, [])
  const { datos: abiertas, recargar: recargarClases } = useConsultaPeriodica(() => api.clases.listar({ estado: "abiertas" }), 10000, [])
  const { datos: solicitudes, recargar: recargarSolicitudes } = useConsultaPeriodica(() => api.solicitudes.listar({ estado: "pendiente" }), 10000, [])
  const [soloNoLeidos, setSoloNoLeidos] = useState(true)
  const { datos: avisos, recargar: recargarAvisos } = useConsultaPeriodica(() => api.avisos.listar(soloNoLeidos ? { leido: false } : {}), 15000, [soloNoLeidos])

  const tarjeta = `${c.tarjeta} border-none shadow-lg`

  const cerrar = async (clase: Clase) => {
    if (!(await alertas.confirmar(`¿Finalizar la clase de ${clase.materia}?`, `${clase.laboratorio.nombre}, ${clase.docente ?? "sin docente"}. Se registrará la salida de todos.`, { aceptar: "Finalizar", peligro: true }))) return
    try {
      await api.clases.cerrar(clase.id)
      await recargarClases()
    } catch (error) {
      await alertas.error("No se pudo finalizar la clase", error)
    }
  }

  const resolver = async (solicitud: Solicitud, estado: "aprobada_temporal" | "aprobada_permanente" | "rechazada") => {
    const comentario = estado === "rechazada" ? await alertas.pedirTexto("¿Por qué se rechaza?", { etiqueta: "Comentario (opcional)" }) : undefined
    if (comentario === null) return
    try {
      await api.solicitudes.resolver(solicitud.id, estado, comentario || undefined)
      // Si la clase ya terminó, la API solo lo inscribe: ya no hay clase a la cual entrar.
      const entra = !solicitud.clase || solicitud.clase.abierta
      const mensajes = {
        rechazada: "Solicitud rechazada",
        aprobada_permanente: entra ? "Aprobada: quedó inscrito y ya está dentro" : "Aprobada: quedó inscrito al curso",
        aprobada_temporal: "Aprobada: ya está dentro",
      }
      await alertas.breve(mensajes[estado])
      await Promise.all([recargarSolicitudes(), recargarAvisos()])
    } catch (error) {
      await alertas.error("No se pudo resolver la solicitud", error)
    }
  }

  const marcar = async (aviso: Aviso) => {
    try {
      await api.avisos.marcar(aviso.id, !aviso.leido)
      await recargarAvisos()
    } catch (error) {
      await alertas.error("No se pudo marcar el aviso", error)
    }
  }

  const marcarTodos = async () => {
    try {
      await api.avisos.marcarTodos()
      await recargarAvisos()
    } catch (error) {
      await alertas.error("No se pudieron marcar los avisos", error)
    }
  }

  const estadoActual = (aviso: Aviso) => {
    if (aviso.solicitud) return ESTADO_DE_SOLICITUD[aviso.solicitud.estado]
    if (aviso.reporte) return `${nombreDeEquipo(aviso.reporte.equipo.etiqueta)}: ${ESTADO_DE_REPORTE[aviso.reporte.estado].toLowerCase()}`
    if (aviso.clase) return aviso.clase.abierta ? "Clase en curso" : "Clase terminada"
    return null
  }

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-7 gap-4">
        {[
          ["Alumnos", estadisticas?.alumnos, GraduationCap],
          ["Docentes", estadisticas?.docentes, Users],
          ["Laboratoristas", estadisticas?.laboratoristas, UserCheck],
          ["Materias", estadisticas?.materias, BookOpen],
          ["Cursos del periodo", estadisticas?.cursosDelPeriodo, ClipboardCheck],
          ["Solicitudes", estadisticas?.solicitudesPendientes, UserPlus],
          ["Fallas abiertas", estadisticas?.reportesAbiertos, AlertTriangle],
        ].map(([titulo, valor, Icono]) => {
          const I = Icono as typeof Mail
          return (
            <Card key={titulo as string} className={tarjeta}>
              <CardContent className="pt-6 text-center">
                <I className="w-5 h-5 mx-auto mb-1 text-[#1BB827]" />
                <div className="text-2xl font-bold">{(valor as number | undefined) ?? "…"}</div>
                <p className={`text-xs ${c.textoSuave}`}>{titulo as string}</p>
              </CardContent>
            </Card>
          )
        })}
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <Card className={tarjeta}>
          <CardHeader className={encabezadoDeTarjeta(oscuro)}>
            <CardTitle className="text-lg">Clases abiertas</CardTitle>
          </CardHeader>
          <CardContent className="pt-4 space-y-2">
            {abiertas?.clases.length === 0 && <p className={c.textoSuave}>No hay clases abiertas.</p>}
            {(abiertas?.clases ?? []).map((clase) => (
              <div key={clase.id} className={`flex justify-between items-center gap-2 p-3 rounded-xl ${c.panel}`}>
                <div>
                  <p className="font-medium">
                    {clase.materia} {clase.grupo ? `(${clase.grupo})` : ""} {clase.tipo === "invitado" ? "· invitado" : ""}
                  </p>
                  <p className="text-sm opacity-80">
                    {clase.laboratorio.nombre} · {clase.docente ?? "Sin docente"} · desde las {hora(clase.abiertaEn)} · {clase.asistentes} dentro
                  </p>
                </div>
                <Button type="button" size="sm" variant="destructive" onClick={() => cerrar(clase)} title="Finalizar">
                  <XCircle className="w-4 h-4" />
                </Button>
              </div>
            ))}
          </CardContent>
        </Card>

        <Card className={tarjeta}>
          <CardHeader className={encabezadoDeTarjeta(oscuro)}>
            <CardTitle className="text-lg">Solicitudes de ingreso</CardTitle>
          </CardHeader>
          <CardContent className="pt-4 space-y-2">
            {solicitudes?.length === 0 && <p className={c.textoSuave}>No hay solicitudes por resolver.</p>}
            {(solicitudes ?? []).map((s) => (
              <div key={s.id} className={`p-3 rounded-xl ${c.panel} space-y-2`}>
                <div>
                  <p className="font-medium">
                    {s.alumno.nombre} {s.alumno.apellido} <span className="opacity-60">({s.alumno.matricula})</span>
                  </p>
                  <p className="text-sm opacity-80">
                    {s.clase ? `Quiere entrar a ${s.clase.materia}${s.clase.grupo ? ` ${s.clase.grupo}` : ""} (${s.clase.docente ?? "sin docente"})` : `Quiere usar ${s.laboratorio.nombre} fuera de clase`}
                    {" · "}
                    {s.alumno.carrera?.nombre ?? "Sin carrera"} {s.alumno.semestre ? `${s.alumno.semestre}°` : ""} {s.alumno.grupo ?? ""} · {s.equipo ? nombreDeEquipo(s.equipo) : "equipo propio"} · {hora(s.creadaEn)}
                  </p>
                  {s.motivo && <p className="text-sm italic">“{s.motivo}”</p>}
                  {s.clase && !s.clase.abierta && <p className="text-xs text-orange-600">La clase ya terminó: solo se puede inscribir o rechazar.</p>}
                </div>
                <div className="flex flex-wrap gap-2">
                  {(!s.clase || s.clase.abierta) && (
                    <Button type="button" size="sm" onClick={() => resolver(s, "aprobada_temporal")} className="bg-[#1d5631] hover:bg-[#153d23] text-white">
                      <Check className="w-4 h-4 mr-1" /> Dejar entrar esta vez
                    </Button>
                  )}
                  {s.clase && (
                    <Button type="button" size="sm" onClick={() => resolver(s, "aprobada_permanente")} className="bg-[#800040] hover:bg-[#5c002e] text-white">
                      <UserPlus className="w-4 h-4 mr-1" /> Inscribirlo al curso
                    </Button>
                  )}
                  <Button type="button" size="sm" variant="destructive" onClick={() => resolver(s, "rechazada")}>
                    <X className="w-4 h-4 mr-1" /> Rechazar
                  </Button>
                </div>
              </div>
            ))}
          </CardContent>
        </Card>
      </div>

      <Card className={tarjeta}>
        <CardHeader className={`${encabezadoDeTarjeta(oscuro)} flex flex-row items-center justify-between`}>
          <CardTitle className="text-lg">Avisos</CardTitle>
          <Button type="button" size="sm" onClick={marcarTodos} className="bg-white/20 hover:bg-white/30 text-white">
            <CheckCheck className="w-4 h-4 mr-1" /> Marcar todos como leídos
          </Button>
        </CardHeader>
        <CardContent className="pt-4 space-y-2">
          <Tabs value={soloNoLeidos ? "no_leidos" : "todos"} onValueChange={(v) => setSoloNoLeidos(v === "no_leidos")}>
            <TabsList>
              <TabsTrigger value="no_leidos">No leídos</TabsTrigger>
              <TabsTrigger value="todos">Todos</TabsTrigger>
            </TabsList>
          </Tabs>
          {avisos?.length === 0 && <p className={c.textoSuave}>No hay avisos.</p>}
          {(avisos ?? []).map((aviso) => {
            const Icono = ICONO_DE_AVISO[aviso.tipo]
            const estado = estadoActual(aviso)
            return (
              <div key={aviso.id} className={`flex items-start gap-3 p-3 rounded-xl ${c.panel} ${aviso.leido ? "opacity-60" : ""}`}>
                <Icono className={`w-5 h-5 mt-0.5 shrink-0 ${aviso.prioridad === "alta" ? "text-red-500" : "text-[#1BB827]"}`} />
                <div className="flex-1 min-w-0">
                  <p className="font-medium">{aviso.titulo}</p>
                  {aviso.mensaje && <p className="text-sm opacity-80">{aviso.mensaje}</p>}
                  <p className="text-xs opacity-70">
                    {fechaHora(aviso.creadoEn)}
                    {aviso.laboratorio ? ` · ${aviso.laboratorio.nombre}` : ""}
                    {estado ? ` · Ahora: ${estado}` : ""}
                  </p>
                </div>
                <button type="button" onClick={() => marcar(aviso)} title={aviso.leido ? "Marcar como no leído" : "Marcar como leído"} className="shrink-0">
                  {aviso.leido ? <Mail className="w-4 h-4" /> : <MailOpen className="w-4 h-4" />}
                </button>
              </div>
            )
          })}
        </CardContent>
      </Card>
    </div>
  )
}
