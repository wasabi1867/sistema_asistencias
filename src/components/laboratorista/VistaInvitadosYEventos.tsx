import { useCallback, useEffect, useState, type FormEvent } from "react"
import { useRouter } from "next/navigation"
import { CalendarPlus, Edit, PlayCircle, Trash2, X } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { api, type Cuenta, type DatosDeEvento, type Evento } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { fecha } from "../../lib/formato"
import { encabezadoDeTarjeta, type PropsDeVista } from "./vista"

/** Abrir la clase de un maestro invitado, y los eventos del laboratorio. */
export function VistaInvitadosYEventos(props: PropsDeVista) {
  return (
    <div className="space-y-6">
      <ClaseDeInvitado {...props} />
      <Eventos {...props} />
    </div>
  )
}

function ClaseDeInvitado({ laboratorio, oscuro, c }: PropsDeVista) {
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
      router.push("/lista-asistenciasInvitado")
    } catch (error) {
      await alertas.error("No se pudo abrir la clase", error)
    }
  }

  return (
    <Card className={`${c.tarjeta} border-none shadow-lg`}>
      <CardHeader className={encabezadoDeTarjeta(oscuro)}>
        <CardTitle className="text-lg">Clase de maestro invitado</CardTitle>
      </CardHeader>
      <CardContent className="pt-4">
        {hayUna ? (
          <div className="space-y-3">
            <p className={c.texto}>Ya hay una clase de maestro invitado abierta en este laboratorio.</p>
            <Button type="button" onClick={() => router.push("/lista-asistenciasInvitado")} className={c.botonSecundario}>
              <PlayCircle className="w-4 h-4 mr-2" /> Ir a la clase
            </Button>
          </div>
        ) : (
          <form onSubmit={abrir} className="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div className="space-y-1">
              <Label htmlFor="docente-invitado">Docente</Label>
              <Input id="docente-invitado" list="docentes-del-instituto" value={datos.docente} onChange={(e) => elegirDocente(e.target.value)} placeholder="Nombre del maestro" />
              <datalist id="docentes-del-instituto">
                {docentes.map((d) => (
                  <option key={d.id} value={`${d.nombre} ${d.apellido}`} />
                ))}
              </datalist>
            </div>
            <div className="space-y-1">
              <Label htmlFor="departamento-invitado">Departamento</Label>
              <Input id="departamento-invitado" value={datos.departamento} onChange={(e) => setDatos({ ...datos, departamento: e.target.value })} />
            </div>
            <div className="space-y-1">
              <Label htmlFor="materia-invitado">Materia o actividad</Label>
              <Input id="materia-invitado" value={datos.materia} onChange={(e) => setDatos({ ...datos, materia: e.target.value })} required />
            </div>
            <div className="space-y-1">
              <Label htmlFor="practica-invitado">Práctica</Label>
              <Input id="practica-invitado" value={datos.practica} onChange={(e) => setDatos({ ...datos, practica: e.target.value })} />
            </div>
            <div className="space-y-1">
              <Label htmlFor="grupo-invitado">Grupo</Label>
              <Input id="grupo-invitado" value={datos.grupo} onChange={(e) => setDatos({ ...datos, grupo: e.target.value })} />
            </div>
            <div className="flex items-end">
              <Button type="submit" className={`w-full ${c.botonSecundario}`}>
                <PlayCircle className="w-4 h-4 mr-2" /> Abrir la clase
              </Button>
            </div>
            <p className={`md:col-span-2 text-xs ${c.textoSuave}`}>A una clase de maestro invitado entra cualquiera: los alumnos con su número de control y la gente de fuera con su nombre.</p>
          </form>
        )}
      </CardContent>
    </Card>
  )
}

const EVENTO_VACIO = { nombre: "", tipo: "", organizador: "", participantes: "", fecha: "", hora: "", duracion: "", descripcion: "", resultados: "" }

function Eventos({ laboratorio, oscuro, c }: PropsDeVista) {
  const [eventos, setEventos] = useState<Evento[]>([])
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
    <div className="space-y-1">
      <Label htmlFor={`evento-${clave}`}>{etiqueta}</Label>
      <Input id={`evento-${clave}`} type={tipo} value={formulario[clave]} onChange={(e) => setFormulario({ ...formulario, [clave]: e.target.value })} required={clave === "nombre"} />
    </div>
  )

  return (
    <Card className={`${c.tarjeta} border-none shadow-lg`}>
      <CardHeader className={`${encabezadoDeTarjeta(oscuro)} flex flex-row items-center justify-between`}>
        <CardTitle className="text-lg">Eventos del laboratorio</CardTitle>
        {editando === null && (
          <Button type="button" size="sm" onClick={() => abrir(null)} className="bg-white/20 hover:bg-white/30 text-white">
            <CalendarPlus className="w-4 h-4 mr-1" /> Nuevo
          </Button>
        )}
      </CardHeader>
      <CardContent className="pt-4 space-y-4">
        {editando !== null && (
          <form onSubmit={guardar} className="grid grid-cols-1 md:grid-cols-3 gap-3 p-3 rounded-xl border border-[#1BB827]/30">
            {campo("nombre", "Nombre")}
            {campo("tipo", "Tipo (taller, concurso...)")}
            {campo("organizador", "Organizador")}
            {campo("fecha", "Fecha", "date")}
            {campo("hora", "Hora", "time")}
            {campo("duracion", "Duración")}
            {campo("participantes", "Participantes", "number")}
            <div className="space-y-1 md:col-span-2">
              <Label htmlFor="evento-descripcion">Descripción</Label>
              <Textarea id="evento-descripcion" value={formulario.descripcion} onChange={(e) => setFormulario({ ...formulario, descripcion: e.target.value })} rows={2} />
            </div>
            <div className="space-y-1 md:col-span-3">
              <Label htmlFor="evento-resultados">Resultados</Label>
              <Textarea id="evento-resultados" value={formulario.resultados} onChange={(e) => setFormulario({ ...formulario, resultados: e.target.value })} rows={2} />
            </div>
            <div className="flex gap-2 md:col-span-3">
              <Button type="submit" className={c.botonSecundario}>
                Guardar
              </Button>
              <Button type="button" variant="outline" onClick={() => setEditando(null)}>
                <X className="w-4 h-4 mr-1" /> Cancelar
              </Button>
            </div>
          </form>
        )}
        {eventos.length === 0 && <p className={c.textoSuave}>No hay eventos registrados.</p>}
        {eventos.map((evento) => (
          <div key={evento.id} className={`p-3 rounded-xl ${c.panel} flex justify-between gap-2`}>
            <div>
              <p className="font-medium">{evento.nombre}</p>
              <p className="text-sm opacity-80">
                {[evento.tipo, evento.fecha ? fecha(evento.fecha) : null, evento.hora, evento.organizador, evento.participantes ? `${evento.participantes} participantes` : null].filter(Boolean).join(" · ")}
              </p>
              {evento.resultados && <p className="text-sm mt-1">{evento.resultados}</p>}
            </div>
            <div className="flex gap-1 shrink-0">
              <button type="button" onClick={() => abrir(evento)} title="Editar" className="p-1">
                <Edit className="w-4 h-4" />
              </button>
              <button type="button" onClick={() => eliminar(evento)} title="Eliminar" className="p-1 text-red-500">
                <Trash2 className="w-4 h-4" />
              </button>
            </div>
          </div>
        ))}
      </CardContent>
    </Card>
  )
}
