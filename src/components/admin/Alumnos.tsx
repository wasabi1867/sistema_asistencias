import { useState } from "react"
import { Edit, Eye, FileSpreadsheet, Plus, RefreshCw, Search, Trash2, UserCheck, UserX } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { api, type Alumno, type Turno } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { turno as textoDeTurno } from "../../lib/formato"
import { useDatos } from "../../hooks/useDatos"
import { Paginador } from "../comunes/Paginador"
import { FormularioDeAlumno } from "./alumnos/FormularioDeAlumno"
import { CargaDesdeArchivo } from "./alumnos/CargaDesdeArchivo"
import { CambioMasivo } from "./alumnos/CambioMasivo"
import { DetalleDeAlumno } from "./alumnos/DetalleDeAlumno"
import type { PropsDeSeccion } from "./seccion"

const POR_PAGINA = 50
const TODOS = "todos"

/** Los alumnos: búsqueda, alta, edición, carga desde archivo y cambio masivo. */
export function Alumnos({ usuario, c }: PropsDeSeccion) {
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

  return (
    <div className="space-y-4">
      <Card className={`${c.tarjeta} border-none shadow-lg`}>
        <CardContent className="pt-6 space-y-3">
          <div className="flex flex-wrap gap-2">
            <form
              className="flex gap-2 flex-1 min-w-[16rem]"
              onSubmit={(e) => {
                e.preventDefault()
                filtrar(() => setBusqueda(texto.trim()))
              }}
            >
              <Input value={texto} onChange={(e) => setTexto(e.target.value)} placeholder="Buscar por número de control o nombre" aria-label="Buscar por número de control o nombre" />
              <Button type="submit" variant="outline" aria-label="Buscar" title="Buscar">
                <Search className="w-4 h-4" />
              </Button>
            </form>
            <Button type="button" onClick={() => setEditando("nuevo")} className={c.botonSecundario}>
              <Plus className="w-4 h-4 mr-2" /> Nuevo alumno
            </Button>
            <Button type="button" variant="outline" onClick={() => setDialogo("carga")}>
              <FileSpreadsheet className="w-4 h-4 mr-2" /> Cargar desde Excel
            </Button>
            <Button type="button" variant="outline" onClick={() => setDialogo("masivo")}>
              <RefreshCw className="w-4 h-4 mr-2" /> Cambio masivo
            </Button>
          </div>
          <div className="flex flex-wrap gap-2">
            <Select value={carrera} onValueChange={(v) => filtrar(() => setCarrera(v))}>
              <SelectTrigger className="w-64">
                <SelectValue />
              </SelectTrigger>
              <SelectContent className={c.menu}>
                <SelectItem value={TODOS}>Todas las carreras</SelectItem>
                {(carreras ?? []).map((carr) => (
                  <SelectItem key={carr.clave} value={carr.clave}>
                    {carr.nombre}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Select value={semestre} onValueChange={(v) => filtrar(() => setSemestre(v))}>
              <SelectTrigger className="w-40">
                <SelectValue />
              </SelectTrigger>
              <SelectContent className={c.menu}>
                <SelectItem value={TODOS}>Todos los semestres</SelectItem>
                {Array.from({ length: 12 }, (_, i) => i + 1).map((s) => (
                  <SelectItem key={s} value={String(s)}>
                    {s}° semestre
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Select value={turno} onValueChange={(v) => filtrar(() => setTurno(v))}>
              <SelectTrigger className="w-40">
                <SelectValue />
              </SelectTrigger>
              <SelectContent className={c.menu}>
                <SelectItem value={TODOS}>Todos los turnos</SelectItem>
                <SelectItem value="matutino">Matutino</SelectItem>
                <SelectItem value="vespertino">Vespertino</SelectItem>
              </SelectContent>
            </Select>
            <Button type="button" variant={inactivos ? "default" : "outline"} onClick={() => filtrar(() => setInactivos(!inactivos))}>
              {inactivos ? "Viendo desactivados" : "Ver desactivados"}
            </Button>
          </div>
        </CardContent>
      </Card>

      <p className={`text-sm ${c.textoSuave}`}>{respuesta ? `${respuesta.total} alumnos` : "Cargando..."}</p>

      <Card className={`${c.tarjeta} border-none shadow-lg`}>
        <CardContent className="p-0 overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Número de control</TableHead>
                <TableHead>Nombre</TableHead>
                <TableHead>Carrera</TableHead>
                <TableHead>Semestre</TableHead>
                <TableHead>Grupo</TableHead>
                <TableHead>Turno</TableHead>
                <TableHead />
              </TableRow>
            </TableHeader>
            <TableBody>
              {(respuesta?.alumnos ?? []).map((alumno) => (
                <TableRow key={alumno.id}>
                  <TableCell className="font-mono">{alumno.matricula}</TableCell>
                  <TableCell>
                    {alumno.apellido} {alumno.nombre}
                  </TableCell>
                  <TableCell>{alumno.carrera?.nombre ?? ""}</TableCell>
                  <TableCell>{alumno.semestre ? `${alumno.semestre}°` : ""}</TableCell>
                  <TableCell>{alumno.grupo}</TableCell>
                  <TableCell>{textoDeTurno(alumno.turno)}</TableCell>
                  <TableCell className="text-right whitespace-nowrap">
                    <Button type="button" size="sm" variant="ghost" onClick={() => setViendo(alumno)} title="Cursos">
                      <Eye className="w-4 h-4" />
                    </Button>
                    <Button type="button" size="sm" variant="ghost" onClick={() => setEditando(alumno)} title="Editar">
                      <Edit className="w-4 h-4" />
                    </Button>
                    <Button type="button" size="sm" variant="ghost" onClick={() => cambiarActivo(alumno)} title={alumno.activo ? "Desactivar" : "Activar"}>
                      {alumno.activo ? <UserX className="w-4 h-4" /> : <UserCheck className="w-4 h-4" />}
                    </Button>
                    {usuario.rol === "admin" && (
                      <Button type="button" size="sm" variant="ghost" onClick={() => eliminar(alumno)} title="Eliminar" className="text-red-500">
                        <Trash2 className="w-4 h-4" />
                      </Button>
                    )}
                  </TableCell>
                </TableRow>
              ))}
              {respuesta?.alumnos.length === 0 && (
                <TableRow>
                  <TableCell colSpan={7} className="text-center py-8 opacity-60">
                    No hay alumnos con esos filtros
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      {respuesta && <Paginador pagina={pagina} total={respuesta.total} porPagina={POR_PAGINA} alCambiar={setPagina} />}

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
      {viendo && <DetalleDeAlumno alumno={viendo} alCerrar={() => setViendo(null)} />}
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
