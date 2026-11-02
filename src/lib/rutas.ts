import type { Cuenta, Rol } from "./api"

/** El panel de cada rol. */
export const PANEL_DE: Record<Rol, string> = { docente: "/docente", laboratorista: "/laboratorio", admin: "/admin" }

export const NOMBRE_DEL_ROL: Record<Rol, string> = { docente: "Docente", laboratorista: "Laboratorista", admin: "Administrador" }

/**
 * Los paneles y quién puede usar cada uno, en el orden en que se prefieren al entrar
 * cuando la cuenta tiene varios roles y no se recuerda otro.
 */
const PANELES: { href: string; nombre: string; roles: Rol[] }[] = [
  { href: PANEL_DE.admin, nombre: "Panel del administrador", roles: ["admin"] },
  { href: PANEL_DE.laboratorista, nombre: "Panel del laboratorio", roles: ["laboratorista", "admin"] },
  { href: PANEL_DE.docente, nombre: "Panel del docente", roles: ["docente"] },
]

/** Los paneles a los que puede entrar la cuenta. */
export function panelesDe(cuenta: Cuenta) {
  return PANELES.filter((p) => p.roles.some((r) => cuenta.roles.includes(r)))
}

/** Los roles de la cuenta para mostrar: "Docente · Administrador". */
export function nombreDeLosRoles(roles: Rol[]) {
  return roles.map((r) => NOMBRE_DEL_ROL[r]).join(" · ")
}

// El último panel que se usó en esta computadora: quien tiene varios roles casi
// siempre vuelve al mismo.
const PANEL_GUARDADO = "panel"

/** A dónde va la cuenta al iniciar sesión: el último panel que usó aquí, si todavía puede. */
export function panelInicial(cuenta: Cuenta): string {
  const suyos = panelesDe(cuenta)
  let guardado: string | null = null
  try {
    guardado = localStorage.getItem(PANEL_GUARDADO)
  } catch {
    // Sin almacenamiento: el primero que le toque.
  }
  return suyos.find((p) => p.href === guardado)?.href ?? suyos[0]?.href ?? "/"
}

/** Anota el panel de la página actual, si es uno. */
export function recordarPanelActual() {
  const actual = PANELES.find((p) => window.location.pathname.replace(/\/$/, "") === p.href)
  if (!actual) return
  try {
    localStorage.setItem(PANEL_GUARDADO, actual.href)
  } catch {
    // No pasa nada si no se recuerda.
  }
}

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
