import { useState, type FormEvent } from "react"
import { Edit, Plus, Trash2 } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { api, type Carrera, type Laboratorio, type Periodo } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { fecha } from "../../lib/formato"
import { useDatos } from "../../hooks/useDatos"
import { encabezadoDeTarjeta } from "../laboratorista/vista"
import type { PropsDeSeccion } from "./seccion"

/** Carreras, periodos escolares y laboratorios. */
export function Catalogos(props: PropsDeSeccion) {
  return (
    <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
      <Periodos {...props} />
      <Carreras {...props} />
      <Laboratorios {...props} />
    </div>
  )
}

function Tarjeta({ titulo, oscuro, c, children }: { titulo: string; oscuro: boolean; c: PropsDeSeccion["c"]; children: React.ReactNode }) {
  return (
    <Card className={`${c.tarjeta} border-none shadow-lg h-fit`}>
      <CardHeader className={encabezadoDeTarjeta(oscuro)}>
        <CardTitle className="text-lg">{titulo}</CardTitle>
      </CardHeader>
      <CardContent className="pt-4 space-y-3">{children}</CardContent>
    </Card>
  )
}

function Periodos({ oscuro, c }: PropsDeSeccion) {
  const { datos: periodos, recargar } = useDatos(() => api.periodos.listar(), [])
  const [editando, setEditando] = useState<Periodo | "nuevo" | null>(null)
  const [datos, setDatos] = useState({ nombre: "", fechaInicio: "", fechaFin: "" })

  const abrir = (periodo: Periodo | "nuevo") => {
    setEditando(periodo)
    setDatos(periodo === "nuevo" ? { nombre: "", fechaInicio: "", fechaFin: "" } : { nombre: periodo.nombre, fechaInicio: periodo.fechaInicio, fechaFin: periodo.fechaFin })
  }

  const guardar = async (e: FormEvent) => {
    e.preventDefault()
    try {
      if (editando === "nuevo") {
        await api.periodos.crear(datos)
      } else if (editando) {
        await api.periodos.editar(editando.id, datos)
      }
      setEditando(null)
      await recargar()
    } catch (error) {
      await alertas.error("No se pudo guardar el periodo", error)
    }
  }

  const eliminar = async (periodo: Periodo) => {
    if (!(await alertas.confirmar(`¿Eliminar ${periodo.nombre}?`, "Solo se puede si no tiene cursos.", { aceptar: "Eliminar", peligro: true }))) return
    try {
      await api.periodos.eliminar(periodo.id)
      await recargar()
    } catch (error) {
      await alertas.error("No se pudo eliminar el periodo", error)
    }
  }

  return (
    <Tarjeta titulo="Periodos escolares" oscuro={oscuro} c={c}>
      <p className={`text-xs ${c.textoSuave}`}>El periodo actual es el que incluye la fecha de hoy. No se pueden encimar.</p>
      {editando ? (
        <form onSubmit={guardar} className="grid grid-cols-3 gap-2">
          <Input value={datos.nombre} onChange={(e) => setDatos({ ...datos, nombre: e.target.value })} placeholder="Ej. AGO-DIC 2026" aria-label="Nombre del periodo" required className="col-span-3" />
          <Input type="date" value={datos.fechaInicio} onChange={(e) => setDatos({ ...datos, fechaInicio: e.target.value })} aria-label="Inicio del periodo" required />
          <Input type="date" value={datos.fechaFin} onChange={(e) => setDatos({ ...datos, fechaFin: e.target.value })} aria-label="Fin del periodo" required />
          <div className="flex gap-1">
            <Button type="submit" className={c.botonSecundario}>
              Guardar
            </Button>
            <Button type="button" variant="outline" onClick={() => setEditando(null)} aria-label="Cancelar" title="Cancelar">
              ✕
            </Button>
          </div>
        </form>
      ) : (
        <Button type="button" onClick={() => abrir("nuevo")} className={c.botonSecundario}>
          <Plus className="w-4 h-4 mr-2" /> Nuevo periodo
        </Button>
      )}
      <ul className="space-y-1">
        {(periodos ?? []).map((p) => (
          <li key={p.id} className={`flex justify-between items-center p-2 rounded-lg ${c.panel}`}>
            <span>
              <span className="font-medium">{p.nombre}</span> {p.actual && <span className="text-xs px-2 rounded-full bg-[#1BB827] text-white">actual</span>}
              <span className="text-sm opacity-70">
                {" "}
                {fecha(p.fechaInicio)} – {fecha(p.fechaFin)}
              </span>
            </span>
            <span className="flex gap-1">
              <button type="button" onClick={() => abrir(p)} title="Editar">
                <Edit className="w-4 h-4" />
              </button>
              <button type="button" onClick={() => eliminar(p)} title="Eliminar" className="text-red-500">
                <Trash2 className="w-4 h-4" />
              </button>
            </span>
          </li>
        ))}
      </ul>
    </Tarjeta>
  )
}

