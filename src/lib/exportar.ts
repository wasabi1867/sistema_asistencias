import type { Asistencia, Clase } from "./api"
import { alertas } from "./alertas"
import { fecha, hora, turno } from "./formato"

/**
 * La hoja de registro de una clase, en PDF (para imprimir y firmar) y en Excel. La
 * usan el docente, el laboratorista y los reportes, así que vive en un solo lugar.
 */
export interface HojaDeRegistro {
  laboratorio: string
  fecha: string
  hora: string
  materia: string
  practica?: string | null
  docente?: string | null
  grupo?: string | null
  carrera?: string | null
  semestre?: string | number | null
  turno?: string | null
  horaFin?: string | null
  personas: {
    matricula?: string | null
    nombre: string
    apellido?: string | null
    equipo?: string | null
    carrera?: string | null
    grupo?: string | null
    semestre?: string | number | null
    turno?: string | null
  }[]
}

const FILAS_MINIMAS = 25

/**
 * La hoja de una clase. Carrera, semestre y turno salen de lo que más se repite entre
 * quienes asistieron (en una clase regular, todos son del mismo grupo).
 */
export function hojaDeClase(clase: Clase, personas: Asistencia[] = clase.personas ?? []): HojaDeRegistro {
  const masComun = (valores: (string | number | null | undefined)[]) => {
    const cuenta = new Map<string, number>()
    for (const valor of valores) {
      if (valor !== null && valor !== undefined && valor !== "") cuenta.set(String(valor), (cuenta.get(String(valor)) ?? 0) + 1)
    }
    return [...cuenta.entries()].sort((a, b) => b[1] - a[1])[0]?.[0] ?? null
  }
  return {
    laboratorio: clase.laboratorio.nombre,
    fecha: fecha(clase.abiertaEn),
    hora: hora(clase.abiertaEn),
    horaFin: hora(clase.cerradaEn),
    materia: clase.materia,
    practica: clase.practica?.titulo,
    docente: clase.docente,
    grupo: clase.grupo,
    carrera: masComun(personas.map((p) => p.carrera?.nombre)),
    semestre: masComun(personas.map((p) => p.semestre)),
    turno: turno(masComun(personas.map((p) => p.turno))),
    personas: [...personas]
      .sort((a, b) => `${a.apellido ?? ""} ${a.nombre}`.localeCompare(`${b.apellido ?? ""} ${b.nombre}`, "es", { sensitivity: "base" }))
      .map((p) => ({
        matricula: p.matricula,
        nombre: p.nombre,
        apellido: p.apellido,
        equipo: p.equipo,
        carrera: p.carrera?.nombre,
        grupo: p.grupo,
        semestre: p.semestre,
        turno: turno(p.turno),
      })),
  }
}

