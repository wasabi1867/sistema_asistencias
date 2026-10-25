// Reglas de ESLint de Next.js (core web vitals), en el formato de ESLint 9. Desde Next.js 16
// ya no existe `next lint` ni se revisa al compilar: se corre con `npm run lint`.
import nextCoreWebVitals from "eslint-config-next/core-web-vitals"

const config = [
  { ignores: ["out/", ".next/", "api/", "vendor/", "tests/", "scripts/", "next-env.d.ts"] },
  ...nextCoreWebVitals,
  {
    // Reglas que llegaron con Next.js 16 (react-hooks 7) para el compilador de React, que el
    // sitio no usa. Marcan patrones válidos que ya tiene el código, como cargar datos en un
    // efecto (useDatos, useConsultaPeriodica) o elegir el ícono del laboratorio en una tabla.
    // Se apagaron para que la actualización a Next.js 16 no cambiara ninguna pantalla;
    // rules-of-hooks y exhaustive-deps siguen como antes. Adoptarlas es trabajo aparte.
    rules: {
      "react-hooks/set-state-in-effect": "off",
      "react-hooks/refs": "off",
      "react-hooks/static-components": "off",
    },
  },
]

export default config
