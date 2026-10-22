import { useState, type FormEvent } from "react"
import { Check, Clock, Inbox, LogOut, Monitor, Trash2, UserPlus, Users, X } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { api, type Asistencia, type Equipo, type Solicitud } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { hora, nombreDeEquipo } from "../../lib/formato"
import { useConsultaPeriodica } from "../../hooks/useConsultaPeriodica"
import { Campo, Cifra, Panel, SinDatos } from "../comunes/Bloques"
import { MenuDeAcciones } from "../comunes/MenuDeAcciones"
import { DetalleDeEquipo, MapaDeEquipos } from "./MapaDeEquipos"
import type { PropsDeVista } from "./vista"

const PROPIO = "propio"

/**
 * Lo que pasa ahora en el laboratorio: quién está dentro y en qué computadora, el uso
 * fuera de clase (que registra el personal mientras el instituto no diga quién lo
 * autoriza) y las solicitudes de alumnos que piden usarlo.
 */
export function VistaEnElLaboratorio({ laboratorio, usuario }: PropsDeVista) {
  const { datos: dentro, recargar } = useConsultaPeriodica(() => api.asistencias.activas(laboratorio.clave), 5000, [laboratorio.clave])
  const { datos: equipos, recargar: recargarEquipos } = useConsultaPeriodica(() => api.equipos.deLaboratorio(laboratorio.clave), 5000, [laboratorio.clave])
  const { datos: solicitudes, recargar: recargarSolicitudes } = useConsultaPeriodica(
    () => api.solicitudes.listar({ laboratorio: laboratorio.clave, estado: "pendiente" }),
    10000,
    [laboratorio.clave],
  )
  const [elegido, setElegido] = useState<Equipo | null>(null)

  const actualizar = () => Promise.all([recargar(), recargarEquipos(), recargarSolicitudes()])

  const salida = async (persona: Asistencia) => {
    try {
      await api.asistencias.registrarSalida(persona.id)
      alertas.breve("Salida registrada")
      await actualizar()
    } catch (error) {
      await alertas.error("No se pudo registrar la salida", error)
    }
  }

  const quitar = async (persona: Asistencia) => {
    if (!(await alertas.confirmar("¿Quitar esta entrada?", `${persona.nombre} ${persona.apellido ?? ""}. Úsalo solo si se registró por error.`, { aceptar: "Quitar", peligro: true }))) return
    try {
      await api.asistencias.quitar(persona.id)
      await actualizar()
    } catch (error) {
      await alertas.error("No se pudo quitar la entrada", error)
    }
  }

  const resolver = async (solicitud: Solicitud, aprobar: boolean) => {
    const comentario = aprobar ? undefined : await alertas.pedirTexto("¿Por qué se rechaza?", { etiqueta: "Comentario (opcional)" })
    if (comentario === null) return
    try {
      await api.solicitudes.resolver(solicitud.id, aprobar ? "aprobada_temporal" : "rechazada", comentario)
      alertas.breve(aprobar ? "Solicitud aprobada: ya está dentro" : "Solicitud rechazada")
      await actualizar()
    } catch (error) {
      await alertas.error("No se pudo resolver la solicitud", error)
    }
  }

  const deFueraDeClase = (solicitudes ?? []).filter((s) => s.clase === null)
  const deClase = (solicitudes ?? []).filter((s) => s.clase !== null)
  const lista = equipos ?? []

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <Cifra etiqueta="Personas dentro" valor={dentro?.length} icono={Users} />
        <Cifra etiqueta="Fuera de clase" valor={dentro?.filter((p) => p.claseId === null).length} icono={Clock} />
        <Cifra etiqueta="Equipos libres" valor={equipos ? lista.filter((e) => e.estado === "libre").length : undefined} icono={Monitor} tono="success" detalle={equipos ? `de ${lista.length}` : undefined} />
        <Cifra etiqueta="Solicitudes" valor={solicitudes?.length} icono={Inbox} tono={solicitudes?.length ? "warning" : "neutral"} detalle={solicitudes?.length ? "Por resolver" : "Al día"} />
      </div>

      {deFueraDeClase.length > 0 && (
        <Panel titulo="Piden usar el laboratorio" descripcion="Alumnos que quieren entrar fuera de clase." acciones={<Badge variant="warning">{deFueraDeClase.length}</Badge>} sinRelleno>
          <ul className="divide-y">
            {deFueraDeClase.map((s) => (
              <li key={s.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-5">
                <div className="min-w-0">
                  <p className="font-medium">
                    {s.alumno.nombre} {s.alumno.apellido} <span className="font-normal text-muted-foreground tabular-nums">({s.alumno.matricula})</span>
                  </p>
                  <p className="text-sm text-muted-foreground">
                    {s.motivo ?? "Sin motivo"} · {s.equipo ? nombreDeEquipo(s.equipo) : "Equipo propio"} · {hora(s.creadaEn)}
                  </p>
                </div>
                <div className="flex gap-2">
                  <Button type="button" size="sm" variant="success" onClick={() => resolver(s, true)}>
                    <Check /> Dejar entrar
                  </Button>
                  <Button type="button" size="sm" variant="ghost" className="text-destructive hover:bg-destructive/10 hover:text-destructive" onClick={() => resolver(s, false)}>
                    <X /> Rechazar
                  </Button>
                </div>
              </li>
            ))}
          </ul>
        </Panel>
      )}
      {deClase.length > 0 && (
        <p className="text-sm text-muted-foreground">
          Hay {deClase.length} {deClase.length === 1 ? "solicitud" : "solicitudes"} para entrar a una clase; esas las resuelve el administrador.
        </p>
      )}

      <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <Panel titulo="Mapa del laboratorio" descripcion="Elige una computadora para ver su detalle, sacarla de servicio o reportar una falla.">
          {equipos && lista.length === 0 ? (
            <SinDatos icono={Monitor} titulo="El laboratorio no tiene equipos">
              Se configuran en la sección Equipos.
            </SinDatos>
          ) : (
            <MapaDeEquipos equipos={lista} alElegir={setElegido} />
          )}
        </Panel>
        <UsoFueraDeClase laboratorio={laboratorio.clave} equipos={lista} alRegistrar={actualizar} />
      </div>

      <Panel titulo="Quién está dentro" acciones={<Badge variant="primary">{dentro?.length ?? 0}</Badge>} sinRelleno>
        {dentro?.length === 0 ? (
          <SinDatos icono={Users} titulo="No hay nadie dentro del laboratorio" />
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Persona</TableHead>
                <TableHead>Computadora</TableHead>
                <TableHead>Desde</TableHead>
                <TableHead>Motivo</TableHead>
                <TableHead className="w-12">
                  <span className="sr-only">Acciones</span>
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {(dentro ?? []).map((persona) => {
                const nombre = `${persona.nombre} ${persona.apellido ?? ""}`.trim()
                return (
                  <TableRow key={persona.id}>
                    <TableCell>
                      <p className="font-medium">{nombre}</p>
                      <p className="text-xs text-muted-foreground tabular-nums">{persona.matricula ?? (persona.tipo === "invitado" ? "De fuera" : "")}</p>
                    </TableCell>
                    <TableCell>{persona.equipo ? nombreDeEquipo(persona.equipo) : <span className="text-muted-foreground">Propio</span>}</TableCell>
                    <TableCell className="tabular-nums">{hora(persona.horaEntrada)}</TableCell>
                    <TableCell>{persona.claseId ? <Badge variant="success">Clase</Badge> : <Badge variant="info">Fuera de clase</Badge>}</TableCell>
                    <TableCell>
                      <MenuDeAcciones
                        etiqueta={`Acciones para ${nombre}`}
                        acciones={[
                          { texto: "Registrar su salida", icono: LogOut, alElegir: () => salida(persona) },
                          { texto: "Quitar la entrada", icono: Trash2, peligro: true, alElegir: () => quitar(persona) },
                        ]}
                      />
                    </TableCell>
                  </TableRow>
                )
              })}
            </TableBody>
          </Table>
        )}
      </Panel>

      {elegido && (
        <DetalleDeEquipo equipo={lista.find((e) => e.id === elegido.id) ?? elegido} esAdmin={usuario.rol === "admin"} alCerrar={() => setElegido(null)} alCambiar={actualizar} />
      )}
    </div>
  )
}

