# Decisiones

Las decisiones que dan forma al sistema, con su fecha y su porqué. Una decisión nueva se agrega al final; si una cambia, se anota la nueva y se marca la anterior como sustituida, sin borrarla.

## D1. El sistema sigue en este repositorio, con la API en PHP

*21 de octubre de 2026.*

El equipo pasó a trabajar en `wasabi1867/sistema_asistencias`, que Andrés armó en Next.js y PHP. El repositorio anterior (`Ozelot12100/Sistema_asignacion_academica_ITSPP`, con la API en .NET) ya no se usa.

**Por qué:** el hospedaje es Hostinger compartido, que corre PHP pero no .NET. Lo que ya estaba resuelto en .NET (el diseño de la base y las reglas del registro) se pasó a PHP.

## D2. La base se rediseñó sobre el esquema de la versión .NET

*21 de octubre de 2026.*

Materias como catálogo, cursos por periodo (materia, grupo, docente y periodo) e inscripciones como tabla, entre otras cosas. Las 19 tablas y lo que cambió respecto de la base original están en [modelo-de-datos.md](modelo-de-datos.md).

**Por qué:** la base original repetía datos y no tenía llaves foráneas. Cada semestre había que recapturar las materias, y la pantalla de asistencia descargaba a todos los alumnos al navegador.

## D3. Las pantallas no hablan con Firebase: todo pasa por la API

*22 de octubre de 2026.*

Las sesiones y los permisos los revisa el servidor, y las contraseñas se comparan allá.

**Por qué:** la versión original comparaba las contraseñas en el navegador y escribía en Firestore sin iniciar sesión. Cualquiera con la configuración web, que viene en el código publicado, podía leer y cambiar los datos.

## D4. Cada cambio va en una rama con su pull request

*22 de octubre de 2026.*

David tiene permiso de escritura en el repositorio. Aun así, nada va directo a `main`: cada cambio va en una rama con su pull request, que David acepta cuando pasan las pruebas de GitHub.

**Por qué:** el repositorio es de Andrés y lo ven los demás. Así queda registro de cada cambio, y la integración continua lo revisa antes de entrar.

## D5. El alumno solo da su número de control; lo demás lo carga el personal

*22 de octubre de 2026.*

El alumno no tiene cuenta ni escribe sus datos: escribe su número de control y elige su computadora. Sus datos y sus inscripciones los carga el personal (alumnos desde Excel, cursos, inscripciones con una lista de números de control). El registro revisa que esté inscrito en el curso de la clase; si no lo está, puede pedir permiso.

**Por qué:** es como lo pidió el instituto y como funcionaba la versión original, que también revisaba la inscripción. La inscripción es lo que permite saber quién faltó. Si el instituto prefiere no revisarla, se puede volver opcional (pregunta 10 de [preguntas-para-el-instituto.md](preguntas-para-el-instituto.md)).

## D6. Producción en MariaDB 11.8, con SQL que funcione también en MySQL

*22 de octubre de 2026.*

Hostinger da MariaDB 11.8.9, con el aislamiento de transacciones en READ COMMITTED. La computadora de desarrollo usa la misma versión, y la integración continua prueba en MariaDB 10.6 y 11.8 (esta con READ COMMITTED, desde el 23 de octubre) y en MySQL 8.0. No se usa nada que solo exista en uno de los dos, como `RETURNING` de MariaDB.

**Por qué:**
- Probar con lo mismo que producción evita sorpresas; ya hubo una, con el `mysqldump` de MariaDB.
- La 11.8 tiene soporte largo.
- Si el sistema cambia de hospedaje, lo más común en otros es MySQL. Mantener los dos cuesta muy poco, porque las pruebas corren en paralelo.

## D7. La dirección oficial es el subdominio

*22 de octubre de 2026.*

El sistema vive en https://laboratorios.soycorrecaminos.xyz. Quien entre por `soycorrecaminos.xyz/laboratorios/` se redirige al subdominio.

**Por qué:** el subdominio es la carpeta `laboratorios/` del dominio principal, así que contesta por las dos direcciones. Pero las pantallas piden `/api` y `/_next` desde la raíz, y por la segunda dirección eso apuntaría al sitio de la carrera.

## D8. Los scripts se conectan a la base de producción por MySQL remoto y cifrado

*22 de octubre de 2026.*

El plan no tiene SSH, así que instalar, crear administradores e importar se hace desde la computadora:
- Por MySQL remoto, autorizando solo la IP de esa computadora y solo para la base del sistema; nunca "Cualquier host".
- Con TLS y comprobando el certificado del servidor (`ASISTENCIAS_DB_TLS` y `ASISTENCIAS_DB_TLS_CA`).
- Con los datos de conexión en un archivo fuera del repositorio.
- La autorización se quita cuando no se ocupa.

**Por qué:** sin TLS, los datos de los alumnos viajarían por internet tal cual. Y sin comprobar el certificado, alguien en medio podría hacerse pasar por el servidor.

