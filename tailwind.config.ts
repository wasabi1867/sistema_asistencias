import type { Config } from "tailwindcss"
import { fontFamily } from "tailwindcss/defaultTheme"
import animate from "tailwindcss-animate"

// Los colores salen de las variables de src/styles/globals.css: cada tema las redefine
// y los componentes solo usan los nombres (primary, success...), nunca un color fijo.
const color = (nombre: string) => `hsl(var(--${nombre}) / <alpha-value>)`

const config: Config = {
  darkMode: ["class"],
  content: ["./src/**/*.{ts,tsx}"],
  theme: {
    extend: {
      fontFamily: {
        sans: ["Inter Variable", ...fontFamily.sans],
      },
      colors: {
        border: color("border"),
        input: color("input"),
        ring: color("ring"),
        background: color("background"),
        foreground: color("foreground"),
        primary: { DEFAULT: color("primary"), foreground: color("primary-foreground") },
        secondary: { DEFAULT: color("secondary"), foreground: color("secondary-foreground") },
        muted: { DEFAULT: color("muted"), foreground: color("muted-foreground") },
        accent: { DEFAULT: color("accent"), foreground: color("accent-foreground") },
        popover: { DEFAULT: color("popover"), foreground: color("popover-foreground") },
        card: { DEFAULT: color("card"), foreground: color("card-foreground") },
        destructive: { DEFAULT: color("destructive"), foreground: color("destructive-foreground") },
        success: { DEFAULT: color("success"), foreground: color("success-foreground") },
        warning: { DEFAULT: color("warning"), foreground: color("warning-foreground") },
        info: { DEFAULT: color("info"), foreground: color("info-foreground") },
      },
      keyframes: {
        // La barra que se vacía mientras la confirmación del registro sigue en pantalla.
        vaciar: { from: { transform: "scaleX(1)" }, to: { transform: "scaleX(0)" } },
      },
      animation: {
        vaciar: "vaciar var(--duracion, 6s) linear forwards",
      },
      borderRadius: {
        xl: "calc(var(--radius) + 4px)",
        lg: "var(--radius)",
        md: "calc(var(--radius) - 2px)",
        sm: "calc(var(--radius) - 4px)",
      },
    },
  },
  plugins: [animate],
}

export default config
