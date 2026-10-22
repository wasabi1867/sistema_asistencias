import type { Cuenta, Laboratorio } from "../../lib/api"

/** Lo que recibe cada vista del panel del laboratorio. */
export interface PropsDeVista {
  laboratorio: Laboratorio
  usuario: Cuenta
}
