# Publicar en Hostinger y dejar Firebase

Con esta versión, todas las pantallas leen y escriben en la base de Hostinger a través de la API ([`api/`](../api)), y Firebase deja de usarse. Este documento lista lo que hay que hacer una sola vez para publicarla, en el orden en que se hace.

## Cómo es el hospedaje

Lo averiguado el 22 de octubre de 2026:

- **La cuenta** es de Daniel Alonso Osuna Talamantes. David entra como colaborador: puede administrar, pero no comprar ni cambiar de plan.
- **El plan** es Single Web Hosting:
  - Un solo sitio y un número limitado de bases de datos. Al crear la de este sistema apareció el aviso de límite.
  - **Sin terminal (SSH).**
  - **Con MySQL remoto:** la base se puede usar desde una computadora cuya IP se autorice.
  - PHP 8.3.
- **La base de datos** es MariaDB 11.8.9, en `srv827.hstgr.io`, puerto 3306. La de este sistema es `u568725086_laboratorio`, con el usuario `u568725086_lab_api`. Las demás bases de la cuenta no se tocan.
- **El sitio** es `laboratorios.soycorrecaminos.xyz`. En Hostinger ese subdominio es la carpeta `laboratorios/` del dominio principal, así que también contesta en `soycorrecaminos.xyz/laboratorios/`. Ahí las pantallas no funcionarían: piden `/api` y `/_next` desde la raíz, que es el sitio de la carrera. Por eso el `.htaccess` manda esa dirección al subdominio.
- **Lo que está publicado hoy** es una página de "Plataforma en desarrollo · Próximamente disponible", sin Firebase. Publicar esta versión no le quita nada a nadie.
- **Delante del sitio hay un CDN de Hostinger** (`Server: hcdn`). Después de subir hay que vaciar su caché.

## Los datos de Firebase

Firebase no está conectado a nada publicado: ni a este sitio ni a Firebase Hosting (`integrador-7b39d.web.app`, que solo muestra la página de bienvenida de Firebase). Antes de importar, **hay que preguntarle a Andrés si alguien usó de verdad la versión con Firebase**, por ejemplo corriéndola en las computadoras del laboratorio:

- **Si nadie la usó,** sus datos son de prueba y no se importan. Se publica con la base vacía y se cargan los datos reales desde el sistema.
- **Si sí la usaron,** se importan con `scripts/importar-de-firebase.php` (paso 4). El script lee Firebase directamente con la configuración web del proyecto: no hay que descargar nada a mano. Son datos personales de alumnos, así que hace falta el visto bueno de Andrés.

Lo mejor es importar **antes** de que se use el sistema nuevo. Importar sobre una base en uso es posible, porque el script busca lo que ya existe en lugar de duplicarlo, pero hay que tener en cuenta lo siguiente:
- **Las materias se buscan por nombre exacto.** Una capturada con otro nombre que en Firebase ("Programación Web" y "Programacion web I") queda dos veces.
- **Los cursos se buscan por materia, periodo y docente.** Si el docente es otro, se crea un curso más y las inscripciones de Firebase van a ese.
- **Los alumnos que estaban activos en Firebase se sobrescriben** con los datos de Firebase (nombre, carrera, semestre, grupo, turno) y quedan activos, aunque se hayan corregido o desactivado en el sistema nuevo.
- **Los equipos de cada laboratorio quedan como estaban en Firebase:** los que no estén allá se retiran, y "fuera de servicio" y las notas vuelven a los de Firebase.
- **Los periodos se buscan por fecha.** Si ningún periodo cubre la fecha de una clase de Firebase, la importación crea "AGO-DIC" (del 1 de agosto al 31 de diciembre) o "ENE-JUL" (del 1 de enero al 31 de julio) con su año. Si ya existe uno con ese nombre pero con otras fechas, la importación entera falla sin guardar nada; si el creado a mano se llama distinto, quedan dos periodos encimados. Para evitarlo, el periodo actual se crea como **AGO-DIC 2026, del 1 de agosto al 31 de diciembre de 2026**.
- **Las cuentas del personal se buscan por rol y por matrícula o correo.** Se actualizan sus datos, pero no su contraseña.

## 1. Conectarse a la base desde la computadora

Sin SSH, los scripts de `scripts/` se corren en una computadora y se conectan a la base de Hostinger por MySQL remoto.

