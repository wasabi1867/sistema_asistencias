/**
 * Cliente de la API en PHP (public/api). Sustituye a Firebase en todas las pantallas.
 *
 * Las funciones devuelven los datos con los mismos nombres de campo que tenían los
 * documentos de Firestore (Nombre, Apellido, Materias, NombreMateria...), para que
 * las pantallas no tengan que cambiar más que la forma de pedirlos.
 *
 * La sesión va en una cookie que pone la API al iniciar sesión; el navegador la manda
 * sola en cada petición.
 */

// La API se publica junto con el sitio (public/api queda en /api). Durante el
// desarrollo con `npm run dev` se puede apuntar a otra con NEXT_PUBLIC_API_URL.
const API_BASE = (process.env.NEXT_PUBLIC_API_URL || "/api").replace(/\/$/, "")

/** Un error de la API, con el mensaje que ya viene en español para mostrarlo tal cual. */
export class ErrorDeApi extends Error {
  estado: number
  codigo?: string
  datos: any

  constructor(mensaje: string, estado: number, datos: any) {
    super(mensaje)
    this.name = "ErrorDeApi"
    this.estado = estado
    this.codigo = datos?.codigo
    this.datos = datos
  }
}

async function pedir<T = any>(ruta: string, opciones: { metodo?: string; cuerpo?: unknown; keepalive?: boolean } = {}): Promise<T> {
  let respuesta: Response
  try {
    respuesta = await fetch(`${API_BASE}/${ruta}`, {
      method: opciones.metodo || "GET",
      credentials: "include",
      keepalive: opciones.keepalive,
      headers: {
        Accept: "application/json",
        ...(opciones.cuerpo !== undefined ? { "Content-Type": "application/json" } : {}),
      },
      body: opciones.cuerpo !== undefined ? JSON.stringify(opciones.cuerpo) : undefined,
    })
  } catch {
    throw new ErrorDeApi("No se pudo conectar con el servidor. Revisa tu conexión a internet.", 0, {})
  }

  let datos: any = {}
  try {
    datos = await respuesta.json()
  } catch {
    // Respuesta vacía o que no es JSON (por ejemplo, una página de error del hosting).
  }
  if (!respuesta.ok || datos.success === false) {
    throw new ErrorDeApi(datos.error || `Error del servidor (${respuesta.status})`, respuesta.status, datos)
  }
  return datos
}

const q = (parametros: Record<string, string | number | boolean | undefined | null>) => {
  const p = new URLSearchParams()
  for (const [clave, valor] of Object.entries(parametros)) {
    if (valor !== undefined && valor !== null && valor !== "" && valor !== false) {
      p.set(clave, String(valor))
    }
  }
  const texto = p.toString()
  return texto ? `?${texto}` : ""
}

/** Convierte "2026-10-21 14:05:00" (como lo da la base) en Date local. */
export function fechaDeLaBase(valor: string | null | undefined): Date | null {
  if (!valor) return null
  const [fecha, hora = "00:00:00"] = valor.split(" ")
  const [a, m, d] = fecha.split("-").map(Number)
  const [h, mi, s] = hora.split(":").map(Number)
  return new Date(a, m - 1, d, h || 0, mi || 0, s || 0)
}

/** "14:05:00" → "2:05:00 p.m.", como lo mostraba toLocaleTimeString(). */
export function horaLegible(valor: string | null | undefined): string {
  const fecha = fechaDeLaBase(valor)
  return fecha ? fecha.toLocaleTimeString() : ""
}

// ---------------------------------------------------------------------------
// Tipos (con los nombres de campo de Firestore)
// ---------------------------------------------------------------------------

export type Rol = "admin" | "laboratorista" | "docente"

export interface UsuarioEnSesion {
  rol: Rol
  id: string
  nombre: string
  email: string
}

