import { useCallback, useEffect, useRef, useState, type FormEvent } from "react"
import { ArrowRight, CheckCircle2, Clock, Loader2, LogIn, Pencil, ScanLine, Send } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { cn } from "@/lib/utils"
import { api, ErrorDeApi, type ClaseAbierta, type Equipo } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { hora, nombreDeEquipo } from "../../lib/formato"
import { Campo } from "../comunes/Bloques"
import { CuadriculaDeEquipos, PROPIO } from "./CuadriculaDeEquipos"

type Paso = "quien" | "equipo" | "listo"
type Resultado = { tipo: "entrada"; nombre: string; materia: string; equipo: string | null; hora: string } | { tipo: "solicitud"; materia: string }

// Cuánto se queda la confirmación antes de dejar la pantalla lista para el siguiente.
const SEGUNDOS_DE_CONFIRMACION = 6

/**
 * El alumno registra su entrada en dos pasos: quién es (número de control) y qué
 * computadora usa. Al terminar, la confirmación se queda unos segundos y la pantalla
 * vuelve al principio para el siguiente de la fila. Las reglas (clase abierta,
 * inscripción, equipo libre, registro repetido) las revisa la API; aquí se reacciona.
 */
export function FormularioDeEntrada({
  laboratorio,
  clases,
  equipos,
  alCambiarEquipos,
  alUsarCredencial,
}: {
  laboratorio: string
  clases: ClaseAbierta[]
  equipos: Equipo[]
  alCambiarEquipos: () => void
  alUsarCredencial: () => void
}) {
  const [paso, setPaso] = useState<Paso>("quien")
  const [matricula, setMatricula] = useState("")
  const [equipo, setEquipo] = useState("")
  const [codigo, setCodigo] = useState("")
  const [deFuera, setDeFuera] = useState(false)
  const [nombre, setNombre] = useState("")
  const [apellido, setApellido] = useState("")
  const [enviando, setEnviando] = useState(false)
  const [resultado, setResultado] = useState<Resultado | null>(null)
  // Evita registros dobles con internet lento, antes de que el botón se desactive.
  const enviandoRef = useRef(false)
  const tituloDelResultado = useRef<HTMLHeadingElement>(null)

  const hayClase = clases.length > 0
  const soloDeInvitado = hayClase && clases.every((clase) => clase.tipo === "invitado")
  const pideCodigo = clases.some((clase) => clase.pideCodigo)
  const quien = soloDeInvitado && deFuera ? `${nombre} ${apellido}`.trim() : matricula

  const reiniciar = useCallback(() => {
    setMatricula("")
    setEquipo("")
    setCodigo("")
    setNombre("")
    setApellido("")
    setDeFuera(false)
    setResultado(null)
    setPaso("quien")
  }, [])

  // Si la clase se cierra a media captura, se vuelve al principio.
  useEffect(() => {
    if (!hayClase && paso === "equipo") reiniciar()
  }, [hayClase, paso, reiniciar])

  // Si la computadora elegida se ocupa mientras el alumno decide, se quita la elección.
  useEffect(() => {
    if (equipo && equipo !== PROPIO && !equipos.some((e) => e.etiqueta === equipo && e.estado === "libre")) setEquipo("")
  }, [equipos, equipo])

  useEffect(() => {
    if (paso !== "listo") return
    tituloDelResultado.current?.focus()
    const espera = setTimeout(reiniciar, SEGUNDOS_DE_CONFIRMACION * 1000)
    return () => clearTimeout(espera)
  }, [paso, reiniciar])

  const datosDelEquipo = () => (equipo === PROPIO ? { equipoPropio: true } : { equipo })

  const continuar = async (e: FormEvent) => {
    e.preventDefault()
    if (pideCodigo && codigo.length !== 4) {
      await alertas.aviso("Falta el código de la clase", "Escribe los 4 números que muestra quien lleva la clase.")
      return
    }
    setPaso("equipo")
  }

  const registrar = async (e: FormEvent) => {
    e.preventDefault()
    if (enviandoRef.current || !equipo) return
    enviandoRef.current = true
    setEnviando(true)
    try {
      const respuesta = await api.registro.entrar({
        laboratorio,
        ...(soloDeInvitado && deFuera ? { tipo: "invitado" as const, nombre, apellido } : { matricula }),
        ...datosDelEquipo(),
        codigo: pideCodigo ? codigo : undefined,
      })
      setResultado({ tipo: "entrada", nombre: respuesta.nombre, materia: respuesta.clase.materia, equipo: respuesta.equipo, hora: hora(new Date().toISOString()) })
      setPaso("listo")
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
    const mensaje = error instanceof Error ? error.message : undefined
    if (codigoDeError === "no_inscrito" && error instanceof ErrorDeApi) {
      await ofrecerSolicitud(error.datos.claseId, error.datos.materia)
    } else if (codigoDeError === "ya_registrado") {
      await alertas.aviso("Ya registraste tu entrada", mensaje)
      reiniciar()
    } else if (codigoDeError === "equipo_ocupado" || codigoDeError === "equipo_fuera_de_servicio") {
      setEquipo("")
      await alertas.aviso("Elige otra computadora", mensaje)
    } else if (codigoDeError === "matricula_no_encontrada" || codigoDeError === "codigo_incorrecto") {
      setPaso("quien")
      await alertas.aviso(codigoDeError === "codigo_incorrecto" ? "El código no es correcto" : "No encontramos ese número de control", mensaje)
    } else {
      await alertas.error("No se pudo registrar tu entrada", error)
    }
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
      setResultado({ tipo: "solicitud", materia })
      setPaso("listo")
    } catch (error) {
      await alertas.error("No se pudo enviar la solicitud", error)
    }
  }

  if (!hayClase && paso !== "listo") {
    return (
      <section className="flex gap-4 rounded-xl border bg-card p-6 shadow-sm sm:p-8" aria-label="Registro de entrada">
        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-muted">
          <Clock className="h-5 w-5 text-muted-foreground" aria-hidden />
        </div>
        <div>
          <h2 className="text-lg font-semibold">El registro se abre con la clase</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            En cuanto el docente abra su clase podrás escribir tu número de control aquí. Si eres docente o laboratorista, entra desde <span className="font-medium text-foreground">Personal</span>.
          </p>
        </div>
      </section>
    )
  }

  if (paso === "listo" && resultado) {
    const esEntrada = resultado.tipo === "entrada"
    return (
      <section className="flex flex-col items-center rounded-xl border bg-card px-6 py-10 text-center shadow-sm sm:px-8" aria-label="Registro de entrada">
        <div className={cn("flex h-16 w-16 items-center justify-center rounded-full", esEntrada ? "bg-success/15 text-success" : "bg-info/15 text-info")}>
          {esEntrada ? <CheckCircle2 className="h-9 w-9" aria-hidden /> : <Send className="h-8 w-8" aria-hidden />}
        </div>
        <h2 ref={tituloDelResultado} tabIndex={-1} className="mt-4 text-2xl font-semibold tracking-tight outline-none">
          {esEntrada ? `¡Listo, ${resultado.nombre}!` : "Solicitud enviada"}
        </h2>
        <p className="mt-1 text-muted-foreground">{esEntrada ? "Tu entrada quedó registrada." : `Espera a que el administrador la revise para entrar a ${resultado.materia}.`}</p>
        {esEntrada && (
          <dl className="mt-6 grid w-full max-w-md divide-y rounded-lg border text-sm sm:grid-cols-[2fr_1fr_1fr] sm:divide-x sm:divide-y-0">
            {[
              ["Clase", resultado.materia],
              ["Computadora", resultado.equipo ? nombreDeEquipo(resultado.equipo) : "Propia"],
              ["Hora", resultado.hora],
            ].map(([etiqueta, valor]) => (
              <div key={etiqueta} className="flex items-baseline justify-between gap-3 px-3 py-2.5 text-left sm:block sm:text-center">
                <dt className="text-xs text-muted-foreground">{etiqueta}</dt>
                <dd className="font-medium sm:truncate" title={valor}>
                  {valor}
                </dd>
              </div>
            ))}
          </dl>
        )}
        <Button type="button" variant="outline" className="mt-6" onClick={reiniciar}>
          Registrar a otra persona
        </Button>
        <div className="mt-6 h-1 w-full max-w-md overflow-hidden rounded-full bg-muted" aria-hidden>
          <div className="h-full origin-left animate-vaciar bg-success" style={{ ["--duracion" as string]: `${SEGUNDOS_DE_CONFIRMACION}s` }} />
        </div>
      </section>
    )
  }

  return (
    <section className="rounded-xl border bg-card p-5 shadow-sm sm:p-8" aria-label="Registro de entrada">
      <header className="mb-6 flex items-start justify-between gap-4">
        <div>
          <p className="text-sm font-medium text-primary">Registro de entrada</p>
          <h2 className="mt-0.5 text-xl font-semibold tracking-tight sm:text-2xl">{paso === "quien" ? "¿Quién eres?" : "¿Qué computadora vas a usar?"}</h2>
        </div>
        <Pasos actual={paso === "quien" ? 1 : 2} />
      </header>

      {paso === "quien" ? (
        <form onSubmit={continuar} className="space-y-5">
          {soloDeInvitado && (
            <div className="grid grid-cols-2 gap-1 rounded-lg bg-muted p-1" role="group" aria-label="Quién entra">
              {[false, true].map((opcion) => (
                <button
                  key={String(opcion)}
                  type="button"
                  aria-pressed={deFuera === opcion}
                  onClick={() => setDeFuera(opcion)}
                  className={cn("rounded-md px-3 py-2 text-sm font-medium transition-colors", deFuera === opcion ? "bg-card text-foreground shadow-sm" : "text-muted-foreground hover:text-foreground")}
                >
                  {opcion ? "Vengo de fuera" : "Soy alumno del ITSPP"}
                </button>
              ))}
            </div>
          )}

          {soloDeInvitado && deFuera ? (
            <div className="grid gap-4 sm:grid-cols-2">
              <Campo etiqueta="Nombre" htmlFor="nombre">
                <Input id="nombre" value={nombre} onChange={(e) => setNombre(e.target.value)} className="h-12 text-base" autoComplete="given-name" required />
              </Campo>
              <Campo etiqueta="Apellidos" htmlFor="apellido">
                <Input id="apellido" value={apellido} onChange={(e) => setApellido(e.target.value)} className="h-12 text-base" autoComplete="family-name" required />
              </Campo>
            </div>
          ) : (
            <Campo etiqueta="Número de control" htmlFor="matricula" ayuda="El que aparece en tu credencial, por ejemplo 23303080.">
              <Input
                id="matricula"
                value={matricula}
                onChange={(e) => setMatricula(e.target.value.toUpperCase().trim())}
                autoComplete="off"
                autoCapitalize="characters"
                spellCheck={false}
                autoFocus
                className="h-14 text-xl font-medium tracking-wider sm:text-xl"
                required
              />
            </Campo>
          )}

          {pideCodigo && (
            <Campo etiqueta="Código de la clase" htmlFor="codigo" ayuda="Los 4 números que muestra quien lleva la clase.">
              <Input
                id="codigo"
                value={codigo}
                onChange={(e) => setCodigo(e.target.value.replace(/\D/g, "").slice(0, 4))}
                inputMode="numeric"
                autoComplete="off"
                className="h-14 text-center text-2xl font-semibold tracking-[0.5em] sm:text-2xl"
                required
              />
            </Campo>
          )}

          <Button type="submit" size="xl" className="w-full">
            Continuar <ArrowRight />
          </Button>

          {!(soloDeInvitado && deFuera) && (
            <>
              <div className="flex items-center gap-3 text-xs text-muted-foreground" aria-hidden>
                <span className="h-px flex-1 bg-border" /> o <span className="h-px flex-1 bg-border" />
              </div>
              <Button type="button" variant="outline" size="lg" className="w-full" onClick={alUsarCredencial}>
                <ScanLine /> Registrar con mi credencial
              </Button>
              <p className="text-center text-xs text-muted-foreground">Con la credencial se registra que traes tu propio equipo.</p>
            </>
          )}
        </form>
      ) : (
        <form onSubmit={registrar} className="space-y-5">
          <div className="flex items-center justify-between gap-2 rounded-lg bg-muted px-3 py-2 text-sm">
            <span className="min-w-0 truncate">
              {soloDeInvitado && deFuera ? "Entra" : "Número de control"} <strong className="font-semibold tabular-nums">{quien}</strong>
            </span>
            <Button type="button" variant="ghost" size="sm" onClick={() => setPaso("quien")}>
              <Pencil /> Cambiar
            </Button>
          </div>
          <CuadriculaDeEquipos equipos={equipos} valor={equipo} alElegir={setEquipo} />
          <Button type="submit" size="xl" className="w-full" disabled={!equipo || enviando}>
            {enviando ? <Loader2 className="animate-spin" /> : <LogIn />}
            {enviando ? "Registrando…" : !equipo ? "Elige una computadora" : equipo === PROPIO ? "Registrar entrada con mi equipo" : `Registrar entrada en ${nombreDeEquipo(equipo)}`}
          </Button>
        </form>
      )}
    </section>
  )
}

function Pasos({ actual }: { actual: 1 | 2 }) {
  return (
    <div className="flex shrink-0 flex-col items-end gap-1.5 pt-1">
      <span className="text-xs text-muted-foreground">Paso {actual} de 2</span>
      <div className="flex gap-1" aria-hidden>
        {[1, 2].map((n) => (
          <span key={n} className={cn("h-1.5 w-8 rounded-full", n <= actual ? "bg-primary" : "bg-muted")} />
        ))}
      </div>
    </div>
  )
}
