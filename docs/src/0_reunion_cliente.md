Se requiere:

Las plantillas y documentos los crea un usuario para un "Tipo de estudio" , "Estudio", "Grupo", "Modulo", privado (usuario) por tanto su visibilidad es solo para el usuario que lo creó, y una vez publicos para quien se comparte.

El editor debera tener primero una seccion donde se establecen los bloques de contenido, y luego una seccion donde se edita el bloque seleccionado.

Cuando alguien esta editando un bloque se queda bloquieado para el resto de usuarios, y se muestra un mensaje de "bloqueado por usuario X" para el resto de usuarios, y el usuario que lo esta editando ve un mensaje de "editando bloque X".

Cada bloque podra contener una estructura json generada por blocknotes, y se guardara en la base de datos como un bloque de contenido, con su estructura json.

cada bloque de una plantilla podra ser, editable, modificable, o bloqueado. Editable: el bloque se puede editar, modificar y eliminar. Modificable: el bloque se puede modificar pero avisara al revisor con un menaje del texto inicial para que el revisor pueda ver que dicho texto ha sido modificado. Bloqueado: el bloque no se puede modificar.

hay que distinguir entre bloque editable y bloque modificable, el bloque editable es aquel que se puede modificar sin restricciones, mientras que el bloque modificable es aquel que se puede modificar pero se debe avisar al revisor con un mensaje del texto inicial para que el revisor pueda ver que dicho texto ha sido modificado.

