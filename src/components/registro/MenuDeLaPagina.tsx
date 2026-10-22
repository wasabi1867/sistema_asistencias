import { Menu, User } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetTrigger } from "@/components/ui/sheet"

/** El menú lateral de la página principal. Por ahora solo lleva al acceso del administrador. */
export function MenuDeLaPagina({ oscuro, alEntrarComoAdministrador }: { oscuro: boolean; alEntrarComoAdministrador: () => void }) {
  return (
    <Sheet>
      <SheetTrigger asChild>
        <Button variant="outline" size="icon" className={oscuro ? "text-white" : "text-gray-800"}>
          <Menu className="h-6 w-6" />
          <span className="sr-only">Abrir el menú</span>
        </Button>
      </SheetTrigger>
      <SheetContent side="left" className={oscuro ? "bg-gray-800 text-white" : "bg-white text-gray-800"}>
        <SheetHeader className="mb-6">
          <SheetTitle className={oscuro ? "text-white" : ""}>Menú</SheetTitle>
          <SheetDescription className="sr-only">Opciones para el personal del instituto</SheetDescription>
        </SheetHeader>
        <nav className="space-y-4">
          <Button variant="ghost" className="w-full justify-start" onClick={alEntrarComoAdministrador}>
            <User className="mr-2 h-4 w-4" />
            Administrador
          </Button>
        </nav>
      </SheetContent>
    </Sheet>
  )
}