export const hojaDeRegistroPdf = (hoja: HojaDeRegistro, archivo = "lista_asistencias.pdf") =>
  descargar(async () => {
    const { jsPDF, autoTable } = await cargarPdf()
    const doc = new jsPDF("p", "mm", "letter")
    const ancho = doc.internal.pageSize.getWidth()
    const margen = 10

    const logo = await imagenComoDatos("/FondoItspp.png")
    if (logo) {
      doc.addImage(logo, "PNG", margen, margen, 25, 25)
    }
    doc.setFontSize(16)
    doc.setTextColor(0, 0, 0)
    doc.text(hoja.laboratorio.toUpperCase(), ancho / 2, margen + 10, { align: "center" })
    doc.setFontSize(14)
    doc.text("HOJA DE REGISTRO", ancho / 2, margen + 18, { align: "center" })

    doc.setFontSize(10)
    const y = margen + 35
    const derecha = ancho / 2 + 5
    doc.text(`FECHA: ${hoja.fecha}`, margen, y)
    doc.text(`GRUPO: ${hoja.grupo ?? ""}`, margen, y + 7)
    doc.text(`HORA: ${hoja.hora}`, margen, y + 14)
    doc.text(`MATERIA: ${hoja.materia}`, margen, y + 21)
    doc.text(`DOCENTE: ${hoja.docente ?? ""}`, margen, y + 28)
    doc.text(`CARRERA: ${hoja.carrera ?? ""}`, derecha, y)
    doc.text(`TURNO: ${hoja.turno ?? ""}`, derecha, y + 7)
    doc.text(`PRÁCTICA: ${hoja.practica ?? ""}`, derecha, y + 14)
    doc.text(`SEMESTRE: ${hoja.semestre ?? ""}`, derecha, y + 21)

    const filas: (string | number)[][] = hoja.personas.map((p, i) => [i + 1, `${p.nombre} ${p.apellido ?? ""}`.trim(), p.equipo ?? "Propio"])
    while (filas.length < FILAS_MINIMAS) {
      filas.push([filas.length + 1, "", ""])
    }
    autoTable(doc, {
      head: [["#", "NOMBRE DEL ALUMNO", "NÚM. PC"]],
      body: filas,
      startY: y + 40,
      theme: "grid",
      styles: { fontSize: 11, halign: "center", valign: "middle", textColor: [0, 0, 0], lineColor: [120, 120, 120], lineWidth: 0.3 },
      headStyles: { fillColor: [149, 41, 82], textColor: [255, 255, 255], fontStyle: "bold" },
      columnStyles: { 0: { cellWidth: 15, fontStyle: "bold" }, 1: { cellWidth: "auto" }, 2: { cellWidth: 25 } },
      alternateRowStyles: { fillColor: [245, 245, 245] },
    })

    const firmas = finDeLaTabla(doc, y + 120) + 20
    doc.line(margen, firmas, margen + 70, firmas)
    doc.text("FIRMA DOCENTE", margen + 35, firmas + 5, { align: "center" })
    doc.line(ancho - margen - 70, firmas, ancho - margen, firmas)
    doc.text("FIRMA ENCARGADO LAB.", ancho - margen - 35, firmas + 5, { align: "center" })

    doc.save(archivo)
  })

export const hojaDeRegistroExcel = (hoja: HojaDeRegistro, archivo = `control_asistencia_${new Date().toISOString().slice(0, 10)}.xlsx`) =>
  descargar(async () => {
    const XLSX = await cargarExcel()
    const encabezado = [
      ["INSTITUTO TECNOLÓGICO SUPERIOR DE PUERTO PEÑASCO"],
      [`CONTROL DE ASISTENCIA - ${hoja.laboratorio.toUpperCase()}`],
      [""],
      ["Información de la clase"],
      ["Fecha:", hoja.fecha, "", "Carrera:", hoja.carrera ?? ""],
      ["Grupo:", hoja.grupo ?? "", "", "Materia:", hoja.materia],
      ["Práctica:", hoja.practica ?? "", "", "Docente:", hoja.docente ?? ""],
      ["Hora de inicio:", hoja.hora, "", "Hora de fin:", hoja.horaFin ?? ""],
      [""],
      ["Lista de asistencia"],
      ["#", "Matrícula", "Nombre", "Apellido", "Carrera", "Grupo", "Semestre", "Turno", "Equipo"],
    ]
    const renglones = hoja.personas.map((p, i) => [
      i + 1, p.matricula ?? "", p.nombre, p.apellido ?? "", p.carrera ?? "", p.grupo ?? "", p.semestre ?? "", p.turno ?? "", p.equipo ?? "Propio",
    ])
    const hojaDeCalculo = XLSX.utils.aoa_to_sheet([...encabezado, ...renglones])
    hojaDeCalculo["!cols"] = [5, 15, 20, 20, 30, 10, 10, 12, 10].map((wch) => ({ wch }))
    hojaDeCalculo["!merges"] = [0, 1, 3].map((r) => ({ s: { r, c: 0 }, e: { r, c: 8 } }))

    const libro = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(libro, hojaDeCalculo, "Control de asistencia")
    XLSX.writeFile(libro, archivo)
  })

/** Los datos del encabezado del reporte de uso del laboratorio (formato del ITSPP). */
export interface EncabezadoDeReporte {
  division: string
  subdireccion: string
  laboratorio: string
  responsable: string
  periodo: string
}