1. **Autorizar la IP** de la computadora en hPanel → Bases de datos → MySQL remoto, solo para `u568725086_laboratorio`. **Nunca "Cualquier host".** La IP pública se consulta en https://api.ipify.org; conviene consultarla varias veces, porque algunas redes alternan entre dos. Si un día falla la conexión con "Access denied", lo primero es revisar si la IP cambió.
2. **Crear un archivo con la conexión, fuera del repositorio**, por ejemplo `C:\Users\<usuario>\hostinger-itspp\base-hostinger.env`:

   ```
   ASISTENCIAS_DB_HOST=srv827.hstgr.io
   ASISTENCIAS_DB_PORT=3306
   ASISTENCIAS_DB_NAME=u568725086_laboratorio
   ASISTENCIAS_DB_USER=u568725086_lab_api
   ASISTENCIAS_DB_PASS=la contraseña, sin comillas
   ASISTENCIAS_DB_TLS=1
   ASISTENCIAS_DB_TLS_CA=C:/Program Files/Git/mingw64/etc/ssl/certs/ca-bundle.crt
   ```

   - **Las variables de entorno van antes que `api/config.local.php`**, así que no hace falta tocar ese archivo.
   - **`ASISTENCIAS_DB_TLS` cifra la conexión.** Sin ella, todo, incluidos los datos de los alumnos, viajaría por internet sin cifrar.
   - **`ASISTENCIAS_DB_TLS_CA` comprueba que del otro lado está Hostinger.** El servidor presenta un certificado de Let's Encrypt para `*.hstgr.io`, y el archivo de autoridades que trae Git para Windows lo reconoce.
3. **Cargar el archivo en PowerShell.** Las variables quedan solo en esa ventana y desaparecen al cerrarla:

   ```powershell
   Get-Content "$env:USERPROFILE\hostinger-itspp\base-hostinger.env" | Where-Object { $_ -match '^\s*[A-Z_]+=' } | ForEach-Object { $n, $v = $_ -split '=', 2; [Environment]::SetEnvironmentVariable($n.Trim(), $v.Trim(), 'Process') }
   ```

Si algún día no hubiera MySQL remoto, se puede trabajar con una base local y subir sus datos con phpMyAdmin: `php scripts/exportar-datos.php archivo.sql` genera un archivo que carga igual en MariaDB que en MySQL, sin las tablas. `mysqldump` no sirve para esto, porque el de MariaDB escribe valores que MySQL rechaza.

## 2. Instalar la base

En la misma ventana, desde la carpeta del repositorio:

```powershell
php scripts/instalar.php
```

Crea las 19 tablas y carga los datos iniciales: laboratorios, carreras y los equipos del Taller de Programación. Si la base ya tiene tablas, se niega a correr.

## 3. Primer administrador

```powershell
php scripts/crear-admin.php correo@puertopenasco.tecnm.mx "Nombre" "Apellido"
```

Pide la contraseña al correrlo, para que no quede en el historial. Mientras se escribe, se ve en pantalla.

## 4. Importar los datos de Firebase (solo si se usaron)

Siempre primero con `--simular`, que hace todo, muestra el reporte y al final lo deshace. `--respaldo` guarda cada colección tal como venía de Firebase:

```powershell
php scripts/importar-de-firebase.php --proyecto=integrador-7b39d --clave=APIKEY --respaldo=C:\Users\<usuario>\hostinger-itspp\respaldo-firebase --simular
```

`APIKEY` es la `apiKey` de la configuración web de Firebase. El respaldo tiene datos personales y contraseñas cifradas: va fuera del repositorio y no se sube al sitio.

El reporte dice cuántos registros de cada cosa se importarían y qué hay que revisar a mano. Lo más común son **carreras escritas de otra forma**: la importación las busca en el catálogo sin importar mayúsculas ni acentos, pero si una no está, sus alumnos quedan sin carrera. Se agrega la carrera al catálogo y se vuelve a simular con `--desde=<carpeta del respaldo>`, que ya no descarga nada.

Cuando el reporte esté bien, se importa en serio:

```powershell
php scripts/importar-de-firebase.php --desde=C:\Users\<usuario>\hostinger-itspp\respaldo-firebase
```

Cada quien entra con su contraseña de siempre:
- Las que venían cifradas (bcrypt) se copian tal cual y se vuelven a cifrar en el formato de PHP la primera vez que su dueño inicia sesión.
- Las que estuvieran en texto se cifran durante la importación.

## 5. Configuración de la API en el servidor

Con el administrador de archivos de hPanel, en la carpeta `laboratorios/api/` del sitio, se crea `config.local.php` a partir de [`config.ejemplo.php`](../api/config.ejemplo.php):
- `db_host` es `localhost`, porque la API corre en el mismo servidor que la base.
- `db_name`, `db_user` y `db_pass` son los de `u568725086_laboratorio`.
- `db_tls` se queda en `false`, porque la conexión no sale del servidor.

