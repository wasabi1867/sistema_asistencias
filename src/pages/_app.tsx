import "@/styles/globals.css"
import type { AppProps } from "next/app"
import { Alertas } from "@/components/comunes/Alertas"

export default function App({ Component, pageProps }: AppProps) {
  return (
    <>
      <Component {...pageProps} />
      {/* Fuera de cada página: así un aviso breve sigue a la vista después de cambiar de página. */}
      <Alertas />
    </>
  )
}
