import { useEffect, useState } from "react"
import Image from "next/image"
import { AnimatePresence, motion } from "framer-motion"

export interface ImagenDelCarrusel {
  src: string
  alt: string
}

const variantes = {
  entra: (direccion: number) => ({ x: direccion > 0 ? "100%" : "-100%", opacity: 0 }),
  centro: { x: 0, opacity: 1 },
  sale: (direccion: number) => ({ x: direccion < 0 ? "100%" : "-100%", opacity: 0 }),
}

/** Las imágenes de la institución en la página principal; cambian solas cada pocos segundos. */
export function CarruselDeImagenes({ imagenes, intervalo = 5000 }: { imagenes: ImagenDelCarrusel[]; intervalo?: number }) {
  const [actual, setActual] = useState(0)
  const [direccion, setDireccion] = useState(0)

  useEffect(() => {
    const temporizador = setInterval(() => {
      setDireccion(1)
      setActual((i) => (i + 1) % imagenes.length)
    }, intervalo)
    return () => clearInterval(temporizador)
  }, [intervalo, imagenes.length])

  return (
    <div className="relative w-full h-full overflow-hidden bg-transparent">
      <AnimatePresence initial={false} custom={direccion} mode="wait">
        <motion.div
          key={actual}
          custom={direccion}
          variants={variantes}
          initial="entra"
          animate="centro"
          exit="sale"
          transition={{ x: { type: "spring", stiffness: 300, damping: 30 }, opacity: { duration: 0.2 } }}
          className="absolute inset-0 flex items-center justify-center"
        >
          <Image src={imagenes[actual].src} alt={imagenes[actual].alt} fill priority className="object-contain" />
        </motion.div>
      </AnimatePresence>

      <div className="absolute bottom-4 left-1/2 -translate-x-1/2 flex space-x-2 z-10">
        {imagenes.map((imagen, i) => (
          <button
            key={imagen.src}
            type="button"
            onClick={() => {
              setDireccion(i > actual ? 1 : -1)
              setActual(i)
            }}
            className={`w-2 h-2 rounded-full transition-all duration-300 ${i === actual ? "bg-white scale-125" : "bg-white/50"}`}
            aria-label={`Ver ${imagen.alt}`}
          />
        ))}
      </div>
    </div>
  )
}
