/**
 * Cómo se muestran fechas, horas y nombres. La API manda las fechas con hora con su
 * desfase (2026-10-21T14:05:00-07:00), así que el navegador las interpreta bien.
 */

const zona = "America/Hermosillo"

/** "21/10/2026" */
export function fecha(valor: string | null | undefined): string {
  if (!valor) return ""
  // Una fecha sola ("2026-10-21") se toma como fecha y no como medianoche en UTC.
  const soloFecha = /^\d{4}-\d{2}-\d{2}$/.test(valor)
  const d = new Date(soloFecha ? `${valor}T12:00:00` : valor)
  return d.toLocaleDateString("es-MX", { day: "2-digit", month: "2-digit", year: "numeric", timeZone: soloFecha ? undefined : zona })
}

/** "2:05 p. m." */
export function hora(valor: string | null | undefined): string {
  if (!valor) return ""
  return new Date(valor).toLocaleTimeString("es-MX", { hour: "numeric", minute: "2-digit", timeZone: zona })
}

export function fechaHora(valor: string | null | undefined): string {
  return valor ? `${fecha(valor)} ${hora(valor)}` : ""
}

/** La fecha de hoy en Puerto Peñasco, como la piden los filtros de la API: "2026-10-21". */
export function hoy(): string {
  return new Date().toLocaleDateString("en-CA", { timeZone: zona })
}

/** El primer día del mes de una fecha "AAAA-MM-DD". */
export function inicioDeMes(dia: string = hoy()): string {
  return `${dia.slice(0, 7)}-01`
}

export function nombreCompleto(persona: { nombre: string; apellido?: string | null }): string {
  return `${persona.nombre} ${persona.apellido ?? ""}`.trim()
}

/** 120 → "2 h", 90 → "1 h 30 min", 45 → "45 min" */
export function duracion(minutos: number | null | undefined): string {
  if (!minutos) return ""
  const horas = Math.floor(minutos / 60)
  const resto = minutos % 60
  return [horas ? `${horas} h` : "", resto ? `${resto} min` : ""].filter(Boolean).join(" ")
}

export const DIAS = ["", "Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado"]

export function turno(valor: string | null | undefined): string {
  return valor ? valor.charAt(0).toUpperCase() + valor.slice(1) : ""
}

/** "5" → "Equipo 5"; "PC-5" se queda igual. */
export function nombreDeEquipo(etiqueta: string): string {
  return /^\d+$/.test(etiqueta) ? `Equipo ${etiqueta}` : etiqueta
}