function Carreras({ oscuro, c }: PropsDeSeccion) {
  const { datos: carreras, recargar } = useDatos(() => api.carreras.listar(true), [])
  const [editando, setEditando] = useState<Carrera | "nueva" | null>(null)
  const [datos, setDatos] = useState({ clave: "", nombre: "" })

  const abrir = (carrera: Carrera | "nueva") => {
    setEditando(carrera)
    setDatos(carrera === "nueva" ? { clave: "", nombre: "" } : { clave: carrera.clave, nombre: carrera.nombre })
  }

  const guardar = async (e: FormEvent) => {
    e.preventDefault()
    try {
      if (editando === "nueva") {
        await api.carreras.crear(datos)
      } else if (editando) {
        await api.carreras.editar(editando.id, datos)
      }
      setEditando(null)
      await recargar()
    } catch (error) {
      await alertas.error("No se pudo guardar la carrera", error)
    }
  }

  const cambiarActiva = async (carrera: Carrera) => {
    try {
      await api.carreras.editar(carrera.id, { activa: !carrera.activa })
      await recargar()
    } catch (error) {
      await alertas.error("No se pudo cambiar la carrera", error)
    }
  }

  const eliminar = async (carrera: Carrera) => {
    if (!(await alertas.confirmar(`¿Eliminar ${carrera.nombre}?`, "Solo se puede si ningún alumno ni materia la usa; si no, desactívala.", { aceptar: "Eliminar", peligro: true }))) return
    try {
      await api.carreras.eliminar(carrera.id)
      await recargar()
    } catch (error) {
      await alertas.error("No se pudo eliminar la carrera", error)
    }
  }

  return (
    <Tarjeta titulo="Carreras" oscuro={oscuro} c={c}>
      {editando ? (
        <form onSubmit={guardar} className="grid grid-cols-4 gap-2">
          <Input value={datos.clave} onChange={(e) => setDatos({ ...datos, clave: e.target.value.toUpperCase() })} placeholder="Clave" aria-label="Clave de la carrera" required />
          <Input value={datos.nombre} onChange={(e) => setDatos({ ...datos, nombre: e.target.value })} placeholder="Nombre oficial" aria-label="Nombre de la carrera" required className="col-span-2" />
          <Button type="submit" className={c.botonSecundario}>
            Guardar
          </Button>
        </form>
      ) : (
        <Button type="button" onClick={() => abrir("nueva")} className={c.botonSecundario}>
          <Plus className="w-4 h-4 mr-2" /> Nueva carrera
        </Button>
      )}
      <ul className="space-y-1">
        {(carreras ?? []).map((carr) => (
          <li key={carr.id} className={`flex justify-between items-center p-2 rounded-lg ${c.panel} ${carr.activa ? "" : "opacity-60"}`}>
            <span>
              <span className="font-mono text-sm">{carr.clave}</span> {carr.nombre}
            </span>
            <span className="flex gap-2 items-center">
              <button type="button" onClick={() => cambiarActiva(carr)} className="text-xs underline">
                {carr.activa ? "Desactivar" : "Activar"}
              </button>
              <button type="button" onClick={() => abrir(carr)} title="Editar">
                <Edit className="w-4 h-4" />
              </button>
              <button type="button" onClick={() => eliminar(carr)} title="Eliminar" className="text-red-500">
                <Trash2 className="w-4 h-4" />
              </button>
            </span>
          </li>
        ))}
      </ul>
    </Tarjeta>
  )
}

