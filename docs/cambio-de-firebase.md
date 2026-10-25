# Publicar en Hostinger y dejar Firebase

Con esta versión, todas las pantallas leen y escriben en la base de Hostinger a través de la API ([`api/`](../api)), y Firebase deja de usarse. Este documento lista lo que hay que hacer una sola vez para publicarla, en el orden en que se hace.

**Estado al 23 de octubre de 2026:** los pasos 1, 2, 3, 5, 6 y 7 están hechos y el sistema está publicado. Los respaldos de la base están probados (sección "Respaldos", al final). Falta el **día del cambio**: dejar la aplicación anterior, que sigue en uso en Vercel, e importar sus datos (paso 4).

## Cómo es el hospedaje

Lo averiguado el 22 de octubre de 2026:

- **La cuenta** es de Daniel Alonso Osuna Talamantes. David entra como colaborador: puede administrar, pero no comprar ni cambiar de plan.
- **El plan** es Single Web Hosting:
  - Un solo sitio y un número limitado de bases de datos. Para crear la de este sistema se borró la anterior, `u568725086_asistencias`, que tenía el esquema del sistema original; las pantallas de ese sistema guardaban todo en Firebase, no en ella.
  - **Sin terminal (SSH).**
  - **Con MySQL remoto:** la base se puede usar desde una computadora cuya IP se autorice.
  - PHP 8.3.
- **La base de datos** es MariaDB 11.8.9, en `srv827.hstgr.io`, puerto 3306. La de este sistema es `u568725086_laboratorio`, con el usuario `u568725086_lab_api`. Las demás bases de la cuenta no se tocan.
- **El sitio** es `laboratorios.soycorrecaminos.xyz`. En Hostinger ese subdominio es la carpeta `laboratorios/` del dominio principal, así que también contesta en `soycorrecaminos.xyz/laboratorios/`. Ahí las pantallas no funcionarían: piden `/api` y `/_next` desde la raíz, que es el sitio de la carrera. Por eso el `.htaccess` manda esa dirección al subdominio.
- **Lo que había antes** en la carpeta del subdominio: una página de "Plataforma en desarrollo · Próximamente disponible", el `default.php` de Hostinger y una carpeta `frontend/` con una compilación vieja del sistema original y su API en PHP sin protección. Se retiraron al publicar, con respaldo en la computadora de David.
- **Delante del sitio hay un CDN de Hostinger** (`Server: hcdn`). No guarda copias de las páginas ni de la API (responde `x-hcdn-cache-status: DYNAMIC`), así que no hace falta vaciarlo al subir. La primera visita sin cookie recibe una redirección 307 a la misma dirección; los navegadores la siguen solos.

## La aplicación anterior y sus datos

**La que se usa hoy en el ITSPP** es la versión original del sistema, publicada en Vercel: https://asistencialaboratorios.vercel.app. Lo que se sabe, revisando solo lo que el sitio publica (22 de octubre de 2026):
- Es la versión del primer commit de este repositorio: tiene las mismas páginas (`/`, `/AdminPanel`, `/lista-asistencias`, `/lista-asistenciasInvitado`, `/panel-laboratorista`, `/schedule-dashboard`, `/layout`).
- Su última publicación es del 15 de octubre de 2026, según Vercel; puede estar en uso desde antes.
- Guarda todo en el proyecto de Firebase `integrador-7b39d`, el mismo que lee `scripts/importar-de-firebase.php`. **Sus datos son reales y siguen creciendo.**
- Compara las contraseñas en el navegador (`bcrypt` en el JavaScript publicado) y escribe en Firestore sin iniciar sesión. Eso quiere decir que las reglas de Firestore dejan leer y escribir a cualquiera que tenga la configuración web, que está en el código publicado, incluidas las contraseñas cifradas del personal. No se han revisado las reglas mismas. Es otra razón para hacer el cambio pronto.

