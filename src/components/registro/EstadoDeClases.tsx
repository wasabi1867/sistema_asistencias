import type { ClaseAbierta } from "../../lib/api"
import { hora } from "../../lib/formato"

/** Qué clase está abierta en el laboratorio, para que el alumno sepa a dónde entra. */
export function EstadoDeClases({ clases, oscuro }: { clases: ClaseAbierta[]; oscuro: boolean }) {
  return (
    <div className={`p-3 rounded-xl ${oscuro ? "bg-[#153731] text-white" : "bg-[#e6ffe9] text-[#1C4A3F]"}`}>
      {clases.length > 0 ? (
        <>
          <div className="flex items-center justify-center mb-2">
            <div className={`w-3 h-3 rounded-full animate-pulse mr-2 ${oscuro ? "bg-green-400" : "bg-green-500"}`} />
            <p className="font-medium text-center">{clases.length > 1 ? "Hay dos clases activas en este momento" : "¡Clase activa en este momento!"}</p>
          </div>
          {clases.map((clase) => (
            <div key={clase.id} className="mt-3 p-3 rounded-lg bg-white/20">
              {clase.tipo === "invitado" && <p className="text-xs font-semibold uppercase tracking-wide mb-1">Clase de maestro invitado</p>}
              <div className="grid grid-cols-[auto_1fr] gap-x-2 gap-y-1">
                <span className="font-semibold">Maestro:</span>
                <span>{clase.docente ?? "—"}</span>
                <span className="font-semibold">Materia:</span>
                <span>
                  {clase.materia}
                  {clase.grupo ? ` (${clase.grupo})` : ""}
                </span>
                {clase.practica && (
                  <>
                    <span className="font-semibold">Práctica:</span>
                    <span>{clase.practica}</span>
                  </>
                )}
                <span className="font-semibold">Inicio:</span>
                <span>{hora(clase.abiertaEn)}</span>
              </div>
            </div>
          ))}
        </>
      ) : (
        <div className="flex items-center justify-center">
          <div className={`w-3 h-3 rounded-full mr-2 ${oscuro ? "bg-red-400" : "bg-red-500"}`} />
          <p className="font-medium text-center">No hay clases iniciadas en este momento</p>
        </div>
      )}
    </div>
  )
}
