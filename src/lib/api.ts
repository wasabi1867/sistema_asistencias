/**
 * Cliente de la API (carpeta api/ del repositorio). Los tipos son los de sus
 * respuestas; las rutas y quién puede usar cada una están en docs/api.md.
 *
 * La sesión va en una cookie que pone la API al iniciar sesión; el navegador la manda
 * sola en cada petición.
 */

// La API se publica junto con el sitio, en /api. Durante el desarrollo con
// `npm run dev` se apunta a otra con NEXT_PUBLIC_API_URL (ver README).
const BASE = (process.env.NEXT_PUBLIC_API_URL || "/api").replace(/\/$/, "")

/**
 * Un error que respondió la API. El mensaje viene en español y se muestra tal cual.
 * `codigo` permite reaccionar sin leer el mensaje (ver docs/api.md); `campo` dice qué
 * dato corregir.
 */
export class ErrorDeApi extends Error {
  constructor(
    mensaje: string,
    public readonly estado: number,
    public readonly datos: Record<string, any> = {},
  ) {
    super(mensaje)
    this.name = "ErrorDeApi"
  }

  get codigo(): string | undefined {
    return this.datos.codigo
  }

  get campo(): string | undefined {
    return this.datos.campo
  }
}

/** El mensaje de un error cualquiera, para mostrarlo. */
export function mensajeDeError(error: unknown, porOmision = "Ocurrió un error. Inténtalo de nuevo."): string {
  return error instanceof Error && error.message ? error.message : porOmision
}

type Consulta = Record<string, string | number | boolean | null | undefined>

async function pedir<T>(ruta: string, opciones: { metodo?: string; cuerpo?: unknown; consulta?: Consulta; keepalive?: boolean } = {}): Promise<T> {
  let respuesta: Response
  try {
    respuesta = await fetch(`${BASE}/${ruta}${aConsulta(opciones.consulta)}`, {
      method: opciones.metodo ?? "GET",
      credentials: "include",
      keepalive: opciones.keepalive,
      headers: {
        Accept: "application/json",
        ...(opciones.cuerpo !== undefined ? { "Content-Type": "application/json" } : {}),
      },
      body: opciones.cuerpo !== undefined ? JSON.stringify(opciones.cuerpo) : undefined,
    })
  } catch {
    throw new ErrorDeApi("No se pudo conectar con el servidor. Revisa tu conexión a internet.", 0)
  }
  if (respuesta.status === 204) {
    return undefined as T
  }
  let datos: any = null
  try {
    datos = await respuesta.json()
  } catch {
    // Una respuesta que no es JSON: por ejemplo, una página de error del hosting.
  }
  if (!respuesta.ok) {
    throw new ErrorDeApi(datos?.error || `Error del servidor (${respuesta.status})`, respuesta.status, datos ?? {})
  }
  return datos as T
}

function aConsulta(consulta?: Consulta): string {
  if (!consulta) return ""
  const parametros = new URLSearchParams()
  for (const [clave, valor] of Object.entries(consulta)) {
    if (valor !== undefined && valor !== null && valor !== "" && valor !== false) {
      parametros.set(clave, String(valor))
    }
  }
  const texto = parametros.toString()
  return texto ? `?${texto}` : ""
}

const obtener = <T>(ruta: string, consulta?: Consulta) => pedir<T>(ruta, { consulta })
const crear = <T>(ruta: string, cuerpo: unknown = {}) => pedir<T>(ruta, { metodo: "POST", cuerpo })
const cambiar = <T>(ruta: string, cuerpo: unknown) => pedir<T>(ruta, { metodo: "PUT", cuerpo })
const borrar = (ruta: string) => pedir<void>(ruta, { metodo: "DELETE" })

// ---------------------------------------------------------------------------
// Tipos
// ---------------------------------------------------------------------------

export type Rol = "admin" | "laboratorista" | "docente"
export type Turno = "matutino" | "vespertino"

