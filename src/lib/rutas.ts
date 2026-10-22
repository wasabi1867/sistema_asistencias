import type { Rol } from "./api"

/** A dónde va cada rol después de iniciar sesión. */
export const PANEL_DE: Record<Rol, string> = { docente: "/docente", laboratorista: "/laboratorio", admin: "/admin" }

export const NOMBRE_DEL_ROL: Record<Rol, string> = { docente: "Docente", laboratorista: "Laboratorista", admin: "Administrador" }

// El laboratorio donde está esta computadora: se elige una vez en la página principal y
// lo usan el registro, la clase del docente y el panel del laboratorio.
const CLAVE = "laboratorio"

export function laboratorioDeEstaComputadora(): string {
  try {
    return localStorage.getItem(CLAVE) ?? ""
  } catch {
    return ""
  }
}

export function recordarLaboratorio(clave: string) {
  try {
    if (clave) {
      localStorage.setItem(CLAVE, clave)
    } else {
      localStorage.removeItem(CLAVE)
    }
  } catch {
    // Sin almacenamiento (ventana privada): se vuelve a preguntar la próxima vez.
  }
}
