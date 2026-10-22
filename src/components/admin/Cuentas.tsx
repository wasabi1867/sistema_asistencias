import { useState, type FormEvent } from "react"
import { Edit, KeyRound, Plus, Trash2, UserCheck, UserX } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { api, type Cuenta, type Rol } from "../../lib/api"
import { alertas } from "../../lib/alertas"
import { useDatos } from "../../hooks/useDatos"
import type { PropsDeSeccion } from "./seccion"

const NOMBRES: Record<Rol, string> = { docente: "Docentes", laboratorista: "Laboratoristas", admin: "Administradores" }
const UNO: Record<Rol, string> = { docente: "docente", laboratorista: "laboratorista", admin: "administrador" }

/** Las cuentas del personal. Una cuenta con historial no se borra: se desactiva. */
export function Cuentas({ usuario, c }: PropsDeSeccion) {
  const [rol, setRol] = useState<Rol>("docente")
  const { datos: cuentas, recargar } = useDatos(() => api.usuarios.listar({ rol }), [rol])
  const [editando, setEditando] = useState<Cuenta | "nueva" | null>(null)

  const cambiarActiva = async (cuenta: Cuenta) => {
    try {
      await api.usuarios.editar(cuenta.id, { activo: !cuenta.activo })
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
      <div className="flex flex-wrap gap-2 justify-between">
        <Tabs value={rol} onValueChange={(v) => setRol(v as Rol)}>
          <TabsList>
            {(Object.keys(NOMBRES) as Rol[]).map((r) => (
              <TabsTrigger key={r} value={r}>
                {NOMBRES[r]}
              </TabsTrigger>
            ))}
          </TabsList>
        </Tabs>
        <Button type="button" onClick={() => setEditando("nueva")} className={c.botonSecundario}>
          <Plus className="w-4 h-4 mr-2" /> Nueva cuenta de {UNO[rol]}
        </Button>
      </div>

      <Card className={`${c.tarjeta} border-none shadow-lg`}>
        <CardContent className="p-0 overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Matrícula</TableHead>
                <TableHead>Nombre</TableHead>
                <TableHead>Correo</TableHead>
                {rol === "docente" && <TableHead>Departamento</TableHead>}
                <TableHead>Estado</TableHead>
                <TableHead />
              </TableRow>
            </TableHeader>
            <TableBody>
              {(cuentas ?? []).map((cuenta) => (
                <TableRow key={cuenta.id} className={cuenta.activo ? "" : "opacity-60"}>
                  <TableCell className="font-mono">{cuenta.matricula}</TableCell>
                  <TableCell>
                    {cuenta.nombre} {cuenta.apellido}
                  </TableCell>
                  <TableCell>{cuenta.email}</TableCell>
                  {rol === "docente" && <TableCell>{cuenta.departamento}</TableCell>}
                  <TableCell>{cuenta.activo ? "Activa" : "Desactivada"}</TableCell>
                  <TableCell className="text-right whitespace-nowrap">
                    <Button type="button" size="sm" variant="ghost" onClick={() => setEditando(cuenta)} title="Editar">
                      <Edit className="w-4 h-4" />
                    </Button>
                    <Button type="button" size="sm" variant="ghost" onClick={() => restablecer(cuenta)} title="Restablecer contraseña">
                      <KeyRound className="w-4 h-4" />
                    </Button>
                    {cuenta.id !== usuario.id && (
                      <>
                        <Button type="button" size="sm" variant="ghost" onClick={() => cambiarActiva(cuenta)} title={cuenta.activo ? "Desactivar" : "Activar"}>
                          {cuenta.activo ? <UserX className="w-4 h-4" /> : <UserCheck className="w-4 h-4" />}
                        </Button>
                        <Button type="button" size="sm" variant="ghost" onClick={() => eliminar(cuenta)} title="Eliminar" className="text-red-500">
                          <Trash2 className="w-4 h-4" />
                        </Button>
                      </>
                    )}
                  </TableCell>
                </TableRow>
              ))}
              {cuentas?.length === 0 && (
                <TableRow>
                  <TableCell colSpan={6} className="text-center py-8 opacity-60">
                    No hay cuentas de {NOMBRES[rol].toLowerCase()}
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

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
      alGuardar()
    } catch (error) {
      await alertas.error("No se pudo guardar la cuenta", error)
    }
  }

  const campo = (clave: keyof typeof datos, etiqueta: string, tipo = "text", obligatorio = false) => (
    <div className="space-y-1">
      <Label htmlFor={`cuenta-${clave}`}>{etiqueta}</Label>
      <Input id={`cuenta-${clave}`} type={tipo} value={datos[clave]} onChange={(e) => setDatos({ ...datos, [clave]: e.target.value })} required={obligatorio} />
    </div>
  )

  return (
    <Dialog open onOpenChange={(abierto) => !abierto && alCerrar()}>
      <DialogContent aria-describedby={undefined} className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{cuenta ? `Editar a ${cuenta.nombre}` : `Nueva cuenta de ${UNO[rol]}`}</DialogTitle>
        </DialogHeader>
        <form onSubmit={guardar} className="grid grid-cols-2 gap-3">
          {campo("nombre", "Nombre", "text", true)}
          {campo("apellido", "Apellidos")}
          {campo("matricula", "Matrícula o número de empleado")}
          {campo("email", "Correo", "email")}
          {rol === "docente" && <div className="col-span-2">{campo("departamento", "Departamento")}</div>}
          {!cuenta && <div className="col-span-2">{campo("contrasena", "Contraseña inicial (al menos 8 caracteres)", "password", true)}</div>}
          <p className="col-span-2 text-xs opacity-70">Se inicia sesión con la matrícula o con el correo: hace falta al menos uno de los dos.</p>
          <Button type="submit" className="col-span-2 bg-[#1d5631] hover:bg-[#153d23] text-white">
            Guardar
          </Button>
        </form>
      </DialogContent>
    </Dialog>
  )
}