export interface Cuenta {
  id: number
  rol: Rol
  matricula: string | null
  nombre: string
  apellido: string
  email: string | null
  departamento: string | null
  activo?: boolean
}

export interface Laboratorio {
  id: number
  clave: string
  nombre: string
  ubicacion: string | null
  activo: boolean
}

export interface Carrera {
  id: number
  clave: string
  nombre: string
  activa: boolean
}

/** La carrera como aparece dentro de un alumno o una materia. */
export interface CarreraBreve {
  clave: string
  nombre: string
}

export interface Periodo {
  id: number
  nombre: string
  fechaInicio: string
  fechaFin: string
  actual: boolean
}

export interface Alumno {
  id: number
  matricula: string
  nombre: string
  apellido: string
  carrera: CarreraBreve | null
  semestre: number | null
  turno: Turno | null
  grupo: string | null
  activo: boolean
}

export interface AlumnoConCursos extends Alumno {
  cursos: { id: number; materia: string; grupo: string; periodo: string; docente: string | null; inscritoEn: string }[]
}

/** Lo que se manda para dar de alta o editar un alumno. La carrera, por clave o nombre. */
export interface DatosDeAlumno {
  matricula: string
  nombre: string
  apellido: string
  carrera?: string | null
  semestre?: number | null
  turno?: Turno | null
  grupo?: string | null
  activo?: boolean
}

export interface Materia {
  id: number
  clave: string | null
  nombre: string
  carrera: CarreraBreve | null
  semestre: number | null
  activa: boolean
}

export interface Practica {
  id: number
  materiaId: number
  numero: number
  titulo: string
  descripcion: string | null
  duracionMinutos: number | null
  fecha: string | null
  /** Común a todos los docentes de la materia, o propia de `docente`. */
  comun: boolean
  docente: { id: number; nombre: string } | null
  activa: boolean
}

export interface DatosDePractica {
  numero?: number | null
  titulo: string
  descripcion?: string | null
  duracionMinutos?: number | null
  fecha?: string | null
  docenteId?: number | null
}

export interface Curso {
  id: number
  materia: { id: number; clave: string | null; nombre: string }
  periodo: { id: number; nombre: string; actual: boolean }
  grupo: string
  turno: Turno | null
  docente: { id: number; nombre: string; apellido: string } | null
  laboratorio: { clave: string; nombre: string } | null
  inscritos: number
}

export interface DatosDeCurso {
  materiaId: number
  periodoId: number
  grupo: string
  turno?: Turno | null
  docenteId?: number | null
  laboratorio?: string | null
}

export interface Inscrito extends Alumno {
  inscritoEn: string
}

export interface ResultadoDeInscripcion {
  inscritos: string[]
  yaInscritos: string[]
  noEncontrados: string[]
  inactivos: string[]
}

export interface ResumenDeAsistencia {
  clases: number
  alumnos: {
    alumnoId: number
    matricula: string
    nombre: string
    apellido: string
    clases: number
    asistencias: number
    faltas: number
    porcentaje: number | null
  }[]
}

export interface Horario {
  id: number
  /** 1 = lunes … 6 = sábado */
  dia: number
  horaInicio: string
  horaFin: string
  laboratorio: { clave: string; nombre: string }
  curso: { id: number; materia: string; grupo: string; docente: string | null }
}

export type EstadoDeEquipo = "libre" | "ocupado" | "fuera_de_servicio"
export type EstadoDeReporte = "reportado" | "en_proceso" | "resuelto"

/** Sin sesión solo llegan id, etiqueta y estado. */
export interface Equipo {
  id: number
  etiqueta: string
  estado: EstadoDeEquipo
  ocupadoPor?: { tipo: TipoDePersona; matricula: string | null; nombre: string; desde: string; claseId: number | null } | null
  notas?: string | null
  reporteAbierto?: { id: number; estado: EstadoDeReporte; descripcion: string } | null
  actualizadoEn?: string
}

