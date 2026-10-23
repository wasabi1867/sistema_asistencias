<?php

declare(strict_types=1);

// Sirve out/ con el servidor de PHP como lo hace el .htaccess en Hostinger: /api/* va a
// la API, los archivos que existen salen tal cual y /pagina se responde con pagina.html.
// Lo arranca tests/pantallas/correr.js:
//
//   php -S 127.0.0.1:8080 -t out tests/pantallas/servidor.php

$raiz = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$ruta = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($ruta === '/api' || str_starts_with($ruta, '/api/')) {
    $_SERVER['SCRIPT_NAME'] = '/api/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $raiz . '/api/index.php';
    chdir($raiz . '/api');
    require $raiz . '/api/index.php';
    return true;
}
if ($ruta !== '/' && is_file($raiz . $ruta)) {
    return false;
}
if ($ruta === '/') {
    $ruta = '/index';
}
$pagina = $raiz . rtrim($ruta, '/') . '.html';
if (is_file($pagina)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($pagina);
    return true;
}
http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
readfile($raiz . '/404.html');
return true;
