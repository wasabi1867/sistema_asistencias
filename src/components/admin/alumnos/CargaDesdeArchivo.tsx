import { useState } from "react"
import { FileSpreadsheet, Upload } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { api, ErrorDeApi, type DatosDeAlumno, type Turno } from "../../../lib/api"
import { alertas } from "../../../lib/alertas"
import { leerHojaDeCalculo } from "../../../lib/exportar"

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

/** Columna con el nombre completo, como en las listas de grupo del instituto. */
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
 * si son dos apellidos o dos nombres: se suponen dos apellidos, que es lo común.
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

/**
 * Carga de alumnos desde Excel o CSV: crea a los nuevos y actualiza a los que ya
 * estaban. Si un renglón tiene error no se guarda ninguno, y se dice cuál falló.
 */
export function CargaDesdeArchivo({ alCerrar, alCargar }: { alCerrar: () => void; alCargar: () => void }) {
  const [alumnos, setAlumnos] = useState<DatosDeAlumno[] | null>(null)
  // El renglón del archivo de cada alumno, para señalar dónde está un error.
  const [renglonDe, setRenglonDe] = useState<number[]>([])
  const [archivo, setArchivo] = useState("")
  const [errores, setErrores] = useState<{ renglon: number; error: string }[]>([])
  const [enviando, setEnviando] = useState(false)
  // Los nombres venían en una sola columna y el sistema los separó: hay que revisarlos.
  const [nombresSeparados, setNombresSeparados] = useState(false)

  const leer = async (file: File | undefined) => {
    if (!file) return
    setErrores([])
    try {
      const filas = await leerHojaDeCalculo(file)
      // Las listas del instituto traen arriba la materia, el periodo y el grupo: el
      // encabezado es el primer renglón que tiene la columna de la matrícula.
      const inicio = filas.findIndex((r) => r.some((col) => COLUMNAS.matricula.includes(normalizar(col))))
      const [encabezado = [], ...renglones] = filas.slice(Math.max(inicio, 0))
      const indices = Object.fromEntries(
        Object.entries(COLUMNAS).map(([campo, nombres]) => [campo, encabezado.findIndex((col) => nombres.includes(normalizar(col)))]),
      ) as Record<keyof DatosDeAlumno, number>
      const separar = indices.nombre < 0 || indices.apellido < 0
      const nombreCompleto = encabezado.findIndex((col) => NOMBRE_COMPLETO.includes(normalizar(col)))
      if (inicio < 0 || (separar && nombreCompleto < 0)) {
        await alertas.aviso(
          "Faltan columnas",
          "El archivo necesita la columna Matrícula (o Número de control) y las de Nombre y Apellidos, o una sola con el nombre completo (Estudiante o Alumno).",
        )
        return
      }
      const celda = (r: string[], campo: keyof DatosDeAlumno) => (indices[campo] >= 0 ? r[indices[campo]] || null : null)
      // +2: el renglón del encabezado más uno, contando desde 1.
      const conMatricula = renglones.map((r, i) => ({ r, renglon: inicio + i + 2 })).filter(({ r }) => celda(r, "matricula"))
      setRenglonDe(conMatricula.map(({ renglon }) => renglon))
      setNombresSeparados(separar)
      setAlumnos(
        conMatricula
          .map(({ r }) => r)
          .map((r) => ({
            matricula: String(celda(r, "matricula")).toUpperCase(),
            ...(separar ? separarNombreCompleto(r[nombreCompleto] ?? "") : { nombre: celda(r, "nombre") ?? "", apellido: celda(r, "apellido") ?? "" }),
            carrera: celda(r, "carrera"),
            semestre: celda(r, "semestre") ? Number(String(celda(r, "semestre")).replace(/\D/g, "")) || null : null,
            turno: (celda(r, "turno")?.toLowerCase() as Turno | undefined) ?? null,
            grupo: celda(r, "grupo"),
          })),
      )
      setArchivo(file.name)
    } catch (error) {
      await alertas.error("No se pudo leer el archivo", error)
    }
  }

  const cargar = async () => {
    if (!alumnos?.length) return
    setEnviando(true)
    try {
      const r = await api.alumnos.cargarLote(alumnos)
      await alertas.exito("Alumnos cargados", `${r.creados} nuevos y ${r.actualizados} actualizados.`)
      alCargar()
    } catch (error) {
      // El archivo tiene renglones con errores: se muestran para corregirlos.
      if (error instanceof ErrorDeApi && Array.isArray(error.datos.renglones)) {
        setErrores(error.datos.renglones.map((r: { renglon: number; error: string }) => ({ renglon: renglonDe[r.renglon - 1] ?? r.renglon, error: r.error })))
      } else {
        await alertas.error("No se pudieron cargar los alumnos", error)
      }
    } finally {
      setEnviando(false)
    }
  }

  return (
    <Dialog open onOpenChange={(abierto) => !abierto && alCerrar()}>
      <DialogContent className="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>Cargar alumnos desde un archivo</DialogTitle>
          <DialogDescription>
            Excel o CSV con un renglón de encabezados: Matrícula, Nombre, Apellidos y, si se tienen, Carrera, Semestre, Turno y Grupo. También sirve una lista de grupo con
            Matrícula y Estudiante. Los que ya existen se actualizan; lo que el archivo deja vacío no borra lo que ya se sabía.
          </DialogDescription>
        </DialogHeader>
        <label className="flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed p-8 text-center transition-colors focus-within:border-ring hover:border-primary/50 hover:bg-accent/40">
          <span className="flex h-11 w-11 items-center justify-center rounded-full bg-accent text-accent-foreground">
            <FileSpreadsheet className="h-5 w-5" aria-hidden />
          </span>
          <span className="font-medium">{archivo || "Elegir archivo"}</span>
          <span className="text-xs text-muted-foreground">.xlsx, .xls o .csv</span>
          <input type="file" accept=".xlsx,.xls,.csv" className="sr-only" onChange={(e) => leer(e.target.files?.[0])} />
        </label>
        {alumnos && (
          <p className="text-sm">
            <span className="font-medium">{alumnos.length} alumnos en el archivo.</span> <span className="text-muted-foreground">Por ejemplo: {alumnos.slice(0, 3).map((a) => `${a.matricula} ${a.nombre} ${a.apellido}`).join("; ")}</span>
          </p>
        )}
        {alumnos && nombresSeparados && (
          <div className="max-h-48 overflow-y-auto rounded-lg border p-3 text-sm">
            <p className="mb-1 font-medium">El nombre venía completo en una columna. Se separó así; si alguno quedó mal, corrígelo después en el alumno:</p>
            <table className="w-full">
              <thead className="text-left text-muted-foreground">
                <tr>
                  <th className="font-normal">Matrícula</th>
                  <th className="font-normal">Apellidos</th>
                  <th className="font-normal">Nombre</th>
                </tr>
              </thead>
              <tbody>
                {alumnos.map((a) => (
                  <tr key={a.matricula}>
                    <td>{a.matricula}</td>
                    <td>{a.apellido}</td>
                    <td>{a.nombre}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {errores.length > 0 && (
          <div className="max-h-48 space-y-1 overflow-y-auto rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
            <p className="font-semibold">No se guardó ninguno. Corrige estos renglones y vuelve a cargar el archivo:</p>
            {errores.map((e) => (
              <p key={e.renglon}>
                Renglón {e.renglon}: {e.error}
              </p>
            ))}
          </div>
        )}
        <DialogFooter>
          <Button type="button" variant="outline" onClick={alCerrar}>
            Cancelar
          </Button>
          <Button type="button" onClick={cargar} disabled={!alumnos?.length || enviando}>
            <Upload /> {enviando ? "Cargando…" : "Cargar alumnos"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