/** Una fila por clase: fecha, docente, materia, práctica, grupo y semestre, alumnos. */
function filasDeUso(clases: Clase[]): string[][] {
  return clases.map((clase) => {
    const grupos = [...new Set((clase.personas ?? []).filter((p) => p.grupo || p.semestre).map((p) => `${p.grupo ?? ""} (${p.semestre ?? ""})`.trim()))]
    return [fecha(clase.abiertaEn), clase.docente ?? "", clase.materia, clase.practica?.titulo ?? "", clase.grupo ?? grupos.join(", "), String(clase.asistentes)]
  })
}

/** El reporte de uso del laboratorio en un periodo, con el formato del ITSPP. */
export const reporteDeUsoPdf = (encabezado: EncabezadoDeReporte, clases: Clase[]) =>
  descargar(async () => {
    const { jsPDF, autoTable } = await cargarPdf()
    const doc = new jsPDF({ orientation: "landscape", unit: "mm", format: "a4" })
    const ancho = doc.internal.pageSize.getWidth()
    const alto = doc.internal.pageSize.getHeight()
    const [logo, logoSistemas] = await Promise.all([imagenComoDatos("/FondoItspp.png"), imagenComoDatos("/LogoSistemas.png")])
    if (logo) doc.addImage(logo, "PNG", 5, 5, 40, 40)
    if (logoSistemas) doc.addImage(logoSistemas, "PNG", ancho - 70, 10, 50, 40)

    doc.setFont("helvetica", "bold")
    doc.setFontSize(12)
    doc.text("INSTITUTO TECNOLÓGICO SUPERIOR DE PUERTO PEÑASCO", ancho / 2, 20, { align: "center" })
    doc.setFontSize(10)
    doc.text(encabezado.division, ancho / 2, 26, { align: "center" })
    doc.text(encabezado.subdireccion, ancho / 2, 32, { align: "center" })
    doc.text(encabezado.laboratorio.toUpperCase(), ancho / 2, 38, { align: "center" })
    doc.text("PERIODO:", 20, 50)
    doc.text(encabezado.periodo.toUpperCase(), 70, 50)

    autoTable(doc, {
      head: [["FECHA", "DOCENTE", "MATERIA", "PRÁCTICA", "GRUPO Y SEMESTRE", "NUM. DE ALUMNOS"]],
      body: filasDeUso(clases),
      startY: 60,
      theme: "grid",
      styles: { fontSize: 8, cellPadding: 3, textColor: [0, 0, 0], lineColor: [120, 120, 120], lineWidth: 0.3 },
      headStyles: { fillColor: [149, 41, 82], textColor: [255, 255, 255], fontStyle: "bold", halign: "center" },
      columnStyles: { 0: { cellWidth: 20 }, 1: { cellWidth: 40 }, 2: { cellWidth: 40 }, 3: { cellWidth: 50 }, 4: { cellWidth: 40 }, 5: { cellWidth: 20, halign: "center" } },
      alternateRowStyles: { fillColor: [245, 245, 245] },
    })

    const total = clases.reduce((suma, clase) => suma + clase.asistentes, 0)
    const y = finDeLaTabla(doc, 80) + 10
    doc.text("PROMEDIO DE NÚMERO DE ALUMNOS:", 20, y)
    doc.text(clases.length ? (total / clases.length).toFixed(1) : "0", 100, y)
    doc.text("NÚMERO TOTAL DE PRÁCTICAS:", 20, y + 7)
    doc.text(String(clases.length), 100, y + 7)
    doc.text("NOMBRE Y FIRMA DE RESPONSABLE DE LABORATORIO", ancho / 2, y + 30, { align: "center" })
    doc.line(ancho / 2 - 40, y + 50, ancho / 2 + 40, y + 50)
    doc.text(encabezado.responsable, ancho / 2, y + 60, { align: "center" })
    doc.setFontSize(8)
    doc.text("SELLO EDUCACIÓN", 50, alto - 20)

    doc.save(`Reporte_Laboratorio_${encabezado.periodo.replace(/\W+/g, "_")}.pdf`)
  })

