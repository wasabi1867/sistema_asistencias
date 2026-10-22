import { useState, type FormEvent } from "react"
import { ClipboardList, Edit, Plus, Search, Trash2 } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Textarea } from "@/components/ui/textarea"
import { api, type Carrera, type Materia, type Practica } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { duracion } from "../../lib/formato"
import { useDatos } from "../../hooks/useDatos"
import type { PropsDeSeccion } from "./seccion"

const NINGUNA = "ninguna"

/** El catálogo de materias y sus prácticas comunes. */
export function Materias({ c }: PropsDeSeccion) {
  const { datos: carreras } = useDatos(() => api.carreras.listar(), [])
  const [texto, setTexto] = useState("")
  const [carrera, setCarrera] = useState(NINGUNA)
  const [inactivas, setInactivas] = useState(false)
  const { datos: materias, recargar } = useDatos(
    () => api.materias.listar({ q: texto || undefined, carrera: carrera === NINGUNA ? undefined : carrera, activa: !inactivas }),
    [texto, carrera, inactivas],
  )
  const [editando, setEditando] = useState<Materia | "nueva" | null>(null)
  const [practicas, setPracticas] = useState<Materia | null>(null)

  const cambiarActiva = async (materia: Materia) => {
    try {
      await api.materias.editar(materia.id, { activa: !materia.activa })
      await recargar()
    } catch (error) {
      await alertas.error("No se pudo cambiar la materia", error)
    }
  }

  const eliminar = async (materia: Materia) => {
    if (!(await alertas.confirmar(`¿Eliminar ${materia.nombre}?`, "Solo se puede si no tiene cursos ni prácticas; si los tiene, desactívala.", { aceptar: "Eliminar", peligro: true }))) return
    try {
      await api.materias.eliminar(materia.id)
      await recargar()
    } catch (error) {
      await alertas.error("No se pudo eliminar la materia", error)
    }
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap gap-2">
        <div className="relative flex-1 min-w-[14rem]">
          <Search className="w-4 h-4 absolute left-2 top-3 opacity-50" />
          <Input value={texto} onChange={(e) => setTexto(e.target.value)} placeholder="Buscar por nombre o clave" aria-label="Buscar por nombre o clave" className="pl-8" />
        </div>
        <Select value={carrera} onValueChange={setCarrera}>
          <SelectTrigger className="w-64">
            <SelectValue />
          </SelectTrigger>
          <SelectContent className={c.menu}>
            <SelectItem value={NINGUNA}>Todas las carreras</SelectItem>
            {(carreras ?? []).map((carr) => (
              <SelectItem key={carr.clave} value={carr.clave}>
                {carr.nombre}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Button type="button" variant={inactivas ? "default" : "outline"} onClick={() => setInactivas(!inactivas)}>
          {inactivas ? "Viendo desactivadas" : "Ver desactivadas"}
        </Button>
        <Button type="button" onClick={() => setEditando("nueva")} className={c.botonSecundario}>
          <Plus className="w-4 h-4 mr-2" /> Nueva materia
        </Button>
      </div>

      <Card className={`${c.tarjeta} border-none shadow-lg`}>
        <CardContent className="p-0 overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Clave</TableHead>
                <TableHead>Nombre</TableHead>
                <TableHead>Carrera</TableHead>
                <TableHead>Semestre</TableHead>
                <TableHead />
              </TableRow>
            </TableHeader>
            <TableBody>
              {(materias ?? []).map((materia) => (
                <TableRow key={materia.id}>
                  <TableCell className="font-mono">{materia.clave}</TableCell>
                  <TableCell>{materia.nombre}</TableCell>
                  <TableCell>{materia.carrera?.nombre ?? ""}</TableCell>
                  <TableCell>{materia.semestre ? `${materia.semestre}°` : ""}</TableCell>
                  <TableCell className="text-right whitespace-nowrap">
                    <Button type="button" size="sm" variant="ghost" onClick={() => setPracticas(materia)} title="Prácticas">
                      <ClipboardList className="w-4 h-4" />
                    </Button>
                    <Button type="button" size="sm" variant="ghost" onClick={() => setEditando(materia)} title="Editar">
                      <Edit className="w-4 h-4" />
                    </Button>
                    <Button type="button" size="sm" variant="ghost" onClick={() => cambiarActiva(materia)}>
                      {materia.activa ? "Desactivar" : "Activar"}
                    </Button>
                    <Button type="button" size="sm" variant="ghost" onClick={() => eliminar(materia)} title="Eliminar" className="text-red-500">
                      <Trash2 className="w-4 h-4" />
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
              {materias?.length === 0 && (
                <TableRow>
                  <TableCell colSpan={5} className="text-center py-8 opacity-60">
                    No hay materias con esos filtros
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      {editando && (
        <FormularioDeMateria
          materia={editando === "nueva" ? null : editando}
          carreras={carreras ?? []}
          alCerrar={() => setEditando(null)}
          alGuardar={() => {
            setEditando(null)
            recargar()
          }}
        />
      )}
      {practicas && <PracticasDeLaMateria materia={practicas} alCerrar={() => setPracticas(null)} />}
    </div>
  )
}

function FormularioDeMateria({ materia, carreras, alCerrar, alGuardar }: { materia: Materia | null; carreras: Carrera[]; alCerrar: () => void; alGuardar: () => void }) {
  const [datos, setDatos] = useState({
    clave: materia?.clave ?? "",
    nombre: materia?.nombre ?? "",
    carrera: materia?.carrera?.clave ?? NINGUNA,
    semestre: materia?.semestre ? String(materia.semestre) : NINGUNA,
  })

  const guardar = async (e: FormEvent) => {
    e.preventDefault()
    const cuerpo = {
      clave: datos.clave || null,
      nombre: datos.nombre,
      carrera: datos.carrera === NINGUNA ? null : datos.carrera,
      semestre: datos.semestre === NINGUNA ? null : Number(datos.semestre),
    }
    try {
      if (materia) {
        await api.materias.editar(materia.id, cuerpo)
      } else {
        await api.materias.crear(cuerpo)
      }
      alGuardar()
    } catch (error) {
      await alertas.error("No se pudo guardar la materia", error)
    }
  }

  return (
    <Dialog open onOpenChange={(abierto) => !abierto && alCerrar()}>
      <DialogContent aria-describedby={undefined} className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{materia ? "Editar materia" : "Nueva materia"}</DialogTitle>
        </DialogHeader>
        <form onSubmit={guardar} className="grid grid-cols-2 gap-3">
          <div className="space-y-1 col-span-2">
            <Label htmlFor="nombre-materia">Nombre</Label>
            <Input id="nombre-materia" value={datos.nombre} onChange={(e) => setDatos({ ...datos, nombre: e.target.value })} required />
          </div>
          <div className="space-y-1">
            <Label htmlFor="clave-materia">Clave oficial (opcional)</Label>
            <Input id="clave-materia" value={datos.clave} onChange={(e) => setDatos({ ...datos, clave: e.target.value.toUpperCase() })} placeholder="Ej. AED-1286" />
          </div>
          <div className="space-y-1">
            <Label>Semestre</Label>
            <Select value={datos.semestre} onValueChange={(v) => setDatos({ ...datos, semestre: v })}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={NINGUNA}>Sin semestre</SelectItem>
                {Array.from({ length: 12 }, (_, i) => i + 1).map((s) => (
                  <SelectItem key={s} value={String(s)}>
                    {s}°
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1 col-span-2">
            <Label>Carrera</Label>
            <Select value={datos.carrera} onValueChange={(v) => setDatos({ ...datos, carrera: v })}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={NINGUNA}>De varias carreras</SelectItem>
                {carreras.map((carr) => (
                  <SelectItem key={carr.clave} value={carr.clave}>
                    {carr.nombre}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <Button type="submit" className="col-span-2 bg-[#1d5631] hover:bg-[#153d23] text-white">
            Guardar
          </Button>
        </form>
      </DialogContent>
    </Dialog>
  )
}

/** Las prácticas de una materia: las comunes las captura el administrador; las de cada docente se ven aquí. */
function PracticasDeLaMateria({ materia, alCerrar }: { materia: Materia; alCerrar: () => void }) {
  const { datos: practicas, recargar } = useDatos(() => api.practicas.deMateria(materia.id), [materia.id])
  const [editando, setEditando] = useState<Practica | "nueva" | null>(null)
  const [formulario, setFormulario] = useState({ titulo: "", descripcion: "", minutos: "" })

  const abrir = (practica: Practica | "nueva") => {
    setEditando(practica)
    setFormulario(
      practica === "nueva"
        ? { titulo: "", descripcion: "", minutos: "" }
        : { titulo: practica.titulo, descripcion: practica.descripcion ?? "", minutos: practica.duracionMinutos ? String(practica.duracionMinutos) : "" },
    )
  }

  const guardar = async (e: FormEvent) => {
    e.preventDefault()
    const datos = { titulo: formulario.titulo, descripcion: formulario.descripcion || null, duracionMinutos: formulario.minutos ? Number(formulario.minutos) : null }
    try {
      if (editando === "nueva") {
        await api.practicas.crear(materia.id, datos)
      } else if (editando) {
        await api.practicas.editar(editando.id, datos)
      }
      setEditando(null)
      await recargar()
    } catch (error) {
      await alertas.error("No se pudo guardar la práctica", error)
    }
  }

  const eliminar = async (practica: Practica) => {
    if (!(await alertas.confirmar("¿Eliminar la práctica?", `"${practica.titulo}". Si ya se trabajó en clase, solo se desactiva.`, { aceptar: "Eliminar", peligro: true }))) return
    try {
      await api.practicas.eliminar(practica.id)
      await recargar()
    } catch (error) {
      await alertas.error("No se pudo eliminar la práctica", error)
    }
  }

  return (
    <Dialog open onOpenChange={(abierto) => !abierto && alCerrar()}>
      <DialogContent aria-describedby={undefined} className="sm:max-w-2xl max-h-[85vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Prácticas de {materia.nombre}</DialogTitle>
        </DialogHeader>
        {editando ? (
          <form onSubmit={guardar} className="space-y-3">
            <Input value={formulario.titulo} onChange={(e) => setFormulario({ ...formulario, titulo: e.target.value })} placeholder="Título" aria-label="Título" required />
            <Textarea value={formulario.descripcion} onChange={(e) => setFormulario({ ...formulario, descripcion: e.target.value })} placeholder="Descripción" aria-label="Descripción" rows={3} />
            <Input type="number" min={1} value={formulario.minutos} onChange={(e) => setFormulario({ ...formulario, minutos: e.target.value })} placeholder="Duración en minutos" aria-label="Duración en minutos" />
            <div className="flex gap-2">
              <Button type="submit" className="bg-[#1d5631] hover:bg-[#153d23] text-white">
                Guardar
              </Button>
              <Button type="button" variant="outline" onClick={() => setEditando(null)}>
                Cancelar
              </Button>
            </div>
          </form>
        ) : (
          <Button type="button" onClick={() => abrir("nueva")} className="bg-[#1d5631] hover:bg-[#153d23] text-white w-fit">
            <Plus className="w-4 h-4 mr-2" /> Práctica común
          </Button>
        )}
        <p className="text-xs opacity-70">Las comunes las ven todos los docentes de la materia; cada docente además captura las suyas.</p>
        <ul className="space-y-2">
          {(practicas ?? []).map((p) => (
            <li key={p.id} className="flex justify-between gap-2 p-3 rounded-xl bg-[#e6ffe9] dark:bg-[#153731]/60">
              <div>
                <p className="font-medium">
                  {p.numero}. {p.titulo}
                </p>
                <p className="text-xs opacity-70">{[p.comun ? "Común" : `De ${p.docente?.nombre}`, duracion(p.duracionMinutos)].filter(Boolean).join(" · ")}</p>
              </div>
              <div className="flex gap-1 shrink-0">
                <button type="button" onClick={() => abrir(p)} title="Editar" className="p-1">
                  <Edit className="w-4 h-4" />
                </button>
                <button type="button" onClick={() => eliminar(p)} title="Eliminar" className="p-1 text-red-500">
                  <Trash2 className="w-4 h-4" />
                </button>
              </div>
            </li>
          ))}
          {practicas?.length === 0 && <p className="text-sm opacity-70">La materia todavía no tiene prácticas.</p>}
        </ul>
      </DialogContent>
    </Dialog>
  )
}
