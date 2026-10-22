/**
 * La bitácora la llena el servidor en cada acción importante (ver public/api), con
 * quién la hizo y desde dónde: el navegador ya no escribe en ella. Esta función se
 * queda para no tocar las pantallas que todavía la llaman, pero no hace nada.
 */
export async function logAction(_action: string, _details: string) {}
