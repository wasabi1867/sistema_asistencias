import { useState, type FormEvent } from "react"
import { KeyRound, Pencil, Plus, Trash2, UserCheck, UserCog, UserX } from "lucide-react"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { api, type Cuenta, type Rol } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { useDatos } from "../../hooks/useDatos"
import { Campo, Listado, SinDatos } from "../comunes/Bloques"
import { MenuDeAcciones } from "../comunes/MenuDeAcciones"
import type { PropsDeSeccion } from "./seccion"

const NOMBRES: Record<Rol, string> = { docente: "Docentes", laboratorista: "Laboratoristas", admin: "Administradores" }
const UNO: Record<Rol, string> = { docente: "docente", laboratorista: "laboratorista", admin: "administrador" }

/** Las cuentas del personal. Una cuenta con historial no se borra: se desactiva. */
export function Cuentas({ usuario }: PropsDeSeccion) {
  const [rol, setRol] = useState<Rol>("docente")
  const { datos: cuentas, recargar } = useDatos(() => api.usuarios.listar({ rol }), [rol])
  const [editando, setEditando] = useState<Cuenta | "nueva" | null>(null)

  const cambiarActiva = async (cuenta: Cuenta) => {
    try {
      await api.usuarios.editar(cuenta.id, { activo: !cuenta.activo })
      alertas.breve(cuenta.activo ? "Cuenta desactivada" : "Cuenta activada")
      await recargar()
    } catch (error) {
      await alertas.error("No se pudo cambiar la cuenta", error)
    }
  }

  const restablecer = async (cuenta: Cuenta) => {
    const contrasena = await alertas.pedirTexto(`Nueva contraseña de ${cuenta.nombre} ${cuenta.apellido}`, { etiqueta: "Al menos 8 caracteres. Díselo en persona.", obligatorio: true })
    if (!contrasena) return
    try {
      await api.usuarios.restablecerContrasena(cuenta.id, contrasena)
      await alertas.exito("Contraseña restablecida")
    } catch (error) {
      await alertas.error("No se pudo restablecer la contraseña", error)
    }
  }

  const eliminar = async (cuenta: Cuenta) => {
    if (!(await alertas.confirmar(`¿Eliminar la cuenta de ${cuenta.nombre} ${cuenta.apellido}?`, "Solo se puede si nunca la ha usado; si tiene historial, desactívala.", { aceptar: "Eliminar", peligro: true }))) return
    try {
      await api.usuarios.eliminar(cuenta.id)
      await recargar()
    } catch (error) {
      await alertas.error("No se pudo eliminar la cuenta", error)
    }
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <Tabs value={rol} onValueChange={(v) => setRol(v as Rol)}>
          <TabsList>
            {(Object.keys(NOMBRES) as Rol[]).map((r) => (
              <TabsTrigger key={r} value={r}>
                {NOMBRES[r]}
              </TabsTrigger>
            ))}
          </TabsList>
        </Tabs>
        <Button type="button" onClick={() => setEditando("nueva")}>
          <Plus /> Nueva cuenta de {UNO[rol]}
        </Button>
      </div>

      <Listado pie={cuentas ? <span className="tabular-nums">{cuentas.length} cuentas</span> : undefined}>
        {cuentas?.length === 0 ? (
          <SinDatos icono={UserCog} titulo={`No hay cuentas de ${NOMBRES[rol].toLowerCase()}`} />
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Nombre</TableHead>
                <TableHead>Matrícula</TableHead>
                <TableHead className="hidden md:table-cell">Correo</TableHead>
                {rol === "docente" && <TableHead className="hidden lg:table-cell">Departamento</TableHead>}
                <TableHead>Estado</TableHead>
                <TableHead className="w-12">
                  <span className="sr-only">Acciones</span>
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {(cuentas ?? []).map((cuenta) => {
                const propia = cuenta.id === usuario.id
                return (
                  <TableRow key={cuenta.id}>
                    <TableCell className="font-medium">
                      {cuenta.nombre} {cuenta.apellido}
                      {propia && <span className="ml-2 text-xs font-normal text-muted-foreground">(tú)</span>}
                    </TableCell>
                    <TableCell className="font-mono text-[13px] text-muted-foreground">{cuenta.matricula ?? "—"}</TableCell>
                    <TableCell className="hidden text-muted-foreground md:table-cell">{cuenta.email ?? "—"}</TableCell>
                    {rol === "docente" && <TableCell className="hidden text-muted-foreground lg:table-cell">{cuenta.departamento ?? "—"}</TableCell>}
                    <TableCell>{cuenta.activo ? <Badge variant="success">Activa</Badge> : <Badge>Desactivada</Badge>}</TableCell>
                    <TableCell>
                      <MenuDeAcciones
                        etiqueta={`Acciones para ${cuenta.nombre} ${cuenta.apellido}`}
                        acciones={[
                          { texto: "Editar", icono: Pencil, alElegir: () => setEditando(cuenta) },
                          { texto: "Restablecer contraseña", icono: KeyRound, alElegir: () => restablecer(cuenta) },
                          { texto: cuenta.activo ? "Desactivar" : "Activar", icono: cuenta.activo ? UserX : UserCheck, oculta: propia, alElegir: () => cambiarActiva(cuenta) },
                          { texto: "Eliminar", icono: Trash2, peligro: true, oculta: propia, alElegir: () => eliminar(cuenta) },
                        ]}
                      />
                    </TableCell>
                  </TableRow>
                )
              })}
            </TableBody>
          </Table>
        )}
      </Listado>

      {editando && (
        <FormularioDeCuenta
          cuenta={editando === "nueva" ? null : editando}
          rol={rol}
          alCerrar={() => setEditando(null)}
          alGuardar={() => {
            setEditando(null)
            recargar()
          }}
        />
      )}
    </div>
  )
}

