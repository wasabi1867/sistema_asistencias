<?php

declare(strict_types=1);

namespace Asistencias\Http;

use Asistencias\Seguridad\Acceso;
use Closure;

/**
 * Las rutas de la API. Cada una declara su método, su forma ("/clases/{id}/cerrar"),
 * quién puede usarla y qué hace.
 */
final class Enrutador
{
    /** @var list<Ruta> */
    private array $rutas = [];

    public function get(string $forma, Acceso $acceso, Closure $accion): void
    {
        $this->agregar('GET', $forma, $acceso, $accion);
    }

    public function post(string $forma, Acceso $acceso, Closure $accion): void
    {
        $this->agregar('POST', $forma, $acceso, $accion);
    }

    public function put(string $forma, Acceso $acceso, Closure $accion): void
    {
        $this->agregar('PUT', $forma, $acceso, $accion);
    }

    public function delete(string $forma, Acceso $acceso, Closure $accion): void
    {
        $this->agregar('DELETE', $forma, $acceso, $accion);
    }

    /**
     * La ruta que atiende la petición y los parámetros que trae en la dirección.
     * @return array{0: Ruta, 1: array<string, string>}
     */
    public function buscar(string $metodo, string $ruta): array
    {
        $existeConOtroMetodo = false;
        foreach ($this->rutas as $candidata) {
            if (!preg_match($candidata->patron, $ruta, $coincidencias)) {
                continue;
            }
            if ($candidata->metodo !== $metodo) {
                $existeConOtroMetodo = true;
                continue;
            }
            return [$candidata, array_filter($coincidencias, 'is_string', ARRAY_FILTER_USE_KEY)];
        }
        throw $existeConOtroMetodo
            ? new ErrorHttp(405, 'Método no permitido')
            : new ErrorHttp(404, 'La ruta no existe');
    }

    /** @return list<Ruta> */
    public function rutas(): array
    {
        return $this->rutas;
    }

    private function agregar(string $metodo, string $forma, Acceso $acceso, Closure $accion): void
    {
        // {id} y los que terminan en Id solo aceptan números; así "/clases/abiertas" no
        // se confunde con "/clases/{id}", sin depender del orden en que se registran.
        $patron = '#^' . preg_replace_callback(
            '#\\\\\{(\w+)\\\\\}#',
            static fn (array $m) => "(?P<{$m[1]}>" . ($m[1] === 'id' || str_ends_with($m[1], 'Id') ? '\d+' : '[^/]+') . ')',
            preg_quote($forma, '#')
        ) . '$#u';
        $this->rutas[] = new Ruta($metodo, $forma, $patron, $acceso, $accion);
    }
}
