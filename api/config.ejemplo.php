<?php

// Copia este archivo como config.local.php, en esta misma carpeta, y llena los datos.
// config.local.php no se sube a git. En Hostinger se crea una sola vez desde el
// administrador de archivos, en la carpeta api/ del sitio.

return [
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => 'nombre_de_la_base',
    'db_user' => 'usuario_de_la_base',
    'db_pass' => 'contraseña_de_la_base',

    // Solo al conectarse desde otra computadora a la base de Hostinger (MySQL remoto):
    // cifra la conexión y, con el archivo de autoridades, comprueba el certificado del
    // servidor. En el servidor, con 'localhost', se deja en false.
    'db_tls' => false,
    'db_tls_ca' => '',

    // Solo si las pantallas se sirven desde otro dominio, por ejemplo durante el
    // desarrollo con `npm run dev`: 'http://localhost:3000'
    'origenes_permitidos' => '',

    // Zona horaria en que se guardan las horas.
    'zona_horaria' => 'America/Hermosillo',

    // Si el alumno debe escribir el código que se muestra en el aula al registrar su
    // entrada. Evita que alguien registre a un compañero desde fuera del laboratorio.
    'exigir_codigo_de_clase' => false,
];
