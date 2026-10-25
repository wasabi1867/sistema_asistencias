<?php

declare(strict_types=1);

// Publica el sitio compilado (out/) en Hostinger por FTP cifrado (FTPS explícito), con la
// cuenta FTP limitada a la carpeta del sitio. Se corre en la computadora, con la conexión
// en variables (ver docs/cambio-de-firebase.md, paso 6):
//
//   php -d extension=ftp scripts/publicar.php listar
//   php -d extension=ftp scripts/publicar.php respaldar C:\ruta\respaldo-del-sitio
//   php -d extension=ftp scripts/publicar.php subir --simular
//   php -d extension=ftp scripts/publicar.php subir
//
// Variables: ASISTENCIAS_FTP_HOST, ASISTENCIAS_FTP_PORT (21), ASISTENCIAS_FTP_USER y
// ASISTENCIAS_FTP_PASS. Nunca imprime la contraseña.
//
// `subir` no borra nada del servidor, y nunca sube un api/config.local.php: el del servidor
// tiene los datos de la base de producción. Sube primero los archivos de _next/ y al final
// las páginas, para que nadie reciba una página nueva que pida archivos que aún no están.

if (PHP_SAPI !== 'cli') {
    exit(1);
}

function fallar(string $mensaje): never
{
    fwrite(STDERR, $mensaje . "\n");
    exit(1);
}

$comando = $argv[1] ?? '';
$simular = in_array('--simular', $argv, true);
$out = dirname(__DIR__) . '/out';

if (!in_array($comando, ['listar', 'respaldar', 'subir'], true)) {
    fallar("Uso: php -d extension=ftp scripts/publicar.php listar | respaldar CARPETA | subir [--simular]");
}

// Lo que se va a subir se revisa antes de conectarse.
$archivos = [];
if ($comando === 'subir') {
    if (!is_file("$out/index.html") || !is_file("$out/api/index.php")) {
        fallar('Falta compilar el sitio: npm run build');
    }
    if (is_file("$out/api/config.local.php")) {
        fallar("out/api/config.local.php existe y pisaría el del servidor, que tiene la conexión de producción.\n"
            . 'Bórralo (o vuelve a correr npm run build, que no lo copia) y publica de nuevo.');
    }
    $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($out, FilesystemIterator::SKIP_DOTS));
    foreach ($iterador as $archivo) {
        $archivos[] = str_replace('\\', '/', substr($archivo->getPathname(), strlen($out)));
    }
    // Primero _next/, luego lo demás, y las páginas al final.
    $orden = static fn (string $ruta) => str_starts_with($ruta, '/_next/') ? 0 : (str_ends_with($ruta, '.html') ? 2 : 1);
    usort($archivos, static fn (string $a, string $b) => [$orden($a), $a] <=> [$orden($b), $b]);
}

if (!extension_loaded('ftp')) {
    fallar('Falta la extensión ftp de PHP: corre el script con php -d extension=ftp');
}
$variable = static fn (string $nombre, string $porOmision = '') => getenv($nombre) !== false ? (string)getenv($nombre) : $porOmision;
$servidor = $variable('ASISTENCIAS_FTP_HOST');
$usuario = $variable('ASISTENCIAS_FTP_USER');
if ($servidor === '' || $usuario === '') {
    fallar('Faltan las variables ASISTENCIAS_FTP_HOST y ASISTENCIAS_FTP_USER (ver docs/cambio-de-firebase.md, paso 6).');
}

// Solo FTPS: el FTP sin cifrar manda la contraseña tal cual.
$ftp = @ftp_ssl_connect($servidor, (int)$variable('ASISTENCIAS_FTP_PORT', '21'), 30)
    ?: fallar("No se pudo abrir la conexión cifrada con $servidor");
if (!@ftp_login($ftp, $usuario, $variable('ASISTENCIAS_FTP_PASS'))) {
    fallar("El servidor rechazó el usuario $usuario o su contraseña");
}
ftp_pasv($ftp, true);