function Laboratorios({ oscuro, c }: PropsDeSeccion) {
  const { datos: laboratorios, recargar } = useDatos(() => api.laboratorios.listar(true), [])
  const [nuevo, setNuevo] = useState({ clave: "", nombre: "" })

  const crear = async (e: FormEvent) => {
    e.preventDefault()
    try {
      await api.laboratorios.crear(nuevo)
      setNuevo({ clave: "", nombre: "" })
      await recargar()
    } catch (error) {
      await alertas.error("No se pudo crear el laboratorio", error)
    }
  }

  const renombrar = async (laboratorio: Laboratorio) => {
    const nombre = await alertas.pedirTexto("Nombre del laboratorio", { valor: laboratorio.nombre, obligatorio: true })
    if (!nombre) return
    const ubicacion = await alertas.pedirTexto("Ubicación (opcional)", { valor: laboratorio.ubicacion ?? "" })
    try {
      await api.laboratorios.editar(laboratorio.id, { nombre, ubicacion: ubicacion || null })
      await recargar()
    } catch (error) {
      await alertas.error("No se pudo editar el laboratorio", error)
    }
  }

  const cambiarActivo = async (laboratorio: Laboratorio) => {
    try {
      await api.laboratorios.editar(laboratorio.id, { activo: !laboratorio.activo })
      await recargar()
    } catch (error) {
      await alertas.error("No se pudo cambiar el laboratorio", error)
    }
  }

  return (
    <Tarjeta titulo="Laboratorios" oscuro={oscuro} c={c}>
      <form onSubmit={crear} className="grid grid-cols-4 gap-2">
        <Input value={nuevo.clave} onChange={(e) => setNuevo({ ...nuevo, clave: e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, "_") })} placeholder="clave_corta" aria-label="Clave del laboratorio" required />
        <Input value={nuevo.nombre} onChange={(e) => setNuevo({ ...nuevo, nombre: e.target.value })} placeholder="Nombre" aria-label="Nombre del laboratorio" required className="col-span-2" />
        <Button type="submit" className={c.botonSecundario} aria-label="Agregar laboratorio" title="Agregar laboratorio">
          <Plus className="w-4 h-4" />
        </Button>
      </form>
      <p className={`text-xs ${c.textoSuave}`}>La clave no cambia después: es la que recuerdan las computadoras de cada laboratorio.</p>
      <ul className="space-y-1">
        {(laboratorios ?? []).map((l) => (
          <li key={l.id} className={`flex justify-between items-center p-2 rounded-lg ${c.panel} ${l.activo ? "" : "opacity-60"}`}>
            <span>
              {l.nombre} <span className="font-mono text-xs opacity-70">{l.clave}</span> {l.ubicacion && <span className="text-sm opacity-70">· {l.ubicacion}</span>}
            </span>
            <span className="flex gap-2 items-center">
              <button type="button" onClick={() => cambiarActivo(l)} className="text-xs underline">
                {l.activo ? "Desactivar" : "Activar"}
              </button>
              <button type="button" onClick={() => renombrar(l)} title="Editar">
                <Edit className="w-4 h-4" />
              </button>
            </span>
          </li>
        ))}
      </ul>
    </Tarjeta>
  )
}
