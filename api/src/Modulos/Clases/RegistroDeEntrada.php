<?php

declare(strict_types=1);

namespace Asistencias\Modulos\Clases;

use Asistencias\Contexto;
use Asistencias\Http\Enrutador;
use Asistencias\Http\ErrorHttp;
use Asistencias\Http\Respuesta;
use Asistencias\Modulos\Catalogos\Laboratorios;
use Asistencias\Seguridad\Acceso;

/**
 * El alumno registra su entrada a la clase abierta del laboratorio, sin iniciar
 * sesión, con su número de control. También un docente (con su matrícula) o, en una
 * clase de maestro invitado, alguien de fuera con su nombre.
 *
 * Las respuestas de error traen un `codigo` para que la página reaccione: por ejemplo,
 * con `no_inscrito` le ofrece al alumno pedir ingreso.
 */
final class RegistroDeEntrada
{
    // Matrículas inexistentes o códigos equivocados permitidos desde una dirección. Un
    // laboratorio entero sale a internet con la misma dirección, así que el límite
    // alcanza para muchos errores de dedo, pero no para probar matrículas en masa.
    private const FALLOS_PERMITIDOS = 30;
    private const MINUTOS_DE_ESPERA = 10;

    public static function registrar(Enrutador $r): void
    {
        $r->post('/registro/entrada', Acceso::Publica, static fn (Contexto $c) => self::entrar($c));
    }

    private static function entrar(Contexto $c): Respuesta
    {
        $fallos = (int)$c->bd->valor(
            "SELECT COUNT(*) FROM bitacora WHERE accion = 'registro_fallido' AND ip = ? AND fecha > NOW() - INTERVAL " . self::MINUTOS_DE_ESPERA . ' MINUTE',
            [$c->peticion->ip]
        );
        if ($fallos >= self::FALLOS_PERMITIDOS) {
            throw new ErrorHttp(429, 'Demasiados intentos fallidos desde esta red. Espera unos minutos e inténtalo de nuevo');
        }

        try {
            return self::registrarEntrada($c);
        } catch (ErrorHttp $e) {
            if (in_array($e->extra['codigo'] ?? null, ['matricula_no_encontrada', 'codigo_incorrecto'], true)) {
                $c->bitacora->registrarAunqueFalle('registro_fallido', $e->getMessage(), null, (string)$c->peticion->cuerpo()->crudo('matricula'));
            }
            throw $e;
        }
    }

    private static function registrarEntrada(Contexto $c): Respuesta
    {
        $e = $c->peticion->cuerpo();
        $laboratorio = Laboratorios::porClave($c->bd, $e->texto('laboratorio', 'el laboratorio', 30));
        $tipo = $e->opcion('tipo', 'el tipo', ['alumno', 'docente', 'invitado'], false) ?? 'alumno';
        $etiqueta = Entradas::equipoElegido($e);

        $persona = match ($tipo) {
            'alumno' => Asistencias::alumnoQueEntra($c->bd, $e->texto('matricula', 'la matrícula', 20)),
            'docente' => self::docenteQueEntra($c, $e->texto('matricula', 'la matrícula', 30)),
            'invitado' => ['tipo' => 'invitado', 'nombre' => $e->texto('nombre', 'el nombre', 100), 'apellido' => $e->texto('apellido', 'el apellido', 100)],
        };
        $clase = self::claseDondeEntra($c, $laboratorio, $persona, $e->entero('claseId', 'la clase', 1, null, false));

        if ($c->config->exigirCodigoDeClase && !hash_equals((string)$clase['codigo'], (string)$e->crudo('codigo'))) {
            throw new ErrorHttp(403, 'El código de la clase no es correcto. Pídeselo a quien la lleva', ['codigo' => 'codigo_incorrecto']);
        }

        $entrada = Entradas::registrar($c->bd, $laboratorio['id'], $clase['id'], $persona, $etiqueta);
        $nombre = $c->bd->valor(
            'SELECT COALESCE(a.nombre, u.nombre, s.invitado_nombre) FROM asistencias s
             LEFT JOIN alumnos a ON a.id = s.alumno_id LEFT JOIN usuarios u ON u.id = s.usuario_id WHERE s.id = ?',
            [$entrada['id']]
        );
        $datos = Clases::formato($clase);
        return Respuesta::creado([
            'asistenciaId' => $entrada['id'],
            'nombre' => $nombre,
            'equipo' => $entrada['equipo'],
            'clase' => [
                'id' => $datos['id'],
                'tipo' => $datos['tipo'],
                'materia' => $datos['materia'],
                'practica' => $datos['practica']['titulo'] ?? null,
                'docente' => $datos['docente'],
            ],
        ]);
    }

    /**
     * La clase abierta a la que entra. Si hay una regular y una de maestro invitado, el
     * alumno inscrito entra a la de su curso y cualquier otro a la de invitado. Si solo
     * hay una regular y no está inscrito, se le dice para que pueda pedir ingreso.
     */
    private static function claseDondeEntra(Contexto $c, array $laboratorio, array $persona, ?int $claseId): array
    {
        $abiertas = array_map(
            static fn (int $id) => Clases::porId($c->bd, $id),
            $c->bd->columna('SELECT id FROM clases WHERE laboratorio_id = ? AND cerrada_en IS NULL ORDER BY abierta_en', [$laboratorio['id']])
        );
        if ($claseId !== null) {
            $abiertas = array_values(array_filter($abiertas, static fn (array $clase) => $clase['id'] === $claseId));
            if ($abiertas === []) {
                throw ErrorHttp::conflicto('Esa clase ya no está abierta', 'clase_cerrada');
            }
        }
        if ($abiertas === []) {
            throw ErrorHttp::conflicto("No hay ninguna clase abierta en el {$laboratorio['nombre']}", 'sin_clase');
        }

        $regular = array_values(array_filter($abiertas, static fn (array $clase) => $clase['tipo'] === 'regular'))[0] ?? null;
        $invitado = array_values(array_filter($abiertas, static fn (array $clase) => $clase['tipo'] === 'invitado'))[0] ?? null;

        return match ($persona['tipo']) {
            'alumno' => match (true) {
                $regular !== null && Clases::estaInscrito($c->bd, $regular['curso_id'], $persona['alumno_id']) => $regular,
                $invitado !== null => $invitado,
                default => throw ErrorHttp::conflicto("No estás inscrito en {$regular['curso_materia']} {$regular['curso_grupo']}", 'no_inscrito', [
                    'claseId' => $regular['id'],
                    'materia' => $regular['curso_materia'],
                ]),
            },
            // El docente entra a la clase de su curso si la hay; si no, a la que esté abierta.
            'docente' => $regular !== null && $regular['curso_docente_id'] === $persona['usuario_id'] ? $regular : ($invitado ?? $regular),
            'invitado' => $invitado ?? throw ErrorHttp::conflicto('En esta clase cada alumno se registra con su número de control', 'solo_alumnos'),
        };
    }

    private static function docenteQueEntra(Contexto $c, string $matricula): array
    {
        $id = $c->bd->valor("SELECT id FROM usuarios WHERE rol = 'docente' AND matricula = ? AND activo", [$matricula])
            ?? throw new ErrorHttp(404, 'No encontramos a ese docente', ['codigo' => 'matricula_no_encontrada']);
        return ['tipo' => 'docente', 'usuario_id' => $id];
    }
}
