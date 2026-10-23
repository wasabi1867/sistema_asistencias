// Prueba de las pantallas de punta a punta: un navegador de verdad contra el sitio
// compilado (out/) y la API, servidos por PHP. Cada persona usa su propio contexto: la
// computadora del laboratorio, el teléfono del alumno, el administrador y el laboratorista.
//
// No se corre sola: la arranca tests/pantallas/correr.js, que prepara la base, los datos
// y el servidor (ver docs/pantallas.md, "Prueba de las pantallas").
const { chromium } = require("playwright-core")
const XLSX = require("xlsx")
const fs = require("fs")
const path = require("path")

const { admin: cuentaAdmin } = require("./cuentas")

const BASE = process.env.BASE || "http://127.0.0.1:8080"
const SALIDA = path.join(__dirname, "capturas")
// No se descarga ningún navegador: se usa el instalado (Edge en Windows, Chrome en GitHub).
const NAVEGADOR = process.env.NAVEGADOR || (process.platform === "win32" ? "msedge" : "chrome")
const problemas = []
const advertencias = []

function vigilar(page, nombre) {
  page.on("pageerror", (e) => problemas.push(`[${nombre}] error de la página: ${e.message}`))
  page.on("console", (m) => {
    // Los rechazos esperados de la API (409, 401...) salen como "Failed to load resource";
    // los errores del servidor se vigilan aparte, con las respuestas 5xx.
    if (m.type() === "error" && !m.text().startsWith("Failed to load resource")) problemas.push(`[${nombre}] consola: ${m.text()}`)
    if (m.type() === "warning") advertencias.push(`[${nombre}] ${m.text().split("\n")[0]}`)
  })
  page.on("response", (r) => {
    if (r.status() >= 500) problemas.push(`[${nombre}] ${r.status()} en ${r.request().method()} ${r.url()}`)
  })
  page.on("dialog", (d) => d.accept())
}

async function alerta(page, titulo) {
  try {
    await page.getByRole("alertdialog", { name: titulo }).waitFor({ timeout: 15000 })
  } catch {
    const actual = await page.getByRole("alertdialog").innerText({ timeout: 1000 }).catch(() => "(ninguna alerta)")
    throw new Error(`Esperaba la alerta "${titulo}"; en pantalla: ${actual}`)
  }
}

const botonAceptar = (page) => page.getByRole("alertdialog").locator("[data-accion=aceptar]")

async function aceptar(page, titulo) {
  await alerta(page, titulo)
  await botonAceptar(page).click()
  await page.getByRole("alertdialog", { name: titulo }).waitFor({ state: "detached" })
}

async function breve(page, titulo) {
  await page.locator('[role="status"]').getByText(titulo).first().waitFor({ timeout: 15000 })
}

async function paginaVisibleParaLectores(page, donde) {
  const oculto = await page.evaluate(() => document.getElementById("__next").getAttribute("aria-hidden"))
  if (oculto === "true") throw new Error(`${donde}: la página quedó con aria-hidden`)
}

async function elegir(page, disparador, opcion) {
  await page.locator(disparador).click()
  await page.getByRole("option", { name: opcion, exact: true }).click()
}

async function sinAlertaDeError(page, donde) {
  const error = page.locator('[role="alertdialog"][data-tipo="error"]')
  if (await error.count()) {
    throw new Error(`${donde}: salió una alerta de error: ${await error.innerText()}`)
  }
  // Botones y campos sin nombre: un lector de pantalla no sabría decir qué hacen.
  const sinNombre = await page.evaluate(() => {
    const nombre = (e) => (e.getAttribute("aria-label") || e.getAttribute("title") || e.textContent || "").trim() || (e.id && document.querySelector(`label[for="${e.id}"]`)?.textContent.trim()) || e.closest("label")?.textContent.trim()
    return [...document.querySelectorAll("button, input:not([type=hidden]), textarea")]
      .filter((e) => e.offsetParent !== null && !nombre(e))
      .map((e) => e.outerHTML.slice(0, 140))
  })
  for (const html of sinNombre) advertencias.push(`[sin nombre accesible en ${donde}] ${html}`)
}

let numero = 0
async function paso(nombre, trabajo) {
  numero++
  process.stdout.write(`${String(numero).padStart(2, "0")}. ${nombre}... `)
  const inicio = Date.now()
  await trabajo()
  console.log(`bien (${Date.now() - inicio} ms)`)
}


