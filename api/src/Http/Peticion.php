<?php

declare(strict_types=1);

namespace Asistencias\Http;

/** Una petición HTTP, con la ruta ya relativa a la carpeta de la API. */
final class Peticion
{
    /**
     * @param array<string, mixed> $consulta parámetros de la dirección (?a=1)
     * @param array<string, string> $encabezados nombres en minúsculas
     * @param array<string, string> $parametros los de la ruta, como {id}
     */
    public function __construct(
        public readonly string $metodo,
        public readonly string $ruta,
        private readonly array $consulta = [],
        private readonly string $cuerpoCrudo = '',
        private readonly array $encabezados = [],
        public readonly string $ip = '',
        private readonly array $parametros = [],
    ) {
    }

    public static function desdeElServidor(): self
    {
        // La API puede estar en /api o en /laboratorios/api: la ruta es lo que sigue a
        // la carpeta de index.php. Así funciona igual en Hostinger (con .htaccess) que
        // con el servidor de desarrollo de PHP.
        $ruta = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $carpeta = str_ends_with($script, '/index.php') ? substr($script, 0, -strlen('/index.php')) : '';
        if ($carpeta !== '' && str_starts_with($ruta, $carpeta)) {
            $ruta = substr($ruta, strlen($carpeta));
        }
        if ($ruta === '/index.php') {
            $ruta = '/';
        }

        $encabezados = [];
        foreach ($_SERVER as $clave => $valor) {
            if (str_starts_with($clave, 'HTTP_')) {
                $encabezados[strtolower(str_replace('_', '-', substr($clave, 5)))] = (string)$valor;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $encabezados['content-type'] = (string)$_SERVER['CONTENT_TYPE'];
        }

        return new self(
            metodo: strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            ruta: '/' . trim($ruta, '/'),
            consulta: $_GET,
            cuerpoCrudo: (string)file_get_contents('php://input'),
            encabezados: $encabezados,
            ip: (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        );
    }

    /** @param array<string, string> $parametros */
    public function conParametros(array $parametros): self
    {
        return new self($this->metodo, $this->ruta, $this->consulta, $this->cuerpoCrudo, $this->encabezados, $this->ip, $parametros);
    }

    /** Un parámetro de la ruta, como {matricula}. */
    public function parametro(string $nombre): string
    {
        return $this->parametros[$nombre] ?? '';
    }

    /** Un parámetro numérico de la ruta, como {id}. Si no es número, el registro no existe. */
    public function id(string $nombre = 'id'): int
    {
        $id = filter_var($this->parametros[$nombre] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new ErrorHttp(404, 'No existe');
        }
        return $id;
    }

    public function consulta(): Entrada
    {
        return new Entrada($this->consulta);
    }

    public function cuerpo(): Entrada
    {
        if (trim($this->cuerpoCrudo) === '') {
            return new Entrada([]);
        }
        $datos = json_decode($this->cuerpoCrudo, true);
        if (!is_array($datos) || ($datos !== [] && array_is_list($datos))) {
            throw ErrorHttp::datosInvalidos('Los datos deben enviarse como un objeto JSON');
        }
        return new Entrada($datos);
    }

    public function encabezado(string $nombre): ?string
    {
        return $this->encabezados[strtolower($nombre)] ?? null;
    }

    public function esHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || $this->encabezado('x-forwarded-proto') === 'https';
    }
}