export interface Alumno {
  Matricula: string
  Nombre: string
  Apellido: string
  Carrera: string
  Semestre: string
  Turno: string
  Grupo: string
  Materias: string[]
}

export interface Docente {
  Matricula: string
  Nombre: string
  Apellido: string
  Email: string
  Departamento: string
}

export interface Laboratorista {
  Matricula: string
  Nombre: string
  Apellido: string
  Email: string
}

export interface Administrador {
  id: number
  Matricula: string
  Nombre: string
  Apellido: string
  Email: string
}

export interface Practica {
  id: string
  Numero: string
  Titulo: string
  Descripcion: string
  Duracion: string
  fecha: string
}

export interface Materia {
  id: string
  NombreMateria: string
  MaestroID: string
  NombreDocente: string
  Semestre: string
  Turno: string
  CicloEscolar: string
  Laboratorio: string
  Practicas?: Practica[]
}

export interface Equipo {
  id: string
  laboratorio: string
  fueraDeServicio: boolean
  enUso: boolean
  usuarioActual: string | null
  notas: string
  estadoReparacion: "reportado" | "en_proceso" | "resuelto" | "ninguno"
  comentarioEstado: string
  ultimaActualizacion: string
}

export interface Clase {
  id: number
  laboratorio: string
  tipo: "regular" | "invitado"
  materiaId: string | null
  materia: string
  practicaId: number | null
  practica: string
  docenteId: string | null
  docente: string
  departamento: string
  grupo: string
  abiertaEn: string
  cerradaEn: string | null
  abierta: boolean
  cierre: "normal" | "externo" | "automatico" | null
  totalAsistencias: number
  alumnos?: AsistenteDeClase[]
}

export interface AsistenteDeClase {
  alumnoId: string
  nombre: string
  apellido: string
  carrera: string
  semestre: string
  turno: string
  grupo: string
  equipo: string
  tipoUsuario: string
  horaEntrada: string
  horaSalida: string | null
}

export interface Asistencia {
  id: string
  AlumnoId: string
  Nombre: string
  Apellido: string
  Carrera: string
  Semestre: string
  Turno: string
  Grupo: string
  Equipo: string
  Materia: string
  Practica: string
  ClaseId: number | null
  TipoUsuario: string
  laboratorio: string
  Fecha: string
}

export interface Solicitud {
  id: string
  alumnoId: string
  alumnoNombre: string
  alumnoApellido: string
  alumnoCarrera: string
  alumnoSemestre: string
  alumnoGrupo: string
  alumnoTurno: string
  equipo: string
  materiaId: string
  materiaNombre: string
  maestroNombre: string
  motivo: string
  laboratorio: string
  estado: "pendiente" | "aprobada" | "rechazada" | "aprobado_temporal" | "aprobado_permanente"
  fechaSolicitud: Date | null
  leida: boolean
}

export interface Notificacion {
  id: string
  tipo: string
  titulo: string
  mensaje: string
  fecha: Date | null
  leida: boolean
  prioridad: string
  datos: any
  laboratorio: string | null
  /** Solo en avisos de equipos: el estado de reparación que tiene el equipo ahora. */
  estadoEquipo?: "reportado" | "en_proceso" | "resuelto" | null
  comentarioEstado?: string | null
  fechaActualizacionEstado?: Date | null
}

export interface EntradaDeBitacora {
  id: string
  timestamp: Date | null
  action: string
  details: string
  usuario: string
  laboratorio: string | null
  ip: string | null
}

export interface Evento {
  id: string
  nombre: string
  tipo: string
  organizador: string
  participantes: number
  fecha: string
  hora: string
  duracion: string
  descripcion: string
  resultados: string
  createdAt: Date | null
}

// ---------------------------------------------------------------------------
// Conversión de las filas de la API a la forma de las pantallas
// ---------------------------------------------------------------------------

