<?php
// Copia este archivo como config.local.php (en la misma carpeta) y llena los datos.
// config.local.php no se sube a git. En Hostinger se crea una sola vez desde el
// administrador de archivos, en la carpeta api/ del sitio.

return [
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => 'nombre_de_la_base',
    'db_user' => 'usuario_de_la_base',
    'db_pass' => 'contraseña_de_la_base',

    // Solo si el frontend se sirve desde otro dominio, por ejemplo durante el
    // desarrollo con `npm run dev`: 'http://localhost:3000'
    'origenes_permitidos' => '',
];