**Por eso los datos de Firebase se importan el día del cambio, no antes:**
- Mientras la aplicación anterior siga en uso, cada clase agrega datos a Firebase. Lo que se importe antes quedaría incompleto.
- Las dos aplicaciones no deben usarse a la vez en las clases: los alumnos tendrían que registrarse dos veces, y las listas quedarían repartidas.
- Lo que haga falta para un piloto (un grupo, un docente, un laboratorio) se carga a mano en el sistema nuevo. Al importar, el script empata lo que ya exista (ver abajo).

**Lo que hace falta antes del día del cambio** (pedido a Andrés y al instituto; ver [preguntas-para-el-instituto.md](preguntas-para-el-instituto.md), sección "Para probar con datos reales"):
- La autorización por escrito del instituto para usar los datos personales de los alumnos en el sistema nuevo y pasar a él los de Firebase.
- Acceso de lectura al proyecto de Firebase, para revisar los datos y simular la importación.
- La fecha del cambio, acordada con quienes usan la aplicación anterior.

**El día del cambio**, sin clases abiertas:
1. Avisar a docentes y laboratoristas la dirección nueva.
2. Respaldar la base nueva (sección "Respaldos").
3. Simular la importación (paso 4) y revisar el reporte.
4. Importar en serio. Repetirla después no duplica nada, por si alguien registró algo en la anterior a última hora.
5. Respaldar otra vez, ya con los datos importados.
6. Retirar la aplicación anterior: que quien controla el proyecto de Vercel la quite o la haga redirigir a la dirección nueva.
7. Dejar Firebase unas semanas sin borrar, como respaldo, y de solo lectura.

**Si el sistema nuevo ya se usó antes de importar** (por ejemplo, en un piloto), la importación busca lo que ya existe en lugar de duplicarlo, pero hay que tener en cuenta lo siguiente:
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

Pide la contraseña al correrlo, para que no quede en el historial. Mientras se escribe, se ve en pantalla. Si la cuenta ya existe, solo le pone la contraseña nueva.

También se le puede pasar por tubería (`"la contraseña" | php scripts/crear-admin.php ...`). PowerShell 5.1 antepone así una marca invisible (BOM) que el script quita; antes de esa corrección, la contraseña quedaba guardada con ella y no servía para entrar.

## 4. Importar los datos de Firebase (el día del cambio)

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

Comprobado el 22 de octubre: el usuario de la base entra desde `localhost` sin más configuración.

## 6. Compilar y subir el sitio

Se sube por FTP con la cuenta **`labdeploy`**, que se creó en hPanel → Archivos → Cuentas FTP con el directorio `public_html/laboratorios`: solo ve la carpeta de este sitio, no la del sitio de la carrera ni la de otros proyectos. Servidor `ftp.soycorrecaminos.xyz`, puerto 21. Acepta **FTP cifrado (FTPS explícito)**, que es como debe usarse: el FTP sin cifrar manda la contraseña tal cual.

**La conexión va en un archivo fuera del repositorio**, como la de la base, por ejemplo `C:\Users\<usuario>\hostinger-itspp\ftp-hostinger.env`:

```
ASISTENCIAS_FTP_HOST=ftp.soycorrecaminos.xyz
ASISTENCIAS_FTP_PORT=21
ASISTENCIAS_FTP_USER=u568725086.labdeploy
ASISTENCIAS_FTP_PASS=la contraseña, sin comillas
```

Se carga en PowerShell igual que la de la base (paso 1), cambiando el nombre del archivo. Luego, desde la carpeta del repositorio:

1. **Respaldar la base** ("Respaldos", abajo) si la publicación trae una migración.
2. **Compilar**, sin `.env.local`, para que la API quede en `/api`:

   ```bash
   npm ci
   npm run build
   ```

3. **Respaldar el sitio** que está publicado, en una carpeta nueva:

   ```powershell
   php -d extension=ftp scripts/publicar.php respaldar "$env:USERPROFILE\hostinger-itspp\sitio-$(Get-Date -Format 'yyyy-MM-dd-HHmm')"
   ```

