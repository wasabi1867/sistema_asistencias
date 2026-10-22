import { useCallback, useEffect, useState } from "react"
import { useRouter } from "next/navigation"
import { ArrowLeft, UserCheck } from "lucide-react"
import { Button } from "@/components/ui/button"
import { api, type Clase } from "../lib/api"
import { alertas } from "../lib/alertas"
import { PANEL_DE, laboratorioDeEstaComputadora } from "../lib/rutas"
import { useSesion } from "../hooks/useSesion"
import { Cargando } from "../components/comunes/Cargando"
import { EstructuraDelPanel } from "../components/comunes/EstructuraDelPanel"
import { Panel, SinDatos } from "../components/comunes/Bloques"
import { ClaseEnCurso } from "../components/clase/ClaseEnCurso"

/**
 * La clase de un maestro invitado, como la lleva el personal del laboratorio: se abre
 * desde su panel y aquí se ve quién entra, se registra a gente de fuera y se cierra.
 */
export default function ClaseDeInvitado() {
  const router = useRouter()
  const { usuario } = useSesion(["laboratorista", "admin"])
  const [claseId, setClaseId] = useState<number | null | undefined>(undefined)

  useEffect(() => {
    if (!usuario) return
    api.clases
      .listar({ estado: "abiertas", tipo: "invitado", laboratorio: laboratorioDeEstaComputadora() || undefined })
      .then((respuesta) => setClaseId(respuesta.clases[0]?.id ?? null))
      .catch(async (error) => {
        setClaseId(null)
        await alertas.error("No se pudo consultar la clase", error)
      })
  }, [usuario])

  // Si se cierra la pestaña con la clase abierta, se cierra sola.
  useEffect(() => {
    if (!claseId) return
    const alSalir = () => api.clases.cerrarAlSalir(claseId)
    window.addEventListener("beforeunload", alSalir)
    return () => window.removeEventListener("beforeunload", alSalir)
  }, [claseId])

  // El administrador también abre estas clases desde el panel del laboratorio.
  const volver = useCallback(() => router.push(`${PANEL_DE.laboratorista}?seccion=invitado`), [router])

  const alCerrarse = useCallback(
    async (clase: Clase, propia: boolean) => {
      setClaseId(null)
      await (propia
        ? alertas.exito("Clase finalizada", `Se registró la salida de todos. Asistieron ${clase.asistentes}.`)
        : alertas.info("Clase finalizada", "Otra persona del personal finalizó la clase."))
      volver()
    },
    [volver],
  )

  const cerrarSesion = async () => {
    await api.sesion.cerrar().catch(() => undefined)
    router.push("/")
  }

  if (!usuario || claseId === undefined) {
    return <Cargando />
  }

  return (
    <EstructuraDelPanel
      panel="Panel del laboratorio"
      usuario={usuario}
      titulo="Clase de maestro invitado"
      subtitulo={`La lleva ${usuario.nombre} ${usuario.apellido}`}
      acciones={
        <Button type="button" variant="outline" onClick={volver}>
          <ArrowLeft /> <span className="hidden sm:inline">Volver al panel</span>
        </Button>
      }
      alCerrarSesion={cerrarSesion}
      salidaBloqueada={claseId ? "Finaliza la clase antes de salir." : undefined}
    >
      {claseId ? (
        <ClaseEnCurso claseId={claseId} alCerrarse={alCerrarse} />
      ) : (
        <Panel>
          <SinDatos icono={UserCheck} titulo="No hay una clase de maestro invitado abierta">
            Se abre desde el panel del laboratorio, en «Maestro invitado y eventos».
          </SinDatos>
        </Panel>
      )}
    </EstructuraDelPanel>
  )
}
