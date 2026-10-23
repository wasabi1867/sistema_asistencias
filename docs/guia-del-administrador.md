# Guía del administrador: dejar el sistema listo para una clase

Los pasos para que un docente pueda abrir su clase y los alumnos registren su entrada, en el orden en que se hacen. Todo se hace en el sitio, sin instalar nada: https://laboratorios.soycorrecaminos.xyz

Los datos de los alumnos son personales. Antes de cargar los reales hace falta la autorización del instituto (pregunta 11 de [preguntas-para-el-instituto.md](preguntas-para-el-instituto.md)).

## 0. Entrar

1. Abre el sitio y presiona **Personal**, arriba a la derecha.
2. Elige **Administrador**, escribe tu correo y tu contraseña, y presiona **Iniciar sesión**.
3. La primera vez, cambia la contraseña que te dieron: menú con tu nombre (arriba a la derecha) → **Cambiar contraseña**.

A la izquierda está el menú del panel. Las secciones que se usan aquí son **Alumnos**, **Cursos**, **Materias y prácticas**, **Cuentas del personal** y **Carreras, periodos y laboratorios**.

## 1. El periodo

En **Carreras, periodos y laboratorios** → **Periodos escolares** → **Nuevo periodo**:
- **Nombre del periodo:** `AGO-DIC 2026`
- **Inicio:** 1 de agosto de 2026. **Fin:** 31 de diciembre de 2026.

Usa esas fechas exactas, aunque las clases empiecen después. Si un día se importan los datos de la aplicación anterior, esa importación busca un periodo que abarque del 1 de agosto al 31 de diciembre.

En la misma sección revisa:
- **Carreras:** vienen ISIC, IIND, ICIV y LADM. Si falta alguna o una clave no es la oficial, agrégala o corrígela aquí **antes** de cargar alumnos.
- **Laboratorios:** vienen Taller de Programación (con 25 computadoras), Laboratorio de Redes, Laboratorio A y Laboratorio C. Las computadoras de los demás se configuran en el **Panel del laboratorio** (enlace al pie del menú de la izquierda) → **Equipos**, eligiendo cuántas tiene: quedan como PC-1, PC-2, etc.

## 2. Las cuentas del personal

En **Cuentas del personal**, elige la pestaña del rol (**Docentes**, **Laboratoristas** o **Administradores**) y presiona **Nueva cuenta de docente** (o del rol que sea):
- Nombre y apellido.
- **Correo** y/o **Matrícula o número de empleado:** con cualquiera de los dos se inicia sesión. Hace falta al menos uno.
- Departamento, en los docentes.
- **Contraseña inicial**, de al menos 8 caracteres.

Una cuenta por persona: la bitácora registra quién hizo cada cosa. La contraseña inicial díla en persona, no por un chat. Cada quien la cambia al entrar.

## 3. Los alumnos, desde Excel

1. Descarga la plantilla [plantilla-alumnos.xlsx](plantilla-alumnos.xlsx). La hoja **Alumnos** tiene los encabezados y la hoja **Instrucciones** explica cada columna.
2. Llena un alumno por renglón:

   | Columna | Obligatoria | Qué va |
   |---|---|---|
   | Número de control | Sí | Por ejemplo `23303080` |
   | Nombre | Sí | |
   | Apellidos | Sí | Los dos en la misma celda |
   | Carrera | No | La **clave** (`ISIC`) o el **nombre exacto** de una carrera del catálogo |
   | Semestre | No | Un número del 1 al 15 |
   | Turno | No | `matutino` o `vespertino` |
   | Grupo | No | Por ejemplo `5A` |

   Si ya tienes un Excel del sistema escolar, no hace falta copiarlo a la plantilla: basta con que el primer renglón tenga esos encabezados. También se reconocen "Matrícula", "Nombres", "Apellido" y "Programa".
3. En **Alumnos** → **Cargar desde Excel**, elige el archivo. El sistema muestra cuántos alumnos encontró y algunos de ejemplo; confirma.

Si un renglón tiene un error (por ejemplo, una carrera que no está en el catálogo), **no se guarda ninguno** y el sistema dice qué renglones fallaron. Corrige el archivo y vuelve a subirlo. Subirlo otra vez no duplica a nadie: los que ya existen se actualizan.

## 4. Las materias

En **Materias y prácticas** → **Nueva materia**: nombre, clave oficial (opcional), carrera y semestre. Una materia se captura una sola vez y sirve para todos los grupos y periodos.

Escribe el nombre igual que en la aplicación anterior. Si un día se importan sus datos, las materias se buscan por nombre exacto, y una escrita distinto quedaría repetida.

Ahí mismo, cada materia puede tener **prácticas comunes**, que ven todos sus docentes. Cada docente también puede agregar las suyas desde su panel.

## 5. Los cursos y sus alumnos

Un curso es una materia que un docente da a un grupo en un periodo. En **Cursos** → **Nuevo curso**:
- **Materia**, **Periodo** (AGO-DIC 2026) y **Grupo** (por ejemplo `5A`).
- **Docente.**
- **Laboratorio** y **Turno**, si siempre es el mismo.

Luego haz clic en el curso y, en **Inscribir: pega los números de control**, pega los del grupo: uno por renglón, o separados por comas o espacios. Puedes copiarlos directo de la columna del Excel. El sistema dice qué pasó con cada uno, por ejemplo si un número no existe porque falta cargar a ese alumno.

Solo los alumnos inscritos en el curso pueden registrar su entrada a sus clases. Los demás pueden pedir permiso desde la pantalla de registro, y las solicitudes te llegan al **Tablero**.

## 6. Probar una clase

1. **En la computadora del laboratorio** (o en cualquier navegador), abre el sitio y elige el laboratorio. Se hace una sola vez: la computadora lo recuerda.
2. **El docente** entra por **Personal** → **Docente**, elige el curso y la práctica, y presiona **Abrir la clase**. Ve en vivo quién entra y quién falta.
3. **Un alumno inscrito**, desde esa computadora o desde su teléfono, escribe su número de control y elige su computadora, o **Traigo mi propio equipo**.
4. **El docente** cierra la clase: se registra la salida de todos. Puede exportar la hoja de registro en PDF o Excel.

Si algo no sale como se espera, anota qué pasó y a qué hora: la bitácora del sistema guarda quién hizo qué y cuándo.
