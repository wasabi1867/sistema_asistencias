import { useState, type FormEvent } from "react"
import { BookOpen, ClipboardList, Pencil, Plus, Power, PowerOff, Trash2 } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Textarea } from "@/components/ui/textarea"
import { api, type Carrera, type Materia, type Practica } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { duracion } from "../../lib/formato"
import { useDatos } from "../../hooks/useDatos"
import { CampoDeBusqueda, Campo, Listado, SinDatos } from "../comunes/Bloques"
import { MenuDeAcciones } from "../comunes/MenuDeAcciones"

const NINGUNA = "ninguna"

/** El catálogo de materias y sus prácticas comunes. */
export function Materias() {
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
      alertas.breve(materia.activa ? "Materia desactivada" : "Materia activada")
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
      <div className="flex justify-end">
        <Button type="button" onClick={() => setEditando("nueva")}>
          <Plus /> Nueva materia
        </Button>
      </div>

      <Listado
        filtros={
          <>
            <CampoDeBusqueda etiqueta="Buscar por nombre o clave" value={texto} onChange={(e) => setTexto(e.target.value)} className="min-w-[14rem] flex-1" />
            <Select value={carrera} onValueChange={setCarrera}>
              <SelectTrigger className="w-full sm:w-64" aria-label="Carrera">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={NINGUNA}>Todas las carreras</SelectItem>
                {(carreras ?? []).map((carr) => (
                  <SelectItem key={carr.clave} value={carr.clave}>
                    {carr.nombre}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Select value={inactivas ? "desactivadas" : "activas"} onValueChange={(v) => setInactivas(v === "desactivadas")}>
              <SelectTrigger className="w-full sm:w-40" aria-label="Estado">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="activas">Activas</SelectItem>
                <SelectItem value="desactivadas">Desactivadas</SelectItem>
              </SelectContent>
            </Select>
          </>
        }
        pie={materias ? <span className="tabular-nums">{materias.length} materias</span> : undefined}
      >
        {materias?.length === 0 ? (
          <SinDatos icono={BookOpen} titulo="No hay materias con esos filtros" />
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Clave</TableHead>
                <TableHead>Nombre</TableHead>
                <TableHead className="hidden md:table-cell">Carrera</TableHead>
                <TableHead>Semestre</TableHead>
                <TableHead className="w-12">
                  <span className="sr-only">Acciones</span>
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {(materias ?? []).map((materia) => (
                <TableRow key={materia.id} className="cursor-pointer" onClick={() => setPracticas(materia)}>
                  <TableCell className="font-mono text-[13px] text-muted-foreground">{materia.clave ?? "—"}</TableCell>
                  <TableCell className="font-medium">
                    {materia.nombre}
                    {!materia.activa && (
                      <Badge className="ml-2" variant="neutral">
                        Desactivada
                      </Badge>
                    )}
                  </TableCell>
                  <TableCell className="hidden text-muted-foreground md:table-cell">{materia.carrera?.nombre ?? "Varias"}</TableCell>
                  <TableCell>{materia.semestre ? `${materia.semestre}°` : ""}</TableCell>
                  <TableCell>
                    <MenuDeAcciones
                      etiqueta={`Acciones para ${materia.nombre}`}
                      acciones={[
                        { texto: "Prácticas", icono: ClipboardList, alElegir: () => setPracticas(materia) },
                        { texto: "Editar", icono: Pencil, alElegir: () => setEditando(materia) },
                        { texto: materia.activa ? "Desactivar" : "Activar", icono: materia.activa ? PowerOff : Power, alElegir: () => cambiarActiva(materia) },
                        { texto: "Eliminar", icono: Trash2, peligro: true, alElegir: () => eliminar(materia) },
                      ]}
                    />
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </Listado>

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
      alertas.breve(materia ? "Materia actualizada" : "Materia agregada")
      alGuardar()
    } catch (error) {
      await alertas.error("No se pudo guardar la materia", error)
    }
  }

  return (
    <Dialog open onOpenChange={(abierto) => !abierto && alCerrar()}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{materia ? "Editar materia" : "Nueva materia"}</DialogTitle>
          <DialogDescription>Las materias son el catálogo; cada curso del periodo es una materia con su grupo.</DialogDescription>
        </DialogHeader>
        <form onSubmit={guardar} className="grid grid-cols-2 gap-4">
          <Campo etiqueta="Nombre" htmlFor="nombre-materia" className="col-span-2">
            <Input id="nombre-materia" value={datos.nombre} onChange={(e) => setDatos({ ...datos, nombre: e.target.value })} required />
          </Campo>
          <Campo etiqueta="Clave oficial (opcional)" htmlFor="clave-materia">
            <Input id="clave-materia" value={datos.clave} onChange={(e) => setDatos({ ...datos, clave: e.target.value.toUpperCase() })} placeholder="Ej. AED-1286" />
          </Campo>
          <Campo etiqueta="Semestre" htmlFor="semestre-materia">
            <Select value={datos.semestre} onValueChange={(v) => setDatos({ ...datos, semestre: v })}>
              <SelectTrigger id="semestre-materia">
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
          </Campo>
          <Campo etiqueta="Carrera" htmlFor="carrera-materia" className="col-span-2">
            <Select value={datos.carrera} onValueChange={(v) => setDatos({ ...datos, carrera: v })}>
              <SelectTrigger id="carrera-materia">
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
          </Campo>
          <DialogFooter className="col-span-2">
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
      <DialogContent className="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>Prácticas de {materia.nombre}</DialogTitle>
          <DialogDescription>Las comunes las ven todos los docentes de la materia; cada docente además captura las suyas.</DialogDescription>
        </DialogHeader>
        {editando ? (
          <form onSubmit={guardar} className="space-y-4 rounded-lg border p-4">
            <p className="text-sm font-semibold">{editando === "nueva" ? "Nueva práctica común" : `Práctica ${editando.numero}`}</p>
            <Campo etiqueta="Título" htmlFor="titulo-comun">
              <Input id="titulo-comun" value={formulario.titulo} onChange={(e) => setFormulario({ ...formulario, titulo: e.target.value })} required />
            </Campo>
            <Campo etiqueta="Descripción" htmlFor="descripcion-comun">
              <Textarea id="descripcion-comun" value={formulario.descripcion} onChange={(e) => setFormulario({ ...formulario, descripcion: e.target.value })} rows={3} />
            </Campo>
            <Campo etiqueta="Duración en minutos" htmlFor="minutos-comun" className="max-w-[12rem]">
              <Input id="minutos-comun" type="number" min={1} value={formulario.minutos} onChange={(e) => setFormulario({ ...formulario, minutos: e.target.value })} />
            </Campo>
            <div className="flex justify-end gap-2">
              <Button type="button" variant="outline" onClick={() => setEditando(null)}>
                Cancelar
              </Button>
              <Button type="submit">Guardar</Button>
            </div>
          </form>
        ) : (
          <Button type="button" variant="outline" onClick={() => abrir("nueva")} className="w-fit">
            <Plus /> Práctica común
          </Button>
        )}
        {practicas?.length === 0 ? (
          <div className="rounded-lg border">
            <SinDatos icono={ClipboardList} titulo="La materia todavía no tiene prácticas" className="py-6" />
          </div>
        ) : (
          <ul className="max-h-80 divide-y overflow-y-auto rounded-lg border">
            {(practicas ?? []).map((p) => (
              <li key={p.id} className="flex items-center gap-3 px-3 py-2.5">
                <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold tabular-nums">{p.numero}</span>
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-medium">{p.titulo}</p>
                  <p className="text-xs text-muted-foreground">{[p.comun ? "Común" : `De ${p.docente?.nombre}`, duracion(p.duracionMinutos)].filter(Boolean).join(" · ")}</p>
                </div>
                <MenuDeAcciones
                  etiqueta={`Acciones para ${p.titulo}`}
                  acciones={[
                    { texto: "Editar", icono: Pencil, alElegir: () => abrir(p) },
                    { texto: "Eliminar", icono: Trash2, peligro: true, alElegir: () => eliminar(p) },
                  ]}
                />
              </li>
            ))}
          </ul>
        )}
      </DialogContent>
    </Dialog>
  )
}
