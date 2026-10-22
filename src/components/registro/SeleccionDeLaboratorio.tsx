import Image from "next/image"
import { motion } from "framer-motion"
import { Building2, ChevronRight, Monitor, Server, Wifi, type LucideIcon } from "lucide-react"
import type { Laboratorio } from "../../lib/api"

// Un ícono por laboratorio conocido; los que se agreguen después usan el de monitor.
const ICONOS: Record<string, LucideIcon> = { programacion: Monitor, redes: Wifi, laboratorio_a: Building2, laboratorio_c: Server }

/** La primera pantalla de la computadora del laboratorio: en cuál está. Se recuerda en el navegador. */
export function SeleccionDeLaboratorio({ laboratorios, oscuro, alElegir }: { laboratorios: Laboratorio[]; oscuro: boolean; alElegir: (clave: string) => void }) {
  return (
    <motion.div initial={{ opacity: 0, y: 30 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.6 }} className="w-full max-w-2xl">
      <div className="flex justify-center mb-6">
        <div className={`w-24 h-24 rounded-full flex items-center justify-center ${oscuro ? "bg-[#1d5631]/30" : "bg-[#800040]/10"}`}>
          <Image src="/FondoItspp.png" alt="Logo ITSPP" width={80} height={80} className="rounded-full object-cover" />
        </div>
      </div>
      <h1 className={`text-2xl sm:text-3xl font-bold text-center mb-2 ${oscuro ? "text-white" : "text-[#800040]"}`}>Sistema de Control de Asistencias</h1>
      <p className={`text-center mb-8 text-sm sm:text-base ${oscuro ? "text-gray-400" : "text-[#800040]/60"}`}>Selecciona el laboratorio donde te encuentras</p>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
        {laboratorios.map((laboratorio) => {
          const Icono = ICONOS[laboratorio.clave] ?? Monitor
          return (
            <motion.button
              key={laboratorio.clave}
              type="button"
              whileHover={{ scale: 1.03 }}
              whileTap={{ scale: 0.97 }}
              onClick={() => alElegir(laboratorio.clave)}
              className={`group relative overflow-hidden rounded-2xl p-5 sm:p-6 text-left transition-all duration-300 border-2 ${
                oscuro
                  ? "bg-[#1a2e25] border-[#1d5631]/40 hover:border-[#1d5631] hover:bg-[#1d3a2c]"
                  : "bg-white border-[#800040]/20 hover:border-[#800040] hover:shadow-lg hover:shadow-[#800040]/10"
              }`}
            >
              <div className={`w-12 h-12 rounded-xl flex items-center justify-center mb-3 transition-colors ${oscuro ? "bg-[#1d5631]/30 group-hover:bg-[#1d5631]/50" : "bg-[#800040]/10 group-hover:bg-[#800040]/20"}`}>
                <Icono className={`w-6 h-6 ${oscuro ? "text-[#2a7a45]" : "text-[#800040]"}`} />
              </div>
              <h2 className={`text-base sm:text-lg font-bold mb-1 ${oscuro ? "text-white" : "text-[#800040]"}`}>{laboratorio.nombre}</h2>
              {laboratorio.ubicacion && <p className={`text-xs ${oscuro ? "text-gray-400" : "text-[#74726f]"}`}>{laboratorio.ubicacion}</p>}
              <ChevronRight
                className={`absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 opacity-0 group-hover:opacity-100 transition-all group-hover:translate-x-1 ${oscuro ? "text-[#2a7a45]" : "text-[#800040]"}`}
              />
            </motion.button>
          )
        })}
      </div>

      <p className={`text-center mt-6 text-xs ${oscuro ? "text-gray-500" : "text-[#74726f]/60"}`}>Instituto Tecnológico Superior de Puerto Peñasco</p>
    </motion.div>
  )
}

export function iconoDeLaboratorio(clave: string): LucideIcon {
  return ICONOS[clave] ?? Monitor
}
