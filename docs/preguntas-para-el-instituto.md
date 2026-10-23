# Preguntas para el personal del instituto

Estamos terminando el sistema de control de asistencias a los laboratorios de cómputo del ITSPP. Para algunas cosas necesitamos que el personal que administra y usa los laboratorios nos responda. Con respuestas cortas basta.

En cada pregunta decimos qué vamos a suponer si no hay respuesta. Así solo hace falta corregirnos donde estemos equivocados.

**Estado.** Esta versión sustituye a la que se entregó el 10 de octubre de 2026, que nunca tuvo respuesta. Se conservan las preguntas que siguen abiertas y se agregan las que salieron del sistema actual. Las respuestas se anotan aquí mismo, debajo de cada pregunta, con su fecha.

Última actualización: 23 de octubre de 2026 (Andrés no controla la aplicación anterior; A1 y A2 se piden a quien la maneja).

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

Respuesta:

**2. ¿En qué fechas empieza y termina cada semestre?**
*Si no hay respuesta:* los llamaremos agosto–diciembre y enero–julio, como aparecen en el sistema, y el administrador capturará sus fechas.

Respuesta:

**3. ¿De dónde sale la lista de alumnos y las materias que lleva cada uno? ¿El sistema escolar puede entregarla en un archivo, por ejemplo Excel, al inicio de cada semestre?**
*Si no hay respuesta:* se podrá cargar desde un archivo Excel o CSV con número de control, nombre, carrera, semestre y grupo, y también capturar a mano.

Respuesta:

## Sobre los laboratorios

**4. ¿Cuántos laboratorios de cómputo hay, cómo se llaman y cuántas computadoras tiene cada uno?**
En el sistema hay cuatro: Taller de Programación (25 computadoras), Laboratorio de Redes, Laboratorio A y Laboratorio C.

Respuesta:

**5. Cuando un alumno usa un laboratorio sin tener clase, ¿alguien debe autorizarlo? ¿Quién? ¿Quieren que ese uso quede registrado?**
Entendemos que hoy el laboratorista solo pregunta si tienen clase y los deja quedarse.
*Si no hay respuesta:* el laboratorista podrá registrar ese uso sin pedir autorización a nadie más.

Respuesta:

**6. ¿Cada laboratorista atiende siempre el mismo laboratorio, o cualquiera?**
*Si no hay respuesta:* cualquier laboratorista podrá atender cualquier laboratorio.

Respuesta:

## Sobre quién hace qué