const comoAlumno = (a: any): Alumno => ({
  Matricula: a.matricula,
  Nombre: a.nombre,
  Apellido: a.apellido,
  Carrera: a.carrera ?? "",
  Semestre: a.semestre ?? "",
  Turno: a.turno ?? "",
  Grupo: a.grupo ?? "",
  Materias: Array.isArray(a.materias) ? a.materias.map(String) : [],
})

const comoPractica = (p: any): Practica => ({
  id: String(p.id),
  Numero: p.numero_practica ?? "",
  Titulo: p.titulo,
  Descripcion: p.descripcion ?? "",
  Duracion: p.duracion ?? "",
  fecha: p.fecha ?? "",
})

const comoMateria = (m: any): Materia => ({
  id: m.id,
  NombreMateria: m.nombre_materia,
  MaestroID: m.docente_id ?? "",
  NombreDocente: m.nombre_docente ?? "",
  Semestre: m.semestre ?? "",
  Turno: m.turno ?? "",
  CicloEscolar: m.ciclo_escolar ?? "",
  Laboratorio: m.laboratorio ?? "",
  ...(m.practicas ? { Practicas: m.practicas.map(comoPractica) } : {}),
})

const comoAsistencia = (a: any): Asistencia => ({
  id: a.id,
  AlumnoId: a.AlumnoId,
  Nombre: a.Nombre,
  Apellido: a.Apellido,
  Carrera: a.Carrera ?? "",
  Semestre: a.Semestre ?? "",
  Turno: a.Turno ?? "",
  Grupo: a.Grupo ?? "",
  Equipo: a.Equipo,
  Materia: a.Materia ?? "",
  Practica: a.Practica ?? "",
  ClaseId: a.ClaseId,
  TipoUsuario: a.TipoUsuario,
  laboratorio: a.Laboratorio,
  Fecha: a.Fecha,
})

const comoSolicitud = (s: any): Solicitud => {
  // La base guarda el nombre completo; el apellido sale del alumno.
  const apellido = s.alumno_apellido ?? ""
  const nombre = apellido && s.nombre.endsWith(` ${apellido}`) ? s.nombre.slice(0, -apellido.length - 1) : s.nombre
  return {
    id: String(s.id),
    alumnoId: s.alumno_id,
    alumnoNombre: nombre,
    alumnoApellido: apellido,
    alumnoCarrera: s.carrera ?? "",
    alumnoSemestre: s.alumno_semestre ?? "",
    alumnoGrupo: s.alumno_grupo ?? "",
    alumnoTurno: s.alumno_turno ?? "",
    equipo: s.equipo_id ?? "",
    materiaId: s.materia_id ?? "",
    materiaNombre: s.materia_nombre ?? "",
    maestroNombre: s.docente_nombre ?? "",
    motivo: s.motivo ?? "",
    laboratorio: s.laboratorio,
    estado: s.estado,
    fechaSolicitud: fechaDeLaBase(s.fecha_solicitud),
    leida: Boolean(s.leida),
  }
}

const comoNotificacion = (n: any): Notificacion => ({
  id: String(n.id),
  tipo: n.tipo,
  titulo: n.titulo,
  mensaje: n.mensaje,
  fecha: fechaDeLaBase(n.fecha),
  leida: Boolean(n.leida),
  prioridad: n.prioridad,
  datos: n.datos,
  laboratorio: n.laboratorio,
  estadoEquipo: n.estadoEquipo ?? null,
  comentarioEstado: n.comentarioEstado ?? null,
  fechaActualizacionEstado: fechaDeLaBase(n.fechaActualizacionEstado),
})

/** Una clase cerrada con la forma de los documentos de ClassInformation, que usan los reportes. */
export interface InformacionDeClase {
  id: string
  tipo: "regular" | "invitado"
  materia: string
  practica: string
  /** dd/mm/aaaa, como se guardaba en Firebase */
  fecha: string
  fechaInicio: Date | null
  horaInicio: string
  horaFin: string
  totalAsistencias: number
  maestroId: string
  maestroNombre: string
  maestroApellido: string
  departamento: string
  grupo: string
  carrera: string
  laboratorio: string
  alumnos: { id: string; nombre: string; apellido: string; equipo: string; carrera: string; grupo: string; semestre: string; turno: string }[]
}