/** En el teléfono nada debe salirse de la pantalla hacia los lados. */
async function sinDesbordeHorizontal(page, donde) {
  const ancho = await page.evaluate(() => [document.documentElement.scrollWidth, window.innerWidth])
  if (ancho[0] > ancho[1] + 1) throw new Error(`${donde}: la página mide ${ancho[0]} px de ancho en una pantalla de ${ancho[1]} px`)
}

async function entrarComo(page, rol, usuario, contrasena, ruta) {
  await page.goto(BASE + "/entrar")
  await page.getByRole("button", { name: rol, exact: true }).click()
  await page.locator("#usuario").fill(usuario)
  await page.locator("#contrasena").fill(contrasena)
  await page.getByRole("button", { name: "Iniciar sesión" }).click()
  await page.waitForURL(`**${ruta}`)
}

;(async () => {
  // correr.js la vacía antes de empezar; aquí ya está el registro del servidor.
  fs.mkdirSync(SALIDA, { recursive: true })
  const navegador = await chromium.launch({ channel: NAVEGADOR, headless: true })
  const nuevo = async (nombre, opciones = {}) => {
    const contexto = await navegador.newContext({ acceptDownloads: true, viewport: { width: 1366, height: 900 }, locale: "es-MX", timezoneId: "America/Hermosillo", ...opciones })
    const page = await contexto.newPage()
    vigilar(page, nombre)
    return page
  }
  const captura = (page, nombre, completa = true) => page.screenshot({ path: path.join(SALIDA, `${nombre}.png`), fullPage: completa })
  const telefonoComo = { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true }

  try {
    // La computadora del laboratorio
    const compu = await nuevo("computadora")
    await paso("elegir el laboratorio en la computadora", async () => {
      await compu.goto(BASE + "/")
      await captura(compu, "00-elegir-laboratorio")
      await compu.getByRole("button", { name: /Taller de Programación/ }).click()
      await compu.getByText("No hay ninguna clase abierta").waitFor()
      await compu.getByText("El registro se abre con la clase").waitFor()
    })
    await captura(compu, "01-registro-sin-clase")

    await paso("el docente entra desde «Personal»", async () => {
      await compu.getByRole("link", { name: /Personal/ }).click()
      await compu.waitForURL("**/entrar")
      await compu.getByText("Tu clase se abrirá en Taller de Programación").waitFor()
      await captura(compu, "02-entrar")
      await compu.locator("#usuario").fill("D001")
      await compu.locator("#contrasena").fill("docente-local-2026")
      await compu.getByRole("button", { name: "Iniciar sesión" }).click()
      await compu.waitForURL("**/docente")
      await breve(compu, "¡Bienvenido, María!")
    })

    await paso("el docente elige la práctica y abre la clase", async () => {
      await compu.getByRole("heading", { name: "¿Qué práctica toca hoy?" }).waitFor()
      await captura(compu, "03-preparar-clase")
      await compu.getByRole("button", { name: /Maquetado con HTML y CSS/ }).click()
      await compu.getByRole("button", { name: "Abrir la clase" }).click()
      await breve(compu, "Clase abierta")
      await compu.getByText("Han entrado", { exact: true }).waitFor()
      await compu.getByText("Todavía no ha entrado nadie").waitFor()
      const avance = await compu.getByRole("progressbar").getAttribute("aria-valuenow")
      if (avance !== "0") throw new Error(`La barra debería estar en 0 y está en ${avance}`)
    })
    await captura(compu, "04-clase-abierta")

    // El teléfono del alumno
    const telefono = await nuevo("teléfono", telefonoComo)
    await paso("un alumno inscrito registra su entrada desde el teléfono", async () => {
      await telefono.goto(BASE + "/")
      await telefono.getByRole("button", { name: /Taller de Programación/ }).click()
      await telefono.getByRole("heading", { name: "¿Quién eres?" }).waitFor({ timeout: 15000 })
      await sinDesbordeHorizontal(telefono, "registro en el teléfono")
      await captura(telefono, "05-telefono-paso-1")
      await telefono.locator("#matricula").fill("26303001")
      await telefono.getByRole("button", { name: /Continuar/ }).click()
      await telefono.getByRole("heading", { name: "¿Qué computadora vas a usar?" }).waitFor()
      await telefono.getByRole("button", { name: "PC-3, libre" }).click()
      await captura(telefono, "06-telefono-paso-2")
      await sinDesbordeHorizontal(telefono, "elegir computadora en el teléfono")
      await telefono.getByRole("button", { name: "Registrar entrada en PC-3" }).click()
      await telefono.getByRole("heading", { name: /¡Listo, Ana Sofía!/ }).waitFor()
      const foco = await telefono.evaluate(() => document.activeElement?.textContent)
      if (!foco?.includes("¡Listo")) throw new Error("El foco no pasó a la confirmación")
    })
    await captura(telefono, "07-telefono-listo")

    await paso("la confirmación se quita sola y la pantalla queda lista para el siguiente", async () => {
      await telefono.getByRole("heading", { name: "¿Quién eres?" }).waitFor({ timeout: 10000 })
    })

    await paso("el mismo alumno no puede registrarse dos veces", async () => {
      await telefono.locator("#matricula").fill("26303001")
      await telefono.getByRole("button", { name: /Continuar/ }).click()
      await telefono.getByRole("button", { name: "PC-4, libre" }).click()
      await telefono.getByRole("button", { name: "Registrar entrada en PC-4" }).click()
      await aceptar(telefono, "Ya registraste tu entrada")
      await telefono.getByRole("heading", { name: "¿Quién eres?" }).waitFor()
    })

    await paso("la computadora ocupada aparece en uso y no se puede elegir", async () => {
      await telefono.locator("#matricula").fill("26303011")
      await telefono.getByRole("button", { name: /Continuar/ }).click()
      if (!(await telefono.getByRole("button", { name: "PC-3, en uso" }).isDisabled())) throw new Error("PC-3 debería estar desactivada")
    })

    await paso("un alumno no inscrito pide permiso para entrar", async () => {
      await telefono.getByRole("button", { name: "Traigo mi propio equipo" }).click()
      await telefono.getByRole("button", { name: "Registrar entrada con mi equipo" }).click()
      await alerta(telefono, "No estás inscrito en Programación Web")
      await botonAceptar(telefono).click()
      await alerta(telefono, "¿Por qué necesitas entrar?")
      await telefono.getByRole("alertdialog").locator("textarea").fill("Voy a recuperar una práctica")
      await botonAceptar(telefono).click()
      await telefono.getByRole("heading", { name: "Solicitud enviada" }).waitFor()
    })

    // El administrador
    const admin = await nuevo("administrador")
    await paso("el administrador entra desde /entrar", async () => {
      await entrarComo(admin, "Administrador", cuentaAdmin.correo, cuentaAdmin.contrasena, "/admin")
      await admin.getByRole("heading", { name: "Tablero", level: 1 }).waitFor()
      await admin.waitForTimeout(1000)
      await paginaVisibleParaLectores(admin, "después de entrar como administrador")
    })

    await paso("el tablero muestra la clase abierta y la solicitud; se aprueba por esa vez", async () => {
      await admin.getByText("Voy a recuperar una práctica").first().waitFor({ timeout: 15000 })
      await admin.locator("aside nav").getByRole("button", { name: /Tablero\s*1/ }).waitFor({ timeout: 35000 })
      await captura(admin, "08-tablero")
      await admin.getByRole("button", { name: /Dejar entrar esta vez/ }).click()
      await breve(admin, "Aprobada: ya está dentro")
      await admin.getByText("No hay solicitudes por resolver").waitFor()
    })

    // De vuelta con el docente
    await paso("el docente ve a los dos alumnos en la lista", async () => {
      await compu.getByRole("cell", { name: /^Ana Sofía/ }).waitFor({ timeout: 15000 })
      await compu.getByRole("cell", { name: /^Renata/ }).waitFor({ timeout: 15000 })
    })

    await paso("el docente registra a mano a otro alumno", async () => {
      await compu.getByRole("button", { name: /Registrar a alguien a mano/ }).click()
      await compu.locator("#matricula-a-mano").fill("26303002")
      await compu.getByRole("button", { name: "Registrar", exact: true }).click()
      await breve(compu, "Entrada registrada")
      await compu.getByRole("cell", { name: /^Luis Fernando/ }).waitFor({ timeout: 10000 })
    })
    await captura(compu, "09-clase-con-alumnos")

    await paso("el docente descarga la hoja de registro en PDF y en Excel", async () => {
      await compu.getByRole("button", { name: /Exportar/ }).click()
      const [pdf] = await Promise.all([compu.waitForEvent("download", { timeout: 20000 }), compu.getByRole("menuitem", { name: /PDF/ }).click()])
      const rutaPdf = path.join(SALIDA, pdf.suggestedFilename())
      await pdf.saveAs(rutaPdf)
      if (!fs.readFileSync(rutaPdf).subarray(0, 4).toString().startsWith("%PDF")) throw new Error("El PDF no es un PDF")
      await compu.getByRole("button", { name: /Exportar/ }).click()
      const [excel] = await Promise.all([compu.waitForEvent("download", { timeout: 20000 }), compu.getByRole("menuitem", { name: /Excel/ }).click()])
      const rutaExcel = path.join(SALIDA, excel.suggestedFilename())
      await excel.saveAs(rutaExcel)
      const filas = XLSX.utils.sheet_to_json(XLSX.readFile(rutaExcel).Sheets["Control de asistencia"], { header: 1 })
      const matriculas = filas.slice(11).map((f) => f[1])
      for (const m of ["26303001", "26303002", "26303011"]) {
        if (!matriculas.includes(m)) throw new Error(`Falta ${m} en el Excel: ${JSON.stringify(matriculas)}`)
      }
      await sinAlertaDeError(compu, "exportar")
    })

    await paso("el docente quita una entrada desde el menú del renglón", async () => {
      await compu.getByRole("button", { name: /Acciones para Luis Fernando/ }).click()
      await compu.getByRole("menuitem", { name: "Quitar entrada" }).click()
      await alerta(compu, "¿Quitar esta entrada?")
      const focoEnCancelar = await compu.evaluate(() => document.activeElement?.getAttribute("data-accion"))
      if (focoEnCancelar !== "cancelar") throw new Error("En una acción peligrosa el foco debe empezar en Cancelar")
      await botonAceptar(compu).click()
      await compu.getByRole("cell", { name: /^Luis Fernando/ }).waitFor({ state: "detached", timeout: 10000 })
      await paginaVisibleParaLectores(compu, "después de quitar una entrada desde el menú")
    })

    await paso("el docente no puede cerrar sesión con la clase abierta", async () => {
      await compu.getByRole("button", { name: /Cuenta de María/ }).click()
      if ((await compu.getByRole("menuitem", { name: "Cerrar sesión" }).getAttribute("aria-disabled")) !== "true") throw new Error("Cerrar sesión debería estar desactivado")
      await compu.getByText("Finaliza la clase antes de salir.").waitFor()
      await compu.keyboard.press("Escape")
    })

    await paso("el docente finaliza la clase", async () => {
      await compu.getByRole("button", { name: /Finalizar clase/ }).click()
      await alerta(compu, "¿Finalizar la clase?")
      await botonAceptar(compu).click()
      await aceptar(compu, "Clase finalizada")
      await compu.getByRole("heading", { name: "¿Qué práctica toca hoy?" }).waitFor()
    })

    for (const [seccion, texto] of [
      ["Prácticas", "Maquetado con HTML y CSS"],
      ["Clases anteriores", "Maquetado con HTML y CSS"],
      ["Asistencia del curso", "Ana Sofía"],
    ]) {
      await paso(`el docente abre ${seccion}`, async () => {
        await compu.locator("aside nav").getByRole("button", { name: seccion, exact: true }).click()
        await compu.getByRole("heading", { name: seccion, exact: true, level: 1 }).waitFor()
        await compu.getByText(texto).first().waitFor({ timeout: 10000 })
        await sinAlertaDeError(compu, seccion)
        await captura(compu, `10-docente-${seccion.split(" ")[0].toLowerCase()}`)
      })
    }

    await paso("con la clase cerrada, el registro vuelve a esperar la clase", async () => {
      await telefono.getByText("El registro se abre con la clase").waitFor({ timeout: 15000 })
    })

    // Las secciones del administrador
    const secciones = [
      ["Alumnos", "15 alumnos"],
      ["Cursos", "Programación Web"],
      ["Materias y prácticas", "Fundamentos de Base de Datos"],
      ["Cuentas del personal", "María"],
      ["Carreras, periodos y laboratorios", "SEMESTRE DE PRUEBA"],
    ]
    for (const [seccion, texto] of secciones) {
      await paso(`el administrador abre ${seccion}`, async () => {
        await admin.locator("aside nav").getByRole("button", { name: seccion, exact: true }).click()
        await admin.getByRole("heading", { name: seccion, exact: true, level: 1 }).waitFor()
        await admin.getByText(texto).first().waitFor({ timeout: 10000 })
        await sinAlertaDeError(admin, seccion)
        await captura(admin, `11-admin-${seccion.split(" ")[0].toLowerCase()}`)
      })
    }

    await paso("la sección queda en la dirección: al recargar y al ir atrás se conserva", async () => {
      if (!admin.url().includes("seccion=catalogos")) throw new Error(`La dirección no tiene la sección: ${admin.url()}`)
      await admin.reload()
      await admin.getByRole("heading", { name: "Carreras, periodos y laboratorios", level: 1 }).waitFor()
      await admin.goBack()
      await admin.getByRole("heading", { name: "Cuentas del personal", level: 1 }).waitFor()
    })

    await paso("el administrador carga alumnos desde un Excel", async () => {
      const libro = XLSX.utils.book_new()
      XLSX.utils.book_append_sheet(
        libro,
        XLSX.utils.aoa_to_sheet([
          ["Número de control", "Nombre", "Apellidos", "Carrera", "Semestre", "Grupo", "Turno"],
          ["26303101", "Paola", "Navarro Ruiz", "ISIC", 3, "B", "Vespertino"],
          ["26303102", "Andrés", "Cortés León", "Ingeniería Industrial", 3, "B", "vespertino"],
          ["", "", "", "", "", "", ""],
          ["26303103", "Daniela", "Peña Robles", "ISIC", 3, "B", "matutino"],
        ]),
        "Hoja1",
      )
      const archivo = path.join(SALIDA, "alumnos-nuevos.xlsx")
      XLSX.writeFile(libro, archivo)
      await admin.locator("aside nav").getByRole("button", { name: "Alumnos", exact: true }).click()
      await admin.getByRole("button", { name: /Cargar desde Excel/ }).click()
      await admin.locator('input[type="file"]').setInputFiles(archivo)
      await admin.getByText("3 alumnos en el archivo").waitFor()
      await admin.getByRole("button", { name: "Cargar alumnos" }).click()
      await aceptar(admin, "Alumnos cargados")
      await admin.getByText("18 alumnos").waitFor({ timeout: 10000 })
    })

    await paso("el menú de un renglón abre el formulario y al cerrar la página sigue visible", async () => {
      await admin.getByRole("button", { name: "Acciones para Paola Navarro Ruiz" }).click()
      await admin.getByRole("menuitem", { name: "Editar" }).click()
      await admin.getByRole("dialog", { name: "Editar a Paola Navarro Ruiz" }).waitFor()
      await admin.keyboard.press("Escape")
      await admin.getByRole("dialog").waitFor({ state: "detached" })
      await admin.waitForTimeout(400)
      await paginaVisibleParaLectores(admin, "después de editar desde el menú del renglón")
    })

    await paso("un clic en el renglón abre los cursos del alumno", async () => {
      await admin.getByRole("cell", { name: "26303001" }).click()
      await admin.getByRole("dialog", { name: /Ana Sofía/ }).getByText("Programación Web").first().waitFor()
      await captura(admin, "12-detalle-de-alumno", false)
      await admin.keyboard.press("Escape")
    })

    await paso("una alerta encima de un diálogo recibe el foco y Escape cierra solo el de arriba", async () => {
      await admin.locator("aside nav").getByRole("button", { name: "Cuentas del personal" }).click()
      await admin.getByRole("button", { name: /Nueva cuenta de docente/ }).click()
      await admin.locator("#cuenta-nombre").fill("Prueba")
      await admin.locator("#cuenta-contrasena").fill("123")
      await admin.locator("#cuenta-contrasena").press("Enter")
      await alerta(admin, "No se pudo guardar la cuenta")
      const focoEnLaAlerta = await admin.evaluate(() => Boolean(document.activeElement?.closest('[role="alertdialog"]')))
      if (!focoEnLaAlerta) throw new Error("El foco no está en la alerta")
      await admin.keyboard.press("Escape")
      await admin.getByRole("alertdialog").waitFor({ state: "detached" })
      await admin.getByRole("dialog", { name: /Nueva cuenta de docente/ }).waitFor()
      await admin.keyboard.press("Escape")
      await admin.getByRole("dialog").waitFor({ state: "detached" })
      await admin.waitForTimeout(500)
      await paginaVisibleParaLectores(admin, "después de cerrar la alerta y el diálogo")
    })

    await paso("el administrador crea una cuenta de docente", async () => {
      await admin.getByRole("button", { name: /Nueva cuenta de docente/ }).click()
      await admin.locator("#cuenta-nombre").fill("Patricia")
      await admin.locator("#cuenta-apellido").fill("Salas Ibarra")
      await admin.locator("#cuenta-matricula").fill("D003")
      await admin.locator("#cuenta-contrasena").fill("docente-local-2026")
      await admin.getByRole("button", { name: "Guardar" }).click()
      await admin.getByRole("cell", { name: /^Patricia Salas Ibarra/ }).waitFor()
    })

    await paso("el administrador crea una carrera", async () => {
      await admin.locator("aside nav").getByRole("button", { name: "Carreras, periodos y laboratorios" }).click()
      await admin.getByRole("button", { name: "Nueva carrera" }).click()
      await admin.getByLabel("Clave de la carrera").fill("ITIC")
      await admin.getByLabel("Nombre oficial").fill("Ingeniería en Tecnologías de la Información y Comunicaciones")
      await admin.getByRole("button", { name: "Guardar" }).click()
      await admin.getByText("Ingeniería en Tecnologías de la Información y Comunicaciones").waitFor()
      await sinAlertaDeError(admin, "crear carrera")
    })

    await paso("el tema oscuro se elige en el menú de la cuenta y se recuerda al recargar", async () => {
      await admin.locator("aside nav").getByRole("button", { name: "Tablero" }).click()
      await admin.getByRole("button", { name: new RegExp(`Cuenta de ${cuentaAdmin.nombre}`) }).click()
      await admin.getByRole("menuitem", { name: "Tema oscuro" }).click()
      await admin.keyboard.press("Escape")
      await admin.reload()
      const oscuroAlCargar = await admin.evaluate(() => document.documentElement.classList.contains("dark"))
      if (!oscuroAlCargar) throw new Error("La página no quedó en modo oscuro al recargar")
      await admin.getByRole("heading", { name: "Tablero", level: 1 }).waitFor()
      await admin.waitForTimeout(800)
      await captura(admin, "13-admin-oscuro")
      await admin.locator("aside nav").getByRole("button", { name: "Alumnos" }).click()
      await admin.getByText("18 alumnos").waitFor()
      await captura(admin, "14-admin-alumnos-oscuro")
    })

    await paso("en el teléfono, el administrador navega con el menú lateral", async () => {
      const movil = await nuevo("administrador en teléfono", telefonoComo)
      await entrarComo(movil, "Administrador", cuentaAdmin.correo, cuentaAdmin.contrasena, "/admin")
      await movil.getByRole("heading", { name: "Tablero", level: 1 }).waitFor()
      await sinDesbordeHorizontal(movil, "tablero en el teléfono")
      await captura(movil, "15-admin-telefono")
      await movil.getByRole("button", { name: "Abrir el menú" }).click()
      await movil.getByRole("dialog").getByRole("button", { name: "Alumnos" }).click()
      await movil.getByRole("heading", { name: "Alumnos", level: 1 }).waitFor()
      await movil.getByText("18 alumnos").waitFor()
      await sinDesbordeHorizontal(movil, "alumnos en el teléfono")
      await captura(movil, "16-admin-alumnos-telefono")
    })

    // El laboratorista
    const lab = await nuevo("laboratorista")
    await paso("el laboratorista inicia sesión", async () => {
      await lab.goto(BASE + "/")
      await lab.getByRole("button", { name: /Taller de Programación/ }).click()
      await entrarComo(lab, "Laboratorista", "carlos.mendez@itspp.test", "laboratorista-local-2026", "/laboratorio")
      await lab.getByRole("heading", { name: "En el laboratorio", level: 1 }).waitFor()
    })

    const vistas = [
      ["En el laboratorio", "Mapa del laboratorio"],
      ["Historial de clases", "Programación Web"],
      ["Reportes", "Clases hoy"],
      ["Bitácora", "registros"],
      ["Equipos", "PC-25"],
      ["Horario", "Programación Web"],
      ["Maestro invitado y eventos", "Clase de maestro invitado"],
    ]
    for (const [vista, texto] of vistas) {
      await paso(`el laboratorista abre ${vista}`, async () => {
        await lab.locator("aside nav").getByRole("button", { name: vista, exact: true }).click()
        await lab.getByRole("heading", { name: vista, exact: true, level: 1 }).waitFor()
        await lab.getByText(texto).locator("visible=true").first().waitFor({ timeout: 10000 })
        await lab.waitForTimeout(600)
        await sinAlertaDeError(lab, vista)
        await captura(lab, `17-lab-${vista.split(" ")[0].toLowerCase()}`)
      })
    }

    await paso("el laboratorista reporta una falla en PC-5 y queda fuera de servicio", async () => {
      await lab.locator("aside nav").getByRole("button", { name: "Equipos", exact: true }).click()
      await lab.getByRole("button", { name: /^PC-5\s/ }).click()
      await lab.locator("#falla-equipo").fill("No enciende el monitor")
      await lab.getByRole("dialog").getByRole("button", { name: "Reportar" }).click()
      await breve(lab, "Falla reportada")
      await lab.keyboard.press("Escape")
      await lab.getByText("No enciende el monitor").first().waitFor({ timeout: 10000 })
    })

    await paso("en el registro, PC-5 aparece fuera de servicio", async () => {
      const r = await (await fetch(BASE + "/api/laboratorios/programacion/equipos")).json()
      const pc5 = r.find((e) => e.etiqueta === "PC-5")
      if (pc5.estado !== "fuera_de_servicio") throw new Error(`PC-5 está ${pc5.estado}`)
    })

    await paso("el laboratorista abre una clase de maestro invitado, alguien de fuera entra y la cierra", async () => {
      await lab.locator("aside nav").getByRole("button", { name: "Maestro invitado y eventos" }).click()
      await lab.locator("#materia-invitado").fill("Taller de Arduino")
      await lab.getByRole("button", { name: "Abrir la clase" }).click()
      await lab.waitForURL("**/clase-invitada")
      await lab.getByRole("heading", { name: /Taller de Arduino/ }).waitFor()
      await lab.getByRole("button", { name: /Registrar a alguien a mano/ }).click()
      await lab.locator("#nombre-a-mano").fill("Rosa")
      await lab.locator("#apellido-a-mano").fill("Félix Duarte")
      await lab.getByRole("button", { name: "Registrar", exact: true }).click()
      await lab.getByRole("cell", { name: /^Rosa Félix Duarte/ }).waitFor({ timeout: 10000 })
      await captura(lab, "18-clase-invitada")
      await lab.getByRole("button", { name: /Finalizar clase/ }).click()
      await alerta(lab, "¿Finalizar la clase?")
      await botonAceptar(lab).click()
      await aceptar(lab, "Clase finalizada")
      await lab.waitForURL("**/laboratorio?seccion=invitado")
    })

    await paso("la bitácora registra lo que se hizo", async () => {
      await lab.locator("aside nav").getByRole("button", { name: "Bitácora", exact: true }).click()
      await lab.getByText("Clase abierta").first().waitFor({ timeout: 10000 })
    })

    await paso("la página principal en tema oscuro, en la computadora y en el teléfono", async () => {
      await compu.context().addCookies([{ name: "theme", value: "dark", url: BASE }])
      await compu.goto(BASE + "/")
      await compu.getByText("No hay ninguna clase abierta").waitFor()
      await captura(compu, "19-registro-oscuro")
      await telefono.context().addCookies([{ name: "theme", value: "dark", url: BASE }])
      await telefono.reload()
      await telefono.getByText("No hay ninguna clase abierta").waitFor()
      await captura(telefono, "20-telefono-oscuro")
    })
  } catch (error) {
    console.log("FALLÓ")
    console.error(error.message)
    process.exitCode = 1
  } finally {
    if (problemas.length) {
      console.log(`\nErrores en la consola o en las páginas (${problemas.length}):`)
      for (const p of [...new Set(problemas)]) console.log("  " + p)
      process.exitCode = 1
    } else {
      console.log("\nSin errores en la consola ni en las páginas.")
    }
    if (advertencias.length) {
      console.log(`\nAdvertencias de la consola (${advertencias.length}):`)
      for (const a of [...new Set(advertencias)]) console.log("  " + a)
    }
    // Al cerrar, Playwright rechaza las navegaciones que quedaron pendientes; no importa.
    process.on("unhandledRejection", () => {})
    await navegador.close().catch(() => {})
  }
})()
