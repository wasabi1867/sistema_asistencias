import { useState, type FormEvent } from "react"
import { Check, LogOut, Trash2, UserPlus, X } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { api, type Asistencia, type Equipo, type Solicitud } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { hora } from "../../lib/formato"
import { useConsultaPeriodica } from "../../hooks/useConsultaPeriodica"
import { nombreDeEquipo } from "../registro/FormularioDeEntrada"
import { encabezadoDeTarjeta, type PropsDeVista } from "./vista"

const PROPIO = "propio"

/**
 * Lo que pasa ahora en el laboratorio: quién está dentro, el uso fuera de clase (que
 * registra el personal mientras el instituto no diga quién lo autoriza) y las
 * solicitudes de alumnos que piden usarlo.
 */
export function VistaEnElLaboratorio({ laboratorio, oscuro, c }: PropsDeVista) {
  const { datos: dentro, recargar } = useConsultaPeriodica(() => api.asistencias.activas(laboratorio.clave), 5000, [laboratorio.clave])
  const { datos: equipos, recargar: recargarEquipos } = useConsultaPeriodica(() => api.equipos.deLaboratorio(laboratorio.clave), 5000, [laboratorio.clave])
  const { datos: solicitudes, recargar: recargarSolicitudes } = useConsultaPeriodica(
    () => api.solicitudes.listar({ laboratorio: laboratorio.clave, estado: "pendiente" }),
    10000,
    [laboratorio.clave],
  )

  const actualizar = () => Promise.all([recargar(), recargarEquipos(), recargarSolicitudes()])

  const salida = async (persona: Asistencia) => {
    try {
      await api.asistencias.registrarSalida(persona.id)
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
      await alertas.breve(aprobar ? "Solicitud aprobada: ya está dentro" : "Solicitud rechazada")
      await actualizar()
    } catch (error) {
      await alertas.error("No se pudo resolver la solicitud", error)
    }
  }

  const tarjeta = `${c.tarjeta} border-none shadow-lg`
  const deFueraDeClase = (solicitudes ?? []).filter((s) => s.clase === null)
  const deClase = (solicitudes ?? []).filter((s) => s.clase !== null)

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {[
          ["Personas dentro", dentro?.length ?? 0],
          ["Fuera de clase", dentro?.filter((p) => p.claseId === null).length ?? 0],
          ["Equipos libres", equipos?.filter((e) => e.estado === "libre").length ?? 0],
          ["Solicitudes pendientes", solicitudes?.length ?? 0],
        ].map(([titulo, valor]) => (
          <Card key={titulo} className={tarjeta}>
            <CardContent className="pt-6 text-center">
              <div className="text-3xl font-bold text-[#1BB827]">{valor}</div>
              <p className={`text-sm ${c.textoSuave}`}>{titulo}</p>
            </CardContent>
          </Card>
        ))}
      </div>

      <UsoFueraDeClase laboratorio={laboratorio.clave} equipos={equipos ?? []} c={c} alRegistrar={actualizar} />

      {deFueraDeClase.length > 0 && (
        <Card className={tarjeta}>
          <CardHeader className={encabezadoDeTarjeta(oscuro)}>
            <CardTitle className="text-lg">Piden usar el laboratorio</CardTitle>
          </CardHeader>
          <CardContent className="pt-4 space-y-2">
            {deFueraDeClase.map((s) => (
              <div key={s.id} className={`flex flex-wrap items-center justify-between gap-2 p-3 rounded-xl ${c.panel}`}>
                <div>
                  <p className="font-medium">
                    {s.alumno.nombre} {s.alumno.apellido} ({s.alumno.matricula})
                  </p>
                  <p className="text-sm opacity-80">
                    {s.motivo ?? "Sin motivo"} · {s.equipo ? nombreDeEquipo(s.equipo) : "Equipo propio"} · {hora(s.creadaEn)}
                  </p>
                </div>
                <div className="flex gap-2">
                  <Button type="button" size="sm" onClick={() => resolver(s, true)} className="bg-[#1d5631] hover:bg-[#153d23] text-white">
                    <Check className="w-4 h-4 mr-1" /> Dejar entrar
                  </Button>
                  <Button type="button" size="sm" variant="destructive" onClick={() => resolver(s, false)}>
                    <X className="w-4 h-4 mr-1" /> Rechazar
                  </Button>
                </div>
              </div>
            ))}
          </CardContent>
        </Card>
      )}
      {deClase.length > 0 && (
        <p className={`text-sm ${c.textoSuave}`}>
          Hay {deClase.length} {deClase.length === 1 ? "solicitud" : "solicitudes"} para entrar a una clase; esas las resuelve el administrador.
        </p>
      )}

      <Card className={tarjeta}>
        <CardHeader className={encabezadoDeTarjeta(oscuro)}>
          <CardTitle className="text-lg">Quién está dentro</CardTitle>
        </CardHeader>
        <CardContent className="p-0 overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Persona</TableHead>
                <TableHead>Equipo</TableHead>
                <TableHead>Desde</TableHead>
                <TableHead>En</TableHead>
                <TableHead />
              </TableRow>
            </TableHeader>
            <TableBody>
              {(dentro ?? []).map((persona) => (
                <TableRow key={persona.id}>
                  <TableCell>
                    {persona.nombre} {persona.apellido} <span className="opacity-60">{persona.matricula ?? (persona.tipo === "invitado" ? "(de fuera)" : "")}</span>
                  </TableCell>
                  <TableCell>{persona.equipo ? nombreDeEquipo(persona.equipo) : "Propio"}</TableCell>
                  <TableCell>{hora(persona.horaEntrada)}</TableCell>
                  <TableCell>{persona.claseId ? "Clase" : "Fuera de clase"}</TableCell>
                  <TableCell className="text-right whitespace-nowrap">
                    <Button type="button" size="sm" variant="outline" onClick={() => salida(persona)} title="Registrar su salida">
                      <LogOut className="w-4 h-4" />
                    </Button>{" "}
                    <Button type="button" size="sm" variant="destructive" onClick={() => quitar(persona)} title="Quitar la entrada">
                      <Trash2 className="w-4 h-4" />
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
              {dentro?.length === 0 && (
                <TableRow>
                  <TableCell colSpan={5} className="text-center py-8 opacity-60">
                    No hay nadie dentro del laboratorio
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  )
}

/** Registrar a alguien que usa el laboratorio sin tener clase. */
function UsoFueraDeClase({ laboratorio, equipos, c, alRegistrar }: { laboratorio: string; equipos: Equipo[]; c: PropsDeVista["c"]; alRegistrar: () => void }) {
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
      await alertas.breve("Entrada registrada")
      alRegistrar()
    } catch (error) {
      await alertas.error("No se pudo registrar la entrada", error)
    }
  }

  return (
    <Card className={`${c.tarjeta} border-none shadow-lg`}>
      <CardContent className="pt-6">
        <form onSubmit={registrar} className="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
          <div className="space-y-1 md:col-span-2">
            <label htmlFor="matricula-libre" className="text-sm font-medium">
              Uso fuera de clase: número de control
            </label>
            <Input id="matricula-libre" value={matricula} onChange={(e) => setMatricula(e.target.value.toUpperCase().trim())} placeholder="Vacío si es alguien de fuera" />
          </div>
          {!matricula && (
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
          <Button type="submit" className={`md:col-start-5 ${c.botonSecundario}`}>
            <UserPlus className="w-4 h-4 mr-2" /> Registrar entrada
          </Button>
        </form>
      </CardContent>
    </Card>
  )
}
