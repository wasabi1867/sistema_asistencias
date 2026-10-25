// Prueba rápida del sitio publicado, para correrla después de cada publicación. No crea
// datos: elige un laboratorio (solo en el navegador de la prueba), revisa la página de
// registro y, si hay cuenta, inicia sesión como administrador, recorre el panel y cierra
// la sesión (eso queda en la bitácora, como cualquier inicio de sesión).
//
//   npm run prueba:produccion
//
// Variables:
// - SITIO: por omisión https://laboratorios.soycorrecaminos.xyz
// - ASISTENCIAS_HUMO_USUARIO y ASISTENCIAS_HUMO_CONTRASENA: una cuenta de administrador.
//   Sin ellas solo se revisan las páginas públicas. Nunca se escriben en el repositorio.
// - NAVEGADOR: "msedge" o "chrome" (por omisión, Edge en Windows y Chrome en lo demás).
const { chromium } = require("playwright-core")
const fs = require("fs")
const path = require("path")

const SITIO = (process.env.SITIO || "https://laboratorios.soycorrecaminos.xyz").replace(/\/$/, "")
const USUARIO = process.env.ASISTENCIAS_HUMO_USUARIO || ""
const CONTRASENA = process.env.ASISTENCIAS_HUMO_CONTRASENA || ""
const NAVEGADOR = process.env.NAVEGADOR || (process.platform === "win32" ? "msedge" : "chrome")
const CAPTURAS = path.join(__dirname, "capturas")

const problemas = []
let paso = 0
const bien = (texto) => console.log(`${String(++paso).padStart(2, "0")}. ${texto}... bien`)

;(async () => {
  fs.mkdirSync(CAPTURAS, { recursive: true })
  const navegador = await chromium.launch({ channel: NAVEGADOR, headless: true })
  for (const [nombre, tamano] of [["computadora", { width: 1366, height: 800 }], ["teléfono", { width: 390, height: 844 }]]) {
    const contexto = await navegador.newContext({ viewport: tamano, locale: "es-MX", timezoneId: "America/Hermosillo" })
    const pagina = await contexto.newPage()
    pagina.on("console", (m) => m.type() === "error" && !m.text().startsWith("Failed to load resource") && problemas.push(`[${nombre}] consola: ${m.text()}`))
    pagina.on("pageerror", (e) => problemas.push(`[${nombre}] error de la página: ${e.message}`))
    pagina.on("response", (r) => r.status() >= 500 && problemas.push(`[${nombre}] ${r.status()} en ${r.url()}`))
    const captura = (cual) => pagina.screenshot({ path: path.join(CAPTURAS, `${nombre}-${cual}.png`), fullPage: true })

    await pagina.goto(SITIO + "/", { waitUntil: "networkidle" })
    await pagina.getByRole("button", { name: /Taller de Programación/ }).waitFor()
    bien(`${nombre}: la página principal ofrece los laboratorios`)
    await captura("1-laboratorios")

    await pagina.getByRole("button", { name: /Taller de Programación/ }).click()
    await pagina.locator("header h1").getByText("Taller de Programación").waitFor()
    await pagina.getByText(/no hay (ninguna )?clase|¿Quién eres\?/i).first().waitFor()
    bien(`${nombre}: la página de registro del laboratorio carga`)
    await captura("2-registro")

    const api = await pagina.evaluate(async () => (await fetch("/api/laboratorios")).status)
    if (api !== 200) problemas.push(`[${nombre}] /api/laboratorios respondió ${api}`)
    else bien(`${nombre}: la API responde`)

    if (USUARIO && CONTRASENA) {
      await pagina.goto(SITIO + "/entrar", { waitUntil: "networkidle" })
      await pagina.getByRole("button", { name: "Administrador", exact: true }).click()
      await pagina.locator("#usuario").fill(USUARIO)
      await pagina.locator("#contrasena").fill(CONTRASENA)
      await pagina.getByRole("button", { name: "Iniciar sesión" }).click()
      await pagina.waitForURL(/\/admin/)
      await pagina.getByRole("heading", { name: "Tablero", level: 1 }).waitFor()
      const rol = await pagina.evaluate(async () => (await (await fetch("/api/sesion")).json()).rol)
      if (rol !== "admin") problemas.push(`[${nombre}] la sesión tiene el rol "${rol}"`)
      bien(`${nombre}: el administrador inicia sesión y llega al tablero`)
      await captura("3-tablero")

      if (nombre === "computadora") {
        for (const seccion of ["Alumnos", "Cursos", "Materias y prácticas", "Cuentas del personal", "Carreras, periodos y laboratorios"]) {
          await pagina.locator("aside nav").getByRole("button", { name: seccion, exact: true }).click()
          await pagina.getByRole("heading", { name: seccion, exact: true, level: 1 }).waitFor()
          await pagina.waitForTimeout(400)
          bien(`${nombre}: abre ${seccion}`)
        }
      }

      const salida = await pagina.evaluate(async () => (await fetch("/api/sesion", { method: "DELETE" })).status)
      const despues = await pagina.evaluate(async () => (await fetch("/api/sesion")).status)
      if (salida !== 204 || despues !== 401) problemas.push(`[${nombre}] cerrar sesión dio ${salida} y luego ${despues}`)
      else bien(`${nombre}: cierra la sesión`)
    }
    await contexto.close()
  }
  await navegador.close()

  if (!USUARIO || !CONTRASENA) console.log("\nSin ASISTENCIAS_HUMO_USUARIO y _CONTRASENA: no se revisó el panel del administrador.")
  // Los 401 de comprobar que la sesión se cerró salen en la consola como "Failed to load resource" y no cuentan.
  console.log(problemas.length ? `\nPROBLEMAS (${problemas.length}):\n  ` + problemas.join("\n  ") : "\nSin errores en la consola ni en las páginas.")
  process.exit(problemas.length ? 1 : 0)
})().catch((e) => {
  console.error(`FALLÓ en el paso ${paso + 1}: ${e.message.split("\n")[0]}`)
  if (problemas.length) console.error("  " + problemas.join("\n  "))
  process.exit(1)
})
