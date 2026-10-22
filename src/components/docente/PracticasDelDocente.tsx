import { useCallback, useEffect, useState, type FormEvent } from "react"
import { ClipboardList, Pencil, Plus, Trash2 } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { api, type Practica } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { duracion, fecha } from "../../lib/formato"
import { Campo, Panel, SinDatos } from "../comunes/Bloques"
import { MenuDeAcciones } from "../comunes/MenuDeAcciones"

const VACIA = { titulo: "", descripcion: "", horas: "", minutos: "", fecha: "" }

/**
 * Las prácticas de una materia que ve el docente: las comunes (de solo lectura) y las
 * suyas, que puede capturar, editar y borrar. Una práctica ya trabajada en clase no se
 * borra: la API la desactiva.
 */
export function PracticasDelDocente({ materia, docenteId, alCambiar }: { materia: { id: number; nombre: string } | null; docenteId: number; alCambiar?: () => void }) {
  const [practicas, setPracticas] = useState<Practica[] | null>(null)
  const [editando, setEditando] = useState<Practica | "nueva" | null>(null)
  const materiaId = materia?.id ?? null

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

  if (!materia) {
    return (
      <Panel>
        <SinDatos icono={ClipboardList} titulo="Elige un curso">
          Las prácticas son de la materia del curso.
        </SinDatos>
      </Panel>
    )
  }

  return (
    <Panel
      titulo={`Prácticas de ${materia.nombre}`}
      descripcion="Las comunes las ven todos los docentes de la materia; las tuyas solo tú."
      acciones={
        <Button type="button" onClick={() => setEditando("nueva")}>
          <Plus /> Nueva práctica
        </Button>
      }
      sinRelleno
    >
      {practicas?.length === 0 ? (
        <SinDatos icono={ClipboardList} titulo="Esta materia todavía no tiene prácticas">
          Agrega las tuyas para elegirlas al abrir la clase.
        </SinDatos>
      ) : (
        <ul className="divide-y">
          {(practicas ?? []).map((practica) => {
            const propia = practica.docente?.id === docenteId
            return (
              <li key={practica.id} className="flex items-start gap-4 px-4 py-3 sm:px-5">
                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-semibold tabular-nums">{practica.numero}</span>
                <div className="min-w-0 flex-1">
                  <p className="font-medium">{practica.titulo}</p>
                  {practica.descripcion && <p className="mt-0.5 line-clamp-2 text-sm text-muted-foreground">{practica.descripcion}</p>}
                  <div className="mt-1.5 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                    <Badge variant={practica.comun ? "neutral" : "primary"}>{practica.comun ? "Común" : propia ? "Tuya" : `De ${practica.docente?.nombre ?? "otro docente"}`}</Badge>
                    {[duracion(practica.duracionMinutos), fecha(practica.fecha)].filter(Boolean).join(" · ")}
                  </div>
                </div>
                {propia && (
                  <MenuDeAcciones
                    etiqueta={`Acciones para ${practica.titulo}`}
                    acciones={[
                      { texto: "Editar", icono: Pencil, alElegir: () => setEditando(practica) },
                      { texto: "Eliminar", icono: Trash2, peligro: true, alElegir: () => eliminar(practica) },
                    ]}
                  />
                )}
              </li>
            )
          })}
        </ul>
      )}

      {editando && (
        <FormularioDePractica
          materiaId={materia.id}
          practica={editando === "nueva" ? null : editando}
          alCerrar={() => setEditando(null)}
          alGuardar={async () => {
            setEditando(null)
            await cargar()
            alCambiar?.()
          }}
        />
      )}
    </Panel>
  )
}

function FormularioDePractica({ materiaId, practica, alCerrar, alGuardar }: { materiaId: number; practica: Practica | null; alCerrar: () => void; alGuardar: () => void }) {
  const [formulario, setFormulario] = useState(
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

  const guardar = async (e: FormEvent) => {
    e.preventDefault()
    const minutos = Number(formulario.horas || 0) * 60 + Number(formulario.minutos || 0)
    const datos = { titulo: formulario.titulo, descripcion: formulario.descripcion || null, duracionMinutos: minutos || null, fecha: formulario.fecha || null }
    try {
      if (practica) {
        await api.practicas.editar(practica.id, datos)
      } else {
        await api.practicas.crear(materiaId, datos)
      }
      alertas.breve(practica ? "Práctica actualizada" : "Práctica agregada")
      alGuardar()
    } catch (error) {
      await alertas.error("No se pudo guardar la práctica", error)
    }
  }

  return (
    <Dialog open onOpenChange={(abierto) => !abierto && alCerrar()}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{practica ? `Práctica ${practica.numero}` : "Nueva práctica"}</DialogTitle>
          <DialogDescription>Solo el título es obligatorio.</DialogDescription>
        </DialogHeader>
        <form onSubmit={guardar} className="space-y-4">
          <Campo etiqueta="Título" htmlFor="titulo-practica">
            <Input id="titulo-practica" value={formulario.titulo} onChange={(e) => setFormulario({ ...formulario, titulo: e.target.value })} required />
          </Campo>
          <Campo etiqueta="Descripción" htmlFor="descripcion-practica">
            <Textarea id="descripcion-practica" value={formulario.descripcion} onChange={(e) => setFormulario({ ...formulario, descripcion: e.target.value })} rows={3} />
          </Campo>
          <div className="grid grid-cols-3 gap-3">
            <Campo etiqueta="Horas" htmlFor="horas-practica">
              <Input id="horas-practica" type="number" min={0} max={24} value={formulario.horas} onChange={(e) => setFormulario({ ...formulario, horas: e.target.value })} />
            </Campo>
            <Campo etiqueta="Minutos" htmlFor="minutos-practica">
              <Input id="minutos-practica" type="number" min={0} max={59} value={formulario.minutos} onChange={(e) => setFormulario({ ...formulario, minutos: e.target.value })} />
            </Campo>
            <Campo etiqueta="Fecha" htmlFor="fecha-practica">
              <Input id="fecha-practica" type="date" value={formulario.fecha} onChange={(e) => setFormulario({ ...formulario, fecha: e.target.value })} />
            </Campo>
          </div>
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
