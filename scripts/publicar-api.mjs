// Copia la API (api/) dentro del sitio compilado (out/api), para subir todo junto a
// Hostinger. Corre después de `next build` (ver package.json).
//
// No copia config.local.php: cada servidor tiene el suyo, y subir el de la máquina
// donde se compila pisaría el del servidor con otros datos.
import { cpSync, rmSync } from 'node:fs'

rmSync('out/api', { recursive: true, force: true })
cpSync('api', 'out/api', {
  recursive: true,
  filter: (origen) => !origen.replace(/\\/g, '/').endsWith('/config.local.php'),
})
console.log('API copiada a out/api (sin config.local.php)')
