import { collection, addDoc, serverTimestamp } from "firebase/firestore"
import { db } from "./firebase"

export async function logAction(action: string, details: string) {
  try {
    const lab = typeof window !== "undefined" ? localStorage.getItem("laboratorio") || "programacion" : "programacion"
    await addDoc(collection(db, "logs"), {
      timestamp: serverTimestamp(),
      action,
      details,
      laboratorio: lab,
    })
  } catch (error) {
    console.error("Error al registrar log:", error)
  }
}