export function claseComoInformacion(c: Clase): InformacionDeClase {
  const inicio = fechaDeLaBase(c.abiertaEn)
  const alumnos = (c.alumnos ?? []).map((a) => ({
    id: a.alumnoId, nombre: a.nombre, apellido: a.apellido, equipo: a.equipo,
    carrera: a.carrera, grupo: a.grupo, semestre: a.semestre, turno: a.turno,
  }))
  return {
    id: String(c.id),
    tipo: c.tipo,
    materia: c.materia,
    practica: c.practica,
    fecha: inicio ? inicio.toLocaleDateString("es-ES", { day: "2-digit", month: "2-digit", year: "numeric" }) : "",
    fechaInicio: inicio,
    horaInicio: horaLegible(c.abiertaEn),
    horaFin: horaLegible(c.cerradaEn),
    totalAsistencias: c.abierta ? alumnos.length : c.totalAsistencias,
    maestroId: c.docenteId ?? "",
    // El nombre completo del docente va en maestroNombre; las pantallas los juntan.
    maestroNombre: c.docente,
    maestroApellido: "",
    departamento: c.departamento,
    grupo: c.grupo || alumnos[0]?.grupo || "",
    carrera: alumnos[0]?.carrera ?? "",
    laboratorio: c.laboratorio,
    alumnos,
  }
}

// ---------------------------------------------------------------------------
// Rutas
// ---------------------------------------------------------------------------

