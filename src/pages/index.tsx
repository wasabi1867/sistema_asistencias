"use client"

import { useEffect, useState } from "react"
import Image from "next/image"
import { AnimatePresence, motion } from "framer-motion"
import { ArrowLeft, Computer, QrCode, User, UserCog, X } from "lucide-react"
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from "@/components/ui/card"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { api, type ClaseAbierta, type Equipo, type Laboratorio } from "../lib/api"
import { useTema } from "../hooks/useTema"
import { useConsultaPeriodica } from "../hooks/useConsultaPeriodica"
import { Cargando } from "../components/comunes/Cargando"
import { BotonDeTema } from "../components/comunes/BotonDeTema"
import { CarruselDeImagenes } from "../components/registro/CarruselDeImagenes"
import { MenuDeLaPagina } from "../components/registro/MenuDeLaPagina"
import { SeleccionDeLaboratorio, iconoDeLaboratorio } from "../components/registro/SeleccionDeLaboratorio"
import { FormularioDeEntrada } from "../components/registro/FormularioDeEntrada"
import { AccesoDelAdministrador, InicioDeSesionDelPersonal } from "../components/registro/InicioDeSesion"
import { EscanerDeCredencial } from "../components/registro/EscanerDeCredencial"
import { alertas } from "../lib/alertas"

const MENSAJES = ['"Hombres y Mujeres Del Mar y Desierto', 'Unidos Por La Educación Tecnológica De Calidad."']
const IMAGENES = [
  { src: "/FondoItspp.png", alt: "Logotipo del ITSPP" },
  { src: "/tecnmImagen.png", alt: "Logotipo del Tecnológico Nacional de México" },
  { src: "/LogoSistemas.png", alt: "Logotipo de Ingeniería en Sistemas Computacionales" },
]
const CLAVE_GUARDADA = "laboratorio"

/**
 * La página de la computadora del laboratorio: el alumno registra su entrada y el
 * personal inicia sesión. El laboratorio se elige una vez y se recuerda.
 */