export interface ReporteDeEquipo {
  id: number
  equipo: { id: number; etiqueta: string }
  laboratorio: { clave: string; nombre: string }
  descripcion: string
  estado: EstadoDeReporte
  comentario: string | null
  reportadoPor: string | null
  reportadoEn: string
  actualizadoEn: string
  resueltoEn: string | null
}

export type TipoDeClase = "regular" | "invitado"
export type TipoDePersona = "alumno" | "docente" | "invitado"

export interface Clase {
  id: number
  tipo: TipoDeClase
  laboratorio: { clave: string; nombre: string }
  cursoId: number | null
  materia: string
  practica: { id: number | null; numero: number | null; titulo: string } | null
  docente: string | null
  docenteId: number | null
  grupo: string | null
  departamento: string | null
  abierta: boolean
  abiertaEn: string
  cerradaEn: string | null
  cierre: "normal" | "externo" | "automatico" | null
  abiertaPor: string
  cerradaPor: string | null
  asistentes: number
  /** Solo para quien lleva la clase. */
  codigo?: string | null
  /** Con `conAsistentes` o al pedir una sola clase. */
  personas?: Asistencia[]
  /** Al pedir una clase regular: los inscritos, con quién asistió. */
  lista?: ListaDeClase
}

/** Lo que ve el alumno sin sesión. */
export interface ClaseAbierta {
  id: number
  tipo: TipoDeClase
  materia: string
  practica: string | null
  docente: string | null
  grupo: string | null
  abiertaEn: string
  pideCodigo: boolean
}

export interface Asistencia {
  id: number
  tipo: TipoDePersona
  matricula: string | null
  nombre: string
  apellido: string | null
  carrera: CarreraBreve | null
  semestre: number | null
  grupo: string | null
  turno: Turno | null
  /** Etiqueta de la computadora; null si trajo la suya. */
  equipo: string | null
  laboratorio: string
  claseId: number | null
  horaEntrada: string
  horaSalida: string | null
}

export interface ListaDeClase {
  inscritos: number
  presentes: number
  faltas: number
  alumnos: {
    alumnoId: number
    matricula: string
    nombre: string
    apellido: string
    inscrito: boolean
    presente: boolean
    asistenciaId: number | null
    equipo: string | null
    horaEntrada: string | null
    horaSalida: string | null
  }[]
}

export interface EntradaRegistrada {
  asistenciaId: number
  nombre: string
  equipo: string | null
  clase: { id: number; tipo: TipoDeClase; materia: string; practica: string | null; docente: string | null }
}

export type EstadoDeSolicitud = "pendiente" | "aprobada_temporal" | "aprobada_permanente" | "rechazada"

export interface Solicitud {
  id: number
  estado: EstadoDeSolicitud
  leida: boolean
  motivo: string | null
  comentario: string | null
  laboratorio: { clave: string; nombre: string }
  alumno: { matricula: string; nombre: string; apellido: string; carrera: CarreraBreve | null; semestre: number | null; grupo: string | null; turno: Turno | null }
  equipo: string | null
  clase: { id: number; materia: string; grupo: string | null; docente: string | null; abierta: boolean } | null
  creadaEn: string
  resueltaEn: string | null
  resueltaPor: string | null
}

export type TipoDeAviso = "solicitud" | "equipo" | "clase" | "sistema"

export interface Aviso {
  id: number
  tipo: TipoDeAviso
  titulo: string
  mensaje: string | null
  prioridad: "baja" | "media" | "alta"
  leido: boolean
  creadoEn: string
  laboratorio: { clave: string; nombre: string } | null
  solicitud: { id: number; estado: EstadoDeSolicitud } | null
  reporte: { id: number; estado: EstadoDeReporte; equipo: { id: number; etiqueta: string } } | null
  clase: { id: number; abierta: boolean } | null
}