export const API = {
  // Sesión
  iniciarSesion: (usuario: string, password: string, rol: Rol) =>
    pedir<{ user: any; role: Rol }>("auth.php", { metodo: "POST", cuerpo: { email: usuario, password, role: rol } }),
  sesion: async (): Promise<UsuarioEnSesion | null> => {
    try {
      return (await pedir<{ user: UsuarioEnSesion }>("auth.php")).user
    } catch (e) {
      if (e instanceof ErrorDeApi && e.estado === 401) return null
      throw e
    }
  },
  cerrarSesion: () => pedir("auth.php", { metodo: "DELETE" }),

  // Alumnos
  getAlumnos: async (busqueda?: string) => (await pedir("alumnos.php" + q({ q: busqueda }))).data.map(comoAlumno) as Alumno[],
  getAlumno: async (matricula: string) => comoAlumno((await pedir("alumnos.php" + q({ matricula }))).data),
  guardarAlumno: (alumno: Alumno) =>
    pedir("alumnos.php", {
      metodo: "POST",
      cuerpo: {
        matricula: alumno.Matricula, nombre: alumno.Nombre, apellido: alumno.Apellido, carrera: alumno.Carrera,
        semestre: alumno.Semestre, turno: alumno.Turno, grupo: alumno.Grupo, materias: alumno.Materias ?? [],
      },
    }),
  eliminarAlumno: (matricula: string) => pedir("alumnos.php" + q({ matricula }), { metodo: "DELETE" }),
  cambioMasivoDeAlumnos: (cambio: { carrera: string; semestre: string; grupo?: string; nuevo_semestre?: string; nuevo_grupo?: string }) =>
    pedir<{ actualizados: number }>("alumnos.php?action=cambio_masivo", { metodo: "POST", cuerpo: cambio }),

  // Docentes
  getDocentes: async (): Promise<Docente[]> =>
    (await pedir("docentes.php")).data.map((d: any) => ({
      Matricula: d.matricula, Nombre: d.nombre, Apellido: d.apellido, Email: d.email ?? "", Departamento: d.departamento ?? "",
    })),
  guardarDocente: (d: Docente & { Contraseña?: string }) =>
    pedir("docentes.php", {
      metodo: "POST",
      cuerpo: { matricula: d.Matricula, nombre: d.Nombre, apellido: d.Apellido, email: d.Email, departamento: d.Departamento, password: d.Contraseña ?? "" },
    }),
  eliminarDocente: (matricula: string) => pedir("docentes.php" + q({ matricula }), { metodo: "DELETE" }),

  // Laboratoristas
  getLaboratoristas: async (): Promise<Laboratorista[]> =>
    (await pedir("laboratoristas.php")).data.map((l: any) => ({ Matricula: l.matricula, Nombre: l.nombre, Apellido: l.apellido, Email: l.email ?? "" })),
  guardarLaboratorista: (l: Laboratorista & { Contraseña?: string }) =>
    pedir("laboratoristas.php", {
      metodo: "POST",
      cuerpo: { matricula: l.Matricula, nombre: l.Nombre, apellido: l.Apellido, email: l.Email, password: l.Contraseña ?? "" },
    }),
  eliminarLaboratorista: (matricula: string) => pedir("laboratoristas.php" + q({ matricula }), { metodo: "DELETE" }),

  // Administradores
  getAdministradores: async (): Promise<Administrador[]> =>
    (await pedir("administradores.php")).data.map((a: any) => ({
      id: Number(a.id), Matricula: a.matricula ?? "", Nombre: a.nombre, Apellido: a.apellido ?? "", Email: a.email,
    })),
  guardarAdministrador: (a: Partial<Administrador> & { Contraseña?: string }) =>
    pedir("administradores.php", {
      metodo: "POST",
      cuerpo: { id: a.id, matricula: a.Matricula, nombre: a.Nombre, apellido: a.Apellido, email: a.Email, password: a.Contraseña ?? "" },
    }),
  eliminarAdministrador: (id: number) => pedir("administradores.php" + q({ id }), { metodo: "DELETE" }),

  // Materias y prácticas
  getMaterias: async (filtros: { docenteId?: string; laboratorio?: string; conPracticas?: boolean } = {}) =>
    (await pedir("materias.php" + q({ docente_id: filtros.docenteId, laboratorio: filtros.laboratorio, con_practicas: filtros.conPracticas ? 1 : undefined })))
      .data.map(comoMateria) as Materia[],
  getMateria: async (id: string) => comoMateria((await pedir("materias.php" + q({ id }))).data),
  getPracticas: async (materiaId: string) => (await API.getMateria(materiaId)).Practicas ?? [],
  guardarMateria: (m: Partial<Materia>) =>
    pedir<{ id: string }>("materias.php", {
      metodo: "POST",
      cuerpo: {
        id: m.id, nombre_materia: m.NombreMateria, docente_id: m.MaestroID, semestre: m.Semestre, turno: m.Turno,
        ciclo_escolar: m.CicloEscolar, laboratorio: m.Laboratorio,
      },
    }),
  eliminarMateria: (id: string) => pedir("materias.php" + q({ id }), { metodo: "DELETE" }),
  guardarPractica: (materiaId: string, p: Partial<Practica>) =>
    pedir("materias.php?action=" + (p.id ? "editar_practica" : "crear_practica"), {
      metodo: "POST",
      cuerpo: { id: p.id, materia_id: materiaId, numero_practica: p.Numero, titulo: p.Titulo, descripcion: p.Descripcion, duracion: p.Duracion, fecha: p.fecha },
    }),
  eliminarPractica: (id: string) => pedir("materias.php" + q({ action: "practica", id }), { metodo: "DELETE" }),

  // Equipos
  getEquipos: async (laboratorio: string) => (await pedir<{ data: Equipo[] }>("equipos.php" + q({ laboratorio }))).data,
  actualizarEquipo: (equipo: Partial<Equipo> & { id: string; laboratorio: string }) => pedir("equipos.php", { metodo: "POST", cuerpo: equipo }),
  agregarEquipo: (id: string, laboratorio: string) => pedir("equipos.php?action=crear", { metodo: "POST", cuerpo: { id, laboratorio } }),
  quitarEquipo: (id: string, laboratorio: string) => pedir("equipos.php" + q({ id, laboratorio }), { metodo: "DELETE" }),
  /** Reemplaza los equipos del laboratorio por PC-1 a PC-cantidad. */
  configurarEquipos: (cantidad: number, laboratorio: string) =>
    pedir<{ anteriores: number }>("equipos.php?action=configurar", { metodo: "POST", cuerpo: { cantidad, laboratorio } }),

  // Clases
  getClasesAbiertas: async (laboratorio: string) => (await pedir<{ data: Clase[] }>("clases.php" + q({ abiertas: 1, laboratorio }))).data,
  getClase: async (id: number) => (await pedir<{ data: Clase }>("clases.php" + q({ id }))).data,
  /** Las clases con quiénes asistieron, en la forma de ClassInformation (para los reportes). */
  getInformacionDeClases: async (filtros: { laboratorio?: string; desde?: string; hasta?: string; estado?: "abiertas" | "cerradas"; docenteId?: string } = {}): Promise<InformacionDeClase[]> =>
    (await pedir<{ data: Clase[] }>("clases.php" + q({
      laboratorio: filtros.laboratorio, docente_id: filtros.docenteId, desde: filtros.desde, hasta: filtros.hasta, estado: filtros.estado, con_alumnos: 1,
    }))).data.map(claseComoInformacion),
  getClases: async (filtros: { laboratorio?: string; tipo?: string; docenteId?: string; desde?: string; hasta?: string; estado?: "abiertas" | "cerradas"; conAlumnos?: boolean } = {}) =>
    (await pedir<{ data: Clase[] }>("clases.php" + q({
      laboratorio: filtros.laboratorio, tipo: filtros.tipo, docente_id: filtros.docenteId, desde: filtros.desde, hasta: filtros.hasta,
      estado: filtros.estado, con_alumnos: filtros.conAlumnos ? 1 : undefined,
    }))).data,
  abrirClase: async (datos: { laboratorio: string; materia_id: string; practica_id: string | number; grupo?: string }) =>
    (await pedir<{ data: Clase }>("clases.php?action=abrir", { metodo: "POST", cuerpo: { tipo: "regular", ...datos } })).data,
  abrirClaseDeInvitado: async (datos: { laboratorio: string; docente_id?: string; docente?: string; departamento: string; materia: string; practica: string; grupo?: string }) =>
    (await pedir<{ data: Clase }>("clases.php?action=abrir", { metodo: "POST", cuerpo: { tipo: "invitado", ...datos } })).data,
  cerrarClase: async (id: number) => (await pedir<{ data: Clase }>("clases.php?action=cerrar", { metodo: "POST", cuerpo: { id } })).data,
  /** Para cuando el docente cierra la pestaña: la petición sigue aunque la página ya no exista. */
  cerrarClaseAlSalir: (id: number) =>
    pedir("clases.php?action=cerrar", { metodo: "POST", cuerpo: { id, automatico: true }, keepalive: true }).catch(() => undefined),

  // Asistencias
  getAsistenciasActivas: async (filtro: { laboratorio?: string; claseId?: number }) =>
    (await pedir("asistencias.php" + q({ action: "activas", laboratorio: filtro.laboratorio, clase_id: filtro.claseId }))).data.map(comoAsistencia) as Asistencia[],
  registrarEntrada: (registro: { AlumnoId?: string; Equipo: string; Laboratorio: string; TipoUsuario?: "estudiante" | "docente" | "invitado"; Nombre?: string; Apellido?: string; ClaseId?: number }) =>
    pedir<{ id: number; nombre: string; clase: Pick<Clase, "id" | "tipo" | "materia" | "practica" | "docente"> }>("asistencias.php?action=entrada", { metodo: "POST", cuerpo: registro }),
  registrarSalida: (id: string | number) => pedir("asistencias.php?action=salida", { metodo: "POST", cuerpo: { id } }),
  eliminarAsistencia: (id: string | number) => pedir("asistencias.php" + q({ id }), { metodo: "DELETE" }),
  getHistorico: async (filtros: { laboratorio?: string; fecha_inicio?: string; fecha_fin?: string; carrera?: string; materia?: string; alumno_id?: string } = {}) =>
    (await pedir("asistencias.php" + q({ action: "historico", ...filtros }))).data as any[],

  // Solicitudes
  getSolicitudes: async (laboratorio: string, estado?: string) => (await pedir("solicitudes.php" + q({ laboratorio, estado }))).data.map(comoSolicitud) as Solicitud[],
  crearSolicitud: (s: { alumno_id: string; laboratorio: string; equipo?: string; clase_id?: number; motivo?: string }) =>
    pedir<{ id: number }>("solicitudes.php", { metodo: "POST", cuerpo: s }),
  resolverSolicitud: (id: string, estado: "aprobado_temporal" | "aprobado_permanente" | "rechazada") =>
    pedir("solicitudes.php", { metodo: "PUT", cuerpo: { id, estado } }),
  marcarSolicitudLeida: (id: string) => pedir("solicitudes.php", { metodo: "PUT", cuerpo: { id, leida: true } }),

  // Notificaciones
  getNotificaciones: async (laboratorio?: string, tipo?: string) =>
    (await pedir("notificaciones.php" + q({ laboratorio, tipo }))).data.map(comoNotificacion) as Notificacion[],
  crearNotificacion: (n: { tipo: string; titulo: string; mensaje: string; laboratorio: string; prioridad?: string; datos?: any }) =>
    pedir("notificaciones.php", { metodo: "POST", cuerpo: n }),
  /** Sin id, las marca todas. */
  marcarNotificacionLeida: (id?: string) => pedir("notificaciones.php", { metodo: "PUT", cuerpo: { id } }),
  marcarNotificacionNoLeida: (id: string) => pedir("notificaciones.php", { metodo: "PUT", cuerpo: { id, leida: false } }),

  // Bitácora
  getBitacora: async (filtros: { desde?: string; hasta?: string; accion?: string; laboratorio?: string; q?: string; limite?: number } = {}): Promise<EntradaDeBitacora[]> =>
    (await pedir("bitacora.php" + q(filtros))).data.map((l: any): EntradaDeBitacora => ({
      id: String(l.id), timestamp: fechaDeLaBase(l.fecha), action: l.accion, details: l.detalles ?? "", usuario: l.usuario,
      laboratorio: l.laboratorio, ip: l.ip,
    })),

  // Eventos
  getEventos: async (laboratorio: string): Promise<Evento[]> =>
    (await pedir("eventos.php" + q({ laboratorio }))).data.map((e: any): Evento => ({
      id: String(e.id), nombre: e.nombre, tipo: e.tipo, organizador: e.organizador, participantes: e.participantes,
      fecha: e.fecha ?? "", hora: e.hora, duracion: e.duracion, descripcion: e.descripcion ?? "", resultados: e.resultados ?? "",
      createdAt: fechaDeLaBase(e.creado_en),
    })),
  guardarEvento: (e: Partial<Evento> & { laboratorio?: string }) =>
    pedir<{ id: number }>("eventos.php", { metodo: "POST", cuerpo: { ...e, id: e.id ? Number(e.id) : undefined } }),
  eliminarEvento: (id: string) => pedir("eventos.php" + q({ id }), { metodo: "DELETE" }),

  // Estadísticas
  getStats: async (laboratorio: string) => (await pedir("stats.php" + q({ laboratorio }))).data,
}