/** Registrar a alguien que usa el laboratorio sin tener clase. */
function UsoFueraDeClase({ laboratorio, equipos, alRegistrar }: { laboratorio: string; equipos: Equipo[]; alRegistrar: () => void }) {
  const [matricula, setMatricula] = useState("")
  const [nombre, setNombre] = useState("")
  const [apellido, setApellido] = useState("")
  const [equipo, setEquipo] = useState(PROPIO)

  const registrar = async (e: FormEvent) => {
    e.preventDefault()
    try {
      await api.asistencias.usoFueraDeClase({
        laboratorio,
        ...(matricula ? { matricula } : { nombre, apellido }),
        ...(equipo === PROPIO ? { equipoPropio: true } : { equipo }),
      })
      setMatricula("")
      setNombre("")
      setApellido("")
      setEquipo(PROPIO)
      alertas.breve("Entrada registrada")
      alRegistrar()
    } catch (error) {
      await alertas.error("No se pudo registrar la entrada", error)
    }
  }

  return (
    <Panel titulo="Uso fuera de clase" descripcion="Registra a quien entra sin tener clase.">
      <form onSubmit={registrar} className="space-y-4">
        <Campo etiqueta="Número de control" htmlFor="matricula-libre" ayuda="Vacío si es alguien de fuera.">
          <Input id="matricula-libre" value={matricula} onChange={(e) => setMatricula(e.target.value.toUpperCase().trim())} />
        </Campo>
        {!matricula && (
          <div className="grid grid-cols-2 gap-3">
            <Campo etiqueta="Nombre" htmlFor="nombre-libre">
              <Input id="nombre-libre" value={nombre} onChange={(e) => setNombre(e.target.value)} required />
            </Campo>
            <Campo etiqueta="Apellidos" htmlFor="apellido-libre">
              <Input id="apellido-libre" value={apellido} onChange={(e) => setApellido(e.target.value)} required />
            </Campo>
          </div>
        )}
        <Campo etiqueta="Computadora" htmlFor="equipo-libre">
          <Select value={equipo} onValueChange={setEquipo}>
            <SelectTrigger id="equipo-libre">
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
        <Button type="submit" className="w-full">
          <UserPlus /> Registrar entrada
        </Button>
      </form>
    </Panel>
  )
}