export interface Evento {
  id: number
  laboratorio: string
  nombre: string
  tipo: string | null
  organizador: string | null
  participantes: number | null
  fecha: string | null
  hora: string | null
  duracion: string | null
  descripcion: string | null
  resultados: string | null
  creadoEn: string
}

export type DatosDeEvento = Partial<Omit<Evento, "id" | "creadoEn" | "laboratorio">> & { nombre: string }

export interface EntradaDeBitacora {
  id: number
  fecha: string
  usuario: string
  usuarioId: number | null
  rol: Rol | null
  accion: string
  entidad: string | null
  entidadId: string | null
  detalles: string | null
  laboratorio: string | null
  ip: string | null
}

export interface Estadisticas {
  alumnos: number
  docentes: number
  laboratoristas: number
  administradores: number
  materias: number
  cursosDelPeriodo: number
  solicitudesPendientes: number
  reportesAbiertos: number
  laboratorio?: {
    equipos: { total: number; ocupados: number; fueraDeServicio: number; libres: number }
    personasDentro: number
    clasesAbiertas: number
    asistenciasHoy: number
    clasesHoy: number
  }
}

export interface Paginacion {
  total: number
  pagina: number
  porPagina: number
}

// ---------------------------------------------------------------------------
// Rutas
// ---------------------------------------------------------------------------