function FormularioDeCuenta({ cuenta, rol, alCerrar, alGuardar }: { cuenta: Cuenta | null; rol: Rol; alCerrar: () => void; alGuardar: () => void }) {
  const [datos, setDatos] = useState({
    matricula: cuenta?.matricula ?? "",
    nombre: cuenta?.nombre ?? "",
    apellido: cuenta?.apellido ?? "",
    email: cuenta?.email ?? "",
    departamento: cuenta?.departamento ?? "",
    contrasena: "",
  })

  const guardar = async (e: FormEvent) => {
    e.preventDefault()
    const cuerpo = {
      matricula: datos.matricula || null,
      nombre: datos.nombre,
      apellido: datos.apellido,
      email: datos.email || null,
      departamento: datos.departamento || null,
    }
    try {
      if (cuenta) {
        await api.usuarios.editar(cuenta.id, cuerpo)
      } else {
        await api.usuarios.crear({ ...cuerpo, rol, contrasena: datos.contrasena })
      }
      alertas.breve(cuenta ? "Cuenta actualizada" : "Cuenta creada")
      alGuardar()
    } catch (error) {
      await alertas.error("No se pudo guardar la cuenta", error)
    }
  }

  const campo = (clave: keyof typeof datos, etiqueta: string, tipo = "text", obligatorio = false) => (
    <Campo etiqueta={etiqueta} htmlFor={`cuenta-${clave}`}>
      <Input id={`cuenta-${clave}`} type={tipo} value={datos[clave]} onChange={(e) => setDatos({ ...datos, [clave]: e.target.value })} required={obligatorio} />
    </Campo>
  )

  return (
    <Dialog open onOpenChange={(abierto) => !abierto && alCerrar()}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{cuenta ? `Editar a ${cuenta.nombre}` : `Nueva cuenta de ${UNO[rol]}`}</DialogTitle>
          <DialogDescription>Se inicia sesión con la matrícula o con el correo: hace falta al menos uno de los dos.</DialogDescription>
        </DialogHeader>
        <form onSubmit={guardar} className="grid grid-cols-2 gap-4">
          {campo("nombre", "Nombre", "text", true)}
          {campo("apellido", "Apellidos")}
          {campo("matricula", "Matrícula o número de empleado")}
          {campo("email", "Correo", "email")}
          {rol === "docente" && <div className="col-span-2">{campo("departamento", "Departamento")}</div>}
          {!cuenta && <div className="col-span-2">{campo("contrasena", "Contraseña inicial (al menos 8 caracteres)", "password", true)}</div>}
          <DialogFooter className="col-span-2">
            <Button type="button" variant="outline" onClick={alCerrar}>
              Cancelar
            </Button>
            <Button type="submit">Guardar</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
