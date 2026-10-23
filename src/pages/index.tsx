import { useEffect, useState } from "react"
import Link from "next/link"
import { ArrowLeftRight, LogIn } from "lucide-react"
import { Button } from "@/components/ui/button"
import { api, type ClaseAbierta, type Equipo, type Laboratorio } from "../lib/api"
import { alertas } from "../lib/alertas"
import { laboratorioDeEstaComputadora, recordarLaboratorio } from "../lib/rutas"
import { useTema } from "../hooks/useTema"
import { useConsultaPeriodica } from "../hooks/useConsultaPeriodica"
import { Cargando } from "../components/comunes/Cargando"
import { BotonDeTema } from "../components/comunes/BotonDeTema"
import { Logo } from "../components/comunes/Marca"
import { SeleccionDeLaboratorio, iconoDeLaboratorio } from "../components/registro/SeleccionDeLaboratorio"
import { ClaseActual } from "../components/registro/ClaseActual"
import { FormularioDeEntrada } from "../components/registro/FormularioDeEntrada"
import { EscanerDeCredencial } from "../components/registro/EscanerDeCredencial"
import { Reloj } from "../components/registro/Reloj"

/**
 * La página de la computadora del laboratorio (y del teléfono del alumno): la clase
 * abierta y el registro de entrada. El personal entra desde "Personal". El laboratorio
 * se elige una vez y se recuerda.
 */
export default function PaginaPrincipal() {
  const { oscuro, alternar } = useTema()
  const [laboratorios, setLaboratorios] = useState<Laboratorio[] | null>(null)
  const [clave, setClave] = useState("")
  const [credencial, setCredencial] = useState(false)

  useEffect(() => {
    setClave(laboratorioDeEstaComputadora())
    api.laboratorios
      .listar()
      .then(setLaboratorios)
      .catch(async (error) => {
        setLaboratorios([])
        await alertas.error("No se pudo conectar con el servidor", error)
      })
  }, [])

  // En la computadora del laboratorio, evita cerrar la página por accidente.
  useEffect(() => {
    const alSalir = (e: BeforeUnloadEvent) => {
      e.preventDefault()
      e.returnValue = ""
    }
    window.addEventListener("beforeunload", alSalir)
    return () => window.removeEventListener("beforeunload", alSalir)
  }, [])

  const laboratorio = laboratorios?.find((l) => l.clave === clave) ?? null
  const { datos: clases } = useConsultaPeriodica<ClaseAbierta[]>(() => (laboratorio ? api.clases.abiertas(laboratorio.clave) : undefined), 4000, [laboratorio?.clave])
  const { datos: equipos, recargar: recargarEquipos } = useConsultaPeriodica<Equipo[]>(
    () => (laboratorio ? api.equipos.deLaboratorio(laboratorio.clave) : undefined),
    4000,
    [laboratorio?.clave],
  )

  const elegir = (nueva: string) => {
    setClave(nueva)
    recordarLaboratorio(nueva)
  }

  const cambiarDeLaboratorio = async () => {
    if (await alertas.confirmar("¿Cambiar el laboratorio de esta computadora?", "Los alumnos registran su entrada en el laboratorio que elijas.", { aceptar: "Cambiar" })) elegir("")
  }

  if (laboratorios === null) {
    return <Cargando />
  }

  if (!laboratorio) {
    return (
      <div className="flex min-h-dvh flex-col bg-background">
        <div className="flex justify-end p-3">
          <BotonDeTema oscuro={oscuro} alternar={alternar} />
        </div>
        <main className="flex flex-1 items-center px-4 pb-16">
          <SeleccionDeLaboratorio laboratorios={laboratorios} alElegir={elegir} />
        </main>
      </div>
    )
  }

  const IconoDelLaboratorio = iconoDeLaboratorio(laboratorio.clave)

  return (
    <div className="flex min-h-dvh flex-col bg-background">
      <header className="border-b bg-card">
        <div className="mx-auto flex min-h-16 max-w-6xl items-center gap-3 px-4 py-2 sm:h-20 sm:px-6 sm:py-0">
          <Logo conTecnm className="hidden h-12 sm:flex" />
          <Logo className="sm:hidden" />
          <div className="min-w-0 flex-1 sm:ml-2">
            <p className="truncate text-xs text-muted-foreground">Control de asistencias</p>
            {/* En el teléfono el nombre ocupa hasta dos renglones: cortado, "Taller de Progra…" no se distinguía de otros laboratorios. */}
            <h1 className="flex items-center gap-2 text-base font-semibold leading-tight sm:text-lg">
              <IconoDelLaboratorio className="hidden h-5 w-5 shrink-0 text-primary sm:block" aria-hidden />
              <span className="line-clamp-2 sm:truncate" title={laboratorio.nombre}>{laboratorio.nombre}</span>
            </h1>
          </div>
          <Reloj className="mr-2 hidden md:block" />
          <Button type="button" variant="ghost" size="icon" onClick={cambiarDeLaboratorio} title="Cambiar de laboratorio" aria-label="Cambiar de laboratorio">
            <ArrowLeftRight />
          </Button>
          <BotonDeTema oscuro={oscuro} alternar={alternar} />
          <Button asChild variant="outline">
            <Link href="/entrar">
              <LogIn /> <span className="hidden sm:inline">Personal</span>
              <span className="sr-only sm:hidden">Acceso del personal</span>
            </Link>
          </Button>
        </div>
      </header>

      <main className="mx-auto grid w-full max-w-6xl flex-1 content-start gap-4 lg:content-center px-4 py-5 sm:gap-6 sm:px-6 sm:py-8 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:py-12">
        <ClaseActual clases={clases} />
        <FormularioDeEntrada laboratorio={laboratorio.clave} clases={clases ?? []} equipos={equipos ?? []} alCambiarEquipos={recargarEquipos} alUsarCredencial={() => setCredencial(true)} />
      </main>

      <footer className="border-t px-4 py-4 text-center text-xs text-muted-foreground">
        Instituto Tecnológico Superior de Puerto Peñasco · <span className="italic">Hombres y Mujeres del Mar y Desierto, unidos por la educación tecnológica de calidad</span>
      </footer>

      {credencial && <EscanerDeCredencial laboratorio={laboratorio.clave} hayClase={(clases ?? []).length > 0} alCerrar={() => setCredencial(false)} />}
    </div>
  )
}
