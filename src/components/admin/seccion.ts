import type { Cuenta } from "../../lib/api"
import type { Colores } from "../../lib/tema"

/** Lo que recibe cada sección del panel del administrador. */
export interface PropsDeSeccion {
  usuario: Cuenta
  oscuro: boolean
  c: Colores
}
