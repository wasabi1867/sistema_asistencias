import type { Cuenta, Laboratorio } from "../../lib/api"
import type { Colores } from "../../lib/tema"

/** Lo que recibe cada vista del panel del laboratorio. */
export interface PropsDeVista {
  laboratorio: Laboratorio
  usuario: Cuenta
  oscuro: boolean
  c: Colores
}

/** El encabezado de color de las tarjetas del panel. */
export const encabezadoDeTarjeta = (oscuro: boolean) =>
  oscuro ? "bg-gradient-to-r from-[#1d5631] to-[#2a7a45] text-white rounded-t-lg" : "bg-gradient-to-r from-[#800040] to-[#a30050] text-white rounded-t-lg"
