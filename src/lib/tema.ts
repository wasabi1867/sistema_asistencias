/**
 * El tema claro u oscuro, guardado en una cookie para que _document lo aplique antes
 * de pintar la página (sin parpadeo). Los colores de cada tema están en globals.css.
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
