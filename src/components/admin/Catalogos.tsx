import { useState, type FormEvent, type ReactNode } from "react"
import { CalendarRange, GraduationCap, Monitor, Pencil, Plus, Power, PowerOff, Trash2 } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { cn } from "@/lib/utils"
import { api, type Carrera, type Laboratorio, type Periodo } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { fecha } from "../../lib/formato"
import { useDatos } from "../../hooks/useDatos"
import { Campo, Panel, SinDatos } from "../comunes/Bloques"
import { MenuDeAcciones } from "../comunes/MenuDeAcciones"

/** Carreras, periodos escolares y laboratorios. */
export function Catalogos() {
  return (
    <div className="grid items-start gap-6 xl:grid-cols-2">
      <Periodos />
      <Carreras />
      <Laboratorios />
    </div>
  )
}

/** Un renglón de catálogo: el texto a la izquierda y sus acciones a la derecha. */
function Renglon({ inactivo, children, acciones }: { inactivo?: boolean; children: ReactNode; acciones: ReactNode }) {
  return (
    <li className={cn("flex items-center justify-between gap-3 px-4 py-2.5 sm:px-5", inactivo && "opacity-60")}>
      <div className="min-w-0">{children}</div>
      {acciones}
    </li>
  )
}

/** El diálogo de alta o edición de un elemento de catálogo. */
function Formulario({ titulo, descripcion, alCerrar, alGuardar, children }: { titulo: string; descripcion?: string; alCerrar: () => void; alGuardar: () => Promise<void>; children: ReactNode }) {
  const enviar = async (e: FormEvent) => {
    e.preventDefault()
    await alGuardar()
  }
  return (
    <Dialog open onOpenChange={(abierto) => !abierto && alCerrar()}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{titulo}</DialogTitle>
          {descripcion ? <DialogDescription>{descripcion}</DialogDescription> : <DialogDescription className="sr-only">{titulo}</DialogDescription>}
        </DialogHeader>
        <form onSubmit={enviar} className="space-y-4">
          {children}
          <DialogFooter>
            <Button type="button" variant="outline" onClick={alCerrar}>
              Cancelar
            </Button>
            <Button type="submit">Guardar</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

function Periodos() {
  const { datos: periodos, recargar } = useDatos(() => api.periodos.listar(), [])
  const [editando, setEditando] = useState<Periodo | "nuevo" | null>(null)
  const [datos, setDatos] = useState({ nombre: "", fechaInicio: "", fechaFin: "" })

  const abrir = (periodo: Periodo | "nuevo") => {
    setEditando(periodo)
    setDatos(periodo === "nuevo" ? { nombre: "", fechaInicio: "", fechaFin: "" } : { nombre: periodo.nombre, fechaInicio: periodo.fechaInicio, fechaFin: periodo.fechaFin })
  }

  const guardar = async () => {
    try {
      if (editando === "nuevo") {
        await api.periodos.crear(datos)
      } else if (editando) {
        await api.periodos.editar(editando.id, datos)
      }
      setEditando(null)
      alertas.breve("Periodo guardado")
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
    <Panel
      titulo="Periodos escolares"
      descripcion="El actual es el que incluye la fecha de hoy. No se pueden encimar."
      acciones={
        <Button type="button" variant="outline" size="sm" onClick={() => abrir("nuevo")}>
          <Plus /> Nuevo periodo
        </Button>
      }
      sinRelleno
    >
      {periodos?.length === 0 ? (
        <SinDatos icono={CalendarRange} titulo="No hay periodos" className="py-8" />
      ) : (
        <ul className="divide-y">
          {(periodos ?? []).map((p) => (
            <Renglon
              key={p.id}
              acciones={
                <MenuDeAcciones
                  etiqueta={`Acciones para ${p.nombre}`}
                  acciones={[
                    { texto: "Editar", icono: Pencil, alElegir: () => abrir(p) },
                    { texto: "Eliminar", icono: Trash2, peligro: true, alElegir: () => eliminar(p) },
                  ]}
                />
              }
            >
              <p className="font-medium">
                {p.nombre}
                {p.actual && (
                  <Badge variant="success" className="ml-2">
                    Actual
                  </Badge>
                )}
              </p>
              <p className="text-xs text-muted-foreground tabular-nums">
                {fecha(p.fechaInicio)} – {fecha(p.fechaFin)}
              </p>
            </Renglon>
          ))}
        </ul>
      )}
      {editando && (
        <Formulario titulo={editando === "nuevo" ? "Nuevo periodo" : `Editar ${editando.nombre}`} alCerrar={() => setEditando(null)} alGuardar={guardar}>
          <Campo etiqueta="Nombre del periodo" htmlFor="periodo-nombre">
            <Input id="periodo-nombre" value={datos.nombre} onChange={(e) => setDatos({ ...datos, nombre: e.target.value })} placeholder="Ej. AGO-DIC 2026" required />
          </Campo>
          <div className="grid grid-cols-2 gap-4">
            <Campo etiqueta="Inicio" htmlFor="periodo-inicio">
              <Input id="periodo-inicio" type="date" value={datos.fechaInicio} onChange={(e) => setDatos({ ...datos, fechaInicio: e.target.value })} required />
            </Campo>
            <Campo etiqueta="Fin" htmlFor="periodo-fin">
              <Input id="periodo-fin" type="date" value={datos.fechaFin} onChange={(e) => setDatos({ ...datos, fechaFin: e.target.value })} required />
            </Campo>
          </div>
        </Formulario>
      )}
    </Panel>
  )
}

function Carreras() {
  const { datos: carreras, recargar } = useDatos(() => api.carreras.listar(true), [])
  const [editando, setEditando] = useState<Carrera | "nueva" | null>(null)
  const [datos, setDatos] = useState({ clave: "", nombre: "" })

  const abrir = (carrera: Carrera | "nueva") => {
    setEditando(carrera)
    setDatos(carrera === "nueva" ? { clave: "", nombre: "" } : { clave: carrera.clave, nombre: carrera.nombre })
  }

  const guardar = async () => {
    try {
      if (editando === "nueva") {
        await api.carreras.crear(datos)
      } else if (editando) {
        await api.carreras.editar(editando.id, datos)
      }
      setEditando(null)
      alertas.breve("Carrera guardada")
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
    <Panel
      titulo="Carreras"
      acciones={
        <Button type="button" variant="outline" size="sm" onClick={() => abrir("nueva")}>
          <Plus /> Nueva carrera
        </Button>
      }
      sinRelleno
    >
      {carreras?.length === 0 ? (
        <SinDatos icono={GraduationCap} titulo="No hay carreras" className="py-8" />
      ) : (
        <ul className="divide-y">
          {(carreras ?? []).map((carr) => (
            <Renglon
              key={carr.id}
              inactivo={!carr.activa}
              acciones={
                <MenuDeAcciones
                  etiqueta={`Acciones para ${carr.nombre}`}
                  acciones={[
                    { texto: "Editar", icono: Pencil, alElegir: () => abrir(carr) },
                    { texto: carr.activa ? "Desactivar" : "Activar", icono: carr.activa ? PowerOff : Power, alElegir: () => cambiarActiva(carr) },
                    { texto: "Eliminar", icono: Trash2, peligro: true, alElegir: () => eliminar(carr) },
                  ]}
                />
              }
            >
              <p className="font-medium">{carr.nombre}</p>
              <p className="font-mono text-xs text-muted-foreground">
                {carr.clave}
                {!carr.activa && " · desactivada"}
              </p>
            </Renglon>
          ))}
        </ul>
      )}
      {editando && (
        <Formulario titulo={editando === "nueva" ? "Nueva carrera" : `Editar ${editando.nombre}`} alCerrar={() => setEditando(null)} alGuardar={guardar}>
          <Campo etiqueta="Clave de la carrera" htmlFor="carrera-clave">
            <Input id="carrera-clave" value={datos.clave} onChange={(e) => setDatos({ ...datos, clave: e.target.value.toUpperCase() })} placeholder="Ej. ISC" required />
          </Campo>
          <Campo etiqueta="Nombre oficial" htmlFor="carrera-nombre">
            <Input id="carrera-nombre" value={datos.nombre} onChange={(e) => setDatos({ ...datos, nombre: e.target.value })} required />
          </Campo>
        </Formulario>
      )}
    </Panel>
  )
}

function Laboratorios() {
  const { datos: laboratorios, recargar } = useDatos(() => api.laboratorios.listar(true), [])
  const [editando, setEditando] = useState<Laboratorio | "nuevo" | null>(null)
  const [datos, setDatos] = useState({ clave: "", nombre: "", ubicacion: "" })

  const abrir = (laboratorio: Laboratorio | "nuevo") => {
    setEditando(laboratorio)
    setDatos(laboratorio === "nuevo" ? { clave: "", nombre: "", ubicacion: "" } : { clave: laboratorio.clave, nombre: laboratorio.nombre, ubicacion: laboratorio.ubicacion ?? "" })
  }

  const guardar = async () => {
    try {
      if (editando === "nuevo") {
        await api.laboratorios.crear({ clave: datos.clave, nombre: datos.nombre, ubicacion: datos.ubicacion || null })
      } else if (editando) {
        await api.laboratorios.editar(editando.id, { nombre: datos.nombre, ubicacion: datos.ubicacion || null })
      }
      setEditando(null)
      alertas.breve("Laboratorio guardado")
      await recargar()
    } catch (error) {
      await alertas.error("No se pudo guardar el laboratorio", error)
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
    <Panel
      titulo="Laboratorios"
      descripcion="La clave no cambia después: es la que recuerdan las computadoras de cada laboratorio."
      acciones={
        <Button type="button" variant="outline" size="sm" onClick={() => abrir("nuevo")}>
          <Plus /> Nuevo laboratorio
        </Button>
      }
      sinRelleno
    >
      {laboratorios?.length === 0 ? (
        <SinDatos icono={Monitor} titulo="No hay laboratorios" className="py-8" />
      ) : (
        <ul className="divide-y">
          {(laboratorios ?? []).map((l) => (
            <Renglon
              key={l.id}
              inactivo={!l.activo}
              acciones={
                <MenuDeAcciones
                  etiqueta={`Acciones para ${l.nombre}`}
                  acciones={[
                    { texto: "Editar", icono: Pencil, alElegir: () => abrir(l) },
                    { texto: l.activo ? "Desactivar" : "Activar", icono: l.activo ? PowerOff : Power, alElegir: () => cambiarActivo(l) },
                  ]}
                />
              }
            >
              <p className="font-medium">{l.nombre}</p>
              <p className="text-xs text-muted-foreground">
                <span className="font-mono">{l.clave}</span>
                {l.ubicacion ? ` · ${l.ubicacion}` : ""}
                {!l.activo && " · desactivado"}
              </p>
            </Renglon>
          ))}
        </ul>
      )}
      {editando && (
        <Formulario titulo={editando === "nuevo" ? "Nuevo laboratorio" : `Editar ${editando.nombre}`} alCerrar={() => setEditando(null)} alGuardar={guardar}>
          <Campo etiqueta="Clave del laboratorio" htmlFor="laboratorio-clave" ayuda={editando === "nuevo" ? "Minúsculas, sin espacios. No se puede cambiar después." : "La clave no se puede cambiar."}>
            <Input
              id="laboratorio-clave"
              value={datos.clave}
              onChange={(e) => setDatos({ ...datos, clave: e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, "_") })}
              placeholder="clave_corta"
              disabled={editando !== "nuevo"}
              required
            />
          </Campo>
          <Campo etiqueta="Nombre del laboratorio" htmlFor="laboratorio-nombre">
            <Input id="laboratorio-nombre" value={datos.nombre} onChange={(e) => setDatos({ ...datos, nombre: e.target.value })} required />
          </Campo>
          <Campo etiqueta="Ubicación (opcional)" htmlFor="laboratorio-ubicacion">
            <Input id="laboratorio-ubicacion" value={datos.ubicacion} onChange={(e) => setDatos({ ...datos, ubicacion: e.target.value })} placeholder="Ej. Edificio B, planta alta" />
          </Campo>
        </Formulario>
      )}
    </Panel>
  )
}