export default function PaginaPrincipal() {
  const { oscuro, c, alternar } = useTema()
  const [laboratorios, setLaboratorios] = useState<Laboratorio[] | null>(null)
  const [clave, setClave] = useState("")
  const [pestana, setPestana] = useState<"alumno" | "personal">("alumno")
  const [mensaje, setMensaje] = useState(0)
  const [accesoAdmin, setAccesoAdmin] = useState(false)
  const [escaner, setEscaner] = useState(false)

  useEffect(() => {
    setClave(localStorage.getItem(CLAVE_GUARDADA) ?? "")
    api.laboratorios
      .listar()
      .then(setLaboratorios)
      .catch(async (error) => {
        setLaboratorios([])
        await alertas.error("No se pudo conectar con el servidor", error)
      })
  }, [])

  useEffect(() => {
    const intervalo = setInterval(() => setMensaje((i) => (i + 1) % MENSAJES.length), 3500)
    return () => clearInterval(intervalo)
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
    if (nueva) {
      localStorage.setItem(CLAVE_GUARDADA, nueva)
    } else {
      localStorage.removeItem(CLAVE_GUARDADA)
    }
  }

  if (laboratorios === null) {
    return <Cargando />
  }

  if (!laboratorio) {
    return (
      <div className={`min-h-screen flex flex-col items-center justify-center p-4 ${c.fondo} transition-colors duration-300`}>
        <BotonDeTema oscuro={oscuro} alternar={alternar} />
        <SeleccionDeLaboratorio laboratorios={laboratorios} oscuro={oscuro} alElegir={elegir} />
      </div>
    )
  }

  const IconoDelLaboratorio = iconoDeLaboratorio(laboratorio.clave)
  const hayClase = (clases ?? []).length > 0

  return (
    <div className={`min-h-screen flex flex-col items-center justify-center p-2 sm:p-4 ${c.fondo} transition-colors duration-300`}>
      <div className="fixed top-2 left-2 z-50">
        <MenuDeLaPagina oscuro={oscuro} alEntrarComoAdministrador={() => setAccesoAdmin(true)} />
      </div>
      <button
        type="button"
        onClick={() => elegir("")}
        title="Cambiar de laboratorio"
        className={`fixed top-14 left-2 sm:top-4 sm:left-16 z-40 flex items-center gap-2 px-3 py-2 rounded-full transition-all duration-300 text-xs sm:text-sm font-medium text-white ${
          oscuro ? "bg-[#1C4A3F] hover:bg-[#153731]" : "bg-[#800040] hover:bg-[#5c002e]"
        }`}
      >
        <IconoDelLaboratorio size={16} />
        <span>{laboratorio.nombre}</span>
        <ArrowLeft size={14} className="opacity-60" />
      </button>
      <BotonDeTema oscuro={oscuro} alternar={alternar} />
      <button
        type="button"
        onClick={() => setEscaner(!escaner)}
        title={escaner ? "Cerrar el registro con credencial" : "Registrar con credencial"}
        className={`fixed bottom-6 right-6 z-50 p-4 rounded-full shadow-lg transition-all text-white ${escaner ? "bg-red-500 hover:bg-red-600" : "bg-[#1BB827] hover:bg-[#159620]"}`}
      >
        {escaner ? <X className="h-6 w-6" /> : <QrCode className="h-6 w-6" />}
      </button>

      <AnimatePresence mode="wait">
        <motion.div
          key={mensaje}
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          exit={{ opacity: 0, y: -20 }}
          transition={{ duration: 0.5 }}
          className={`text-base sm:text-lg md:text-xl lg:text-2xl xl:text-3xl font-bold ${oscuro ? "text-white" : "text-[#1C4A3F]"} mb-6 sm:mb-8 text-center z-10 p-2 sm:p-3 rounded-xl max-w-[95%] sm:max-w-[90%] mx-auto`}
        >
          {MENSAJES[mensaje]}
        </motion.div>
      </AnimatePresence>

      <Card className={`w-full max-w-[95%] sm:max-w-[90%] md:max-w-[85%] lg:max-w-[80%] xl:max-w-[75%] mx-auto overflow-hidden ${c.tarjeta} border-none relative z-10 shadow-xl rounded-2xl sm:rounded-3xl`}>
        <div className="flex flex-col lg:flex-row min-h-[70vh] sm:min-h-[80vh] relative overflow-hidden rounded-2xl sm:rounded-3xl">
          <div className={`lg:w-2/5 relative h-52 sm:h-72 lg:h-auto ${pestana === "personal" ? "lg:order-last" : ""}`}>
            <CarruselDeImagenes imagenes={IMAGENES} />
          </div>

          <div className="lg:w-3/5 relative flex flex-col">
            <div className="absolute top-0 left-0 right-0 h-16 sm:h-20 flex justify-center items-center z-10 bg-white/80 dark:bg-[#1C4A3F]/80 backdrop-blur-sm">
              <div className="w-28 sm:w-36 h-14 sm:h-18 relative">
                <Image src="/logo itspp.jpeg" alt="Logos institucionales" fill className="object-contain" priority />
              </div>
            </div>
            <CardHeader className={`relative z-10 ${c.encabezado} p-6 sm:p-8 pt-20 sm:pt-24`}>
              <CardTitle className="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold flex flex-col items-center justify-center text-white mb-2 sm:mb-3 text-center">
                <Computer className="w-8 h-8 sm:w-10 sm:h-10 md:w-12 md:h-12 mb-2 sm:mb-3" />
                <motion.span initial={{ opacity: 0, scale: 0.5 }} animate={{ opacity: 1, scale: 1 }} transition={{ duration: 0.8, delay: 0.3, ease: [0, 0.71, 0.2, 1.01] }}>
                  {laboratorio.nombre}
                </motion.span>
              </CardTitle>
            </CardHeader>

            <CardContent className={`flex-grow flex flex-col justify-center items-center p-6 sm:p-8 ${c.tarjeta}`}>
              <Tabs value={pestana} onValueChange={(valor) => setPestana(valor as typeof pestana)} className="w-full max-w-md mx-auto">
                <TabsList className="grid w-full grid-cols-2 mb-6 p-1 bg-[#1BB827]/10 dark:bg-[#1BB827]/20 rounded-xl">
                  <TabsTrigger value="alumno" className="text-sm sm:text-base py-3 rounded-lg data-[state=active]:bg-[#1BB827] data-[state=active]:text-white">
                    <User className="w-4 h-4 mr-2" />
                    Estudiante
                  </TabsTrigger>
                  <TabsTrigger value="personal" className="text-sm sm:text-base py-3 rounded-lg data-[state=active]:bg-[#1BB827] data-[state=active]:text-white">
                    <UserCog className="w-4 h-4 mr-2" />
                    Maestro/Laboratorista
                  </TabsTrigger>
                </TabsList>
                <TabsContent value="alumno" className="space-y-6">
                  <FormularioDeEntrada laboratorio={laboratorio.clave} clases={clases ?? []} equipos={equipos ?? []} oscuro={oscuro} c={c} alCambiarEquipos={recargarEquipos} />
                </TabsContent>
                <TabsContent value="personal" className="space-y-6">
                  <InicioDeSesionDelPersonal c={c} />
                </TabsContent>
              </Tabs>
            </CardContent>

            <CardFooter className={`p-4 ${oscuro ? "bg-[#153731]" : "bg-[#f0fff4]"} text-center text-sm`}>
              <p className={`w-full ${oscuro ? "text-gray-300" : "text-[#1C4A3F]/70"}`}>Instituto Tecnológico Superior de Puerto Peñasco © {new Date().getFullYear()}</p>
            </CardFooter>
          </div>
        </div>
      </Card>

      {escaner && <EscanerDeCredencial laboratorio={laboratorio.clave} hayClase={hayClase} oscuro={oscuro} alCerrar={() => setEscaner(false)} />}
      <AccesoDelAdministrador abierto={accesoAdmin} alCambiar={setAccesoAdmin} oscuro={oscuro} c={c} />
    </div>
  )
}
