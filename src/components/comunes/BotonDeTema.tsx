import { Moon, Sun } from "lucide-react"

/** El botón redondo de la esquina que cambia entre tema claro y oscuro. */
export function BotonDeTema({ oscuro, alternar, className = "fixed top-4 right-4 z-50" }: { oscuro: boolean; alternar: () => void; className?: string }) {
  return (
    <button
      type="button"
      onClick={alternar}
      title={oscuro ? "Tema claro" : "Tema oscuro"}
      className={`${className} p-3 rounded-full transition-all duration-300 text-white ${
        oscuro ? "bg-[#1C4A3F] hover:bg-[#153731]" : "bg-[#1BB827] hover:bg-[#18a423]"
      }`}
    >
      {oscuro ? <Sun size={20} /> : <Moon size={20} />}
    </button>
  )
}
