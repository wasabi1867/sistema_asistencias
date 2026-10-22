import { mensajeDeError } from "./api"

/**
 * Los avisos y confirmaciones de las pantallas, todos con el mismo aspecto. Cualquier
 * pantalla los pide con estas funciones, que devuelven una promesa; los muestra
 * components/comunes/Alertas.tsx, montado una sola vez en _app.
 *
 * Son diálogos de Radix, igual que los demás de la aplicación. Así, un aviso que sale
 * encima de un diálogo recibe el foco, Escape cierra solo el de arriba y la página no
 * se queda oculta para los lectores de pantalla al cerrarlos.
 */
export type TipoDeMensaje = "exito" | "info" | "aviso" | "error"

export type Alerta =
  | { tipo: TipoDeMensaje; titulo: string; texto?: string; responder: () => void }
  | { tipo: "confirmar"; titulo: string; texto: string; aceptar: string; peligro: boolean; responder: (acepto: boolean) => void }
  | { tipo: "texto"; titulo: string; etiqueta?: string; valor: string; obligatorio: boolean; largo: boolean; responder: (texto: string | null) => void }

/** Un aviso breve: no pide respuesta y se quita solo. */
export interface AvisoBreve {
  id: number
  titulo: string
  tipo: "exito" | "error" | "info"
}

interface Estado {
  /** Se muestran de una en una, en el orden en que se pidieron. */
  pendientes: (Alerta & { id: number })[]
  breves: AvisoBreve[]
}

const DURACION_DEL_AVISO_BREVE = 2200

let estado: Estado = { pendientes: [], breves: [] }
let siguienteId = 0
const oyentes = new Set<() => void>()

function cambiar(nuevo: Estado) {
  estado = nuevo
  oyentes.forEach((oyente) => oyente())
}

export function suscribirse(oyente: () => void) {
  oyentes.add(oyente)
  return () => {
    oyentes.delete(oyente)
  }
}

export const estadoDeLasAlertas = () => estado

function mostrar<R>(crear: (responder: (valor: R) => void) => Alerta): Promise<R> {
  return new Promise((resolver) => {
    const id = ++siguienteId
    let respondida = false
    const responder = (valor: R) => {
      if (respondida) return
      respondida = true
      cambiar({ ...estado, pendientes: estado.pendientes.filter((a) => a.id !== id) })
      resolver(valor)
    }
    cambiar({ ...estado, pendientes: [...estado.pendientes, { ...crear(responder), id }] })
  })
}

// Si se pide un aviso igual a uno que sigue en espera (por ejemplo, el mismo error de
// red desde dos lugares), se comparte: así no hay que cerrar el mismo mensaje dos veces.
const enEspera = new Map<string, Promise<void>>()

const aviso = (tipo: TipoDeMensaje) => (titulo: string, texto?: string) => {
  const clave = JSON.stringify([tipo, titulo, texto])
  let promesa = enEspera.get(clave)
  if (!promesa) {
    promesa = mostrar<void>((responder) => ({ tipo, titulo, texto, responder })).finally(() => enEspera.delete(clave))
    enEspera.set(clave, promesa)
  }
  return promesa
}

export const alertas = {
  exito: aviso("exito"),

  info: aviso("info"),

  aviso: aviso("aviso"),

  /** Un error, con el mensaje de la API si lo trae (vienen en español y se muestran tal cual). */
  error: (titulo: string, error?: unknown) => aviso("error")(titulo, error === undefined ? undefined : mensajeDeError(error)),

  /** Un aviso que se quita solo, para lo que no necesita respuesta. No detiene a quien lo pide. */
  breve: (titulo: string, icono: "success" | "error" | "info" = "success") => {
    const id = ++siguienteId
    const tipo = icono === "success" ? "exito" : icono
    cambiar({ ...estado, breves: [...estado.breves, { id, titulo, tipo }] })
    setTimeout(() => cambiar({ ...estado, breves: estado.breves.filter((b) => b.id !== id) }), DURACION_DEL_AVISO_BREVE)
    return Promise.resolve()
  },

  /** Pregunta antes de hacer algo. Devuelve true si la persona aceptó. */
  confirmar: (titulo: string, texto: string, opciones: { aceptar?: string; peligro?: boolean } = {}) =>
    mostrar<boolean>((responder) => ({ tipo: "confirmar", titulo, texto, aceptar: opciones.aceptar ?? "Sí", peligro: opciones.peligro ?? false, responder })),

  /** Pide un texto. Devuelve null si la persona canceló. */
  pedirTexto: (titulo: string, opciones: { etiqueta?: string; valor?: string; obligatorio?: boolean; largo?: boolean } = {}) =>
    mostrar<string | null>((responder) => ({
      tipo: "texto",
      titulo,
      etiqueta: opciones.etiqueta,
      valor: opciones.valor ?? "",
      obligatorio: opciones.obligatorio ?? false,
      largo: opciones.largo ?? false,
      responder: (texto) => responder(texto === null ? null : texto.trim()),
    })),
}
