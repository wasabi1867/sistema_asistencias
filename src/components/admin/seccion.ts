import type { Cuenta } from "../../lib/api"

export type IdDeSeccion = "tablero" | "alumnos" | "cursos" | "materias" | "cuentas" | "catalogos"

/** Lo que recibe cada sección del panel del administrador. */
export interface PropsDeSeccion {
  usuario: Cuenta
  /** Lleva a otra sección del panel, por ejemplo desde una cifra del tablero. */
  irA: (seccion: IdDeSeccion) => void
}