4. **Subir** `out/`. Primero `--simular`, que solo dice cuántos archivos subiría:

   ```powershell
   php -d extension=ftp scripts/publicar.php subir --simular
   php -d extension=ftp scripts/publicar.php subir
   ```

   - Sube primero `_next/` y al final las páginas: nadie recibe una página nueva que pida archivos que aún no están.
   - **No borra nada del servidor.** Los archivos de `_next/` de publicaciones anteriores se quedan; no estorban.
   - **Nunca sube un `api/config.local.php`.** Si existe en `out/api/`, se niega a subir: pisaría el del servidor, que tiene la conexión de producción. `npm run build` no lo copia.
   - Solo usa FTPS; si el servidor no acepta cifrado, no se conecta.
   - La primera vez, además, se sube `api/config.local.php` a mano (paso 5).
5. **Comprobar** con la prueba rápida (paso 7). El CDN no guarda copias, así que no hace falta vaciarlo.

**Si algo sale mal**, se sube la carpeta del respaldo del paso 3 con FileZilla, en modo "Requiere FTP explícito sobre TLS".

Las direcciones de la aplicación anterior (`/AdminPanel`, `/lista-asistencias`, `/lista-asistenciasInvitado`, `/panel-laboratorista`) el `.htaccess` las redirige a las nuevas. Sirve para quien cambie `asistencialaboratorios.vercel.app` por `laboratorios.soycorrecaminos.xyz` en una dirección guardada.

## 7. Probar

**Después de cada publicación**, la prueba rápida del sitio publicado (`tests/produccion/prueba-de-humo.js`):
- revisa la página principal y la de registro, en computadora y teléfono;
- comprueba que la API responda;
- con una cuenta de administrador, inicia sesión, abre cada sección del panel y cierra la sesión.

No crea datos.

```powershell
$env:ASISTENCIAS_HUMO_USUARIO = "correo del administrador"
$env:ASISTENCIAS_HUMO_CONTRASENA = "su contraseña"
npm run prueba:produccion
```

Sin esas dos variables, solo revisa las páginas públicas. Usa Edge en Windows.

**La primera vez, y cuando haya datos reales**, además a mano:
- Entrar como administrador, docente y laboratorista.
- Abrir una clase, registrar a un alumno desde un teléfono y cerrarla.
- Revisar que la clase aparezca en los reportes y, si se importó, que el historial se vea.
- Revisar que `soycorrecaminos.xyz/laboratorios/` lleve al subdominio.
- Revisar que el CDN no guarde respuestas de `/api`: un cambio, como registrar una entrada, debe verse al recargar.

## 8. Después de publicar

- **Quitar la autorización de MySQL remoto** cuando ya no haga falta correr scripts desde la computadora. Se vuelve a agregar en un minuto si se necesita (por ejemplo, el día del cambio).
- **Cambiar la contraseña del administrador** desde el sistema (menú con su nombre → Cambiar contraseña). La inicial se generó al azar y se guardó en la computadora de David.
- **Después del día del cambio:** dejar Firebase unas semanas sin borrar y de solo lectura, y luego borrarlo junto con la aplicación de Vercel.
- **Respaldar la base** con la frecuencia de la sección siguiente.

## Respaldos

**Los de Hostinger no bastan.** El plan Single hace un respaldo automático **a la semana**, de los archivos y de la base, y restaurarlo desde hPanel reemplaza la base completa: se pierde todo lo registrado desde ese respaldo. Los diarios son un complemento de pago. Por eso, además, se respalda la base desde la computadora.

**Cómo.** Con la conexión cargada (paso 1, y la IP autorizada en MySQL remoto), desde la carpeta del repositorio:

