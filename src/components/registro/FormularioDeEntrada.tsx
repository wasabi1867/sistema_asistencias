import { useRef, useState, type FormEvent } from "react"
import { ChevronRight, Loader2 } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { api, ErrorDeApi, type ClaseAbierta, type Equipo } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import type { Colores } from "../../lib/tema"
import { EstadoDeClases } from "./EstadoDeClases"

const PROPIO = "propio"

/** "5" → "Equipo 5"; "PC-5" se queda igual. */
export function nombreDeEquipo(etiqueta: string): string {
  return /^\d+$/.test(etiqueta) ? `Equipo ${etiqueta}` : etiqueta
}

/**
 * El alumno registra su entrada con su número de control y la computadora que ocupa.
 * Las reglas (clase abierta, inscripción, equipo libre, registro repetido) las revisa
 * la API; aquí se reacciona a cada respuesta.
 */
export function FormularioDeEntrada({
  laboratorio,
  clases,
  equipos,
  oscuro,
  c,
  alCambiarEquipos,
}: {
  laboratorio: string
  clases: ClaseAbierta[]
  equipos: Equipo[]
  oscuro: boolean
  c: Colores
  alCambiarEquipos: () => void
}) {
  const [matricula, setMatricula] = useState("")
  const [equipo, setEquipo] = useState("")
  const [codigo, setCodigo] = useState("")
  const [deFuera, setDeFuera] = useState(false)
  const [nombre, setNombre] = useState("")
  const [apellido, setApellido] = useState("")
  const [enviando, setEnviando] = useState(false)
  // Evita registros dobles con internet lento, antes de que el botón se desactive.
  const enviandoRef = useRef(false)

  const hayClase = clases.length > 0
  const soloDeInvitado = hayClase && clases.every((clase) => clase.tipo === "invitado")
  const pideCodigo = clases.some((clase) => clase.pideCodigo)
  const entrada = `${c.entrada} rounded-xl border-2 focus:ring-[#1BB827] focus:border-[#1BB827] transition-all duration-300`

  const limpiar = () => {
    setMatricula("")
    setEquipo("")
    setCodigo("")
    setNombre("")
    setApellido("")
  }

  const datosDelEquipo = () => (equipo === PROPIO ? { equipoPropio: true } : { equipo })

  const enviar = async (e: FormEvent) => {
    e.preventDefault()
    if (enviandoRef.current) return
    if (!equipo) {
      await alertas.aviso("Falta la computadora", "Elige la computadora que vas a usar o marca que traes tu propio equipo.")
      return
    }
    enviandoRef.current = true
    setEnviando(true)
    try {
      const respuesta = await api.registro.entrar({
        laboratorio,
        ...(soloDeInvitado && deFuera ? { tipo: "invitado" as const, nombre, apellido } : { matricula }),
        ...datosDelEquipo(),
        codigo: pideCodigo ? codigo : undefined,
      })
      await alertas.exito(
        "¡Asistencia registrada!",
        `Bienvenido, ${respuesta.nombre}. ${respuesta.clase.materia}${respuesta.equipo ? `, ${nombreDeEquipo(respuesta.equipo)}` : ", con tu propio equipo"}.`,
      )
      limpiar()
    } catch (error) {
      await alResponderConError(error)
    } finally {
      enviandoRef.current = false
      setEnviando(false)
      alCambiarEquipos()
    }
  }

  const alResponderConError = async (error: unknown) => {
    const codigoDeError = error instanceof ErrorDeApi ? error.codigo : undefined
    if (codigoDeError === "no_inscrito" && error instanceof ErrorDeApi) {
      await ofrecerSolicitud(error.datos.claseId, error.datos.materia)
      return
    }
    if (codigoDeError === "ya_registrado") {
      await alertas.aviso("Ya registraste tu entrada", (error as Error).message)
      return
    }
    if (codigoDeError === "equipo_ocupado" || codigoDeError === "equipo_fuera_de_servicio") {
      setEquipo("")
      await alertas.aviso("Elige otra computadora", (error as Error).message)
      return
    }
    await alertas.error("No se pudo registrar tu entrada", error)
  }

  /** El alumno no está inscrito: puede pedirle al administrador que lo deje entrar. */
  const ofrecerSolicitud = async (claseId: number, materia: string) => {
    const quiere = await alertas.confirmar(
      `No estás inscrito en ${materia}`,
      "¿Quieres pedir permiso para entrar a esta clase? Tu solicitud le llega al administrador.",
      { aceptar: "Pedir permiso" },
    )
    if (!quiere) return
    const motivo = await alertas.pedirTexto("¿Por qué necesitas entrar?", { etiqueta: "Motivo (opcional)", largo: true })
    if (motivo === null) return
    try {
      await api.solicitudes.crear({ laboratorio, matricula, claseId, ...datosDelEquipo(), motivo: motivo || undefined })
      await alertas.exito("Solicitud enviada", "Espera a que el administrador la revise.")
      limpiar()
    } catch (error) {
      await alertas.error("No se pudo enviar la solicitud", error)
    }
  }

  return (
    <form onSubmit={enviar} className="space-y-5">
      <div className="space-y-3">
        <Label className={`text-sm sm:text-base font-medium ${c.titulo}`}>Estado de clases</Label>
        <EstadoDeClases clases={clases} oscuro={oscuro} />
      </div>

      {hayClase && (
        <>
          <div className="space-y-3">
            <Label htmlFor="equipo" className={`text-sm sm:text-base font-medium ${c.titulo}`}>
              Equipo
            </Label>
            <Select value={equipo} onValueChange={setEquipo}>
              <SelectTrigger id="equipo" className={entrada}>
                <SelectValue placeholder="Selecciona un equipo" />
              </SelectTrigger>
              <SelectContent className={c.menu}>
                <SelectItem value={PROPIO}>Traigo mi propio equipo</SelectItem>
                {equipos.map((e) => (
                  <SelectItem key={e.id} value={e.etiqueta} disabled={e.estado !== "libre"} className={e.estado !== "libre" ? "opacity-50" : ""}>
                    {nombreDeEquipo(e.etiqueta)}
                    {e.estado === "ocupado" ? " (en uso)" : e.estado === "fuera_de_servicio" ? " (fuera de servicio)" : ""}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {soloDeInvitado && (
            <div className="grid grid-cols-2 gap-2">
              {[false, true].map((opcion) => (
                <Button
                  key={String(opcion)}
                  type="button"
                  variant="outline"
                  onClick={() => setDeFuera(opcion)}
                  className={`rounded-xl ${deFuera === opcion ? "border-[#1BB827] bg-[#1BB827]/10" : ""}`}
                >
                  {opcion ? "Vengo de fuera" : "Soy alumno del ITSPP"}
                </Button>
              ))}
            </div>
          )}

          {soloDeInvitado && deFuera ? (
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div className="space-y-2">
                <Label htmlFor="nombre" className={`font-medium ${c.titulo}`}>
                  Nombre
                </Label>
                <Input id="nombre" value={nombre} onChange={(e) => setNombre(e.target.value)} placeholder="Tu nombre" className={entrada} required />
              </div>
              <div className="space-y-2">
                <Label htmlFor="apellido" className={`font-medium ${c.titulo}`}>
                  Apellido
                </Label>
                <Input id="apellido" value={apellido} onChange={(e) => setApellido(e.target.value)} placeholder="Tu apellido" className={entrada} required />
              </div>
            </div>
          ) : (
            <div className="space-y-3">
              <Label htmlFor="matricula" className={`text-sm sm:text-base font-medium ${c.titulo}`}>
                Número de control
              </Label>
              <Input
                id="matricula"
                value={matricula}
                onChange={(e) => setMatricula(e.target.value.toUpperCase().trim())}
                placeholder="Ej. 23303080"
                autoComplete="off"
                className={entrada}
                required
              />
            </div>
          )}

          {pideCodigo && (
            <div className="space-y-3">
              <Label htmlFor="codigo" className={`text-sm sm:text-base font-medium ${c.titulo}`}>
                Código de la clase
              </Label>
              <Input
                id="codigo"
                value={codigo}
                onChange={(e) => setCodigo(e.target.value.replace(/\D/g, "").slice(0, 4))}
                placeholder="El que se muestra en el aula"
                inputMode="numeric"
                autoComplete="off"
                className={`${entrada} tracking-[0.5em] text-center text-lg`}
                required
              />
            </div>
          )}
        </>
      )}

      <Button
        type="submit"
        className={`w-full py-6 rounded-xl text-base sm:text-lg font-medium flex items-center justify-center gap-2 ${c.botonPrimario} transition-all duration-300`}
        disabled={!hayClase || enviando}
      >
        {!hayClase ? (
          "No hay clases iniciadas"
        ) : enviando ? (
          <>
            <Loader2 className="w-5 h-5 animate-spin" />
            Registrando...
          </>
        ) : (
          <>
            Registrar asistencia
            <ChevronRight className="w-5 h-5" />
          </>
        )}
      </Button>
    </form>
  )
}
