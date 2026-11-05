# Preguntas para el personal del instituto

Estamos terminando el sistema de control de asistencias a los laboratorios de cómputo del ITSPP. Para algunas cosas necesitamos que el personal que administra y usa los laboratorios nos responda. Con respuestas cortas basta.

En cada pregunta decimos qué vamos a suponer si no hay respuesta. Así solo hace falta corregirnos donde estemos equivocados.

**Estado.** Esta versión sustituye a la que se entregó el 10 de octubre de 2026, que nunca tuvo respuesta. Se conservan las preguntas que siguen abiertas y se agregan las que salieron del sistema actual. Las respuestas se anotan aquí mismo, debajo de cada pregunta, con su fecha.

Última actualización: 4 de noviembre de 2026 (respuestas del jefe de carrera).

**Respuestas del 4 de noviembre de 2026.** Las dio el jefe de carrera de Ingeniería en Sistemas Computacionales mientras leía el cuestionario con el equipo. Se grabó la plática y de la grabación salen estas respuestas; David las considera confiables. Las respuestas por escrito en el Word llegan después y se compararán con estas. Donde dice "parcial", falta lo que se indica.

**Versiones para entregar.** Las preguntas 1 a 15 están también en un cuestionario en Word, con espacio para escribir cada respuesta. Lo acompañan una presentación del sistema en PDF y, para la pregunta 11, un oficio para firma. Se prepararon el 23 de octubre de 2026 y están fuera del repositorio (decisión D17 de [decisiones.md](decisiones.md)). Las preguntas A1 a A4 no van ahí: son para quien maneja la aplicación anterior. Las 16 a 18 y la A4 se agregaron después de entregarlo, el 2 de noviembre de 2026, y van en el cuestionario en línea que el equipo comparte con Andrés.

