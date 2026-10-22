import { useEffect, useRef, useState, useSyncExternalStore, type FormEvent } from "react"
import { createPortal } from "react-dom"
import * as AlertDialog from "@radix-ui/react-alert-dialog"
import { AlertTriangle, CheckCircle2, HelpCircle, Info, XCircle, type LucideIcon } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { cn } from "@/lib/utils"
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
  exito: "bg-success/15 text-success",
  info: "bg-info/15 text-info",
  aviso: "bg-warning/15 text-warning",
  error: "bg-destructive/15 text-destructive",
  confirmar: "bg-accent text-accent-foreground",
  texto: "",
}

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
  const peligro = alerta.tipo === "confirmar" && alerta.peligro

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

  const conCancelar = alerta.tipo === "confirmar" || alerta.tipo === "texto"

  return (
    <AlertDialog.Root open onOpenChange={(abierto) => !abierto && cancelar()}>
      <AlertDialog.Portal>
        <AlertDialog.Overlay className="fixed inset-0 z-[100] bg-black/50 backdrop-blur-[2px] data-[state=open]:animate-in data-[state=open]:fade-in-0" />
        <AlertDialog.Content
          data-tipo={alerta.tipo}
          // Al abrir, el foco va a lo que se usa primero: el campo, o Cancelar si la acción es peligrosa.
          onOpenAutoFocus={(e) => {
            e.preventDefault()
            primerFoco.current?.focus()
          }}
          className="fixed left-1/2 top-1/2 z-[100] w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 rounded-xl border bg-card p-6 text-card-foreground shadow-xl data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95"
        >
          <form onSubmit={aceptar}>
            <div className="flex gap-4">
              {Icono && (
                <div className={cn("flex h-10 w-10 shrink-0 items-center justify-center rounded-full", peligro ? "bg-destructive/15 text-destructive" : COLOR_DEL_ICONO[alerta.tipo])}>
                  <Icono className="h-5 w-5" aria-hidden />
                </div>
              )}
              <div className="min-w-0 flex-1 pt-1.5">
                <AlertDialog.Title className="text-lg font-semibold leading-tight">{alerta.titulo}</AlertDialog.Title>
                {/* Una alerta siempre lleva descripción para los lectores de pantalla; si no hay texto, repite el título sin mostrarlo. */}
                {alerta.tipo !== "texto" && (
                  <AlertDialog.Description className={descripcion ? "mt-2 whitespace-pre-line text-sm text-muted-foreground" : "sr-only"}>{descripcion ?? alerta.titulo}</AlertDialog.Description>
                )}

                {alerta.tipo === "texto" && (
                  <div className="mt-3 space-y-1.5">
                    <AlertDialog.Description asChild>
                      <label htmlFor="texto-de-la-alerta" className={alerta.etiqueta ? "text-sm text-muted-foreground" : "sr-only"}>
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
                        aria-invalid={falta}
                      />
                    )}
                    {falta && <p className="text-sm text-destructive">Escribe algo para continuar</p>}
                  </div>
                )}
              </div>
            </div>

            <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
              {conCancelar && (
                <Button
                  type="button"
                  variant="outline"
                  onClick={cancelar}
                  data-accion="cancelar"
                  ref={(e) => {
                    if (peligro) primerFoco.current = e
                  }}
                >
                  Cancelar
                </Button>
              )}
              <Button
                type="submit"
                data-accion="aceptar"
                variant={peligro ? "destructive" : "default"}
                className="sm:min-w-24"
                ref={(e) => {
                  if (alerta.tipo !== "texto" && !peligro) primerFoco.current = e
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
 * Los avisos breves, abajo a la derecha (abajo al centro en el teléfono), donde no tapan
 * el encabezado. Van fuera de la página y con aria-live: los diálogos de Radix no
 * ocultan esas regiones, así que se anuncian aunque haya uno abierto.
 */
function AvisosBreves({ breves }: { breves: AvisoBreve[] }) {
  const [montado, setMontado] = useState(false)
  useEffect(() => setMontado(true), [])
  if (!montado) return null
  return createPortal(
    <div role="status" aria-live="polite" className="pointer-events-none fixed inset-x-0 bottom-4 z-[110] flex flex-col items-center gap-2 px-4 sm:inset-x-auto sm:right-6 sm:items-end">
      {breves.map((breve) => {
        const Icono = ICONO[breve.tipo]
        return (
          <div key={breve.id} className="flex items-center gap-3 rounded-xl border bg-popover px-4 py-3 text-popover-foreground shadow-lg animate-in fade-in-0 slide-in-from-bottom-2">
            {Icono && (
              <span className={cn("flex h-7 w-7 shrink-0 items-center justify-center rounded-full", COLOR_DEL_ICONO[breve.tipo])}>
                <Icono className="h-4 w-4" aria-hidden />
              </span>
            )}
            <span className="text-sm font-medium">{breve.titulo}</span>
          </div>
        )
      })}
    </div>,
    document.body,
  )
}
