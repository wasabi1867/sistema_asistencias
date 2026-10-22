import { useEffect, useRef, useState, type KeyboardEvent } from "react"
import { CheckCircle2, XCircle } from "lucide-react"
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { cn } from "@/lib/utils"
import { api, mensajeDeError } from "../../lib/api"

/**
 * Registro con la credencial: con la cámara (código QR) o con un lector de códigos,
 * que escribe la matrícula y un Enter como si fuera un teclado. Quien entra así trae
 * su propio equipo.
 */
export function EscanerDeCredencial({ laboratorio, hayClase, alCerrar }: { laboratorio: string; hayClase: boolean; alCerrar: () => void }) {
  const [lectura, setLectura] = useState("")
  const [mensaje, setMensaje] = useState<{ texto: string; bien: boolean } | null>(null)
  const ocupado = useRef(false)
  const ultima = useRef("")
  const campo = useRef<HTMLInputElement>(null)

  const registrar = async (matricula: string) => {
    // El lector puede mandar la misma credencial dos veces seguidas.
    if (ocupado.current || !matricula || matricula === ultima.current) return
    ocupado.current = true
    ultima.current = matricula
    setTimeout(() => (ultima.current = ""), 3000)
    try {
      const respuesta = await api.registro.entrar({ laboratorio, matricula, equipoPropio: true })
      setMensaje({ texto: `Entrada registrada: ${respuesta.nombre}`, bien: true })
    } catch (error) {
      setMensaje({ texto: mensajeDeError(error, "No se pudo registrar la entrada"), bien: false })
    } finally {
      ocupado.current = false
      setTimeout(() => setMensaje(null), 3000)
    }
  }

  // La cámara: html5-qrcode solo funciona en el navegador, así que se carga al abrir.
  useEffect(() => {
    let escaner: { clear: () => Promise<void> } | null = null
    let vigente = true
    import("html5-qrcode").then(({ Html5QrcodeScanner }) => {
      if (!vigente || !document.getElementById("lector-qr")) return
      const nuevo = new Html5QrcodeScanner("lector-qr", { fps: 10, qrbox: { width: 220, height: 220 }, aspectRatio: 1 }, false)
      nuevo.render((texto) => registrar(texto.split(",")[0]?.trim().toUpperCase() ?? ""), () => undefined)
      escaner = nuevo
    })
    return () => {
      vigente = false
      escaner?.clear().catch(() => undefined)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [laboratorio])

  // El lector de códigos escribe donde esté el cursor: se mantiene en el campo.
  useEffect(() => {
    const intervalo = setInterval(() => campo.current?.focus(), 500)
    return () => clearInterval(intervalo)
  }, [])

  const alTeclear = (e: KeyboardEvent<HTMLInputElement>) => {
    if (e.key !== "Enter") return
    e.preventDefault()
    registrar(lectura.trim().split(/[\s,]+/)[0]?.toUpperCase() ?? "")
    setLectura("")
  }

  return (
    <Dialog open onOpenChange={(abierto) => !abierto && alCerrar()}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Registro con credencial</DialogTitle>
          <DialogDescription>{hayClase ? "Acerca el código de tu credencial a la cámara o pásala por el lector. Se registra que traes tu propio equipo." : "No hay ninguna clase abierta."}</DialogDescription>
        </DialogHeader>
        <div id="lector-qr" className="w-full overflow-hidden rounded-lg [&_img]:inline [&_button]:rounded-md [&_button]:border [&_button]:px-2 [&_button]:py-1" />
        <Input ref={campo} value={lectura} onChange={(e) => setLectura(e.target.value)} onKeyDown={alTeclear} placeholder="Esperando la credencial…" aria-label="Lectura de la credencial" autoFocus />
        <div role="status" aria-live="polite" className="min-h-[3rem]">
          {mensaje && (
            <p className={cn("flex items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium", mensaje.bien ? "bg-success/15 text-success" : "bg-destructive/15 text-destructive")}>
              {mensaje.bien ? <CheckCircle2 className="h-4 w-4 shrink-0" aria-hidden /> : <XCircle className="h-4 w-4 shrink-0" aria-hidden />}
              {mensaje.texto}
            </p>
          )}
        </div>
      </DialogContent>
    </Dialog>
  )
}
