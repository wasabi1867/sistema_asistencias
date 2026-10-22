import { useEffect, useRef, useState, useSyncExternalStore, type FormEvent } from "react"
import { createPortal } from "react-dom"
import * as AlertDialog from "@radix-ui/react-alert-dialog"
import { AlertTriangle, CheckCircle2, HelpCircle, Info, XCircle, type LucideIcon } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { estadoDeLasAlertas, suscribirse, type Alerta, type AvisoBreve } from "../../lib/alertas"

const ICONO: Record<Alerta["tipo"], LucideIcon | null> = {
  exito: CheckCircle2,
  info: Info,
  aviso: AlertTriangle,
  error: XCircle,
  confirmar: HelpCircle,
  texto: null,
}

const COLOR_DEL_ICONO: Record<Alerta["tipo"], string> = {
  exito: "text-[#1BB827]",
  info: "text-sky-500",
  aviso: "text-amber-500",
  error: "text-red-500",
  confirmar: "text-amber-500",
  texto: "",
}

const BOTON = "px-6 py-5 rounded-xl text-base font-medium text-white"
const VERDE = "bg-[#1d5631] hover:bg-[#153d23]"
const GUINDA = "bg-[#800040] hover:bg-[#5c002e]"
const ROJO = "bg-red-600 hover:bg-red-700"

/** Muestra lo que las pantallas piden con lib/alertas.ts. Se monta una sola vez, en _app. */
export function Alertas() {
  const { pendientes, breves } = useSyncExternalStore(suscribirse, estadoDeLasAlertas, estadoDeLasAlertas)
  const actual = pendientes[0]
  return (
    <>
      {actual && <Dialogo key={actual.id} alerta={actual} />}
      <AvisosBreves breves={breves} />
    </>
  )
}