export function reporteDeUsoExcel(encabezado: EncabezadoDeReporte, clases: Clase[]) {
  const total = clases.reduce((suma, clase) => suma + clase.asistentes, 0)
  return tablasExcel(
    [
      {
        nombre: "Reporte Laboratorio",
        filas: [
          ["INSTITUTO TECNOLÓGICO SUPERIOR DE PUERTO PEÑASCO"],
          [encabezado.division],
          [encabezado.subdireccion],
          [encabezado.laboratorio.toUpperCase()],
          [],
          ["PERIODO:", encabezado.periodo.toUpperCase()],
          [],
          ["FECHA", "DOCENTE", "MATERIA", "PRÁCTICA", "GRUPO Y SEMESTRE", "NUM. DE ALUMNOS"],
          ...filasDeUso(clases),
          [],
          ["TOTAL DE PRÁCTICAS:", clases.length],
          ["TOTAL DE ALUMNOS ATENDIDOS:", total],
          ["PROMEDIO DE ALUMNOS POR PRÁCTICA:", clases.length ? Math.round(total / clases.length) : 0],
          [],
          ["NOMBRE Y FIRMA DE RESPONSABLE DE LABORATORIO:", encabezado.responsable],
        ],
      },
    ],
    `Reporte_Laboratorio_${encabezado.periodo.replace(/\W+/g, "_")}.xlsx`,
  )
}

/** Descarga un Excel con una hoja por cada tabla. Las celdas van tal cual. */
export const tablasExcel = (hojas: { nombre: string; filas: (string | number | null)[][] }[], archivo: string) =>
  descargar(async () => {
    const XLSX = await cargarExcel()
    const libro = XLSX.utils.book_new()
    for (const hoja of hojas) {
      XLSX.utils.book_append_sheet(libro, XLSX.utils.aoa_to_sheet(hoja.filas), hoja.nombre.slice(0, 31))
    }
    XLSX.writeFile(libro, archivo)
  })

/** Lee la primera hoja de un archivo de Excel o CSV como renglones de texto. Si falla, el error llega a quien la llama. */
export async function leerHojaDeCalculo(archivo: File): Promise<string[][]> {
  const XLSX = await cargarExcel()
  const libro = XLSX.read(await archivo.arrayBuffer(), { type: "array" })
  const hoja = libro.Sheets[libro.SheetNames[0]]
  const renglones = XLSX.utils.sheet_to_json<unknown[]>(hoja, { header: 1, blankrows: false, defval: "" })
  return renglones.map((r) => r.map((celda) => String(celda ?? "").trim()))
}

// jsPDF y SheetJS pesan cerca de 400 kB: se descargan la primera vez que alguien exporta
// o lee un archivo, no cada vez que se abre una pantalla del personal.
async function cargarPdf() {
  const [{ default: jsPDF }, { default: autoTable }] = await Promise.all([import("jspdf"), import("jspdf-autotable")])
  return { jsPDF, autoTable }
}

const cargarExcel = () => import("xlsx")

/** Genera y descarga un archivo. Si algo falla (por ejemplo, sin red), lo avisa en lugar de no hacer nada. */
async function descargar(trabajo: () => Promise<void>) {
  try {
    await trabajo()
  } catch (error) {
    await alertas.error("No se pudo generar el archivo", error)
  }
}

/** Dónde terminó la última tabla que dibujó autoTable, para seguir escribiendo debajo. */
function finDeLaTabla(doc: object, porOmision: number): number {
  return (doc as { lastAutoTable?: { finalY?: number } }).lastAutoTable?.finalY ?? porOmision
}

/** jsPDF necesita la imagen ya cargada, no su dirección. Sin imagen, el PDF sale sin logo. */
async function imagenComoDatos(ruta: string): Promise<string | null> {
  try {
    const blob = await (await fetch(ruta)).blob()
    return await new Promise((resolver) => {
      const lector = new FileReader()
      lector.onload = () => resolver(String(lector.result))
      lector.onerror = () => resolver(null)
      lector.readAsDataURL(blob)
    })
  } catch {
    return null
  }
}
