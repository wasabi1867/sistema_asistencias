<?php

namespace Tests\Api;

/**
 * Hace peticiones a la API como lo haría un navegador: guarda las cookies que recibe
 * y las manda en las siguientes peticiones. Cada cliente es un navegador distinto.
 */
final class Cliente
{
    /** @var array<string, string> */
    public array $cookies = [];

    /** @param array<string, string> $encabezados */
    public function pedir(string $metodo, string $ruta, ?array $cuerpo = null, array $encabezados = []): Respuesta
    {
        $curl = curl_init(Entorno::url() . '/api/' . ltrim($ruta, '/'));
        $lineas = ['Accept: application/json'];
        foreach ($encabezados as $nombre => $valor) {
            $lineas[] = "$nombre: $valor";
        }
        if ($this->cookies) {
            $pares = [];
            foreach ($this->cookies as $nombre => $valor) {
                $pares[] = "$nombre=$valor";
            }
            $lineas[] = 'Cookie: ' . implode('; ', $pares);
        }
        if ($cuerpo !== null) {
            $lineas[] = 'Content-Type: application/json';
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($cuerpo));
        }

        $recibidos = [];
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_HTTPHEADER => $lineas,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HEADERFUNCTION => function ($curl, string $linea) use (&$recibidos) {
                $partes = explode(':', $linea, 2);
                if (count($partes) === 2) {
                    $recibidos[strtolower(trim($partes[0]))][] = trim($partes[1]);
                }
                return strlen($linea);
            },
        ]);
        $texto = curl_exec($curl);
        $estado = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        foreach ($recibidos['set-cookie'] ?? [] as $cookie) {
            [$par] = explode(';', $cookie, 2);
            [$nombre, $valor] = explode('=', $par, 2);
            if ($valor === '' || $valor === 'deleted' || stripos($cookie, 'expires=Thu, 01 Jan 1970') !== false
                || preg_match('/Max-Age=0\b/i', $cookie)) {
                unset($this->cookies[$nombre]);
            } else {
                $this->cookies[$nombre] = $valor;
            }
        }

        return new Respuesta($estado, $recibidos, (string)$texto);
    }

    public function get(string $ruta): Respuesta
    {
        return $this->pedir('GET', $ruta);
    }

    public function post(string $ruta, array $cuerpo = []): Respuesta
    {
        return $this->pedir('POST', $ruta, $cuerpo);
    }

    public function put(string $ruta, array $cuerpo = []): Respuesta
    {
        return $this->pedir('PUT', $ruta, $cuerpo);
    }

    public function delete(string $ruta): Respuesta
    {
        return $this->pedir('DELETE', $ruta);
    }
}
