import { useState, type FormEvent } from "react"
import { Inbox, UserPlus, X } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { api, type SolicitudDeAlta, type Turno } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { fechaHora } from "../../lib/formato"
import { useDatos } from "../../hooks/useDatos"
import { Campo, Panel, SinDatos } from "../comunes/Bloques"

const SIN_CARRERA = "ninguna"
const SIN_TURNO = "ninguno"

/**
 * Los alumnos que no están dados de alta y la pidieron desde la pantalla de registro.
 * El administrador revisa sus datos, los completa y los da de alta, o rechaza la solicitud.
 */
export function SolicitudesDeAltaPendientes({ solicitudes, alResolver }: { solicitudes: SolicitudDeAlta[] | null | undefined; alResolver: () => void }) {
  const [revisando, setRevisando] = useState<SolicitudDeAlta | null>(null)

  const rechazar = async (s: SolicitudDeAlta) => {
    const comentario = await alertas.pedirTexto(`¿Rechazar el alta de ${s.nombre} ${s.apellido}?`, { etiqueta: "Comentario (opcional)" })
    if (comentario === null) return
    try {
      await api.solicitudesDeAlta.rechazar(s.id, comentario || undefined)
      alertas.breve("Solicitud de alta rechazada")
      alResolver()
    } catch (error) {
      await alertas.error("No se pudo rechazar la solicitud", error)
    }
  }

  return (
    <Panel
      titulo="Piden su alta"
      descripcion="Alumnos que no están en el sistema y pidieron su alta al registrarse."
      acciones={solicitudes?.length ? <Badge variant="warning">{solicitudes.length}</Badge> : undefined}
      sinRelleno
    >
      {!solicitudes?.length ? (
        <SinDatos icono={Inbox} titulo="Nadie ha pedido su alta" className="py-8" />
      ) : (
        <ul className="divide-y">
          {solicitudes.map((s) => (
            <li key={s.id} className="px-4 py-4 sm:px-5">
              <p className="font-medium">
                {s.nombre} {s.apellido} <span className="font-normal text-muted-foreground tabular-nums">({s.matricula})</span>
              </p>
              <p className="text-sm">{s.clase ? `Desde ${s.clase.materia}${s.clase.grupo ? ` ${s.clase.grupo}` : ""} (${s.clase.docente ?? "sin docente"})` : `Desde ${s.laboratorio.nombre}`}</p>
              <p className="mt-0.5 text-xs text-muted-foreground">
                {[s.semestre ? `${s.semestre}° ${s.grupo ?? ""}`.trim() : s.grupo, fechaHora(s.creadaEn)].filter(Boolean).join(" · ")}
              </p>
              <div className="mt-3 flex flex-wrap gap-2">
                <Button type="button" size="sm" variant="success" onClick={() => setRevisando(s)}>
                  <UserPlus /> Revisar y dar de alta
                </Button>
                <Button type="button" size="sm" variant="ghost" className="text-destructive hover:bg-destructive/10 hover:text-destructive" onClick={() => rechazar(s)}>
                  <X /> Rechazar
                </Button>
              </div>
            </li>
          ))}
        </ul>
      )}
      {revisando && (
        <RevisarAlta
          solicitud={revisando}
          alCerrar={() => setRevisando(null)}
          alAprobar={() => {
            setRevisando(null)
            alResolver()
          }}
        />
      )}
    </Panel>
  )
}

