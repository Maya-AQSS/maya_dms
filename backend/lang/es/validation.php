<?php

return [
    'accepted' => 'El campo :attribute debe ser aceptado.',
    'active_url' => 'El campo :attribute debe ser una URL válida.',
    'after' => 'El campo :attribute debe ser una fecha posterior a :date.',
    'after_or_equal' => 'El campo :attribute debe ser una fecha posterior o igual a :date.',
    'alpha' => 'El campo :attribute solo debe contener letras.',
    'alpha_dash' => 'El campo :attribute solo debe contener letras, números, guiones y guiones bajos.',
    'alpha_num' => 'El campo :attribute solo debe contener letras y números.',
    'array' => 'El campo :attribute debe ser un conjunto.',
    'before' => 'El campo :attribute debe ser una fecha anterior a :date.',
    'before_or_equal' => 'El campo :attribute debe ser una fecha anterior o igual a :date.',
    'between' => [
        'array' => 'El campo :attribute debe tener entre :min y :max elementos.',
        'file' => 'El campo :attribute debe pesar entre :min y :max kilobytes.',
        'numeric' => 'El campo :attribute debe estar entre :min y :max.',
        'string' => 'El campo :attribute debe tener entre :min y :max caracteres.',
    ],
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'confirmed' => 'La confirmación del campo :attribute no coincide.',
    'date' => 'El campo :attribute debe ser una fecha válida.',
    'date_equals' => 'El campo :attribute debe ser una fecha igual a :date.',
    'date_format' => 'El campo :attribute debe coincidir con el formato :format.',
    'different' => 'Los campos :attribute y :other deben ser diferentes.',
    'digits' => 'El campo :attribute debe tener :digits dígitos.',
    'digits_between' => 'El campo :attribute debe tener entre :min y :max dígitos.',
    'email' => 'El campo :attribute debe ser una dirección de correo válida.',
    'exists' => 'El campo :attribute seleccionado no es válido.',
    'file' => 'El campo :attribute debe ser un archivo.',
    'filled' => 'El campo :attribute debe tener un valor.',
    'gt' => [
        'array' => 'El campo :attribute debe tener más de :value elementos.',
        'file' => 'El campo :attribute debe pesar más de :value kilobytes.',
        'numeric' => 'El campo :attribute debe ser mayor que :value.',
        'string' => 'El campo :attribute debe tener más de :value caracteres.',
    ],
    'gte' => [
        'array' => 'El campo :attribute debe tener :value elementos o más.',
        'file' => 'El campo :attribute debe pesar :value kilobytes o más.',
        'numeric' => 'El campo :attribute debe ser mayor o igual que :value.',
        'string' => 'El campo :attribute debe tener :value caracteres o más.',
    ],
    'image' => 'El campo :attribute debe ser una imagen.',
    'in' => 'El campo :attribute seleccionado no es válido.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'ip' => 'El campo :attribute debe ser una dirección IP válida.',
    'json' => 'El campo :attribute debe ser una cadena JSON válida.',
    'lt' => [
        'array' => 'El campo :attribute debe tener menos de :value elementos.',
        'file' => 'El campo :attribute debe pesar menos de :value kilobytes.',
        'numeric' => 'El campo :attribute debe ser menor que :value.',
        'string' => 'El campo :attribute debe tener menos de :value caracteres.',
    ],
    'lte' => [
        'array' => 'El campo :attribute no debe tener más de :value elementos.',
        'file' => 'El campo :attribute debe pesar :value kilobytes o menos.',
        'numeric' => 'El campo :attribute debe ser menor o igual que :value.',
        'string' => 'El campo :attribute debe tener :value caracteres o menos.',
    ],
    'max' => [
        'array' => 'El campo :attribute no debe tener más de :max elementos.',
        'file' => 'El campo :attribute no debe pesar más de :max kilobytes.',
        'numeric' => 'El campo :attribute no debe ser mayor que :max.',
        'string' => 'El campo :attribute no debe tener más de :max caracteres.',
    ],
    'mimes' => 'El campo :attribute debe ser un archivo de tipo: :values.',
    'mimetypes' => 'El campo :attribute debe ser un archivo de tipo: :values.',
    'min' => [
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
        'file' => 'El campo :attribute debe pesar al menos :min kilobytes.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'not_in' => 'El campo :attribute seleccionado no es válido.',
    'numeric' => 'El campo :attribute debe ser un número.',
    'present' => 'El campo :attribute debe estar presente.',
    'regex' => 'El formato del campo :attribute no es válido.',
    'required' => 'El campo :attribute es obligatorio.',
    'required_if' => 'El campo :attribute es obligatorio cuando :other es :value.',
    'required_unless' => 'El campo :attribute es obligatorio a menos que :other esté en :values.',
    'required_with' => 'El campo :attribute es obligatorio cuando :values está presente.',
    'required_without' => 'El campo :attribute es obligatorio cuando :values no está presente.',
    'same' => 'Los campos :attribute y :other deben coincidir.',
    'size' => [
        'array' => 'El campo :attribute debe contener :size elementos.',
        'file' => 'El campo :attribute debe pesar :size kilobytes.',
        'numeric' => 'El campo :attribute debe ser :size.',
        'string' => 'El campo :attribute debe tener :size caracteres.',
    ],
    'string' => 'El campo :attribute debe ser una cadena de texto.',
    'unique' => 'El campo :attribute ya ha sido tomado.',
    'url' => 'El campo :attribute debe ser una URL válida.',
    'uuid' => 'El campo :attribute debe ser un UUID válido.',

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mensajes de validación de dominio (maya_dms)
    |--------------------------------------------------------------------------
    */

    'changelog' => [
        'required_submit' => 'El changelog es obligatorio al enviar a validación.',
        'required_publish_document' => 'El changelog es obligatorio al publicar un documento.',
        'required_publish_template' => 'El changelog es obligatorio al publicar una plantilla.',
        'max' => 'El changelog no puede superar :max caracteres.',
    ],

    'rejection_reason' => [
        'required' => 'Debes indicar un motivo para el rechazo o dejar un comentario en algún bloque del documento.',
        'min' => 'El motivo del rechazo debe tener al menos :min caracteres.',
        'max' => 'El motivo del rechazo no puede superar :max caracteres.',
    ],

    'block_ids' => [
        'required' => 'Se requiere al menos un ID de bloque.',
        'uuid' => 'Cada ID de bloque debe ser un UUID válido.',
        'not_found' => 'Uno o más bloques no existen.',
        'reorder_required' => 'Debes enviar al menos un bloque para reordenar.',
        'duplicate' => 'La lista de bloques no puede contener IDs duplicados.',
        'all_required' => 'Debes enviar todos los bloques de la plantilla.',
        'mismatch' => 'La lista enviada no coincide con los bloques reales de la plantilla.',
    ],

    'block_state' => [
        'required' => 'Debes enviar block_state para actualizar.',
        'in' => 'El estado del bloque debe ser uno de: :values. Valor recibido: :input.',
    ],

    'blocks' => [
        'complete_editable' => 'Debes completar todos los bloques editables antes de enviar a revisión.',
        'edit_modifiable' => 'Debes editar todos los bloques modificables antes de enviar a revisión.',
    ],

    'document' => [
        'edit_state' => 'Solo se pueden editar metadatos de documentos en borrador o rechazados.',
        'delete_published' => 'No se puede eliminar un documento publicado sin versión de trabajo activa.',
        'new_version_state' => 'Solo un documento publicado puede pasar a borrador para una nueva versión.',
        'migrate_state' => 'El documento debe estar en borrador (nueva versión) para migrar de plantilla.',
        'title_required' => 'El título del documento es obligatorio.',
        'deadline_required' => 'La fecha de entrega del documento es obligatoria.',
        'submit_state' => 'Solo los documentos en borrador o rechazados pueden enviarse a revisión.',
        'publish_state' => 'Solo se puede publicar un documento en borrador o en revisión.',
        'reviews_pending' => 'El documento tiene validadores asignados. Debe completar la revisión para publicarse.',
        'new_owner_distinct' => 'El nuevo titular debe ser distinto del actual.',
    ],

    'template' => [
        'no_published' => 'La plantilla no tiene versiones publicadas; no se puede crear un documento.',
        'no_blocks' => 'La versión de plantilla no contiene bloques.',
        'select_required' => 'Debe seleccionar una plantilla cuando existen varias opciones.',
        'delete_published' => 'No se puede eliminar una plantilla publicada sin versión de trabajo activa.',
        'new_version_state' => 'Solo una plantilla publicada puede pasar a borrador para una nueva versión.',
        'name_required' => 'El nombre de la plantilla es obligatorio.',
        'deadline_required' => 'La fecha de entrega de la plantilla es obligatoria.',
        'visibility_required' => 'La visibilidad de la plantilla es obligatoria.',
    ],

    'template_version' => [
        'invalid' => 'La versión publicada no existe o no pertenece a esta plantilla.',
        'unavailable' => 'La versión seleccionada no está disponible para el módulo.',
    ],

    'module' => [
        'no_templates' => 'El módulo no tiene plantillas publicadas disponibles.',
        'not_found' => 'El módulo no existe.',
    ],

    'process' => [
        'mismatch' => 'El proceso no corresponde a la plantilla seleccionada para el módulo.',
    ],

    'migrate' => [
        'target_invalid' => 'La versión de plantilla destino no existe o no es publicada.',
        'target_older' => 'La versión de plantilla destino debe ser más reciente que la actual.',
        'target_no_blocks' => 'La versión de plantilla destino no contiene bloques.',
        'source_not_anchored' => 'El documento origen no está anclado a una versión publicada de plantilla.',
        'no_newer_version' => 'No existe una versión de plantilla más reciente que la del documento origen.',
    ],

    'review' => [
        'only_in_review' => 'Las revisiones solo aplican a documentos en revisión.',
        'already_processed' => 'Esta revisión ya fue procesada.',
        'sequential_order' => 'En revisión secuencial, solo puede actuar la etapa pendiente más baja.',
    ],

    'comment' => [
        'resource_not_allowed' => 'Tipo de recurso no permitido para comentarios.',
        'block_type_id' => 'El bloque debe incluir tipo e identificador juntos.',
        'block_template' => 'El bloque debe ser de tipo plantilla.',
        'block_not_template' => 'El bloque no pertenece a la plantilla indicada.',
        'block_document' => 'El bloque debe ser de tipo documento.',
        'block_not_document' => 'El bloque no pertenece al documento indicado.',
        'parent_not_found' => 'El comentario padre no existe.',
        'parent_unavailable' => 'El comentario padre no está disponible.',
        'parent_same_resource' => 'El comentario padre debe pertenecer al mismo recurso y versión.',
        'parent_same_block' => 'El comentario padre debe pertenecer al mismo bloque.',
    ],

    'template_context' => [
        'team_no_team' => 'La plantilla de equipo no tiene un equipo válido asociado.',
        'personal_no_context_change' => 'Las plantillas personales no permiten cambiar el contexto académico al crear documentos.',
        'module_no_team' => 'Las plantillas de módulo no permiten asignar equipo al documento.',
        'module_no_module' => 'La plantilla de módulo no tiene un módulo válido asociado.',
        'module_same_module' => 'El documento debe crearse en el mismo módulo de la plantilla.',
        'study_no_team' => 'Las plantillas de estudio no permiten asignar equipo al documento.',
        'study_no_study' => 'La plantilla de estudio no tiene un estudio válido asociado.',
        'study_same_study' => 'El documento debe crearse en el mismo estudio o en un módulo de ese estudio.',
        'study_module_same_study' => 'El módulo debe pertenecer al mismo estudio de la plantilla.',
        'study_type_no_team' => 'Las plantillas por tipo de estudio no permiten asignar equipo al documento.',
        'study_type_no_study_type' => 'La plantilla por tipo de estudio no tiene un study_type válido asociado.',
        'study_type_same_type' => 'El documento debe crearse en el mismo tipo de estudio o en niveles inferiores.',
        'study_type_module_same_type' => 'El módulo debe pertenecer a un estudio del mismo tipo que la plantilla.',
        'study_not_match_module' => 'El estudio indicado no corresponde con el módulo seleccionado.',
        'study_same_study_type' => 'El estudio debe pertenecer al mismo tipo de estudio de la plantilla.',
        'global_team_or_context' => 'En plantillas globales, selecciona equipo o contexto académico, pero no ambos a la vez.',
        'global_team_member' => 'Solo miembros del equipo seleccionado pueden crear este documento en ese equipo.',
        'global_module_not_found' => 'El módulo seleccionado no existe.',
        'global_study_type_not_match_module' => 'El tipo de estudio indicado no corresponde con el módulo seleccionado.',
        'global_study_not_found' => 'El estudio seleccionado no existe.',
        'global_study_type_not_match_study' => 'El tipo de estudio indicado no corresponde con el estudio seleccionado.',
    ],

    'template_review' => [
        'submit_state' => 'Solo las plantillas en borrador o rechazadas pueden enviarse a revisión.',
        'min_blocks' => 'La plantilla debe tener al menos un bloque antes de enviarse a revisión.',
        'editable_block' => 'La plantilla debe tener al menos un bloque editable o modificable.',
        'modifiable_not_empty' => 'Los bloques modificables no pueden estar vacíos: el contenido predeterminado es obligatorio.',
        'locked_not_empty' => 'Los bloques bloqueados no pueden estar vacíos.',
        'reviewers_required' => 'Las plantillas no personales requieren al menos un revisor asignado antes de enviarse a revisión.',
        'document_reviewers_required' => 'Las plantillas no personales requieren al menos un validador de documento asignado antes de enviarse a revisión.',
        'reject_state' => 'Solo se puede rechazar una plantilla en revisión.',
        'approve_state' => 'Solo se puede aprobar una plantilla en revisión.',
        'not_assigned' => 'No estás asignado como revisor de esta plantilla.',
        'already_approved_reject' => 'No puedes rechazar una plantilla que ya has aprobado.',
        'already_approved' => 'Ya has aprobado esta plantilla.',
        'sequential_order' => 'Debes esperar a que los revisores de etapas anteriores aprueben primero.',
    ],

    'template_publish' => [
        'state' => 'Solo se puede publicar una plantilla en borrador o en revisión.',
        'min_blocks' => 'La plantilla debe tener al menos un bloque antes de publicarse.',
        'editable_block' => 'La plantilla debe tener al menos un bloque editable o modificable.',
        'locked_not_empty' => 'Los bloques bloqueados no pueden estar vacíos.',
    ],

    'reviewers' => [
        'duplicate_ids' => 'La lista de revisores contiene IDs de usuario duplicados.',
        'duplicate_document_ids' => 'La lista de validadores de documento contiene IDs de usuario duplicados.',
        'sequential_max' => 'La plantilla en modo secuencial admite un máximo de :max revisor(es).',
        'missing_permission' => 'Todos los usuarios asignados deben tener el permiso :permission.',
        'academic_scope' => 'Los validadores asignados deben pertenecer al contexto académico de la plantilla.',
    ],

    'version' => [
        'publish_state' => 'Solo se puede publicar una versión en borrador o en revisión.',
        'already_snapshot' => 'La versión ya tiene un snapshot inmutable publicado.',
        'number_min' => 'El número de versión debe ser mayor o igual a 1.',
        'snapshot_required' => 'El snapshot de publicación es obligatorio.',
    ],

    'share' => [
        'self' => 'No puedes compartir el documento contigo mismo.',
        'owner_has_access' => 'El titular ya tiene acceso completo al documento.',
    ],

    'cover' => [
        'invalid_image' => 'El archivo no es una imagen válida (PNG, JPG o WebP).',
    ],

    'theme_image' => [
        'url_invalid' => 'La URL no es válida.',
        'url_scheme' => 'Solo se permiten URLs http/https.',
        'url_unreachable' => 'No se puede acceder a esta URL.',
        'private_network' => 'No se pueden descargar recursos de redes privadas.',
        'download_failed' => 'No se pudo descargar la imagen.',
        'not_image' => 'El archivo no es una imagen válida.',
        'too_large' => 'La imagen es demasiado grande (máximo 10MB).',
    ],

    'attributes' => [
        'rejection_reason' => 'motivo del rechazo',
    ],
];