export const api = {
  sesion: {
    iniciar: (usuario: string, contrasena: string, rol: Rol) => crear<Cuenta>("sesion", { usuario, contrasena, rol }),
    /** La cuenta con sesión, o null si no hay. */
    actual: async (): Promise<Cuenta | null> => {
      try {
        return await obtener<Cuenta>("sesion")
      } catch (e) {
        if (e instanceof ErrorDeApi && e.estado === 401) return null
        throw e
      }
    },
    cerrar: () => borrar("sesion"),
    cambiarContrasena: (actual: string, nueva: string) => cambiar<void>("sesion/contrasena", { actual, nueva }),
  },

  laboratorios: {
    listar: (todos = false) => obtener<Laboratorio[]>("laboratorios", { todos }),
    crear: (datos: { clave: string; nombre: string; ubicacion?: string | null }) => crear<Laboratorio>("laboratorios", datos),
    editar: (id: number, datos: Partial<Pick<Laboratorio, "nombre" | "ubicacion" | "activo">>) => cambiar<Laboratorio>(`laboratorios/${id}`, datos),
  },

  carreras: {
    listar: (todas = false) => obtener<Carrera[]>("carreras", { todas }),
    crear: (datos: { clave: string; nombre: string }) => crear<Carrera>("carreras", datos),
    editar: (id: number, datos: Partial<Pick<Carrera, "clave" | "nombre" | "activa">>) => cambiar<Carrera>(`carreras/${id}`, datos),
    eliminar: (id: number) => borrar(`carreras/${id}`),
  },

  periodos: {
    listar: () => obtener<Periodo[]>("periodos"),
    /** El periodo que contiene hoy, o null si no hay. */
    actual: async (): Promise<Periodo | null> => {
      try {
        return await obtener<Periodo>("periodos/actual")
      } catch (e) {
        if (e instanceof ErrorDeApi && e.estado === 404) return null
        throw e
      }
    },
    crear: (datos: Omit<Periodo, "id" | "actual">) => crear<Periodo>("periodos", datos),
    editar: (id: number, datos: Omit<Periodo, "id" | "actual">) => cambiar<Periodo>(`periodos/${id}`, datos),
    eliminar: (id: number) => borrar(`periodos/${id}`),
  },

  usuarios: {
    listar: (filtros: { rol?: Rol; activo?: boolean } = {}) =>
      obtener<Cuenta[]>("usuarios", { rol: filtros.rol, activo: filtros.activo === undefined ? undefined : filtros.activo ? "1" : "0" }),
    crear: (datos: Omit<Cuenta, "id" | "activo"> & { contrasena: string }) => crear<Cuenta>("usuarios", datos),
    editar: (id: number, datos: Partial<Omit<Cuenta, "id" | "rol">>) => cambiar<Cuenta>(`usuarios/${id}`, datos),
    restablecerContrasena: (id: number, contrasena: string) => cambiar<void>(`usuarios/${id}/contrasena`, { contrasena }),
    eliminar: (id: number) => borrar(`usuarios/${id}`),
  },

  alumnos: {
    buscar: (filtros: { q?: string; carrera?: string; semestre?: number; grupo?: string; turno?: Turno; activo?: boolean; pagina?: number; porPagina?: number } = {}) =>
      obtener<Paginacion & { alumnos: Alumno[] }>("alumnos", { ...filtros, activo: filtros.activo === false ? "0" : undefined }),
    uno: (matricula: string) => obtener<AlumnoConCursos>(`alumnos/${encodeURIComponent(matricula)}`),
    crear: (datos: DatosDeAlumno) => crear<Alumno>("alumnos", datos),
    editar: (matricula: string, datos: Partial<DatosDeAlumno>) => cambiar<Alumno>(`alumnos/${encodeURIComponent(matricula)}`, datos),
    eliminar: (matricula: string) => borrar(`alumnos/${encodeURIComponent(matricula)}`),
    /** Todo o nada: si un renglón tiene error, la respuesta es un ErrorDeApi con `datos.renglones`. */
    cargarLote: (alumnos: DatosDeAlumno[]) => crear<{ creados: number; actualizados: number }>("alumnos/lote", { alumnos }),
    cambioMasivo: (datos: { carrera: string; semestre: number; grupo?: string; nuevoSemestre?: number; nuevoGrupo?: string; nuevoTurno?: Turno }) =>
      crear<{ actualizados: number }>("alumnos/cambio-masivo", datos),
  },

  materias: {
    listar: (filtros: { q?: string; carrera?: string; semestre?: number; activa?: boolean } = {}) =>
      obtener<Materia[]>("materias", { ...filtros, activa: filtros.activa === false ? "0" : undefined }),
    crear: (datos: { clave?: string | null; nombre: string; carrera?: string | null; semestre?: number | null }) => crear<Materia>("materias", datos),
    editar: (id: number, datos: Partial<{ clave: string | null; nombre: string; carrera: string | null; semestre: number | null; activa: boolean }>) =>
      cambiar<Materia>(`materias/${id}`, datos),
    eliminar: (id: number) => borrar(`materias/${id}`),
  },

  practicas: {
    /** Las comunes y las del docente (al docente con sesión le llegan las suyas). */
    deMateria: (materiaId: number, filtros: { docente?: number; inactivas?: boolean } = {}) => obtener<Practica[]>(`materias/${materiaId}/practicas`, filtros),
    crear: (materiaId: number, datos: DatosDePractica) => crear<Practica>(`materias/${materiaId}/practicas`, datos),
    editar: (id: number, datos: Partial<DatosDePractica>) => cambiar<Practica>(`practicas/${id}`, datos),
    /** Si ya se usó en una clase, la API la desactiva en lugar de borrarla. */
    eliminar: (id: number) => borrar(`practicas/${id}`),
  },

  cursos: {
    /** Sin periodo, los del periodo actual. */
    listar: (filtros: { periodo?: number | "todos"; docente?: number | "yo"; materia?: number; laboratorio?: string } = {}) => obtener<Curso[]>("cursos", filtros),
    uno: (id: number) => obtener<Curso>(`cursos/${id}`),
    crear: (datos: DatosDeCurso) => crear<Curso>("cursos", datos),
    editar: (id: number, datos: Partial<DatosDeCurso>) => cambiar<Curso>(`cursos/${id}`, datos),
    eliminar: (id: number) => borrar(`cursos/${id}`),
    inscritos: (id: number) => obtener<Inscrito[]>(`cursos/${id}/alumnos`),
    inscribir: (id: number, matriculas: string[]) => crear<ResultadoDeInscripcion>(`cursos/${id}/alumnos`, { matriculas }),
    darDeBaja: (id: number, matricula: string) => borrar(`cursos/${id}/alumnos/${encodeURIComponent(matricula)}`),
    asistencia: (id: number) => obtener<ResumenDeAsistencia>(`cursos/${id}/asistencia`),
  },

  horarios: {
    listar: (filtros: { laboratorio?: string; periodo?: number; docente?: number | "yo"; curso?: number } = {}) => obtener<Horario[]>("horarios", filtros),
    crear: (datos: { cursoId: number; laboratorio: string; dia: number; horaInicio: string; horaFin: string }) => crear<Horario>("horarios", datos),
    editar: (id: number, datos: Partial<{ cursoId: number; laboratorio: string; dia: number; horaInicio: string; horaFin: string }>) => cambiar<Horario>(`horarios/${id}`, datos),
    eliminar: (id: number) => borrar(`horarios/${id}`),
  },

  equipos: {
    deLaboratorio: (laboratorio: string) => obtener<Equipo[]>(`laboratorios/${encodeURIComponent(laboratorio)}/equipos`),
    agregar: (laboratorio: string, etiqueta: string) => crear<Equipo>(`laboratorios/${encodeURIComponent(laboratorio)}/equipos`, { etiqueta }),
    /** Deja el laboratorio con PC-1 a PC-cantidad. */
    configurar: (laboratorio: string, cantidad: number) =>
      crear<{ equipos: number; agregados: number; retirados: number }>(`laboratorios/${encodeURIComponent(laboratorio)}/equipos/configurar`, { cantidad }),
    editar: (id: number, datos: { fueraDeServicio?: boolean; notas?: string | null; etiqueta?: string }) =>
      cambiar<{ id: number; etiqueta: string; fueraDeServicio: boolean; notas: string | null }>(`equipos/${id}`, datos),
    retirar: (id: number) => borrar(`equipos/${id}`),
  },

  reportesDeEquipo: {
    listar: (filtros: { laboratorio?: string; estado?: EstadoDeReporte | "abiertos"; equipo?: number } = {}) => obtener<ReporteDeEquipo[]>("reportes-de-equipo", filtros),
    reportar: (equipoId: number, descripcion: string, fueraDeServicio = true) => crear<ReporteDeEquipo>(`equipos/${equipoId}/reportes`, { descripcion, fueraDeServicio }),
    actualizar: (id: number, datos: { estado?: EstadoDeReporte; comentario?: string | null }) => cambiar<ReporteDeEquipo>(`reportes-de-equipo/${id}`, datos),
  },

  clases: {
    /** Pública: lo que necesita la página de registro. */
    abiertas: (laboratorio: string) => obtener<ClaseAbierta[]>("clases/abiertas", { laboratorio }),
    listar: (filtros: { laboratorio?: string; estado?: "abiertas" | "cerradas"; tipo?: TipoDeClase; desde?: string; hasta?: string; docente?: number | "yo"; curso?: number; conAsistentes?: boolean; pagina?: number; porPagina?: number } = {}) =>
      obtener<Paginacion & { clases: Clase[] }>("clases", filtros),
    una: (id: number) => obtener<Clase>(`clases/${id}`),
    abrir: (datos: { cursoId: number; laboratorio?: string; practicaId?: number | null }) => crear<Clase>("clases", { tipo: "regular", ...datos }),
    abrirDeInvitado: (datos: { laboratorio: string; materia: string; practica?: string | null; docente?: string | null; departamento?: string | null; grupo?: string | null }) =>
      crear<Clase>("clases", { tipo: "invitado", ...datos }),
    cerrar: (id: number) => crear<Clase>(`clases/${id}/cerrar`),
    /** Para cuando el docente cierra la pestaña: la petición sigue aunque la página ya no exista. */
    cerrarAlSalir: (id: number) =>
      pedir(`clases/${id}/cerrar`, { metodo: "POST", cuerpo: { automatico: true }, keepalive: true }).catch(() => undefined),
    registrarAMano: (id: number, datos: { matricula?: string; nombre?: string; apellido?: string; equipo?: string | null; equipoPropio?: boolean }) =>
      crear<Asistencia>(`clases/${id}/asistencias`, datos),
  },

  registro: {
    /** Pública. Los rechazos traen un `codigo` (no_inscrito, ya_registrado, equipo_ocupado...). */
    entrar: (datos: { laboratorio: string; tipo?: TipoDePersona; matricula?: string; nombre?: string; apellido?: string; equipo?: string | null; equipoPropio?: boolean; claseId?: number; codigo?: string }) =>
      crear<EntradaRegistrada>("registro/entrada", datos),
  },

  asistencias: {
    activas: (laboratorio: string) => obtener<Asistencia[]>("asistencias/activas", { laboratorio }),
    historial: (filtros: { laboratorio?: string; desde?: string; hasta?: string; alumno?: string; carrera?: string; curso?: number; clase?: number; fueraDeClase?: boolean; pagina?: number; porPagina?: number } = {}) =>
      obtener<Paginacion & { asistencias: Asistencia[] }>("asistencias", filtros),
    usoFueraDeClase: (datos: { laboratorio: string; matricula?: string; nombre?: string; apellido?: string; equipo?: string | null; equipoPropio?: boolean }) =>
      crear<Asistencia>("asistencias", datos),
    registrarSalida: (id: number) => crear<Asistencia>(`asistencias/${id}/salida`),
    quitar: (id: number) => borrar(`asistencias/${id}`),
  },

  solicitudes: {
    /** Pública: la manda el alumno. */
    crear: (datos: { laboratorio: string; matricula: string; claseId?: number; equipo?: string | null; equipoPropio?: boolean; motivo?: string }) =>
      crear<Solicitud>("solicitudes", datos),
    listar: (filtros: { laboratorio?: string; estado?: EstadoDeSolicitud; clase?: number } = {}) => obtener<Solicitud[]>("solicitudes", filtros),
    resolver: (id: number, estado: Exclude<EstadoDeSolicitud, "pendiente">, comentario?: string) => crear<Solicitud>(`solicitudes/${id}/resolver`, { estado, comentario }),
    marcarLeida: (id: number, leida = true) => cambiar<void>(`solicitudes/${id}`, { leida }),
  },

  avisos: {
    listar: (filtros: { laboratorio?: string; tipo?: TipoDeAviso; leido?: boolean } = {}) =>
      obtener<Aviso[]>("avisos", { ...filtros, leido: filtros.leido === undefined ? undefined : filtros.leido ? "1" : "0" }),
    marcar: (id: number, leido = true) => cambiar<void>(`avisos/${id}`, { leido }),
    marcarTodos: (laboratorio?: string) => crear<{ marcados: number }>("avisos/leidos", { laboratorio }),
  },

  eventos: {
    listar: (filtros: { laboratorio?: string; desde?: string; hasta?: string } = {}) => obtener<Evento[]>("eventos", filtros),
    crear: (datos: DatosDeEvento & { laboratorio: string }) => crear<Evento>("eventos", datos),
    editar: (id: number, datos: Partial<DatosDeEvento>) => cambiar<Evento>(`eventos/${id}`, datos),
    eliminar: (id: number) => borrar(`eventos/${id}`),
  },

  bitacora: {
    consultar: (filtros: { desde?: string; hasta?: string; accion?: string; entidad?: string; usuario?: number; laboratorio?: string; q?: string; pagina?: number; porPagina?: number } = {}) =>
      obtener<Paginacion & { entradas: EntradaDeBitacora[] }>("bitacora", filtros),
  },

  estadisticas: (laboratorio?: string) => obtener<Estadisticas>("estadisticas", { laboratorio }),
}
