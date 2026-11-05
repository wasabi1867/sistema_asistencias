import Image from "next/image"
import { cn } from "@/lib/utils"

/**
 * El logotipo del ITSPP con el nombre del sistema. El logotipo va sobre blanco en los dos
 * temas, y a buen tamaño: el jefe de carrera pidió que se vieran más los logos del
 * instituto (4 de noviembre de 2026).
 */
export function Marca({ subtitulo = "ITSPP · Laboratorios", className, tamano = "h-14" }: { subtitulo?: string; className?: string; tamano?: string }) {
  return (
    <div className={cn("flex min-w-0 items-center gap-3", className)}>
      <Logo className={tamano} />
      <div className="min-w-0">
        <p className="text-sm font-semibold leading-tight">Control de asistencias</p>
        <p className="truncate text-xs text-muted-foreground">{subtitulo}</p>
      </div>
    </div>
  )
}

export function Logo({ className, conTecnm = false }: { className?: string; conTecnm?: boolean }) {
  return (
    <span className={cn("flex h-12 shrink-0 items-center gap-2 rounded-lg bg-white px-2 ring-1 ring-black/5", className)}>
      {conTecnm && <Image src="/logo-tecnm.png" alt="Tecnológico Nacional de México" width={164} height={160} className="h-[84%] w-auto" />}
      <Image src="/logo-itspp.png" alt="ITSPP" width={154} height={160} className="h-[84%] w-auto" priority />
    </span>
  )
}
