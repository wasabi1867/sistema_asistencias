import { useEffect, useRef, useState, type KeyboardEvent } from "react"
import { X } from "lucide-react"
import { Input } from "@/components/ui/input"
import { api, mensajeDeError } from "../../lib/api"

/**
 * Registro con la credencial: con la cámara (código QR) o con un lector de códigos,
 * que escribe la matrícula y un Enter como si fuera un teclado. Quien entra así trae
 * su propio equipo.
 */
export function EscanerDeCredencial({ laboratorio, hayClase, oscuro, alCerrar }: { laboratorio: string; hayClase: boolean; oscuro: boolean; alCerrar: () => void }) {
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
      setMensaje({ texto: `Asistencia registrada: ${respuesta.nombre}`, bien: true })
    } catch (error) {
      setMensaje({ texto: mensajeDeError(error, "No se pudo registrar la asistencia"), bien: false })
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
      if (!vigente) return
      const nuevo = new Html5QrcodeScanner("lector-qr", { fps: 10, qrbox: { width: 250, height: 250 }, aspectRatio: 1 }, false)
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
    <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/50 backdrop-blur-sm">
      <div className={`relative w-full max-w-md mx-4 p-6 rounded-2xl shadow-2xl ${oscuro ? "bg-[#0f1b18] text-white" : "bg-white text-gray-900"}`}>
        <div className="flex justify-between items-center mb-4">
          <h3 className="text-xl font-bold">Registro con credencial</h3>
          <button type="button" onClick={alCerrar} className="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700" title="Cerrar">
            <X className="h-6 w-6" />
          </button>
        </div>
        <div id="lector-qr" className="w-full mb-4" />
        <Input
          ref={campo}
          value={lectura}
          onChange={(e) => setLectura(e.target.value)}
          onKeyDown={alTeclear}
          placeholder="Escanea tu credencial..."
          autoFocus
          className={`${oscuro ? "bg-[#153731] text-white" : "bg-[#f0fff4] text-[#1C4A3F]"} border-[#1BB827]/30 rounded-xl border-2 mb-4`}
        />
        {mensaje && <div className={`p-3 rounded-lg text-center font-semibold ${mensaje.bien ? "bg-green-100 text-green-800" : "bg-red-100 text-red-800"}`}>{mensaje.texto}</div>}
        <p className={`text-sm text-center mt-4 ${oscuro ? "text-gray-400" : "text-gray-600"}`}>
          {hayClase ? "Escanea tu credencial para registrar tu entrada con tu propio equipo" : "No hay clases iniciadas."}
        </p>
      </div>
    </div>
  )
}
