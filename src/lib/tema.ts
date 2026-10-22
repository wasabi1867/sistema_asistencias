/**
 * El tema claro u oscuro, guardado en una cookie para que _document lo aplique antes
 * de pintar la página (sin parpadeo), y la paleta de la institución: guinda y verde.
 */
export type Tema = "light" | "dark"

export function temaGuardado(): Tema {
  return /(?:^|; )theme=dark(?:;|$)/.test(document.cookie) ? "dark" : "light"
}

export function aplicarTema(tema: Tema) {
  document.documentElement.classList.toggle("dark", tema === "dark")
}

export function cambiarTema(): Tema {
  const nuevo: Tema = temaGuardado() === "light" ? "dark" : "light"
  document.cookie = `theme=${nuevo}; max-age=${60 * 60 * 24 * 365}; path=/; SameSite=Lax`
  return nuevo
}

/** Clases de Tailwind de cada tema. En el claro manda el guinda; en el oscuro, el verde. */
export const colores = {
  light: {
    fondo: "bg-[#fff0f5]",
    tarjeta: "bg-white",
    encabezado: "bg-gradient-to-r from-[#800040] to-[#a30050]",
    titulo: "text-[#800040]",
    descripcion: "text-[#800040]/80",
    texto: "text-[#1C4A3F]",
    textoSuave: "text-[#74726f]",
    botonPrimario: "bg-[#800040] hover:bg-[#5c002e] text-white",
    botonSecundario: "bg-[#1d5631] hover:bg-[#153d23] text-white",
    entrada: "bg-[#f8f8f8] border-[#800040]/30 text-[#800040]",
    panel: "bg-[#e6ffe9] text-[#1C4A3F]",
    borde: "border-gray-200",
    menu: "bg-white",
  },
  dark: {
    fondo: "bg-[#0c1f15]",
    tarjeta: "bg-[#2a2a2a]",
    encabezado: "bg-gradient-to-r from-[#1d5631] to-[#2a7a45]",
    titulo: "text-white",
    descripcion: "text-gray-300",
    texto: "text-gray-200",
    textoSuave: "text-[#a0a0a0]",
    botonPrimario: "bg-[#1d5631] hover:bg-[#153d23] text-white",
    botonSecundario: "bg-[#800040] hover:bg-[#5c002e] text-white",
    entrada: "bg-[#3a3a3a] border-[#1d5631]/30 text-white",
    panel: "bg-[#153731] text-white",
    borde: "border-gray-700",
    menu: "bg-[#1C4A3F] text-white",
  },
} as const

export type Colores = (typeof colores)[Tema]
