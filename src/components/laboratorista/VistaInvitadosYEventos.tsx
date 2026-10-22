import { useCallback, useEffect, useState, type FormEvent } from "react"
import { useRouter } from "next/navigation"
import { CalendarPlus, CalendarRange, Pencil, PlayCircle, Trash2 } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { api, type Cuenta, type DatosDeEvento, type Evento } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { fecha } from "../../lib/formato"
import { Campo, Panel, PuntoEnVivo, SinDatos } from "../comunes/Bloques"
import { MenuDeAcciones } from "../comunes/MenuDeAcciones"
import type { PropsDeVista } from "./vista"

const RUTA_DE_LA_CLASE = "/clase-invitada"

/** Abrir la clase de un maestro invitado, y los eventos del laboratorio. */
export function VistaInvitadosYEventos(props: PropsDeVista) {
  return (
    <div className="grid items-start gap-6 xl:grid-cols-2">
      <ClaseDeInvitado {...props} />
      <Eventos {...props} />
    </div>
  )
}

function ClaseDeInvitado({ laboratorio }: PropsDeVista) {
  const router = useRouter()
  const [docentes, setDocentes] = useState<Cuenta[]>([])
  const [datos, setDatos] = useState({ docente: "", departamento: "", materia: "", practica: "", grupo: "" })
  const [hayUna, setHayUna] = useState(false)

  useEffect(() => {
    api.usuarios.listar({ rol: "docente" }).then(setDocentes).catch(() => setDocentes([]))
    api.clases
      .listar({ laboratorio: laboratorio.clave, estado: "abiertas", tipo: "invitado" })
      .then((r) => setHayUna(r.clases.length > 0))
      .catch(() => setHayUna(false))
  }, [laboratorio.clave])

  // Elegir un docente del instituto llena su nombre y su departamento.
  const elegirDocente = (nombre: string) => {
    const docente = docentes.find((d) => `${d.nombre} ${d.apellido}` === nombre)
    setDatos({ ...datos, docente: nombre, departamento: docente?.departamento ?? datos.departamento })
  }

  const abrir = async (e: FormEvent) => {
    e.preventDefault()
    try {
      await api.clases.abrirDeInvitado({
        laboratorio: laboratorio.clave,
        materia: datos.materia,
        practica: datos.practica || null,
        docente: datos.docente || null,
        departamento: datos.departamento || null,
        grupo: datos.grupo || null,
      })
      router.push(RUTA_DE_LA_CLASE)
    } catch (error) {
      await alertas.error("No se pudo abrir la clase", error)
    }
  }

  return (
    <Panel titulo="Clase de maestro invitado" descripcion="A esta clase entra cualquiera: los alumnos con su número de control y la gente de fuera con su nombre.">
      {hayUna ? (
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-success/40 bg-success/10 p-4">
          <p className="flex items-center gap-2 text-sm font-medium">
            <PuntoEnVivo /> Ya hay una clase de maestro invitado abierta en este laboratorio.
          </p>
          <Button type="button" onClick={() => router.push(RUTA_DE_LA_CLASE)}>
            <PlayCircle /> Ir a la clase
          </Button>
        </div>
      ) : (
        <form onSubmit={abrir} className="grid gap-4 sm:grid-cols-2">
          <Campo etiqueta="Docente" htmlFor="docente-invitado">
            <Input id="docente-invitado" list="docentes-del-instituto" value={datos.docente} onChange={(e) => elegirDocente(e.target.value)} placeholder="Nombre del maestro" />
            <datalist id="docentes-del-instituto">
              {docentes.map((d) => (
                <option key={d.id} value={`${d.nombre} ${d.apellido}`} />
              ))}
            </datalist>
          </Campo>
          <Campo etiqueta="Departamento" htmlFor="departamento-invitado">
            <Input id="departamento-invitado" value={datos.departamento} onChange={(e) => setDatos({ ...datos, departamento: e.target.value })} />
          </Campo>
          <Campo etiqueta="Materia o actividad" htmlFor="materia-invitado">
            <Input id="materia-invitado" value={datos.materia} onChange={(e) => setDatos({ ...datos, materia: e.target.value })} required />
          </Campo>
          <Campo etiqueta="Práctica" htmlFor="practica-invitado">
            <Input id="practica-invitado" value={datos.practica} onChange={(e) => setDatos({ ...datos, practica: e.target.value })} />
          </Campo>
          <Campo etiqueta="Grupo" htmlFor="grupo-invitado">
            <Input id="grupo-invitado" value={datos.grupo} onChange={(e) => setDatos({ ...datos, grupo: e.target.value })} />
          </Campo>
          <div className="flex items-end">
            <Button type="submit" className="w-full">
              <PlayCircle /> Abrir la clase
            </Button>
          </div>
        </form>
      )}
    </Panel>
  )
}

const EVENTO_VACIO = { nombre: "", tipo: "", organizador: "", participantes: "", fecha: "", hora: "", duracion: "", descripcion: "", resultados: "" }