/** Los datos del alumno antes de darlo de alta: lo que escribió, corregido y completado. */
function RevisarAlta({ solicitud, alCerrar, alAprobar }: { solicitud: SolicitudDeAlta; alCerrar: () => void; alAprobar: () => void }) {
  const { datos: carreras } = useDatos(() => api.carreras.listar(), [])
  const [nombre, setNombre] = useState(solicitud.nombre)
  const [apellido, setApellido] = useState(solicitud.apellido)
  const [carrera, setCarrera] = useState(SIN_CARRERA)
  const [semestre, setSemestre] = useState(solicitud.semestre ? String(solicitud.semestre) : "")
  const [grupo, setGrupo] = useState(solicitud.grupo ?? "")
  const [turno, setTurno] = useState<string>(SIN_TURNO)
  const [inscribir, setInscribir] = useState(true)
  const [enviando, setEnviando] = useState(false)

  const aprobar = async (e: FormEvent) => {
    e.preventDefault()
    setEnviando(true)
    try {
      await api.solicitudesDeAlta.aprobar(solicitud.id, {
        nombre,
        apellido,
        carrera: carrera === SIN_CARRERA ? null : carrera,
        semestre: semestre ? Number(semestre) : null,
        grupo: grupo.trim() || null,
        turno: turno === SIN_TURNO ? null : (turno as Turno),
        inscribir,
      })
      const inscrito = solicitud.clase && inscribir ? ` e inscrito en ${solicitud.clase.materia}` : ""
      await alertas.exito("Alumno dado de alta", `${nombre} ${apellido} quedó dado de alta${inscrito}. Ya puede registrar su entrada.`)
      alAprobar()
    } catch (error) {
      await alertas.error("No se pudo dar de alta", error)
    } finally {
      setEnviando(false)
    }
  }

  return (
    <Dialog open onOpenChange={(abierto) => !abierto && alCerrar()}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Dar de alta a {solicitud.matricula}</DialogTitle>
          <DialogDescription>Revisa lo que escribió el alumno y completa lo que falte. La carrera, el semestre y el grupo también se pueden poner después en Alumnos.</DialogDescription>
        </DialogHeader>
        <form id="revisar-alta" onSubmit={aprobar} className="grid grid-cols-2 gap-4">
          <Campo etiqueta="Nombre" htmlFor="revisar-nombre">
            <Input id="revisar-nombre" value={nombre} onChange={(e) => setNombre(e.target.value)} required maxLength={100} />
          </Campo>
          <Campo etiqueta="Apellidos" htmlFor="revisar-apellido">
            <Input id="revisar-apellido" value={apellido} onChange={(e) => setApellido(e.target.value)} required maxLength={100} />
          </Campo>
          <Campo etiqueta="Carrera" htmlFor="revisar-carrera" className="col-span-2">
            <Select value={carrera} onValueChange={setCarrera}>
              <SelectTrigger id="revisar-carrera">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={SIN_CARRERA}>Sin carrera por ahora</SelectItem>
                {(carreras ?? []).map((c) => (
                  <SelectItem key={c.clave} value={c.clave}>
                    {c.nombre}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Campo>
          <Campo etiqueta="Semestre" htmlFor="revisar-semestre">
            <Input id="revisar-semestre" value={semestre} onChange={(e) => setSemestre(e.target.value.replace(/\D/g, "").slice(0, 2))} inputMode="numeric" />
          </Campo>
          <Campo etiqueta="Grupo" htmlFor="revisar-grupo">
            <Input id="revisar-grupo" value={grupo} onChange={(e) => setGrupo(e.target.value)} maxLength={20} />
          </Campo>
          <Campo etiqueta="Turno" htmlFor="revisar-turno" className="col-span-2">
            <Select value={turno} onValueChange={setTurno}>
              <SelectTrigger id="revisar-turno">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={SIN_TURNO}>Sin turno por ahora</SelectItem>
                <SelectItem value="matutino">Matutino</SelectItem>
                <SelectItem value="vespertino">Vespertino</SelectItem>
              </SelectContent>
            </Select>
          </Campo>
          {solicitud.clase && (
            <label className="col-span-2 flex items-center gap-2 text-sm">
              <input type="checkbox" className="h-4 w-4 accent-primary" checked={inscribir} onChange={(e) => setInscribir(e.target.checked)} />
              Inscribirlo en {solicitud.clase.materia}
              {solicitud.clase.grupo ? ` ${solicitud.clase.grupo}` : ""}, la clase desde donde la pidió
            </label>
          )}
        </form>
        <DialogFooter>
          <Button type="button" variant="outline" onClick={alCerrar}>
            Cancelar
          </Button>
          <Button type="submit" form="revisar-alta" disabled={enviando}>
            <UserPlus /> {enviando ? "Dando de alta…" : "Dar de alta"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