/** @return list<array{nombre: string, tipo: string, tamano: int}> */
function contenido($ftp, string $ruta): array
{
    $lista = ftp_mlsd($ftp, $ruta);
    if ($lista === false) {
        fallar("No se pudo listar $ruta");
    }
    $salida = [];
    foreach ($lista as $e) {
        if (!in_array($e['type'], ['cdir', 'pdir'], true)) {
            $salida[] = ['nombre' => $e['name'], 'tipo' => $e['type'], 'tamano' => (int)($e['size'] ?? 0)];
        }
    }
    return $salida;
}

function listar($ftp, string $ruta, string $sangria = ''): void
{
    foreach (contenido($ftp, $ruta) as $e) {
        echo $sangria . $e['nombre'] . ($e['tipo'] === 'dir' ? '/' : "  ({$e['tamano']} bytes)") . "\n";
        if ($e['tipo'] === 'dir') {
            listar($ftp, rtrim($ruta, '/') . '/' . $e['nombre'], $sangria . '  ');
        }
    }
}

function respaldar($ftp, string $ruta, string $local, int &$cuantos): void
{
    if (!is_dir($local) && !mkdir($local, 0777, true)) {
        fallar("No se pudo crear $local");
    }
    foreach (contenido($ftp, $ruta) as $e) {
        $remota = rtrim($ruta, '/') . '/' . $e['nombre'];
        if ($e['tipo'] === 'dir') {
            respaldar($ftp, $remota, "$local/{$e['nombre']}", $cuantos);
        } elseif (!ftp_get($ftp, "$local/{$e['nombre']}", $remota, FTP_BINARY)) {
            fallar("Falló la descarga de $remota");
        } else {
            $cuantos++;
        }
    }
}

switch ($comando) {
    case 'listar':
        echo "Conectado con cifrado como $usuario.\n";
        listar($ftp, '/');
        break;

    case 'respaldar':
        $destino = $argv[2] ?? '';
        if ($destino === '' || str_starts_with($destino, '--')) {
            fallar('Uso: php -d extension=ftp scripts/publicar.php respaldar CARPETA');
        }
        if (is_dir($destino) && count(scandir($destino)) > 2) {
            fallar("$destino ya tiene archivos: elige una carpeta nueva para no mezclar respaldos.");
        }
        $cuantos = 0;
        respaldar($ftp, '/', $destino, $cuantos);
        echo "Respaldo del sitio: $cuantos archivos en $destino\n";
        break;

    case 'subir':
        $carpetas = [];
        $bytes = 0;
        foreach ($archivos as $i => $ruta) {
            $carpeta = dirname($ruta);
            if ($carpeta !== '/' && !isset($carpetas[$carpeta])) {
                $parcial = '';
                foreach (explode('/', trim($carpeta, '/')) as $parte) {
                    $parcial .= "/$parte";
                    if (!isset($carpetas[$parcial]) && !$simular && @ftp_chdir($ftp, $parcial) === false && !@ftp_mkdir($ftp, $parcial)) {
                        fallar("No se pudo crear la carpeta $parcial");
                    }
                    $carpetas[$parcial] = true;
                }
            }
            $bytes += filesize($out . $ruta);
            if ($simular) {
                continue;
            }
            if (!ftp_put($ftp, $ruta, $out . $ruta, FTP_BINARY)) {
                fallar("Falló la subida de $ruta (se subieron " . $i . ' de ' . count($archivos) . '; vuelve a correr subir)');
            }
            if (($i + 1) % 50 === 0) {
                echo '  ' . ($i + 1) . ' de ' . count($archivos) . "\n";
            }
        }
        $total = count($archivos) . ' archivos (' . round($bytes / 1048576, 1) . ' MB)';
        echo $simular ? "Se subirían $total. No se subió nada.\n" : "Subidos $total.\n";
        break;
}
ftp_close($ftp);