function Eventos({ laboratorio }: PropsDeVista) {
  const [eventos, setEventos] = useState<Evento[] | null>(null)
  const [editando, setEditando] = useState<number | "nuevo" | null>(null)
  const [formulario, setFormulario] = useState(EVENTO_VACIO)

  const cargar = useCallback(async () => {
    try {
      setEventos(await api.eventos.listar({ laboratorio: laboratorio.clave }))
    } catch (error) {
      await alertas.error("No se pudieron cargar los eventos", error)
    }
  }, [laboratorio.clave])

  useEffect(() => {
    cargar()
  }, [cargar])

  const abrir = (evento: Evento | null) => {
    setEditando(evento?.id ?? "nuevo")
    setFormulario(
      evento
        ? {
            nombre: evento.nombre,
            tipo: evento.tipo ?? "",
            organizador: evento.organizador ?? "",
            participantes: evento.participantes ? String(evento.participantes) : "",
            fecha: evento.fecha ?? "",
            hora: evento.hora ?? "",
            duracion: evento.duracion ?? "",
            descripcion: evento.descripcion ?? "",
            resultados: evento.resultados ?? "",
          }
        : EVENTO_VACIO,
    )
  }

  const guardar = async (e: FormEvent) => {
    e.preventDefault()
    const datos: DatosDeEvento = {
      nombre: formulario.nombre,
      tipo: formulario.tipo || null,
      organizador: formulario.organizador || null,
      participantes: formulario.participantes ? Number(formulario.participantes) : null,
      fecha: formulario.fecha || null,
      hora: formulario.hora || null,
      duracion: formulario.duracion || null,
      descripcion: formulario.descripcion || null,
      resultados: formulario.resultados || null,
    }
    try {
      if (editando === "nuevo") {
        await api.eventos.crear({ ...datos, laboratorio: laboratorio.clave })
      } else if (editando !== null) {
        await api.eventos.editar(editando, datos)
      }
      setEditando(null)
      alertas.breve("Evento guardado")
      await cargar()
    } catch (error) {
      await alertas.error("No se pudo guardar el evento", error)
    }
  }

  const eliminar = async (evento: Evento) => {
    if (!(await alertas.confirmar("¿Eliminar el evento?", evento.nombre, { aceptar: "Eliminar", peligro: true }))) return
    try {
      await api.eventos.eliminar(evento.id)
      await cargar()
    } catch (error) {
      await alertas.error("No se pudo eliminar el evento", error)
    }
  }

  const campo = (clave: keyof typeof EVENTO_VACIO, etiqueta: string, tipo = "text") => (
    <Campo etiqueta={etiqueta} htmlFor={`evento-${clave}`}>
      <Input id={`evento-${clave}`} type={tipo} value={formulario[clave]} onChange={(e) => setFormulario({ ...formulario, [clave]: e.target.value })} required={clave === "nombre"} />
    </Campo>
  )

  return (
    <Panel
      titulo="Eventos del laboratorio"
      descripcion="Talleres, concursos y otras actividades."
      acciones={
        <Button type="button" variant="outline" size="sm" onClick={() => abrir(null)}>
          <CalendarPlus /> Nuevo evento
        </Button>
      }
      sinRelleno
    >
      {eventos?.length === 0 ? (
        <SinDatos icono={CalendarRange} titulo="No hay eventos registrados" />
      ) : (
        <ul className="divide-y">
          {(eventos ?? []).map((evento) => (
            <li key={evento.id} className="flex items-start justify-between gap-3 px-4 py-3 sm:px-5">
              <div className="min-w-0">
                <p className="font-medium">{evento.nombre}</p>
                <p className="text-sm text-muted-foreground">
                  {[evento.tipo, evento.fecha ? fecha(evento.fecha) : null, evento.hora, evento.organizador, evento.participantes ? `${evento.participantes} participantes` : null].filter(Boolean).join(" · ")}
                </p>
                {evento.resultados && <p className="mt-1 text-sm">{evento.resultados}</p>}
              </div>
              <MenuDeAcciones
                etiqueta={`Acciones para ${evento.nombre}`}
                acciones={[
                  { texto: "Editar", icono: Pencil, alElegir: () => abrir(evento) },
                  { texto: "Eliminar", icono: Trash2, peligro: true, alElegir: () => eliminar(evento) },
                ]}
              />
            </li>
          ))}
        </ul>
      )}

      {editando !== null && (
        <Dialog open onOpenChange={(abierto) => !abierto && setEditando(null)}>
          <DialogContent className="sm:max-w-2xl">
            <DialogHeader>
              <DialogTitle>{editando === "nuevo" ? "Nuevo evento" : "Editar evento"}</DialogTitle>
              <DialogDescription>Solo el nombre es obligatorio.</DialogDescription>
            </DialogHeader>
            <form onSubmit={guardar} className="grid gap-4 sm:grid-cols-3">
              <div className="sm:col-span-2">{campo("nombre", "Nombre")}</div>
              {campo("tipo", "Tipo (taller, concurso...)")}
              {campo("organizador", "Organizador")}
              {campo("fecha", "Fecha", "date")}
              {campo("hora", "Hora", "time")}
              {campo("duracion", "Duración")}
              {campo("participantes", "Participantes", "number")}
              <Campo etiqueta="Descripción" htmlFor="evento-descripcion" className="sm:col-span-3">
                <Textarea id="evento-descripcion" value={formulario.descripcion} onChange={(e) => setFormulario({ ...formulario, descripcion: e.target.value })} rows={2} />
              </Campo>
              <Campo etiqueta="Resultados" htmlFor="evento-resultados" className="sm:col-span-3">
                <Textarea id="evento-resultados" value={formulario.resultados} onChange={(e) => setFormulario({ ...formulario, resultados: e.target.value })} rows={2} />
              </Campo>
              <DialogFooter className="sm:col-span-3">
                <Button type="button" variant="outline" onClick={() => setEditando(null)}>
                  Cancelar
                </Button>
                <Button type="submit">Guardar</Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>
      )}
    </Panel>
  )
}
