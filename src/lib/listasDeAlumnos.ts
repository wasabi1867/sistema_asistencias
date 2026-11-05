import type { DatosDeAlumno, Turno } from "./api"

/**
 * Columnas que se reconocen, sin importar mayúsculas ni acentos. Así sirve el archivo
 * del sistema escolar sin tener que acomodarlo.
 */
const COLUMNAS: Record<keyof DatosDeAlumno, string[]> = {
  matricula: ["matricula", "numero de control", "no. de control", "num control", "control"],
  nombre: ["nombre", "nombres"],
  apellido: ["apellido", "apellidos"],
  carrera: ["carrera", "programa"],
  semestre: ["semestre"],
  turno: ["turno"],
  grupo: ["grupo"],
  activo: [],
}

/** Columna con el nombre completo, como en las listas de grupo del instituto y las de Mindbox. */
const NOMBRE_COMPLETO = ["estudiante", "alumno", "nombre completo", "nombre del alumno", "nombre del estudiante"]

// Van pegadas al apellido que sigue: "Del Ángel", "De la Cruz".
const PARTICULAS = new Set(["de", "del", "la", "las", "los", "san", "santa", "mc", "mac", "van", "von"])

const normalizar = (texto: string) =>
  texto
    .toLowerCase()
    .normalize("NFD")
    .replace(/[̀-ͯ]/g, "")
    .trim()

/**
 * Separa un nombre escrito como en las listas del instituto, apellido paterno, materno y
 * nombres ("Del Ángel Contreras Jesús Alejandro"). Con tres palabras no hay forma de saber
 * si son dos apellidos o dos nombres: se suponen dos apellidos, que es lo común, y se
 * marca para revisarlo.
 */
function separarNombreCompleto(completo: string) {
  const palabras = completo.split(/\s+/).filter(Boolean)
  const apellidos: string[] = []
  let i = 0
  for (let n = 0; n < 2 && i < palabras.length - 1; n++) {
    while (i < palabras.length - 2 && PARTICULAS.has(normalizar(palabras[i]))) apellidos.push(palabras[i++])
    apellidos.push(palabras[i++])
  }
  return { apellido: apellidos.join(" "), nombre: palabras.slice(i).join(" ") }
}

/** Con tres palabras pueden ser dos apellidos y un nombre, o un apellido y dos nombres. */
const esDudoso = (completo: string) => completo.split(/\s+/).filter(Boolean).length === 3

export interface ListaDeAlumnos {
  alumnos: DatosDeAlumno[]
  /** El renglón del archivo de cada alumno, contando desde 1, para señalar dónde está un error. */
  renglones: number[]
  /** Los nombres venían en una sola columna y se separaron: hay que revisarlos. */
  nombresSeparados: boolean
  /** Por alumno, si su nombre pudo separarse de dos formas. */
  dudosos: boolean[]
}

export const COLUMNAS_QUE_FALTAN =
  "El archivo necesita la columna Matrícula (o Número de control) y las de Nombre y Apellidos, o una sola con el nombre completo (Estudiante o Alumno)."

/**
 * Lee los renglones de una hoja de cálculo con alumnos: la plantilla del sistema, una
 * lista de grupo o la de una materia bajada de Mindbox. Devuelve null si faltan columnas.
 */
export function leerListaDeAlumnos(filas: string[][]): ListaDeAlumnos | null {
  // Las listas del instituto traen arriba la materia, el periodo y el grupo: el
  // encabezado es el primer renglón que tiene la columna de la matrícula.
  const inicio = filas.findIndex((r) => r.some((col) => COLUMNAS.matricula.includes(normalizar(col))))
  if (inicio < 0) return null
  const [encabezado = [], ...renglones] = filas.slice(inicio)
  const indices = Object.fromEntries(
    Object.entries(COLUMNAS).map(([campo, nombres]) => [campo, encabezado.findIndex((col) => nombres.includes(normalizar(col)))]),
  ) as Record<keyof DatosDeAlumno, number>
  const separar = indices.nombre < 0 || indices.apellido < 0
  const nombreCompleto = encabezado.findIndex((col) => NOMBRE_COMPLETO.includes(normalizar(col)))
  if (separar && nombreCompleto < 0) return null

  const celda = (r: string[], campo: keyof DatosDeAlumno) => (indices[campo] >= 0 ? r[indices[campo]] || null : null)
  // +2: el renglón del encabezado más uno, contando desde 1.
  const conMatricula = renglones.map((r, i) => ({ r, renglon: inicio + i + 2 })).filter(({ r }) => celda(r, "matricula"))
  return {
    renglones: conMatricula.map(({ renglon }) => renglon),
    nombresSeparados: separar,
    dudosos: conMatricula.map(({ r }) => separar && esDudoso(r[nombreCompleto] ?? "")),
    alumnos: conMatricula.map(({ r }) => ({
      matricula: String(celda(r, "matricula")).toUpperCase(),
      ...(separar ? separarNombreCompleto(r[nombreCompleto] ?? "") : { nombre: celda(r, "nombre") ?? "", apellido: celda(r, "apellido") ?? "" }),
      carrera: celda(r, "carrera"),
      semestre: celda(r, "semestre") ? Number(String(celda(r, "semestre")).replace(/\D/g, "")) || null : null,
      turno: (celda(r, "turno")?.toLowerCase() as Turno | undefined) ?? null,
      grupo: celda(r, "grupo"),
    })),
  }
}

/** El primer alumno que quedó sin nombre, o null. */
export const alumnoSinNombre = (alumnos: DatosDeAlumno[]) => alumnos.find((a) => !a.nombre.trim()) ?? null
