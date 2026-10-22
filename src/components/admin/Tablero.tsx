import { useState } from "react"
import { useRouter } from "next/navigation"
import { AlertTriangle, BookOpen, Check, CheckCheck, ClipboardCheck, GraduationCap, Inbox, Mail, MailOpen, Monitor, Presentation, UserCheck, UserPlus, Users, X } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { cn } from "@/lib/utils"
import { api, type Aviso, type Clase, type Solicitud, type TipoDeAviso } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { fechaHora, hora, nombreDeEquipo } from "../../lib/formato"
import { PANEL_DE } from "../../lib/rutas"
import { useConsultaPeriodica } from "../../hooks/useConsultaPeriodica"
import { Cifra, Panel, PuntoEnVivo, SinDatos } from "../comunes/Bloques"
import type { PropsDeSeccion } from "./seccion"

const ESTADO_DE_SOLICITUD = { pendiente: "Pendiente", aprobada_temporal: "Aprobada por esa vez", aprobada_permanente: "Aprobada e inscrito", rechazada: "Rechazada" }
const ESTADO_DE_REPORTE = { reportado: "Reportada", en_proceso: "En proceso", resuelto: "Resuelta" }
const ICONO_DE_AVISO: Record<TipoDeAviso, typeof Mail> = { solicitud: UserPlus, equipo: AlertTriangle, clase: UserCheck, sistema: Monitor }