```powershell
New-Item -ItemType Directory -Force "$env:USERPROFILE\hostinger-itspp\respaldos" | Out-Null
php scripts/exportar-datos.php "$env:USERPROFILE\hostinger-itspp\respaldos\laboratorio-$(Get-Date -Format 'yyyy-MM-dd-HHmm').sql"
```

- Lee todas las tablas como estaban en un mismo instante, aunque haya una clase abierta. No cambia nada en la base.
- El archivo anota las migraciones que tenía la base y termina con `-- Fin del respaldo: N renglones de 19 tablas.` Si esa línea falta, el respaldo quedó cortado y no sirve.
- **Tiene datos personales.** Va fuera del repositorio. La segunda copia, fuera de la computadora, va cifrada.
- **Dónde está la copia cifrada** (desde el 24 de octubre de 2026):
  - En el Google Drive de David, con un remoto `crypt` de rclone. Cifra el contenido y los nombres; Google solo ve datos ilegibles.
  - **Las dos claves del cifrado** están en la computadora de David y en otra carpeta del mismo Drive, `Sistema asistencias ITSPP (claves)`, en texto, junto con un `LEEME` que explica cómo recuperar todo en otra computadora. Sin ellas la copia no se puede leer.
  - **Lo que eso implica:** con las claves en la misma cuenta, el cifrado protege los datos de Google, pero no de quien entre a la cuenta. Esa cuenta debe tener verificación en dos pasos. Así lo decidió David el 24 de octubre de 2026, para no depender de recordar una frase (D15).
  - Se comprobó que se recupera desde cero con solo esas claves y que el archivo sale idéntico.
- **En la computadora de David lo hace un script fuera del repositorio** (`hostinger-itspp\respaldar-base.ps1`), que abre una tarea programada cada viernes:
  1. Si Hostinger rechaza la conexión, dice qué IP autorizar en MySQL remoto y espera.
  2. Respalda y revisa que el archivo esté completo.
  3. Sube la copia cifrada y la compara con `rclone cryptcheck`.
  4. Recuerda quitar la autorización de la IP.

**Cuándo:**
- antes del piloto y antes del día del cambio, y otra vez al terminar ese día;
- cada semana mientras el sistema esté en uso;
- antes de aplicar una migración (`php scripts/migrar.php`) o de cualquier cambio a mano en la base.

**Restaurar.** `php scripts/restaurar.php ARCHIVO.sql` crea las tablas con `db/esquema.sql` y carga el respaldo. Antes de tocar la base revisa tres cosas, y si alguna falla no hace nada:
- que el archivo esté completo;
- que sea de la misma versión del esquema: si el repositorio tiene migraciones que el respaldo no, se restaura desde un commit con las mismas migraciones y luego se corre `php scripts/migrar.php`;
- que la base esté vacía. Nunca escribe sobre una base con tablas.

Al terminar, compara los renglones cargados con los que dice el archivo.

- **Para probar un respaldo** (conviene hacerlo cada mes), se restaura en una base vacía de la computadora, apuntando a ella con las variables `ASISTENCIAS_DB_*` en lugar de las de Hostinger, y luego se borra esa base, que tiene los datos personales.
- **Para recuperar producción:**
  1. Respaldar primero lo que haya, aunque esté dañado.
  2. En phpMyAdmin, borrar todas las tablas de `u568725086_laboratorio`.
  3. Correr `restaurar.php` con la conexión de Hostinger.
  4. Revisar el sitio (paso 7).

**Lo comprobado el 23 de octubre de 2026:**
- **Primer respaldo de producción:** 47 renglones de 19 tablas, en la computadora de David.
- **Restauración:** se restauró en una base vacía de la computadora, con el mismo resultado: laboratorios, carreras, equipos, el administrador y la bitácora.
- **El aislamiento de Hostinger:** la base usa **READ COMMITTED**, no el REPEATABLE READ que MariaDB trae por omisión. Por eso el respaldo fija su propio nivel, y la integración continua prueba la MariaDB 11.8 con READ COMMITTED.

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
