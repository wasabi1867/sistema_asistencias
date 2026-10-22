import { Html, Head, Main, NextScript } from "next/document"

// La página se exporta como HTML estático, así que el tema no se puede decidir al compilar:
// este script lee la cookie que guarda lib/tema.ts y pone el modo oscuro antes de pintar,
// para que la página no parpadee en blanco.
const aplicarTemaGuardado = `document.documentElement.classList.toggle("dark", /(?:^|; )theme=dark(?:;|$)/.test(document.cookie))`

export default function Document() {
  return (
    <Html lang="es">
      <Head>
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png" />
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png" />
        <meta name="theme-color" content="#800040" />
      </Head>
      <body className="antialiased">
        <script dangerouslySetInnerHTML={{ __html: aplicarTemaGuardado }} />
        <Main />
        <NextScript />
      </body>
    </Html>
  )
}