**No comprobado:** que el usuario de la base pueda entrar desde `localhost`. Hostinger lo permite por omisión, pero el paso 7 lo confirma.

## 6. Compilar y subir el sitio

1. **Respaldar lo que hay hoy** en la carpeta `laboratorios/`: descargar su contenido desde el administrador de archivos o por FTP. Es la página de "Próximamente".
2. **Compilar** en la computadora, sin `.env.local`, para que la API quede en `/api`:

   ```bash
   npm ci
   npm run build
   ```

3. **Subir el contenido de `out/`** a la carpeta `laboratorios/`, reemplazando lo que había:
   - Incluye la carpeta `api/` con su `.htaccess`, que deja pasar las peticiones a `api/index.php` y no deja leer ningún otro archivo de la API.
   - **No hay que borrar `api/config.local.php` del servidor.** `npm run build` no lo copia a `out/`, para no reemplazar el del servidor con los datos de la computadora.
4. **Vaciar la caché del CDN** en hPanel, para que nadie siga viendo la página anterior.

Las direcciones de la versión con Firebase (`/AdminPanel`, `/lista-asistencias`, `/lista-asistenciasInvitado`, `/panel-laboratorista`) nunca se publicaron aquí, pero el `.htaccess` las redirige a las nuevas por si alguien las tiene guardadas.

## 7. Probar

- Entrar como administrador, docente y laboratorista.
- Abrir una clase, registrar a un alumno desde un teléfono y cerrarla.
- Revisar que la clase aparezca en los reportes y, si se importó, que el historial se vea.
- Revisar que `soycorrecaminos.xyz/laboratorios/` lleve al subdominio.
- Revisar que el CDN no guarde respuestas de `/api`: un cambio, como registrar una entrada, debe verse al recargar.

## 8. Después de publicar

- **Cambiar la contraseña de la base anterior** (`u568725086_asistencias`) en hPanel. Estuvo publicada en el repositorio, y quitarla del código no la borra del historial de git.
- **Quitar la autorización de MySQL remoto** cuando ya no haga falta correr scripts desde la computadora. Se vuelve a agregar en un minuto si se necesita.
- **Dejar Firebase unas semanas sin borrar,** como respaldo. Lo ideal es de solo lectura: la aplicación anterior escribía sin iniciar sesión, así que sus reglas deben permitir escribir a cualquiera que tenga la configuración web. No se han revisado las reglas mismas.

## Qué cambió para cada quien

- **Alumnos:** el registro va en dos pasos: primero el número de control y luego la computadora, que se elige en una cuadrícula donde las ocupadas no se pueden tocar. La confirmación se queda unos segundos y la pantalla vuelve sola al principio para el siguiente. El servidor revisa:
  - que haya una clase abierta;
  - que esté inscrito en el curso, o que la clase sea de maestro invitado;
  - que la computadora esté libre;
  - que no esté ya registrado.

  Si se activa `exigir_codigo_de_clase`, además escriben el código que se muestra en el aula.
- **Personal:** todos entran por el botón **Personal** de la página principal (`/entrar`), eligiendo si son docente, laboratorista o administrador. Antes el administrador entraba desde un menú escondido. La sesión la lleva el servidor: una cuenta desactivada deja de entrar de inmediato. Cambiar la contraseña, el tema y cerrar sesión están en el menú con su nombre, arriba a la derecha.
- **Paneles:** los tres (docente, laboratorio y administrador) tienen el mismo menú lateral, que en el teléfono se abre con un botón. La sección abierta queda en la dirección, así que recargar o usar "atrás" no la pierde. Lo que se puede hacer con cada renglón de una tabla está en su botón "⋯".
- **Administrador:** un panel nuevo con tablero (clases abiertas, solicitudes y avisos), alumnos con carga desde Excel y cambio de semestre en grupo, cursos con sus inscripciones, materias y prácticas, cuentas del personal, y carreras, periodos y laboratorios.
- **Lector de credencial:** el lector físico funciona dentro de la ventana que abre **Registrar con mi credencial**, en el formulario de registro. Antes, un campo oculto de la página principal tenía siempre el foco.
- **Listas en vivo:** las pantallas consultan la API cada pocos segundos, en lugar de recibir los cambios al instante.
- **Bitácora:** la llena el servidor (quién, qué, cuándo y desde dónde), en lugar de cada pantalla.
