import { useState } from "react"
import { FileSpreadsheet, Upload } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { api, ErrorDeApi, type DatosDeAlumno } from "../../../lib/api"
import { alertas } from "../../../lib/alertas"
import { leerHojaDeCalculo } from "../../../lib/exportar"
import { alumnoSinNombre, COLUMNAS_QUE_FALTAN, leerListaDeAlumnos } from "../../../lib/listasDeAlumnos"
import { RevisionDeNombres } from "./RevisionDeNombres"

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
  const [dudosos, setDudosos] = useState<boolean[]>([])

  const corregir = (i: number, campo: "nombre" | "apellido", valor: string) =>
    setAlumnos((lista) => lista && lista.map((a, j) => (j === i ? { ...a, [campo]: valor } : a)))

  const leer = async (file: File | undefined) => {
    if (!file) return
    setErrores([])
    try {
      const lista = leerListaDeAlumnos(await leerHojaDeCalculo(file))
      if (!lista) {
        await alertas.aviso("Faltan columnas", COLUMNAS_QUE_FALTAN)
        return
      }
      setRenglonDe(lista.renglones)
      setNombresSeparados(lista.nombresSeparados)
      setDudosos(lista.dudosos)
      setAlumnos(lista.alumnos)
      setArchivo(file.name)
    } catch (error) {
      await alertas.error("No se pudo leer el archivo", error)
    }
  }

  const cargar = async () => {
    if (!alumnos?.length) return
    const sinNombre = alumnoSinNombre(alumnos)
    if (sinNombre) {
      await alertas.aviso("Falta un nombre", `El alumno ${sinNombre.matricula} quedó sin nombre. Escríbelo en la lista antes de cargar.`)
      return
    }
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
        <SelectorDeArchivo archivo={archivo} alElegir={leer} />
        {alumnos && (
          <p className="text-sm">
            <span className="font-medium">{alumnos.length} alumnos en el archivo.</span> <span className="text-muted-foreground">Por ejemplo: {alumnos.slice(0, 3).map((a) => `${a.matricula} ${a.nombre} ${a.apellido}`).join("; ")}</span>
          </p>
        )}
        {alumnos && nombresSeparados && <RevisionDeNombres alumnos={alumnos} dudosos={dudosos} alCorregir={corregir} />}
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

/** El recuadro para elegir un Excel o CSV. */
export function SelectorDeArchivo({ archivo, alElegir, compacto = false }: { archivo: string; alElegir: (archivo: File | undefined) => void; compacto?: boolean }) {
  return (
    <label
      className={`flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed text-center transition-colors focus-within:border-ring hover:border-primary/50 hover:bg-accent/40 ${compacto ? "p-4" : "p-8"}`}
    >
      <span className="flex h-11 w-11 items-center justify-center rounded-full bg-accent text-accent-foreground">
        <FileSpreadsheet className="h-5 w-5" aria-hidden />
      </span>
      <span className="font-medium">{archivo || "Elegir archivo"}</span>
      <span className="text-xs text-muted-foreground">.xlsx, .xls o .csv</span>
      <input
        type="file"
        accept=".xlsx,.xls,.csv"
        className="sr-only"
        onChange={(e) => {
          alElegir(e.target.files?.[0])
          // Así se puede volver a elegir el mismo archivo después de corregirlo.
          e.target.value = ""
        }}
      />
    </label>
  )
}
