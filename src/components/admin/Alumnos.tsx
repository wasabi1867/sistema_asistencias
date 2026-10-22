import { useState } from "react"
import { Eye, FileSpreadsheet, GraduationCap, Pencil, Plus, RefreshCw, Trash2, UserCheck, UserX } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { api, type Alumno, type Turno } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { turno as textoDeTurno } from "../../lib/formato"
import { useDatos } from "../../hooks/useDatos"
import { Paginador } from "../comunes/Paginador"
import { CampoDeBusqueda, Listado, SinDatos } from "../comunes/Bloques"
import { MenuDeAcciones } from "../comunes/MenuDeAcciones"
import { FormularioDeAlumno } from "./alumnos/FormularioDeAlumno"
import { CargaDesdeArchivo } from "./alumnos/CargaDesdeArchivo"
import { CambioMasivo } from "./alumnos/CambioMasivo"
import { DetalleDeAlumno } from "./alumnos/DetalleDeAlumno"
import type { PropsDeSeccion } from "./seccion"

const POR_PAGINA = 50
const TODOS = "todos"

/** Los alumnos: búsqueda, alta, edición, carga desde archivo y cambio masivo. */
export function Alumnos({ usuario }: PropsDeSeccion) {
  const [texto, setTexto] = useState("")
  const [busqueda, setBusqueda] = useState("")
  const [carrera, setCarrera] = useState(TODOS)
  const [semestre, setSemestre] = useState(TODOS)
  const [turno, setTurno] = useState(TODOS)
  const [inactivos, setInactivos] = useState(false)
  const [pagina, setPagina] = useState(1)
  const [editando, setEditando] = useState<Alumno | null | "nuevo">(null)
  const [viendo, setViendo] = useState<Alumno | null>(null)
  const [dialogo, setDialogo] = useState<"carga" | "masivo" | null>(null)

  const { datos: carreras } = useDatos(() => api.carreras.listar(), [])
  const { datos: respuesta, recargar } = useDatos(
    () =>
      api.alumnos.buscar({
        q: busqueda || undefined,
        carrera: carrera === TODOS ? undefined : carrera,
        semestre: semestre === TODOS ? undefined : Number(semestre),
        turno: turno === TODOS ? undefined : (turno as Turno),
        activo: !inactivos,
        pagina,
        porPagina: POR_PAGINA,
      }),
    [busqueda, carrera, semestre, turno, inactivos, pagina],
    "No se pudieron cargar los alumnos",
  )

  const filtrar = (cambio: () => void) => {
    cambio()
    setPagina(1)
  }

  const cambiarActivo = async (alumno: Alumno) => {
    try {
      await api.alumnos.editar(alumno.matricula, { activo: !alumno.activo })
      alertas.breve(alumno.activo ? "Alumno desactivado" : "Alumno activado")
      await recargar()
    } catch (error) {
      await alertas.error("No se pudo cambiar el alumno", error)
    }
  }

  const eliminar = async (alumno: Alumno) => {
    if (!(await alertas.confirmar(`¿Eliminar a ${alumno.nombre} ${alumno.apellido}?`, "Solo se puede si nunca ha registrado una entrada; si tiene historial, desactívalo.", { aceptar: "Eliminar", peligro: true }))) return
    try {
      await api.alumnos.eliminar(alumno.matricula)
      await recargar()
    } catch (error) {
      await alertas.error("No se pudo eliminar", error)
    }
  }

  const alumnos = respuesta?.alumnos ?? []

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => setDialogo("carga")}>
          <FileSpreadsheet /> Cargar desde Excel
        </Button>
        <Button type="button" variant="outline" onClick={() => setDialogo("masivo")}>
          <RefreshCw /> Cambio masivo
        </Button>
        <Button type="button" onClick={() => setEditando("nuevo")}>
          <Plus /> Nuevo alumno
        </Button>
      </div>

      <Listado
        filtros={
          <>
            <form
              className="flex w-full gap-2"
              onSubmit={(e) => {
                e.preventDefault()
                filtrar(() => setBusqueda(texto.trim()))
              }}
            >
              <CampoDeBusqueda etiqueta="Buscar por número de control o nombre" value={texto} onChange={(e) => setTexto(e.target.value)} className="flex-1" />
              <Button type="submit" variant="secondary">
                Buscar
              </Button>
            </form>
            <Select value={carrera} onValueChange={(v) => filtrar(() => setCarrera(v))}>
              <SelectTrigger className="w-full sm:w-56" aria-label="Carrera">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={TODOS}>Todas las carreras</SelectItem>
                {(carreras ?? []).map((carr) => (
                  <SelectItem key={carr.clave} value={carr.clave}>
                    {carr.nombre}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Select value={semestre} onValueChange={(v) => filtrar(() => setSemestre(v))}>
              <SelectTrigger className="w-[calc(50%-0.25rem)] sm:w-48" aria-label="Semestre">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={TODOS}>Todos los semestres</SelectItem>
                {Array.from({ length: 12 }, (_, i) => i + 1).map((s) => (
                  <SelectItem key={s} value={String(s)}>
                    {s}° semestre
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Select value={turno} onValueChange={(v) => filtrar(() => setTurno(v))}>
              <SelectTrigger className="w-[calc(50%-0.25rem)] sm:w-44" aria-label="Turno">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={TODOS}>Todos los turnos</SelectItem>
                <SelectItem value="matutino">Matutino</SelectItem>
                <SelectItem value="vespertino">Vespertino</SelectItem>
              </SelectContent>
            </Select>
            <Select value={inactivos ? "desactivados" : "activos"} onValueChange={(v) => filtrar(() => setInactivos(v === "desactivados"))}>
              <SelectTrigger className="w-full sm:w-40" aria-label="Estado">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="activos">Activos</SelectItem>
                <SelectItem value="desactivados">Desactivados</SelectItem>
              </SelectContent>
            </Select>
          </>
        }
        pie={
          <>
            <span className="tabular-nums">{respuesta ? `${respuesta.total} ${respuesta.total === 1 ? "alumno" : "alumnos"}${inactivos ? " desactivados" : ""}` : "Cargando…"}</span>
            {respuesta && <Paginador pagina={pagina} total={respuesta.total} porPagina={POR_PAGINA} alCambiar={setPagina} />}
          </>
        }
      >
        {respuesta && alumnos.length === 0 ? (
          <SinDatos icono={GraduationCap} titulo="No hay alumnos con esos filtros">
            {busqueda ? "Revisa lo que buscaste o quita algún filtro." : "Da de alta uno o cárgalos desde un archivo de Excel."}
          </SinDatos>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="hidden sm:table-cell">Número de control</TableHead>
                <TableHead>Nombre</TableHead>
                <TableHead className="hidden lg:table-cell">Carrera</TableHead>
                <TableHead>Semestre</TableHead>
                <TableHead className="hidden sm:table-cell">Turno</TableHead>
                <TableHead className="w-12">
                  <span className="sr-only">Acciones</span>
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {alumnos.map((alumno) => (
                <TableRow key={alumno.id} className="cursor-pointer" onClick={() => setViendo(alumno)}>
                  <TableCell className="hidden font-mono text-[13px] tabular-nums sm:table-cell">{alumno.matricula}</TableCell>
                  <TableCell>
                    <span className="font-medium">
                      {alumno.apellido} {alumno.nombre}
                    </span>
                    {!alumno.activo && (
                      <Badge variant="neutral" className="ml-2">
                        Desactivado
                      </Badge>
                    )}
                    {/* En pantallas angostas la matrícula y la carrera van bajo el nombre, para que quepa el menú del renglón. */}
                    <span className="block text-xs text-muted-foreground lg:hidden">
                      <span className="font-mono tabular-nums sm:hidden">{alumno.matricula}</span>
                      {alumno.carrera && (
                        <>
                          <span className="sm:hidden"> · </span>
                          <span className="sm:hidden">{alumno.carrera.clave}</span>
                          <span className="hidden sm:inline">{alumno.carrera.nombre}</span>
                        </>
                      )}
                    </span>
                  </TableCell>
                  <TableCell className="hidden max-w-[16rem] truncate text-muted-foreground lg:table-cell" title={alumno.carrera?.nombre}>
                    {alumno.carrera?.nombre ?? ""}
                  </TableCell>
                  <TableCell className="whitespace-nowrap">{alumno.semestre ? `${alumno.semestre}° ${alumno.grupo ?? ""}` : (alumno.grupo ?? "")}</TableCell>
                  <TableCell className="hidden text-muted-foreground sm:table-cell">{textoDeTurno(alumno.turno)}</TableCell>
                  <TableCell>
                    <MenuDeAcciones
                      etiqueta={`Acciones para ${alumno.nombre} ${alumno.apellido}`}
                      acciones={[
                        { texto: "Ver sus cursos", icono: Eye, alElegir: () => setViendo(alumno) },
                        { texto: "Editar", icono: Pencil, alElegir: () => setEditando(alumno) },
                        { texto: alumno.activo ? "Desactivar" : "Activar", icono: alumno.activo ? UserX : UserCheck, alElegir: () => cambiarActivo(alumno) },
                        { texto: "Eliminar", icono: Trash2, peligro: true, oculta: usuario.rol !== "admin", alElegir: () => eliminar(alumno) },
                      ]}
                    />
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </Listado>

      {editando && (
        <FormularioDeAlumno
          alumno={editando === "nuevo" ? null : editando}
          carreras={carreras ?? []}
          alCerrar={() => setEditando(null)}
          alGuardar={() => {
            setEditando(null)
            recargar()
          }}
        />
      )}
      {viendo && (
        <DetalleDeAlumno
          alumno={viendo}
          alCerrar={() => setViendo(null)}
          alEditar={() => {
            setEditando(viendo)
            setViendo(null)
          }}
        />
      )}
      {dialogo === "carga" && (
        <CargaDesdeArchivo
          alCerrar={() => setDialogo(null)}
          alCargar={() => {
            setDialogo(null)
            recargar()
          }}
        />
      )}
      {dialogo === "masivo" && (
        <CambioMasivo
          carreras={carreras ?? []}
          alCerrar={() => setDialogo(null)}
          alCambiar={() => {
            setDialogo(null)
            recargar()
          }}
        />
      )}
    </div>
  )
}
