import Image from "next/image"
import { cn } from "@/lib/utils"

/** El logotipo del ITSPP con el nombre del sistema. El logotipo va sobre blanco en los dos temas. */
export function Marca({ subtitulo = "ITSPP · Laboratorios", className }: { subtitulo?: string; className?: string }) {
  return (
    <div className={cn("flex min-w-0 items-center gap-3", className)}>
      <Logo />
      <div className="min-w-0">
        <p className="truncate text-sm font-semibold leading-tight">Control de asistencias</p>
        <p className="truncate text-xs text-muted-foreground">{subtitulo}</p>
      </div>
    </div>
  )
}

export function Logo({ className, conTecnm = false }: { className?: string; conTecnm?: boolean }) {
  return (
    <span className={cn("flex h-10 shrink-0 items-center gap-1.5 rounded-lg bg-white px-1.5 ring-1 ring-black/5", className)}>
      {conTecnm && <Image src="/logo-tecnm.png" alt="Tecnológico Nacional de México" width={164} height={160} className="h-[80%] w-auto" />}
      <Image src="/logo-itspp.png" alt="ITSPP" width={154} height={160} className="h-[80%] w-auto" priority />
    </span>
  )
}
