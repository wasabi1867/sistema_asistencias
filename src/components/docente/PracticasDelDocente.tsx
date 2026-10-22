import { useCallback, useEffect, useState, type FormEvent } from "react"
import { Edit, Plus, Trash2, X } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { api, type Practica } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { duracion, fecha } from "../../lib/formato"

const VACIA = { titulo: "", descripcion: "", horas: "", minutos: "", fecha: "" }

/**
 * Las prácticas de una materia que ve el docente: las comunes (de solo lectura) y las
 * suyas, que puede capturar, editar y borrar. Una práctica ya trabajada en clase no se
 * borra: la API la desactiva.
 */
export function PracticasDelDocente({ materiaId, docenteId, alCambiar }: { materiaId: number | null; docenteId: number; alCambiar?: () => void }) {
  const [practicas, setPracticas] = useState<Practica[]>([])
  const [editando, setEditando] = useState<Practica | null>(null)
  const [formulario, setFormulario] = useState(VACIA)
  const [abierto, setAbierto] = useState(false)

  const cargar = useCallback(async () => {
    if (!materiaId) return setPracticas([])
    try {
      setPracticas(await api.practicas.deMateria(materiaId))
    } catch (error) {
      await alertas.error("No se pudieron cargar las prácticas", error)
    }
  }, [materiaId])

  useEffect(() => {
    cargar()
  }, [cargar])

  const abrir = (practica: Practica | null) => {
    setEditando(practica)
    setFormulario(
      practica
        ? {
            titulo: practica.titulo,
            descripcion: practica.descripcion ?? "",
            horas: practica.duracionMinutos ? String(Math.floor(practica.duracionMinutos / 60)) : "",
            minutos: practica.duracionMinutos ? String(practica.duracionMinutos % 60) : "",
            fecha: practica.fecha ?? "",
          }
        : VACIA,
    )
    setAbierto(true)
  }

  const guardar = async (e: FormEvent) => {
    e.preventDefault()
    if (!materiaId) return
    const minutos = Number(formulario.horas || 0) * 60 + Number(formulario.minutos || 0)
    const datos = { titulo: formulario.titulo, descripcion: formulario.descripcion || null, duracionMinutos: minutos || null, fecha: formulario.fecha || null }
    try {
      if (editando) {
        await api.practicas.editar(editando.id, datos)
      } else {
        await api.practicas.crear(materiaId, datos)
      }
      setAbierto(false)
      await alertas.breve(editando ? "Práctica actualizada" : "Práctica agregada")
      await cargar()
      alCambiar?.()
    } catch (error) {
      await alertas.error("No se pudo guardar la práctica", error)
    }
  }

  const eliminar = async (practica: Practica) => {
    if (!(await alertas.confirmar("¿Eliminar la práctica?", `"${practica.titulo}". Si ya se trabajó en alguna clase, solo deja de aparecer en la lista.`, { aceptar: "Eliminar", peligro: true }))) return
    try {
      await api.practicas.eliminar(practica.id)
      await cargar()
      alCambiar?.()
    } catch (error) {
      await alertas.error("No se pudo eliminar la práctica", error)
    }
  }

  if (!materiaId) {
    return <p className="text-sm text-gray-500">Elige un curso para ver sus prácticas.</p>
  }

  return (
    <div className="space-y-3">
      {!abierto ? (
        <Button type="button" onClick={() => abrir(null)} className="w-full bg-[#1d5631] hover:bg-[#153d23] text-white">
          <Plus className="w-4 h-4 mr-2" /> Nueva práctica
        </Button>
      ) : (
        <form onSubmit={guardar} className="space-y-3 p-3 rounded-xl border border-[#1BB827]/30">
          <div className="flex justify-between items-center">
            <h4 className="font-semibold">{editando ? `Práctica ${editando.numero}` : "Nueva práctica"}</h4>
            <button type="button" onClick={() => setAbierto(false)} title="Cancelar">
              <X className="w-4 h-4" />
            </button>
          </div>
          <div className="space-y-1">
            <Label htmlFor="titulo-practica">Título</Label>
            <Input id="titulo-practica" value={formulario.titulo} onChange={(e) => setFormulario({ ...formulario, titulo: e.target.value })} required />
          </div>
          <div className="space-y-1">
            <Label htmlFor="descripcion-practica">Descripción</Label>
            <Textarea id="descripcion-practica" value={formulario.descripcion} onChange={(e) => setFormulario({ ...formulario, descripcion: e.target.value })} rows={3} />
          </div>
          <div className="grid grid-cols-3 gap-2">
            <div className="space-y-1">
              <Label htmlFor="horas-practica">Horas</Label>
              <Input id="horas-practica" type="number" min={0} max={24} value={formulario.horas} onChange={(e) => setFormulario({ ...formulario, horas: e.target.value })} />
            </div>
            <div className="space-y-1">
              <Label htmlFor="minutos-practica">Minutos</Label>
              <Input id="minutos-practica" type="number" min={0} max={59} value={formulario.minutos} onChange={(e) => setFormulario({ ...formulario, minutos: e.target.value })} />
            </div>
            <div className="space-y-1">
              <Label htmlFor="fecha-practica">Fecha</Label>
              <Input id="fecha-practica" type="date" value={formulario.fecha} onChange={(e) => setFormulario({ ...formulario, fecha: e.target.value })} />
            </div>
          </div>
          <Button type="submit" className="w-full bg-[#800040] hover:bg-[#5c002e] text-white">
            Guardar
          </Button>
        </form>
      )}

      {practicas.length === 0 && <p className="text-sm text-gray-500">Esta materia todavía no tiene prácticas.</p>}
      <ul className="space-y-2">
        {practicas.map((practica) => {
          const propia = practica.docente?.id === docenteId
          return (
            <li key={practica.id} className="p-3 rounded-xl bg-[#e6ffe9] dark:bg-[#153731]/60">
              <div className="flex justify-between gap-2">
                <div className="min-w-0">
                  <p className="font-medium">
                    {practica.numero}. {practica.titulo}
                  </p>
                  <p className="text-xs opacity-70">
                    {[practica.comun ? "Común de la materia" : null, duracion(practica.duracionMinutos), fecha(practica.fecha)].filter(Boolean).join(" · ")}
                  </p>
                </div>
                {propia && (
                  <div className="flex gap-1 shrink-0">
                    <button type="button" onClick={() => abrir(practica)} title="Editar" className="p-1 hover:opacity-70">
                      <Edit className="w-4 h-4" />
                    </button>
                    <button type="button" onClick={() => eliminar(practica)} title="Eliminar" className="p-1 text-red-500 hover:opacity-70">
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>
                )}
              </div>
            </li>
          )
        })}
      </ul>
    </div>
  )
}
