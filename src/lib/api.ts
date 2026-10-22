/**
 * Cliente API para el Sistema de Asistencias (Conexión con Hostinger MySQL API)
 */

const API_BASE = typeof window !== 'undefined' 
  ? (window.location.pathname.startsWith('/laboratorios') ? '/laboratorios/api' : '/laboratorios/api') 
  : (process.env.NEXT_PUBLIC_API_URL || 'https://laboratorios.soycorrecaminos.xyz/laboratorios/api');

async function fetchAPI<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
  const url = `${API_BASE}${endpoint}`;
  const defaultHeaders = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };

  const response = await fetch(url, {
    ...options,
    headers: {
      ...defaultHeaders,
      ...options.headers,
    },
  });

  const data = await response.json();
  if (!response.ok || data.success === false) {
    throw new Error(data.error || 'Error en la petición al servidor');
  }
  return data;
}

export const API = {
  // Autenticación
  login: (credentials: { email: string; password: string; role?: 'admin' | 'laboratorista' | 'docente' }) =>
    fetchAPI<{ success: boolean; user: any; role: string }>('/auth.php', {
      method: 'POST',
      body: JSON.stringify(credentials),
    }),

  // Alumnos
  getAlumnos: (search?: string) =>
    fetchAPI<{ success: boolean; data: any[] }>(`/alumnos.php${search ? `?q=${encodeURIComponent(search)}` : ''}`),
  
  getAlumno: (matricula: string) =>
    fetchAPI<{ success: boolean; data: any }>(`/alumnos.php?matricula=${encodeURIComponent(matricula)}`),
  
  saveAlumno: (alumno: any) =>
    fetchAPI<{ success: boolean; message: string }>('/alumnos.php', {
      method: 'POST',
      body: JSON.stringify(alumno),
    }),
  
  deleteAlumno: (matricula: string) =>
    fetchAPI<{ success: boolean; message: string }>(`/alumnos.php?matricula=${encodeURIComponent(matricula)}`, {
      method: 'DELETE',
    }),

  // Docentes
  getDocentes: () =>
    fetchAPI<{ success: boolean; data: any[] }>('/docentes.php'),
  
  saveDocente: (docente: any) =>
    fetchAPI<{ success: boolean; message: string }>('/docentes.php', {
      method: 'POST',
      body: JSON.stringify(docente),
    }),
  
  deleteDocente: (matricula: string) =>
    fetchAPI<{ success: boolean; message: string }>(`/docentes.php?matricula=${encodeURIComponent(matricula)}`, {
      method: 'DELETE',
    }),

  // Laboratoristas
  getLaboratoristas: () =>
    fetchAPI<{ success: boolean; data: any[] }>('/laboratoristas.php'),
  
  saveLaboratorista: (lab: any) =>
    fetchAPI<{ success: boolean; message: string }>('/laboratoristas.php', {
      method: 'POST',
      body: JSON.stringify(lab),
    }),
  
  deleteLaboratorista: (matricula: string) =>
    fetchAPI<{ success: boolean; message: string }>(`/laboratoristas.php?matricula=${encodeURIComponent(matricula)}`, {
      method: 'DELETE',
    }),

  // Materias & Prácticas
  getMaterias: (conPracticas = false) =>
    fetchAPI<{ success: boolean; data: any[] }>(`/materias.php${conPracticas ? '?con_practicas=1' : ''}`),
  
  getMateria: (id: string) =>
    fetchAPI<{ success: boolean; data: any }>(`/materias.php?id=${encodeURIComponent(id)}`),
  
  saveMateria: (materia: any) =>
    fetchAPI<{ success: boolean; message: string; id: string }>('/materias.php', {
      method: 'POST',
      body: JSON.stringify(materia),
    }),
  
  savePractica: (practica: { materia_id: string; numero_practica?: string; titulo: string; descripcion?: string; duracion?: string }) =>
    fetchAPI<{ success: boolean; message: string }>('/materias.php?action=crear_practica', {
      method: 'POST',
      body: JSON.stringify(practica),
    }),
  
  deleteMateria: (id: string) =>
    fetchAPI<{ success: boolean; message: string }>(`/materias.php?id=${encodeURIComponent(id)}`, {
      method: 'DELETE',
    }),
  
  deletePractica: (id: string | number) =>
    fetchAPI<{ success: boolean; message: string }>(`/materias.php?action=practica&id=${id}`, {
      method: 'DELETE',
    }),

  // Equipos
  getEquipos: (laboratorio = 'programacion') =>
    fetchAPI<{ success: boolean; data: any[] }>(`/equipos.php?laboratorio=${encodeURIComponent(laboratorio)}`),
  
  updateEquipo: (equipo: { id: string; laboratorio?: string; fueraDeServicio?: boolean; enUso?: boolean; usuarioActual?: string | null; notas?: string; estadoReparacion?: string; comentarioEstado?: string }) =>
    fetchAPI<{ success: boolean; message: string }>('/equipos.php', {
      method: 'POST',
      body: JSON.stringify(equipo),
    }),

  // Asistencias
  getAsistenciasActivas: (laboratorio = 'programacion') =>
    fetchAPI<{ success: boolean; data: any[] }>(`/asistencias.php?action=activas&laboratorio=${encodeURIComponent(laboratorio)}`),
  
  registrarEntrada: (registro: { AlumnoId: string; Nombre: string; Apellido: string; Carrera?: string; Semestre?: string; Turno?: string; Grupo?: string; Equipo: string; MateriaId?: string; Laboratorio?: string; TipoUsuario?: string }) =>
    fetchAPI<{ success: boolean; message: string; id: number }>('/asistencias.php?action=entrada', {
      method: 'POST',
      body: JSON.stringify(registro),
    }),
  
  registrarSalida: (params: { id?: string | number; equipo?: string; laboratorio?: string }) =>
    fetchAPI<{ success: boolean; message: string }>('/asistencias.php?action=salida', {
      method: 'POST',
      body: JSON.stringify(params),
    }),
  
  getAsistenciasHistorico: (filtros: { fecha_inicio?: string; fecha_fin?: string; carrera?: string; materia?: string; laboratorio?: string } = {}) => {
    const params = new URLSearchParams({ action: 'historico', ...(filtros as any) });
    return fetchAPI<{ success: boolean; data: any[] }>(`/asistencias.php?${params.toString()}`);
  },

  // Solicitudes
  getSolicitudes: (laboratorio = 'programacion', estado?: string) =>
    fetchAPI<{ success: boolean; data: any[] }>(`/solicitudes.php?laboratorio=${encodeURIComponent(laboratorio)}${estado ? `&estado=${estado}` : ''}`),
  
  crearSolicitud: (solicitud: { alumno_id: string; nombre: string; carrera?: string; motivo: string; laboratorio?: string }) =>
    fetchAPI<{ success: boolean; message: string }>('/solicitudes.php', {
      method: 'POST',
      body: JSON.stringify(solicitud),
    }),
  
  actualizarSolicitud: (id: number, estado: 'aprobada' | 'rechazada') =>
    fetchAPI<{ success: boolean; message: string }>('/solicitudes.php', {
      method: 'PUT',
      body: JSON.stringify({ id, estado }),
    }),

  // Notificaciones
  getNotificaciones: (laboratorio = 'programacion') =>
    fetchAPI<{ success: boolean; data: any[] }>(`/notificaciones.php?laboratorio=${encodeURIComponent(laboratorio)}`),
  
  marcarNotificacionLeida: (id?: number) =>
    fetchAPI<{ success: boolean; message: string }>('/notificaciones.php', {
      method: 'PUT',
      body: JSON.stringify({ id }),
    }),

  // Estadísticas
  getStats: (laboratorio = 'programacion') =>
    fetchAPI<{ success: boolean; data: any }>(`/stats.php?laboratorio=${encodeURIComponent(laboratorio)}`),
};