**A quién le toca cada cosa**, según el directorio del instituto (https://www.puertopenasco.tecnm.mx/directorio/, consultado el 23 de octubre de 2026):

| Para | Puesto |
|---|---|
| La autorización (11) | Jefe de División de la Carrera de Ingeniería en Sistemas Computacionales: Daniel Alonso Osuna Talamantes, a quien va el oficio |
| La lista de alumnos (12) | Jefa del Departamento de Servicios Escolares |
| Los laboratorios y sus computadoras (4, 5, 6) | Jefe del Departamento de Centro de Cómputo |

## Cómo funciona el sistema

1. El docente abre su clase en el sistema al llegar al laboratorio.
2. Cada alumno registra su entrada con su número de control, desde una computadora del laboratorio o desde su teléfono. Además indica qué computadora ocupa, o que trae su propio equipo.
3. Si el alumno no está inscrito en esa materia, puede pedir permiso para entrar, y el administrador lo aprueba o lo rechaza.
4. Al terminar, el docente cierra la clase y el sistema registra la salida de todos.

El laboratorista también puede abrir clases para maestros invitados, a las que entra cualquiera, incluso gente de fuera.

## Las que más nos urgen

**1. ¿Qué carreras tiene el instituto y cuál es el nombre oficial de cada una?**
En el sistema aparecen Ingeniería en Sistemas Computacionales, Ingeniería Industrial, Ingeniería Civil y Licenciatura en Administración. En un lugar aparece "Licenciatura en Administración de Empresas".
*Si no hay respuesta:* usaremos esas cuatro, con esos nombres.

Respuesta (4 de noviembre de 2026, parcial): no dio los nombres oficiales. Explicó que en el número de control un dígito indica la carrera: 1 Industrial, 2 Administración, 3 Sistemas Computacionales y 4 Civil (lo dijo "si mal no recuerdo"). Faltan los nombres oficiales.

**2. ¿En qué fechas empieza y termina cada semestre?**
*Si no hay respuesta:* los llamaremos agosto–diciembre y enero–julio, como aparecen en el sistema, y el administrador capturará sus fechas.

Respuesta:

**3. ¿De dónde sale la lista de alumnos y las materias que lleva cada uno? ¿El sistema escolar puede entregarla en un archivo, por ejemplo Excel, al inicio de cada semestre?**
*Si no hay respuesta:* se podrá cargar desde un archivo Excel o CSV con número de control, nombre, carrera, semestre y grupo, y también capturar a mano.

Respuesta (4 de noviembre de 2026): sale de Mindbox, el sistema escolar del instituto. De ahí el jefe de carrera baja la lista de cada materia en Excel, con matrícula y nombre completo, y prefiere eso a capturar alumno por alumno: pegaría las listas en el formato que le demos. Pide:
- Que el sistema muestre una vista previa antes de guardar, para corregir los nombres que se separen mal (apellidos compuestos como "Del Ángel"). **Hecho:** la carga acepta las listas con el nombre completo desde el 26 de octubre de 2026, y desde el 4 de noviembre los nombres se corrigen en la vista previa.
- Que al pasar de semestre el alumno deje sus materias anteriores y reciba todas las de su nuevo semestre. A los irregulares, más o menos uno de cada 20, los ajusta a mano. En la aplicación anterior había que quitar y poner las materias de cada alumno una por una, y eso no es práctico.
- Que el alumno que llega después de la carga pueda pedir su alta, y que la apruebe el jefe de carrera.

**Hecho el 4 de noviembre de 2026:** en cada curso se sube la lista de su materia tal como sale de Mindbox; inscribe a los que ya existen y da de alta a los demás. Así no hace falta quitar y poner materias alumno por alumno, y los irregulares quedan en las materias que llevan.

Conectar el sistema con Mindbox le parece bien, pero para más adelante. Hay que contactar a quien lo administra en el instituto, y Mindbox cobra por estudiante.

## Sobre los laboratorios

**4. ¿Cuántos laboratorios de cómputo hay, cómo se llaman y cuántas computadoras tiene cada uno?**
En el sistema hay cuatro: Taller de Programación (25 computadoras), Laboratorio de Redes, Laboratorio A y Laboratorio C.

Respuesta (4 de noviembre de 2026, parcial): mencionó los laboratorios de Programación, Redes y C. Falta cuántos hay en total y cuántas computadoras tiene cada uno.

**5. Cuando un alumno usa un laboratorio sin tener clase, ¿alguien debe autorizarlo? ¿Quién? ¿Quieren que ese uso quede registrado?**
Entendemos que hoy el laboratorista solo pregunta si tienen clase y los deja quedarse.
*Si no hay respuesta:* el laboratorista podrá registrar ese uso sin pedir autorización a nadie más.

Respuesta (4 de noviembre de 2026): si no hay maestro, el laboratorista decide y deja entrar a los alumnos. No se bloquean los equipos: basta con registrar el uso. Confirma lo que se suponía. Para más adelante, no para ahora: bloquear y desbloquear los equipos desde el sistema, y entrar con el código QR de la credencial.

**6. ¿Cada laboratorista atiende siempre el mismo laboratorio, o cualquiera?**
*Si no hay respuesta:* cualquier laboratorista podrá atender cualquier laboratorio.

Respuesta (4 de noviembre de 2026): no. Cada laboratorista debe manejar solo los laboratorios que le asigne el administrador, y no debe poder cerrar la clase de un laboratorio que no le toca. Debe iniciar sesión una vez y tener ahí todos sus laboratorios. En la aplicación anterior, quien atiende dos tiene que salir y volver a entrar para cambiar de uno a otro. Los docentes sí pueden usar cualquier laboratorio, por eventos, concursos o exámenes. **Hecho el 4 de noviembre de 2026** (D21): el administrador asigna en Cuentas los laboratorios de cada laboratorista.

## Sobre quién hace qué

**7. ¿Qué debe poder hacer cada tipo de usuario?**
El reparto que usamos mientras tanto está en [api.md](api.md#rutas). Es el más restrictivo que deja trabajar:
- Todos consultan.
- El laboratorista además da de alta alumnos y atiende los equipos.
- El docente abre y cierra sus clases y lleva sus prácticas.
- Solo el administrador maneja cuentas, materias y cursos, y borra.

Respuesta (4 de noviembre de 2026, parcial):
- Los jefes de división, el director académico o una cuenta maestra tienen acceso a todo.
- El laboratorista, solo a sus laboratorios (pregunta 6).
- El docente, a cualquier laboratorio.
- El docente de la materia también debería poder aprobar las solicitudes de entrada (pregunta 10).

Falta confirmar el resto del reparto.

**8. ¿Las prácticas de una materia son las mismas para todos los docentes que la imparten, o cada docente define las suyas?**
El sistema permite las dos cosas: prácticas comunes de la materia y prácticas propias de cada docente.
*Si no hay respuesta:* cada docente seguirá definiendo las suyas, como hasta ahora.

Respuesta:

## Sobre los datos

**9. ¿El número de control siempre tiene 8 dígitos, como 23303080?**
*Si no hay respuesta:* se aceptará cualquier número de control, sin revisar su formato.

Respuesta (4 de noviembre de 2026): no. Hay números de control con letras que pueden tener más de 8 caracteres. Los que empiezan con B son de alumnos que vienen de otro tecnológico por convalidación y conservan su número. Los que empiezan con C son de cambios de carrera. No hay que limitarlo a 8 dígitos ni a solo números. Un alumno llegó a tener dos números de control. Los dos primeros dígitos son el año de ingreso, pero no dicen en qué semestre va el alumno. El sistema ya acepta letras, números y guion, hasta 20 caracteres.

## Sobre el registro

**10. ¿El sistema debe revisar que el alumno esté inscrito en la materia de la clase?**
Hoy el alumno solo escribe su número de control. El sistema lo deja entrar si está en la lista del grupo, que el personal carga al inicio del semestre. Si no está, puede pedir permiso y el administrador lo aprueba o lo rechaza. El sistema anterior hacía lo mismo.
Si no se revisa, cualquier alumno con número de control puede entrar a cualquier clase. Se deja de saber quién faltó: solo quién vino.
*Si no hay respuesta:* se seguirá revisando la inscripción.

Respuesta (4 de noviembre de 2026): sí. El sistema está en internet, y cualquiera podría registrarse desde su casa en una clase que no es suya. Las solicitudes le parecen bien. Propone que también las pueda aprobar el docente de la materia, siempre que el alumno ya esté dado de alta en el sistema. **Hecho el 4 de noviembre de 2026:** el docente del curso deja entrar por esa vez o rechaza; inscribir al alumno en el curso sigue siendo del administrador.

## Para probar con datos reales

El sistema nuevo ya está publicado en https://laboratorios.soycorrecaminos.xyz. Hoy se usa en el instituto la versión anterior (https://asistencialaboratorios.vercel.app), que guarda todo en Firebase. Para probar el nuevo en una clase real y luego reemplazar al anterior hace falta lo siguiente. Se pidió a Andrés el 22 de octubre de 2026. Él lo lleva al instituto, y lo de la aplicación anterior a quien la maneja: **Andrés no controla el proyecto de Vercel ni el de Firebase** (23 de octubre de 2026).

**De quien maneja la aplicación anterior** (Andrés lo pide):

**A1. ¿Quién usa hoy la aplicación de Vercel, en qué laboratorios y desde cuándo?** ¿Quién controla ese proyecto de Vercel y el de Firebase? Hace falta su nombre: con esa persona se coordina el día del cambio.

Respuesta:

**A2. Acceso de solo lectura al proyecto de Firebase (`integrador-7b39d`)**, como miembro con rol de lector, o una exportación desde su consola. Es para revisar los datos y simular la importación sin guardar nada. Lo da quien controla el proyecto: en la consola de Firebase, Configuración del proyecto → Usuarios y permisos → Agregar miembro, con el correo de Google de David y el rol de lector.

Respuesta:

**A3. La fecha del cambio:** el día en que se deja la aplicación anterior y se empieza a usar la nueva. Ese día se importan los datos de Firebase. Antes no conviene usar las dos en las clases, porque los alumnos tendrían que registrarse dos veces.

Respuesta (4 de noviembre de 2026, parcial, del jefe de carrera): parece que quiere cambiar el próximo semestre, para que los maestros empiecen a usar el sistema nuevo. Esa parte de la grabación no se entiende bien. Falta la fecha.

**A4. En la aplicación anterior, ¿hay personas con cuenta de docente y también de administrador o de laboratorista? ¿Usan la misma contraseña en las dos?** En el sistema nuevo cada persona tiene una sola cuenta con todos sus roles (D20 de [decisiones.md](decisiones.md)). Si no hay respuesta, la importación las une con la contraseña de la primera que encuentra (administrador, luego docente, luego laboratorista) y avisa de cada una; a quien usara otra contraseña, el administrador se la restablece.

Respuesta:

**Del instituto:**

**11. Autorización por escrito** para usar los datos personales de los alumnos en el sistema nuevo y pasar a él los que ya están en Firebase. Se pide con el oficio dirigido al jefe de división de Sistemas Computacionales; si le corresponde a otra área (Centro de Cómputo o Subdirección Académica), el oficio le pide canalizarlo. ¿El aviso de privacidad del instituto ya cubre este uso, o hay que agregarlo?

Respuesta (4 de noviembre de 2026, parcial): "No hay como tal nada que tenga que ver con los datos: hay una responsabilidad, pero no hay una firma de documento." No contestó lo del aviso de privacidad. David decidió tomar esta respuesta de palabra el mismo día, así que no se pide un documento firmado (D12 de [decisiones.md](decisiones.md)).

**12. La lista de alumnos del semestre en Excel**, de servicios escolares, con estas columnas en el primer renglón: **Número de control, Nombre, Apellidos, Carrera, Semestre, Turno, Grupo**. Una fila por alumno. Así se carga tal cual en el sistema; hay una plantilla en [plantilla-alumnos.xlsx](plantilla-alumnos.xlsx).

Respuesta (4 de noviembre de 2026, parcial): el Excel que se baja de Mindbox trae matrícula y nombre completo en una sola columna, sin carrera, semestre ni grupo. El jefe de carrera pasaría los datos al formato de la plantilla, pero pide que el sistema acepte el nombre completo y muestre una vista previa (pregunta 3). Falta la lista.

**13. Las materias que usan los laboratorios este semestre:** materia, grupo, docente, laboratorio, días y horario. Y la lista de alumnos de cada grupo, o la confirmación de que todos los alumnos de un grupo llevan las mismas materias.

Respuesta (4 de noviembre de 2026, parcial): en Mindbox se ven las materias de cada semestre y el docente de cada una, así que de ahí pueden salir. Todavía no las entrega. Los alumnos de un semestre llevan las materias de ese semestre, salvo los irregulares (pregunta 3).

**14. El personal que va a usar el sistema:** de cada docente y laboratorista, nombre, correo institucional y número de empleado.

Respuesta:

**15. Un piloto:** un laboratorio, un docente y un grupo con los que se pueda probar en una clase real, y en qué fecha.

Respuesta (4 de noviembre de 2026, parcial): está dispuesto a probarlo en una clase. Antes quiere ver funcionando la entrada de cada rol: docente, laboratorista y administrador. Faltan el laboratorio, el docente, el grupo y la fecha.

Para el piloto basta con las preguntas 1, 2, 4 y 11 a 15, y solo de ese laboratorio, ese docente y ese grupo. El resto se carga después.

## Sobre las cuentas del personal

Se agregaron el 2 de noviembre de 2026. Mientras tanto, el sistema permite que una cuenta tenga varios roles (D20 de [decisiones.md](decisiones.md)).

**16. ¿Una misma persona puede tener más de un rol?** Por ejemplo, un laboratorista o el jefe del Centro de Cómputo que también da clases, o un docente que también administra el sistema. Si es así, ¿quiénes, más o menos?
Si no hay respuesta, supondremos que sí puede pasar: el sistema ya lo permite con una sola cuenta por persona. Si nadie tiene dos roles, no cambia nada.

Respuesta (4 de noviembre de 2026, parcial): no dijo quiénes. Explicó que cada función tiene su propio correo:
- Todos tienen un correo personal. Él usaba el suyo como docente.
- Los jefes de área tienen además un correo del puesto, aparte del personal.
- Cuando cambian a la persona, el correo del puesto pasa con su información a la siguiente.

Pidió tomarlo en cuenta. El sistema ya permite que una persona tenga dos cuentas, porque cada cuenta lleva un correo distinto. Se revisó el mismo día y se queda D20: una cuenta por persona. Lo que pasa al siguiente jefe es el rol de administrador, no la cuenta, porque la información es del sistema y no de la cuenta. Ver D20 en [decisiones.md](decisiones.md).

**17. ¿Cada docente y cada laboratorista tiene un número de empleado propio, que no cambia?** ¿Los laboratoristas también tienen uno?
Si no hay respuesta, supondremos que sí: se usa, junto con el correo, para iniciar sesión, y no puede repetirse entre dos cuentas. Quien no tenga entra con su correo.

Respuesta (4 de noviembre de 2026, parcial): no dijo si todos tienen número de empleado. Le parece raro iniciar sesión con el número de empleado y prefiere el correo, por lo menos para el administrador. Propone preguntar a los usuarios qué prefieren: usuario, correo o número de empleado. Si se usa el número de empleado, el formulario debe dejar que el navegador guarde la contraseña. La pantalla de inicio de sesión del sistema nuevo ya lo permite.

**18. Cuando alguien deja de dar clases o de atender el laboratorio, ¿quién avisa para quitarle ese rol o desactivar su cuenta?**
Si no hay respuesta, supondremos que el jefe de división avisa al administrador del sistema. Una cuenta con historial no se borra: se desactiva, y la bitácora conserva lo que hizo.

Respuesta (4 de noviembre de 2026, parcial): no la contestó directamente. Cuando cambia el jefe de un área, su correo del puesto pasa al siguiente (pregunta 16). En el sistema, el administrador le quita el rol a quien se va y se lo da a quien llega (D20). Falta saber quién avisa.

## Otros comentarios del 4 de noviembre de 2026

Del jefe de carrera, en la misma plática:
- **Diseño:** le pareció "muy minimalista, demasiado básico". No tiene queja de los colores, pero pidió algunos elementos más grandes: se refería a los logos del instituto (lo aclaró David). **Hecho el 4 de noviembre de 2026.**
- **Reportes:** se necesitan por laboratorio: quién entró, cuándo, de qué materia y cuántos alumnos.
- **Para más adelante:** bloquear los equipos, entrar con el QR de la credencial, conectarse con Mindbox y usar inteligencia artificial. Ahora lo que se necesita es que el sistema funcione. El proyecto puede seguir hasta noveno semestre o servir como proyecto de titulación.

## Ya respondidas

| Pregunta | Respuesta | Fecha |
|---|---|---|
| ¿Cómo se identifica el alumno al entrar? | Con su número de control. Él mismo registra su entrada, desde cualquier computadora o teléfono, cuando el docente abre la clase | 10 de octubre de 2026 |
| ¿Quién registra la salida? | El docente, al cerrar la clase. El alumno solo registra su entrada | 10 de octubre de 2026 |
| ¿Qué turnos existen? | Matutino y vespertino | 10 de octubre de 2026 |
| ¿El alumno debe indicar qué computadora ocupa? | Sí: la computadora del laboratorio, o que trae su propio equipo. Una de las dos es obligatoria | 10 de octubre de 2026 |
| ¿Dónde se instala el sistema? | En Hostinger, en internet. No lo respondió el instituto: es donde ya está publicado el sistema actual | 21 de octubre de 2026 |
