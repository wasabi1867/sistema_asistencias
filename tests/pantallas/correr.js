// Prepara todo y corre la prueba de las pantallas (tests/pantallas/prueba.js):
//   1. crea una base nueva, con la conexión de las pruebas de la API, y la instala;
//   2. crea el administrador de la prueba;
//   3. sirve out/ con el servidor de PHP, con la API apuntando a esa base;
//   4. carga los datos de ejemplo y corre la prueba;
//   5. apaga el servidor y borra la base.
//
// Antes hay que compilar el sitio (npm run build). Uso:
//
//   node tests/pantallas/correr.js
//
// Variables:
// - PHP: el ejecutable de PHP, si no está en el PATH.
// - ASISTENCIAS_PRUEBAS_DB_HOST, _PORT, _USER, _PASS: el servidor de la base, igual que en las pruebas de la API.
// - NAVEGADOR: "msedge" o "chrome" (por omisión, Edge en Windows y Chrome en lo demás).
// - PUERTO: dónde escucha el servidor (8090).
// - CONSERVAR_BASE=1: no borra la base al terminar, para revisarla.
const { spawn, spawnSync } = require("child_process")
const crypto = require("crypto")
const fs = require("fs")
const path = require("path")
const { admin } = require("./cuentas")

const RAIZ = path.resolve(__dirname, "../..")
const OUT = path.join(RAIZ, "out")
const CAPTURAS = path.join(__dirname, "capturas")
const PHP = process.env.PHP || "php"
const PUERTO = process.env.PUERTO || "8090"
const BASE = `http://127.0.0.1:${PUERTO}`
const BASE_DE_DATOS = `asistencias_pantallas_${crypto.randomBytes(3).toString("hex")}`

const entornoDeLaApi = {
  ...process.env,
  ASISTENCIAS_DB_HOST: process.env.ASISTENCIAS_PRUEBAS_DB_HOST || "127.0.0.1",
  ASISTENCIAS_DB_PORT: process.env.ASISTENCIAS_PRUEBAS_DB_PORT || "3306",
  ASISTENCIAS_DB_USER: process.env.ASISTENCIAS_PRUEBAS_DB_USER || "root",
  ASISTENCIAS_DB_PASS: process.env.ASISTENCIAS_PRUEBAS_DB_PASS || "",
  ASISTENCIAS_DB_NAME: BASE_DE_DATOS,
  // Las variables van antes que out/api/config.local.php: si alguien copió ahí el de su
  // computadora, la prueba no usa su base ni su configuración.
  ASISTENCIAS_DB_TLS: "0",
  ASISTENCIAS_ORIGENES_PERMITIDOS: "",
  ASISTENCIAS_EXIGIR_CODIGO_DE_CLASE: "0",
}

function correr(ejecutable, argumentos, { entrada, entorno = entornoDeLaApi } = {}) {
  const r = spawnSync(ejecutable, argumentos, { cwd: RAIZ, env: entorno, input: entrada, stdio: [entrada === undefined ? "inherit" : "pipe", "inherit", "inherit"] })
  if (r.error) throw r.error
  if (r.status !== 0) throw new Error(`${path.basename(ejecutable)} ${argumentos.join(" ")} terminó con código ${r.status}`)
}

const php = (...argumentos) => [PHP, ["-d", "xdebug.mode=off", ...argumentos]]

async function esperarAlServidor() {
  for (let intento = 0; intento < 60; intento++) {
    try {
      if ((await fetch(`${BASE}/api/laboratorios`)).ok) return
    } catch {
      // Todavía no escucha.
    }
    await new Promise((r) => setTimeout(r, 250))
  }
  throw new Error(`El servidor no respondió en ${BASE}`)
}

;(async () => {
  if (!fs.existsSync(path.join(OUT, "index.html")) || !fs.existsSync(path.join(OUT, "api", "index.php"))) {
    console.error("Falta compilar el sitio: npm run build")
    process.exit(1)
  }
  fs.rmSync(CAPTURAS, { recursive: true, force: true })
  fs.mkdirSync(CAPTURAS, { recursive: true })

  let servidor
  let codigo = 1
  try {
    console.log(`Base ${BASE_DE_DATOS}`)
    correr(...php("tests/pantallas/base.php", "crear", BASE_DE_DATOS), { entorno: process.env })
    correr(...php("scripts/instalar.php"))
    correr(...php("scripts/crear-admin.php", admin.correo, admin.nombre, admin.apellido), { entrada: `${admin.contrasena}\n` })

    const registro = fs.openSync(path.join(CAPTURAS, "servidor.log"), "w")
    servidor = spawn(...php("-S", `127.0.0.1:${PUERTO}`, "-t", OUT, path.join(__dirname, "servidor.php")), { cwd: RAIZ, env: entornoDeLaApi, stdio: ["ignore", registro, registro] })
    await esperarAlServidor()
    correr(...php("tests/pantallas/datos-de-ejemplo.php", `${BASE}/api`, admin.correo, admin.contrasena))

    const prueba = spawnSync(process.execPath, [path.join(__dirname, "prueba.js")], { cwd: RAIZ, env: { ...process.env, BASE }, stdio: "inherit" })
    codigo = prueba.status ?? 1
  } catch (error) {
    console.error(error.message)
  } finally {
    servidor?.kill()
    if (process.env.CONSERVAR_BASE === "1") {
      console.log(`La base ${BASE_DE_DATOS} se conservó.`)
    } else {
      try {
        correr(...php("tests/pantallas/base.php", "borrar", BASE_DE_DATOS), { entorno: process.env })
      } catch (error) {
        console.error(`No se pudo borrar la base ${BASE_DE_DATOS}: ${error.message}`)
      }
    }
  }
  process.exit(codigo)
})()