## D9. El sitio se sube por FTP cifrado, con una cuenta limitada a su carpeta

*22 de octubre de 2026.*

La cuenta FTP `labdeploy` solo ve `public_html/laboratorios`, y se usa con FTPS.

**Por qué:** en la misma cuenta de Hostinger están el sitio de la carrera y otros proyectos. Si un error o una contraseña filtrada afectara a esta cuenta, no alcanzaría a los demás.

## D10. Se borró la base anterior de Hostinger

*22 de octubre de 2026.*

`u568725086_asistencias`, con el esquema original, se borró para crear `u568725086_laboratorio`.

**Por qué:** el plan limita el número de bases y ya estaba en el límite. Las pantallas de la versión original guardaban todo en Firebase, no en esa base. Además, su contraseña estuvo en el historial de git, así que con borrarla esa contraseña ya no abre nada.

## D11. El primer administrador es una cuenta personal

*22 de octubre de 2026.*

El primer administrador es la cuenta de David, no una compartida. Las demás cuentas se crean desde el panel, una por persona.

**Por qué:** la bitácora registra quién hizo cada cosa. Con una cuenta compartida no se sabría quién fue.

## D12. Los datos de Firebase se importan el día del cambio, con autorización del instituto

*22 de octubre de 2026.*

La versión original sigue en uso en https://asistencialaboratorios.vercel.app y guarda en Firebase. Sus datos se importan el día en que se deja de usar, no antes. Las dos aplicaciones no se usan a la vez en las clases. Antes de descargar los datos hace falta la autorización por escrito del instituto, porque son datos personales de los alumnos. Los pasos están en [cambio-de-firebase.md](cambio-de-firebase.md).

**Por qué:** lo que se importe antes quedaría incompleto, y con las dos en uso los alumnos tendrían que registrarse dos veces.

## D13. Los permisos que el instituto no ha definido son los más restrictivos que dejan operar

*21 de octubre de 2026.*

El reparto está en [api.md](api.md#rutas) y es provisional mientras no responda la pregunta 7 de [preguntas-para-el-instituto.md](preguntas-para-el-instituto.md).

**Por qué:** ampliar un permiso después no rompe nada. Quitarlo sí, a quien ya lo usaba.

## D14. La configuración real nunca va en el repositorio

*21 de octubre de 2026.*

Contraseñas y datos de conexión van en `api/config.local.php` (que git ignora) o en variables de entorno.

**Por qué:** el repositorio es público. La contraseña de la base original estuvo en el código y, aunque se quitó, sigue en el historial de git.

## D15. La base se respalda desde la computadora, además de los respaldos de Hostinger

*23 de octubre de 2026.*

Con `scripts/exportar-datos.php` por MySQL remoto: antes del piloto, cada semana mientras se use, antes de cada migración y alrededor del día del cambio. Se restaura con `scripts/restaurar.php`, solo en una base vacía y del mismo esquema. Los archivos van fuera del repositorio, y la copia fuera de la computadora, cifrada: desde el 24 de octubre, en el Google Drive de David con rclone `crypt`, con las claves en su gestor de contraseñas. Los pasos están en [cambio-de-firebase.md](cambio-de-firebase.md#respaldos).

**Por qué:**
- El plan Single solo respalda una vez a la semana, y restaurar desde hPanel reemplaza la base completa: se perdería hasta una semana de asistencias.
- Un respaldo propio se puede hacer justo antes de un paso riesgoso, como importar Firebase o aplicar una migración.
- Se puede probar restaurándolo en otra base sin tocar producción.
- No se usa `mysqldump` por lo de D6: el de MariaDB escribe valores que MySQL rechaza.

## D16. Next.js se queda en la 14 por ahora; las demás dependencias se actualizan

*23 de octubre de 2026.*

`npm audit` marca 25 avisos de seguridad en Next.js 14.2.35, la última de la 14. Ninguno aplica aquí, porque todos son de su servidor:
- Image Optimization: se usa `images.unoptimized`.
- Server Actions, Server Components, App Router y middleware: el sitio usa Pages Router y no tiene nada de eso.
- Reescrituras, caché y `beforeInteractive`.

En producción no hay servidor de Next: Hostinger sirve los archivos que deja `npm run build`. Los avisos de PostCSS y Tailwind son de herramientas que solo corren al compilar.

Lo que sí llega al navegador se actualizó: jsPDF de la 3 a la 4.2.1 (y jspdf-autotable 5.0.9), y con `npm audit fix` DOMPurify, fflate y nanoid, entre otras.

**Por qué:** pasar a Next.js 15 o 16 obliga a usar React 19 y a revisar todas las pantallas. Vale la pena, porque la 14 ya no recibe arreglos, pero no corre prisa mientras no se use su servidor. Se hará con la prueba del navegador en el repositorio, para comprobar cada pantalla.
