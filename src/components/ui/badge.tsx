import * as React from "react"
import { cva, type VariantProps } from "class-variance-authority"

import { cn } from "@/lib/utils"

/** Una etiqueta de estado: "En curso", "Activa", "Fuera de servicio"... */
const badgeVariants = cva("inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium [&_svg]:size-3.5", {
  variants: {
    variant: {
      neutral: "bg-muted text-muted-foreground",
      primary: "bg-accent text-accent-foreground",
      success: "bg-success/15 text-success",
      warning: "bg-warning/15 text-warning",
      destructive: "bg-destructive/15 text-destructive",
      info: "bg-info/15 text-info",
      outline: "border text-foreground",
    },
  },
  defaultVariants: {
    variant: "neutral",
  },
})

export interface BadgeProps extends React.HTMLAttributes<HTMLSpanElement>, VariantProps<typeof badgeVariants> {}

function Badge({ className, variant, ...props }: BadgeProps) {
  return <span className={cn(badgeVariants({ variant }), className)} {...props} />
}

export { Badge, badgeVariants }