**7. ¿Qué debe poder hacer cada tipo de usuario?**
El reparto que usamos mientras tanto está en [api.md](api.md#rutas). Es el más restrictivo que deja trabajar:
- Todos consultan.
- El laboratorista además da de alta alumnos y atiende los equipos.
- El docente abre y cierra sus clases y lleva sus prácticas.
- Solo el administrador maneja cuentas, materias y cursos, y borra.

Respuesta:

**8. ¿Las prácticas de una materia son las mismas para todos los docentes que la imparten, o cada docente define las suyas?**
El sistema permite las dos cosas: prácticas comunes de la materia y prácticas propias de cada docente.
*Si no hay respuesta:* cada docente seguirá definiendo las suyas, como hasta ahora.

Respuesta:

## Sobre los datos

**9. ¿El número de control siempre tiene 8 dígitos, como 23303080?**
*Si no hay respuesta:* se aceptará cualquier número de control, sin revisar su formato.

Respuesta:

## Sobre el registro

**10. ¿El sistema debe revisar que el alumno esté inscrito en la materia de la clase?**
Hoy el alumno solo escribe su número de control. El sistema lo deja entrar si está en la lista del grupo, que el personal carga al inicio del semestre. Si no está, puede pedir permiso y el administrador lo aprueba o lo rechaza. El sistema anterior hacía lo mismo.
Si no se revisa, cualquier alumno con número de control puede entrar a cualquier clase. Se deja de saber quién faltó: solo quién vino.
*Si no hay respuesta:* se seguirá revisando la inscripción.

Respuesta:

## Para probar con datos reales

El sistema nuevo ya está publicado en https://laboratorios.soycorrecaminos.xyz. Hoy se usa en el instituto la versión anterior (https://asistencialaboratorios.vercel.app), que guarda todo en Firebase. Para probar el nuevo en una clase real y luego reemplazar al anterior hace falta lo siguiente. Se pidió a Andrés el 22 de octubre de 2026. Él lo lleva al instituto, y lo de la aplicación anterior a quien la maneja: **Andrés no controla el proyecto de Vercel ni el de Firebase** (23 de octubre de 2026).

**De quien maneja la aplicación anterior** (Andrés lo pide):

**A1. ¿Quién usa hoy la aplicación de Vercel, en qué laboratorios y desde cuándo?** ¿Quién controla ese proyecto de Vercel y el de Firebase? Hace falta su nombre: con esa persona se coordina el día del cambio.

Respuesta:

**A2. Acceso de solo lectura al proyecto de Firebase (`integrador-7b39d`)**, como miembro con rol de lector, o una exportación desde su consola. Es para revisar los datos y simular la importación sin guardar nada. Lo da quien controla el proyecto: en la consola de Firebase, Configuración del proyecto → Usuarios y permisos → Agregar miembro, con el correo de Google de David y el rol de lector.

Respuesta:

**A3. La fecha del cambio:** el día en que se deja la aplicación anterior y se empieza a usar la nueva. Ese día se importan los datos de Firebase. Antes no conviene usar las dos en las clases, porque los alumnos tendrían que registrarse dos veces.

Respuesta:

**Del instituto:**

**11. Autorización por escrito** de quien sea responsable (jefatura del centro de cómputo o subdirección) para usar los datos personales de los alumnos en el sistema nuevo y pasar a él los que ya están en Firebase. ¿El aviso de privacidad del instituto ya cubre este uso, o hay que agregarlo?

Respuesta:

**12. La lista de alumnos del semestre en Excel**, de servicios escolares, con estas columnas en el primer renglón: **Número de control, Nombre, Apellidos, Carrera, Semestre, Turno, Grupo**. Una fila por alumno. Así se carga tal cual en el sistema; hay una plantilla en [plantilla-alumnos.xlsx](plantilla-alumnos.xlsx).

Respuesta:

**13. Las materias que usan los laboratorios este semestre:** materia, grupo, docente, laboratorio, días y horario. Y la lista de alumnos de cada grupo, o la confirmación de que todos los alumnos de un grupo llevan las mismas materias.

Respuesta:

**14. El personal que va a usar el sistema:** de cada docente y laboratorista, nombre, correo institucional y número de empleado.

Respuesta:

**15. Un piloto:** un laboratorio, un docente y un grupo con los que se pueda probar en una clase real, y en qué fecha.

Respuesta:

Para el piloto basta con las preguntas 1, 2, 4 y 11 a 15, y solo de ese laboratorio, ese docente y ese grupo. El resto se carga después.

## Ya respondidas

| Pregunta | Respuesta | Fecha |
|---|---|---|
| ¿Cómo se identifica el alumno al entrar? | Con su número de control. Él mismo registra su entrada, desde cualquier computadora o teléfono, cuando el docente abre la clase | 10 de octubre de 2026 |
| ¿Quién registra la salida? | El docente, al cerrar la clase. El alumno solo registra su entrada | 10 de octubre de 2026 |
| ¿Qué turnos existen? | Matutino y vespertino | 10 de octubre de 2026 |
| ¿El alumno debe indicar qué computadora ocupa? | Sí: la computadora del laboratorio, o que trae su propio equipo. Una de las dos es obligatoria | 10 de octubre de 2026 |
| ¿Dónde se instala el sistema? | En Hostinger, en internet. No lo respondió el instituto: es donde ya está publicado el sistema actual | 21 de octubre de 2026 |
