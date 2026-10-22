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

const normalizar = (texto: string) =>
  texto
    .toLowerCase()
    .normalize("NFD")
    .replace(/[̀-ͯ]/g, "")
    .trim()

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

  const leer = async (file: File | undefined) => {
    if (!file) return
    setErrores([])
    try {
      const [encabezado, ...renglones] = await leerHojaDeCalculo(file)
      const indices = Object.fromEntries(
        Object.entries(COLUMNAS).map(([campo, nombres]) => [campo, encabezado.findIndex((col) => nombres.includes(normalizar(col)))]),
      ) as Record<keyof DatosDeAlumno, number>
      if (indices.matricula < 0 || indices.nombre < 0 || indices.apellido < 0) {
        await alertas.aviso("Faltan columnas", "El archivo necesita al menos las columnas Matrícula (o Número de control), Nombre y Apellidos, en el primer renglón.")
        return
      }
      const celda = (r: string[], campo: keyof DatosDeAlumno) => (indices[campo] >= 0 ? r[indices[campo]] || null : null)
      // +2: el renglón 1 es el encabezado y los renglones se cuentan desde 1.
      const conMatricula = renglones.map((r, i) => ({ r, renglon: i + 2 })).filter(({ r }) => celda(r, "matricula"))
      setRenglonDe(conMatricula.map(({ renglon }) => renglon))
      setAlumnos(
        conMatricula
          .map(({ r }) => r)
          .map((r) => ({
            matricula: String(celda(r, "matricula")).toUpperCase(),
            nombre: celda(r, "nombre") ?? "",
            apellido: celda(r, "apellido") ?? "",
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
            Excel o CSV con un renglón de encabezados: Matrícula, Nombre, Apellidos y, si se tienen, Carrera, Semestre, Turno y Grupo. Los que ya existen se actualizan; lo que el
            archivo deja vacío no borra lo que ya se sabía.
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