function Dialogo({ alerta }: { alerta: Alerta }) {
  const [texto, setTexto] = useState(alerta.tipo === "texto" ? alerta.valor : "")
  const [falta, setFalta] = useState(false)
  const primerFoco = useRef<HTMLElement | null>(null)
  const Icono = ICONO[alerta.tipo]
  const descripcion = alerta.tipo === "texto" ? undefined : alerta.texto

  const cancelar = () => {
    if (alerta.tipo === "confirmar") {
      alerta.responder(false)
    } else if (alerta.tipo === "texto") {
      alerta.responder(null)
    } else {
      alerta.responder()
    }
  }

  const aceptar = (e: FormEvent) => {
    e.preventDefault()
    if (alerta.tipo === "confirmar") {
      alerta.responder(true)
    } else if (alerta.tipo === "texto") {
      if (alerta.obligatorio && !texto.trim()) {
        setFalta(true)
        return
      }
      alerta.responder(texto)
    } else {
      alerta.responder()
    }
  }

  const colorDeAceptar = alerta.tipo === "confirmar" && alerta.peligro ? ROJO : alerta.tipo === "error" || alerta.tipo === "aviso" ? GUINDA : VERDE
  const conCancelar = alerta.tipo === "confirmar" || alerta.tipo === "texto"

  return (
    <AlertDialog.Root open onOpenChange={(abierto) => !abierto && cancelar()}>
      <AlertDialog.Portal>
        <AlertDialog.Overlay className="fixed inset-0 z-[100] bg-black/50 data-[state=open]:animate-in data-[state=open]:fade-in-0" />
        <AlertDialog.Content
          data-tipo={alerta.tipo}
          // Al abrir, el foco va a lo que se usa primero: el campo, o Cancelar si la acción es peligrosa.
          onOpenAutoFocus={(e) => {
            e.preventDefault()
            primerFoco.current?.focus()
          }}
          className="fixed left-1/2 top-1/2 z-[100] w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 rounded-2xl bg-white p-6 text-center text-gray-800 shadow-xl dark:bg-[#1a2e25] dark:text-white data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95"
        >
          <form onSubmit={aceptar}>
            {Icono && <Icono className={`mx-auto mb-3 h-14 w-14 ${COLOR_DEL_ICONO[alerta.tipo]}`} aria-hidden />}
            <AlertDialog.Title className="text-xl font-semibold">{alerta.titulo}</AlertDialog.Title>
            {/* Una alerta siempre lleva descripción para los lectores de pantalla; si no hay texto, repite el título sin mostrarlo. */}
            {alerta.tipo !== "texto" && (
              <AlertDialog.Description className={descripcion ? "mt-2 whitespace-pre-line text-gray-600 dark:text-gray-300" : "sr-only"}>{descripcion ?? alerta.titulo}</AlertDialog.Description>
            )}

            {alerta.tipo === "texto" && (
              <div className="mt-4 space-y-1 text-left">
                <AlertDialog.Description asChild>
                  <label htmlFor="texto-de-la-alerta" className={alerta.etiqueta ? "text-sm text-gray-600 dark:text-gray-300" : "sr-only"}>
                    {alerta.etiqueta ?? alerta.titulo}
                  </label>
                </AlertDialog.Description>
                {alerta.largo ? (
                  <Textarea
                    id="texto-de-la-alerta"
                    ref={(e) => {
                      primerFoco.current = e
                    }}
                    value={texto}
                    onChange={(e) => {
                      setTexto(e.target.value)
                      setFalta(false)
                    }}
                    rows={3}
                  />
                ) : (
                  <Input
                    id="texto-de-la-alerta"
                    ref={(e) => {
                      primerFoco.current = e
                    }}
                    value={texto}
                    onChange={(e) => {
                      setTexto(e.target.value)
                      setFalta(false)
                    }}
                    autoComplete="off"
                  />
                )}
                {falta && <p className="text-sm text-red-600">Escribe algo para continuar</p>}
              </div>
            )}

            <div className="mt-6 flex flex-wrap justify-center gap-3">
              {conCancelar && (
                <Button
                  type="button"
                  variant="outline"
                  onClick={cancelar}
                  data-accion="cancelar"
                  className="px-6 py-5 rounded-xl text-base dark:bg-transparent dark:text-white"
                  ref={(e) => {
                    if (alerta.tipo === "confirmar" && alerta.peligro) primerFoco.current = e
                  }}
                >
                  Cancelar
                </Button>
              )}
              <Button
                type="submit"
                data-accion="aceptar"
                className={`${BOTON} ${colorDeAceptar}`}
                ref={(e) => {
                  if (alerta.tipo !== "texto" && !(alerta.tipo === "confirmar" && alerta.peligro)) primerFoco.current = e
                }}
              >
                {alerta.tipo === "confirmar" ? alerta.aceptar : "Aceptar"}
              </Button>
            </div>
          </form>
        </AlertDialog.Content>
      </AlertDialog.Portal>
    </AlertDialog.Root>
  )
}

/**
 * Los avisos breves, arriba al centro. Van fuera de la página y con aria-live: los
 * diálogos de Radix no ocultan esas regiones, así que se anuncian aunque haya uno abierto.
 */
function AvisosBreves({ breves }: { breves: AvisoBreve[] }) {
  const [montado, setMontado] = useState(false)
  useEffect(() => setMontado(true), [])
  if (!montado) return null
  return createPortal(
    <div role="status" aria-live="polite" className="pointer-events-none fixed inset-x-0 top-4 z-[110] flex flex-col items-center gap-2 px-4">
      {breves.map((breve) => {
        const Icono = ICONO[breve.tipo]
        return (
          <div
            key={breve.id}
            className="flex items-center gap-2 rounded-xl border border-gray-100 bg-white px-4 py-3 text-gray-800 shadow-lg animate-in fade-in-0 slide-in-from-top-2 dark:border-[#1d5631]/50 dark:bg-[#1a2e25] dark:text-white"
          >
            {Icono && <Icono className={`h-5 w-5 shrink-0 ${COLOR_DEL_ICONO[breve.tipo]}`} aria-hidden />}
            <span className="font-medium">{breve.titulo}</span>
          </div>
        )
      })}
    </div>,
    document.body,
  )
}
