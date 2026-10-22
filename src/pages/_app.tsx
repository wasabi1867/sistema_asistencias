// La letra se publica junto con el sitio: no se pide a ningún servicio externo al abrir la página.
import "@fontsource-variable/inter"
import "@/styles/globals.css"
import type { AppProps } from "next/app"
import Head from "next/head"
import { Alertas } from "@/components/comunes/Alertas"

export default function App({ Component, pageProps }: AppProps) {
  return (
    <>
      <Head>
        <title>Control de asistencias · ITSPP</title>
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
      </Head>
      <Component {...pageProps} />
      {/* Fuera de cada página: así un aviso breve sigue a la vista después de cambiar de página. */}
      <Alertas />
    </>
  )
}
