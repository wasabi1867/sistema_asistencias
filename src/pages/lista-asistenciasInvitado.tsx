"use client"

import { useCallback, useEffect, useState } from "react"
import { useRouter } from "next/navigation"
import { ArrowLeft, UserCheck } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { api, type Clase } from "../lib/api"
import { alertas } from "../lib/alertas"
import { useSesion } from "../hooks/useSesion"
import { useTema } from "../hooks/useTema"
import { Cargando } from "../components/comunes/Cargando"
import { BotonDeTema } from "../components/comunes/BotonDeTema"
import { ClaseEnCurso } from "../components/clase/ClaseEnCurso"
import { PANEL_DE } from "../components/registro/InicioDeSesion"

/**
 * La clase de un maestro invitado, como la lleva el personal del laboratorio: se abre
 * desde su panel y aquí se ve quién entra, se registra a gente de fuera y se cierra.
 */
export default function ClaseDeInvitado() {
  const router = useRouter()
  const { usuario } = useSesion(["laboratorista", "admin"])
  const { oscuro, c, alternar } = useTema()
  const [claseId, setClaseId] = useState<number | null | undefined>(undefined)

  useEffect(() => {
    if (!usuario) return
    const laboratorio = localStorage.getItem("laboratorio") ?? undefined
    api.clases
      .listar({ estado: "abiertas", tipo: "invitado", laboratorio })
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

  const volver = useCallback(() => router.push(usuario ? PANEL_DE[usuario.rol] : "/"), [router, usuario])

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

  if (!usuario || claseId === undefined) {
    return <Cargando />
  }

  return (
    <div className={`min-h-screen w-full p-4 sm:p-6 ${oscuro ? "bg-[#0c1f1a]" : "bg-[#f0fff4]"} transition-colors duration-300 space-y-6`}>
      <BotonDeTema oscuro={oscuro} alternar={alternar} />
      <Card className={`${c.encabezado} border-none shadow-lg`}>
        <CardHeader className="flex flex-row items-center justify-between pb-4">
          <div className="flex items-center space-x-4">
            <div className="p-2 rounded-full bg-white/20">
              <UserCheck className="h-8 w-8 text-white" />
            </div>
            <div>
              <CardTitle className="text-2xl font-bold text-white">Clase de maestro invitado</CardTitle>
              <CardDescription className="text-white/80">La lleva {usuario.nombre} {usuario.apellido}</CardDescription>
            </div>
          </div>
          <Button type="button" onClick={volver} className="bg-white/20 hover:bg-white/30 text-white rounded-full mr-14">
            <ArrowLeft className="w-4 h-4 mr-1" /> Panel
          </Button>
        </CardHeader>
      </Card>

      {claseId ? (
        <ClaseEnCurso claseId={claseId} oscuro={oscuro} c={c} alCerrarse={alCerrarse} />
      ) : (
        <p className={c.texto}>No hay una clase de maestro invitado abierta en este laboratorio. Se abre desde el panel.</p>
      )}
    </div>
  )
}