/** Lo que el administrador revisa a diario: números, solicitudes, clases abiertas y avisos. */
export function Tablero({ irA }: PropsDeSeccion) {
  const router = useRouter()
  const { datos: estadisticas } = useConsultaPeriodica(() => api.estadisticas(), 30000, [])
  const { datos: abiertas, recargar: recargarClases } = useConsultaPeriodica(() => api.clases.listar({ estado: "abiertas" }), 10000, [])
  const { datos: solicitudes, recargar: recargarSolicitudes } = useConsultaPeriodica(() => api.solicitudes.listar({ estado: "pendiente" }), 10000, [])
  const [soloNoLeidos, setSoloNoLeidos] = useState(true)
  const { datos: avisos, recargar: recargarAvisos } = useConsultaPeriodica(() => api.avisos.listar(soloNoLeidos ? { leido: false } : {}), 15000, [soloNoLeidos])

  const cerrar = async (clase: Clase) => {
    if (!(await alertas.confirmar(`¿Finalizar la clase de ${clase.materia}?`, `${clase.laboratorio.nombre}, ${clase.docente ?? "sin docente"}. Se registrará la salida de todos.`, { aceptar: "Finalizar", peligro: true }))) return
    try {
      await api.clases.cerrar(clase.id)
      alertas.breve("Clase finalizada")
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
      alertas.breve(mensajes[estado])
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

  const pendientes = estadisticas?.solicitudesPendientes ?? 0
  const fallas = estadisticas?.reportesAbiertos ?? 0

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
        <Cifra etiqueta="Alumnos" valor={estadisticas?.alumnos} icono={GraduationCap} detalle="Activos" alElegir={() => irA("alumnos")} />
        <Cifra
          etiqueta="Docentes"
          valor={estadisticas?.docentes}
          icono={Users}
          detalle={estadisticas ? `y ${estadisticas.laboratoristas} ${estadisticas.laboratoristas === 1 ? "laboratorista" : "laboratoristas"}` : undefined}
          alElegir={() => irA("cuentas")}
        />
        <Cifra etiqueta="Materias" valor={estadisticas?.materias} icono={BookOpen} detalle="En el catálogo" alElegir={() => irA("materias")} />
        <Cifra etiqueta="Cursos" valor={estadisticas?.cursosDelPeriodo} icono={ClipboardCheck} detalle="En el periodo actual" alElegir={() => irA("cursos")} />
        <Cifra etiqueta="Solicitudes" valor={estadisticas?.solicitudesPendientes} icono={UserPlus} tono={pendientes > 0 ? "warning" : "neutral"} detalle={pendientes > 0 ? "Por resolver" : "Al día"} />
        <Cifra
          etiqueta="Fallas abiertas"
          valor={estadisticas?.reportesAbiertos}
          icono={AlertTriangle}
          tono={fallas > 0 ? "destructive" : "neutral"}
          detalle="Ver en Equipos"
          alElegir={() => router.push(`${PANEL_DE.laboratorista}?seccion=equipos`)}
        />
      </div>

      <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <div className="space-y-6">
          <Panel titulo="Solicitudes de ingreso" descripcion="Alumnos que no están inscritos y piden entrar." acciones={solicitudes?.length ? <Badge variant="warning">{solicitudes.length}</Badge> : undefined} sinRelleno>
            {!solicitudes?.length ? (
              <SinDatos icono={Inbox} titulo="No hay solicitudes por resolver" className="py-8" />
            ) : (
              <ul className="divide-y">
                {solicitudes.map((s) => (
                  <li key={s.id} className="px-4 py-4 sm:px-5">
                    <div className="flex gap-3">
                      <Iniciales nombre={s.alumno.nombre} apellido={s.alumno.apellido} />
                      <div className="min-w-0 flex-1">
                        <p className="font-medium">
                          {s.alumno.nombre} {s.alumno.apellido} <span className="font-normal text-muted-foreground tabular-nums">({s.alumno.matricula})</span>
                        </p>
                        <p className="text-sm">
                          {s.clase ? `Quiere entrar a ${s.clase.materia}${s.clase.grupo ? ` ${s.clase.grupo}` : ""} (${s.clase.docente ?? "sin docente"})` : `Quiere usar ${s.laboratorio.nombre} fuera de clase`}
                        </p>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                          {[s.alumno.carrera?.nombre ?? "Sin carrera", s.alumno.semestre ? `${s.alumno.semestre}° ${s.alumno.grupo ?? ""}`.trim() : null, s.equipo ? nombreDeEquipo(s.equipo) : "Equipo propio", hora(s.creadaEn)]
                            .filter(Boolean)
                            .join(" · ")}
                        </p>
                        {s.motivo && <p className="mt-2 border-l-2 pl-3 text-sm italic text-muted-foreground">“{s.motivo}”</p>}
                        {s.clase && !s.clase.abierta && <p className="mt-2 text-xs font-medium text-warning">La clase ya terminó: solo se puede inscribir o rechazar.</p>}
                        <div className="mt-3 flex flex-wrap gap-2">
                          {(!s.clase || s.clase.abierta) && (
                            <Button type="button" size="sm" variant="success" onClick={() => resolver(s, "aprobada_temporal")}>
                              <Check /> Dejar entrar esta vez
                            </Button>
                          )}
                          {s.clase && (
                            <Button type="button" size="sm" variant="outline" onClick={() => resolver(s, "aprobada_permanente")}>
                              <UserPlus /> Inscribirlo al curso
                            </Button>
                          )}
                          <Button type="button" size="sm" variant="ghost" className="text-destructive hover:bg-destructive/10 hover:text-destructive" onClick={() => resolver(s, "rechazada")}>
                            <X /> Rechazar
                          </Button>
                        </div>
                      </div>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </Panel>

          <Panel
            titulo="Avisos"
            acciones={
              <>
                <Tabs value={soloNoLeidos ? "no_leidos" : "todos"} onValueChange={(v) => setSoloNoLeidos(v === "no_leidos")}>
                  <TabsList>
                    <TabsTrigger value="no_leidos">No leídos</TabsTrigger>
                    <TabsTrigger value="todos">Todos</TabsTrigger>
                  </TabsList>
                </Tabs>
                <Button type="button" size="sm" variant="ghost" onClick={marcarTodos}>
                  <CheckCheck /> Marcar todos como leídos
                </Button>
              </>
            }
            sinRelleno
          >
            {avisos?.length === 0 ? (
              <SinDatos icono={Mail} titulo={soloNoLeidos ? "No hay avisos sin leer" : "No hay avisos"} className="py-8" />
            ) : (
              <ul className="divide-y">
                {(avisos ?? []).map((aviso) => {
                  const Icono = ICONO_DE_AVISO[aviso.tipo]
                  const estado = estadoActual(aviso)
                  return (
                    <li key={aviso.id} className={cn("flex items-start gap-3 px-4 py-3 sm:px-5", aviso.leido && "opacity-70")}>
                      <span className={cn("mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full", aviso.prioridad === "alta" ? "bg-destructive/15 text-destructive" : "bg-accent text-accent-foreground")}>
                        <Icono className="h-4 w-4" aria-hidden />
                      </span>
                      <div className="min-w-0 flex-1">
                        <p className={cn("text-sm", aviso.leido ? "font-medium" : "font-semibold")}>{aviso.titulo}</p>
                        {aviso.mensaje && <p className="text-sm text-muted-foreground">{aviso.mensaje}</p>}
                        <p className="mt-0.5 text-xs text-muted-foreground">
                          {fechaHora(aviso.creadoEn)}
                          {aviso.laboratorio ? ` · ${aviso.laboratorio.nombre}` : ""}
                          {estado ? ` · Ahora: ${estado}` : ""}
                        </p>
                      </div>
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        onClick={() => marcar(aviso)}
                        title={aviso.leido ? "Marcar como no leído" : "Marcar como leído"}
                        aria-label={aviso.leido ? "Marcar como no leído" : "Marcar como leído"}
                      >
                        {aviso.leido ? <Mail /> : <MailOpen />}
                      </Button>
                    </li>
                  )
                })}
              </ul>
            )}
          </Panel>
        </div>

        <Panel titulo="Clases abiertas" acciones={abiertas?.clases.length ? <Badge variant="success">{abiertas.clases.length}</Badge> : undefined} sinRelleno>
          {abiertas?.clases.length === 0 ? (
            <SinDatos icono={Presentation} titulo="No hay clases abiertas" className="py-8" />
          ) : (
            <ul className="divide-y">
              {(abiertas?.clases ?? []).map((clase) => (
                <li key={clase.id} className="flex items-start gap-3 px-4 py-3 sm:px-5">
                  <PuntoEnVivo className="mt-2" />
                  <div className="min-w-0 flex-1">
                    <p className="font-medium">
                      {clase.materia} {clase.grupo ? <span className="text-muted-foreground">· {clase.grupo}</span> : null}
                      {clase.tipo === "invitado" && (
                        <Badge variant="info" className="ml-2 align-middle">
                          Invitado
                        </Badge>
                      )}
                    </p>
                    <p className="text-sm text-muted-foreground">
                      {clase.laboratorio.nombre} · {clase.docente ?? "Sin docente"}
                    </p>
                    <p className="text-xs text-muted-foreground tabular-nums">
                      Desde las {hora(clase.abiertaEn)} · {clase.asistentes} dentro
                    </p>
                  </div>
                  <Button type="button" size="sm" variant="outline-destructive" onClick={() => cerrar(clase)}>
                    Finalizar
                  </Button>
                </li>
              ))}
            </ul>
          )}
        </Panel>
      </div>
    </div>
  )
}

function Iniciales({ nombre, apellido }: { nombre: string; apellido: string }) {
  return (
    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold text-muted-foreground" aria-hidden>
      {nombre.charAt(0)}
      {apellido.charAt(0)}
    </span>
  )
}
