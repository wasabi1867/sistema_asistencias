<?php

declare(strict_types=1);

namespace Asistencias;

use Asistencias\Datos\BaseDeDatos;
use Asistencias\Http\Enrutador;
use Asistencias\Http\ErrorHttp;
use Asistencias\Http\Peticion;
use Asistencias\Http\Respuesta;
use Asistencias\Seguridad\Sesion;
use Throwable;

/** Atiende una petición: busca su ruta, revisa el permiso, la corre y responde JSON. */
final class Aplicacion
{
    public function __construct(private readonly Configuracion $config, private readonly Enrutador $enrutador)
    {
    }

    public static function crear(Configuracion $config): self
    {
        return new self($config, Rutas::todas());
    }

    public function atender(Peticion $peticion): void
    {
        // Un aviso de PHP impreso en la respuesta rompería el JSON y podría mostrar
        // rutas del servidor: los errores van al registro, no al cliente.
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        date_default_timezone_set($this->config->zonaHoraria);

        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        $this->permitirOrigen($peticion);
        if ($peticion->metodo === 'OPTIONS') {
            http_response_code(204);
            return;
        }

        $bd = new BaseDeDatos($this->config);
        $contexto = null;
        try {
            [$ruta, $parametros] = $this->enrutador->buscar($peticion->metodo, $peticion->ruta);
            $contexto = new Contexto($peticion->conParametros($parametros), $bd, $this->config, new Sesion($peticion->esHttps()));
            if (!$ruta->acceso->permite($contexto->usuario())) {
                throw $contexto->usuario() === null ? ErrorHttp::sinSesion() : ErrorHttp::sinPermiso();
            }

            // Lo que cambia datos corre en una transacción: si algo falla a la mitad no
            // queda nada a medias, y la bitácora se guarda junto con el cambio.
            $accion = $ruta->accion;
            $resultado = $peticion->metodo === 'GET'
                ? $accion($contexto)
                : $bd->transaccion(static fn () => $accion($contexto));

            $resultado instanceof Respuesta
                ? $this->responder($resultado->estado, $resultado->datos)
                : $this->responder(200, $resultado);
        } catch (ErrorHttp $e) {
            $this->responder($e->estado, ['error' => $e->getMessage()] + $e->extra);
        } catch (Throwable $e) {
            error_log('API asistencias: ' . $e);
            $this->responder(500, ['error' => 'Error interno del servidor']);
        } finally {
            try {
                $contexto?->bitacora->escribirPendientes();
            } catch (Throwable $e) {
                error_log('API asistencias: no se pudo escribir la bitácora: ' . $e);
            }
        }
    }

    private function responder(int $estado, mixed $datos): void
    {
        http_response_code($estado);
        if ($estado !== 204) {
            echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION);
        }
    }

    /**
     * CORS solo para los orígenes configurados (por ejemplo `npm run dev`). Con '*',
     * cualquier página de internet podría llamar a la API con la sesión ajena.
     */
    private function permitirOrigen(Peticion $peticion): void
    {
        $origen = $peticion->encabezado('origin');
        if ($origen === null || !in_array($origen, $this->config->origenesPermitidos, true)) {
            return;
        }
        header("Access-Control-Allow-Origin: $origen");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Vary: Origin');
    }
}
