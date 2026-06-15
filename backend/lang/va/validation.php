<?php

declare(strict_types=1);

return [
    'accepted' => 'El camp :attribute ha de ser acceptat.',
    'active_url' => 'El camp :attribute ha de ser una URL vàlida.',
    'after' => 'El camp :attribute ha de ser una data posterior a :date.',
    'after_or_equal' => 'El camp :attribute ha de ser una data posterior o igual a :date.',
    'alpha' => 'El camp :attribute només pot contindre lletres.',
    'alpha_dash' => 'El camp :attribute només pot contindre lletres, números, guions i guions baixos.',
    'alpha_num' => 'El camp :attribute només pot contindre lletres i números.',
    'array' => 'El camp :attribute ha de ser un conjunt.',
    'before' => 'El camp :attribute ha de ser una data anterior a :date.',
    'before_or_equal' => 'El camp :attribute ha de ser una data anterior o igual a :date.',
    'between' => [
        'array' => 'El camp :attribute ha de tindre entre :min i :max elements.',
        'file' => 'El camp :attribute ha de pesar entre :min i :max kilobytes.',
        'numeric' => 'El camp :attribute ha d\'estar entre :min i :max.',
        'string' => 'El camp :attribute ha de tindre entre :min i :max caràcters.',
    ],
    'boolean' => 'El camp :attribute ha de ser verdader o fals.',
    'confirmed' => 'La confirmació del camp :attribute no coincidix.',
    'date' => 'El camp :attribute ha de ser una data vàlida.',
    'date_equals' => 'El camp :attribute ha de ser una data igual a :date.',
    'date_format' => 'El camp :attribute ha de coincidir amb el format :format.',
    'different' => 'Els camps :attribute i :other han de ser diferents.',
    'digits' => 'El camp :attribute ha de tindre :digits dígits.',
    'digits_between' => 'El camp :attribute ha de tindre entre :min i :max dígits.',
    'email' => 'El camp :attribute ha de ser una adreça de correu vàlida.',
    'exists' => 'El camp :attribute seleccionat no és vàlid.',
    'file' => 'El camp :attribute ha de ser un arxiu.',
    'filled' => 'El camp :attribute ha de tindre un valor.',
    'gt' => [
        'array' => 'El camp :attribute ha de tindre més de :value elements.',
        'file' => 'El camp :attribute ha de pesar més de :value kilobytes.',
        'numeric' => 'El camp :attribute ha de ser major que :value.',
        'string' => 'El camp :attribute ha de tindre més de :value caràcters.',
    ],
    'gte' => [
        'array' => 'El camp :attribute ha de tindre :value elements o més.',
        'file' => 'El camp :attribute ha de pesar :value kilobytes o més.',
        'numeric' => 'El camp :attribute ha de ser major o igual que :value.',
        'string' => 'El camp :attribute ha de tindre :value caràcters o més.',
    ],
    'image' => 'El camp :attribute ha de ser una imatge.',
    'in' => 'El camp :attribute seleccionat no és vàlid.',
    'integer' => 'El camp :attribute ha de ser un número enter.',
    'ip' => 'El camp :attribute ha de ser una adreça IP vàlida.',
    'json' => 'El camp :attribute ha de ser una cadena JSON vàlida.',
    'lt' => [
        'array' => 'El camp :attribute ha de tindre menys de :value elements.',
        'file' => 'El camp :attribute ha de pesar menys de :value kilobytes.',
        'numeric' => 'El camp :attribute ha de ser menor que :value.',
        'string' => 'El camp :attribute ha de tindre menys de :value caràcters.',
    ],
    'lte' => [
        'array' => 'El camp :attribute no ha de tindre més de :value elements.',
        'file' => 'El camp :attribute ha de pesar :value kilobytes o menys.',
        'numeric' => 'El camp :attribute ha de ser menor o igual que :value.',
        'string' => 'El camp :attribute ha de tindre :value caràcters o menys.',
    ],
    'max' => [
        'array' => 'El camp :attribute no ha de tindre més de :max elements.',
        'file' => 'El camp :attribute no ha de pesar més de :max kilobytes.',
        'numeric' => 'El camp :attribute no ha de ser major que :max.',
        'string' => 'El camp :attribute no ha de tindre més de :max caràcters.',
    ],
    'mimes' => 'El camp :attribute ha de ser un arxiu de tipus: :values.',
    'mimetypes' => 'El camp :attribute ha de ser un arxiu de tipus: :values.',
    'min' => [
        'array' => 'El camp :attribute ha de tindre almenys :min elements.',
        'file' => 'El camp :attribute ha de pesar almenys :min kilobytes.',
        'numeric' => 'El camp :attribute ha de ser almenys :min.',
        'string' => 'El camp :attribute ha de tindre almenys :min caràcters.',
    ],
    'not_in' => 'El camp :attribute seleccionat no és vàlid.',
    'numeric' => 'El camp :attribute ha de ser un número.',
    'present' => 'El camp :attribute ha d\'estar present.',
    'regex' => 'El format del camp :attribute no és vàlid.',
    'required' => 'El camp :attribute és obligatori.',
    'required_if' => 'El camp :attribute és obligatori quan :other és :value.',
    'required_unless' => 'El camp :attribute és obligatori llevat que :other estiga en :values.',
    'required_with' => 'El camp :attribute és obligatori quan :values està present.',
    'required_without' => 'El camp :attribute és obligatori quan :values no està present.',
    'same' => 'Els camps :attribute i :other han de coincidir.',
    'size' => [
        'array' => 'El camp :attribute ha de contindre :size elements.',
        'file' => 'El camp :attribute ha de pesar :size kilobytes.',
        'numeric' => 'El camp :attribute ha de ser :size.',
        'string' => 'El camp :attribute ha de tindre :size caràcters.',
    ],
    'string' => 'El camp :attribute ha de ser una cadena de text.',
    'unique' => 'El camp :attribute ja ha sigut pres.',
    'url' => 'El camp :attribute ha de ser una URL vàlida.',
    'uuid' => 'El camp :attribute ha de ser un UUID vàlid.',

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Missatges de validació de domini (maya_dms)
    |--------------------------------------------------------------------------
    */

    'changelog' => [
        'required_submit' => 'El changelog és obligatori en enviar a validació.',
        'required_publish_document' => 'El changelog és obligatori en publicar un document.',
        'required_publish_template' => 'El changelog és obligatori en publicar una plantilla.',
        'max' => 'El changelog no pot superar :max caràcters.',
    ],

    'rejection_reason' => [
        'required' => 'Has d\'indicar un motiu per al rebuig o deixar un comentari en algun bloc del document.',
        'min' => 'El motiu del rebuig ha de tindre almenys :min caràcters.',
        'max' => 'El motiu del rebuig no pot superar :max caràcters.',
    ],

    'block_ids' => [
        'required' => 'Es requerix almenys un ID de bloc.',
        'uuid' => 'Cada ID de bloc ha de ser un UUID vàlid.',
        'not_found' => 'Un o més blocs no existixen.',
        'reorder_required' => 'Has d\'enviar almenys un bloc per a reordenar.',
        'duplicate' => 'La llista de blocs no pot contindre IDs duplicats.',
        'all_required' => 'Has d\'enviar tots els blocs de la plantilla.',
        'mismatch' => 'La llista enviada no coincidix amb els blocs reals de la plantilla.',
    ],

    'block_state' => [
        'required' => 'Has d\'enviar block_state per a actualitzar.',
        'in' => 'L\'estat del bloc ha de ser un de: :values. Valor rebut: :input.',
    ],

    'blocks' => [
        'complete_editable' => 'Has de completar tots els blocs editables abans d\'enviar a revisió.',
        'edit_modifiable' => 'Has d\'editar tots els blocs modificables abans d\'enviar a revisió.',
    ],

    'document' => [
        'edit_state' => 'Només es poden editar metadades de documents en esborrany o rebutjats.',
        'delete_published' => 'No es pot eliminar un document publicat sense versió de treball activa.',
        'new_version_state' => 'Només un document publicat pot passar a esborrany per a una nova versió.',
        'migrate_state' => 'El document ha d\'estar en esborrany (nova versió) per a migrar de plantilla.',
        'title_required' => 'El títol del document és obligatori.',
        'deadline_required' => 'La data d\'entrega del document és obligatòria.',
        'submit_state' => 'Només els documents en esborrany o rebutjats poden enviar-se a revisió.',
        'publish_state' => 'Només es pot publicar un document en esborrany o en revisió.',
        'reviews_pending' => 'El document té validadors assignats. Cal completar la revisió per a publicar-se.',
        'new_owner_distinct' => 'El nou titular ha de ser diferent de l\'actual.',
    ],

    'template' => [
        'no_published' => 'La plantilla no té versions publicades; no es pot crear un document.',
        'no_blocks' => 'La versió de plantilla no conté blocs.',
        'select_required' => 'Has de seleccionar una plantilla quan hi ha diverses opcions.',
        'delete_published' => 'No es pot eliminar una plantilla publicada sense versió de treball activa.',
        'new_version_state' => 'Només una plantilla publicada pot passar a esborrany per a una nova versió.',
        'name_required' => 'El nom de la plantilla és obligatori.',
        'deadline_required' => 'La data d\'entrega de la plantilla és obligatòria.',
        'visibility_required' => 'La visibilitat de la plantilla és obligatòria.',
    ],

    'template_version' => [
        'invalid' => 'La versió publicada no existix o no pertany a esta plantilla.',
        'unavailable' => 'La versió seleccionada no està disponible per al mòdul.',
    ],

    'module' => [
        'no_templates' => 'El mòdul no té plantilles publicades disponibles.',
        'not_found' => 'El mòdul no existix.',
    ],

    'process' => [
        'mismatch' => 'El procés no correspon a la plantilla seleccionada per al mòdul.',
    ],

    'migrate' => [
        'target_invalid' => 'La versió de plantilla destí no existix o no està publicada.',
        'target_older' => 'La versió de plantilla destí ha de ser més recent que l\'actual.',
        'target_no_blocks' => 'La versió de plantilla destí no conté blocs.',
        'source_not_anchored' => 'El document origen no està ancorat a una versió publicada de plantilla.',
        'no_newer_version' => 'No existix una versió de plantilla més recent que la del document origen.',
    ],

    'review' => [
        'only_in_review' => 'Les revisions només s\'apliquen a documents en revisió.',
        'already_processed' => 'Esta revisió ja ha sigut processada.',
        'sequential_order' => 'En revisió seqüencial, només pot actuar l\'etapa pendent més baixa.',
    ],

    'comment' => [
        'resource_not_allowed' => 'Tipus de recurs no permés per a comentaris.',
        'block_type_id' => 'El bloc ha d\'incloure tipus i identificador junts.',
        'block_template' => 'El bloc ha de ser de tipus plantilla.',
        'block_not_template' => 'El bloc no pertany a la plantilla indicada.',
        'block_document' => 'El bloc ha de ser de tipus document.',
        'block_not_document' => 'El bloc no pertany al document indicat.',
        'parent_not_found' => 'El comentari pare no existix.',
        'parent_unavailable' => 'El comentari pare no està disponible.',
        'parent_same_resource' => 'El comentari pare ha de pertànyer al mateix recurs i versió.',
        'parent_same_block' => 'El comentari pare ha de pertànyer al mateix bloc.',
    ],

    'template_context' => [
        'team_no_team' => 'La plantilla d\'equip no té un equip vàlid associat.',
        'personal_no_context_change' => 'Les plantilles personals no permeten canviar el context acadèmic en crear documents.',
        'module_no_team' => 'Les plantilles de mòdul no permeten assignar equip al document.',
        'module_no_module' => 'La plantilla de mòdul no té un mòdul vàlid associat.',
        'module_same_module' => 'El document ha de crear-se en el mateix mòdul de la plantilla.',
        'study_no_team' => 'Les plantilles d\'estudi no permeten assignar equip al document.',
        'study_no_study' => 'La plantilla d\'estudi no té un estudi vàlid associat.',
        'study_same_study' => 'El document ha de crear-se en el mateix estudi o en un mòdul d\'eixe estudi.',
        'study_module_same_study' => 'El mòdul ha de pertànyer al mateix estudi de la plantilla.',
        'study_type_no_team' => 'Les plantilles per tipus d\'estudi no permeten assignar equip al document.',
        'study_type_no_study_type' => 'La plantilla per tipus d\'estudi no té un study_type vàlid associat.',
        'study_type_same_type' => 'El document ha de crear-se en el mateix tipus d\'estudi o en nivells inferiors.',
        'study_type_module_same_type' => 'El mòdul ha de pertànyer a un estudi del mateix tipus que la plantilla.',
        'study_not_match_module' => 'L\'estudi indicat no correspon amb el mòdul seleccionat.',
        'study_same_study_type' => 'L\'estudi ha de pertànyer al mateix tipus d\'estudi de la plantilla.',
        'global_team_or_context' => 'En plantilles globals, selecciona equip o context acadèmic, però no els dos alhora.',
        'global_team_member' => 'Només membres de l\'equip seleccionat poden crear este document en eixe equip.',
        'global_module_not_found' => 'El mòdul seleccionat no existix.',
        'global_study_type_not_match_module' => 'El tipus d\'estudi indicat no correspon amb el mòdul seleccionat.',
        'global_study_not_found' => 'L\'estudi seleccionat no existix.',
        'global_study_type_not_match_study' => 'El tipus d\'estudi indicat no correspon amb l\'estudi seleccionat.',
    ],

    'template_review' => [
        'submit_state' => 'Només les plantilles en esborrany o rebutjades poden enviar-se a revisió.',
        'min_blocks' => 'La plantilla ha de tindre almenys un bloc abans d\'enviar-se a revisió.',
        'editable_block' => 'La plantilla ha de tindre almenys un bloc editable o modificable.',
        'modifiable_not_empty' => 'Els blocs modificables no poden estar buits: el contingut predeterminat és obligatori.',
        'locked_not_empty' => 'Els blocs bloquejats no poden estar buits.',
        'reviewers_required' => 'Les plantilles no personals requerixen almenys un revisor assignat abans d\'enviar-se a revisió.',
        'document_reviewers_required' => 'Les plantilles no personals requerixen almenys un validador de document assignat abans d\'enviar-se a revisió.',
        'reject_state' => 'Només es pot rebutjar una plantilla en revisió.',
        'approve_state' => 'Només es pot aprovar una plantilla en revisió.',
        'not_assigned' => 'No estàs assignat com a revisor d\'esta plantilla.',
        'already_approved_reject' => 'No pots rebutjar una plantilla que ja has aprovat.',
        'already_approved' => 'Ja has aprovat esta plantilla.',
        'sequential_order' => 'Has d\'esperar que els revisors d\'etapes anteriors aproven primer.',
    ],

    'template_publish' => [
        'state' => 'Només es pot publicar una plantilla en esborrany o en revisió.',
        'min_blocks' => 'La plantilla ha de tindre almenys un bloc abans de publicar-se.',
        'editable_block' => 'La plantilla ha de tindre almenys un bloc editable o modificable.',
        'locked_not_empty' => 'Els blocs bloquejats no poden estar buits.',
    ],

    'reviewers' => [
        'duplicate_ids' => 'La llista de revisors conté IDs d\'usuari duplicats.',
        'duplicate_document_ids' => 'La llista de validadors de document conté IDs d\'usuari duplicats.',
        'sequential_max' => 'La plantilla en mode seqüencial admet un màxim de :max revisor(s).',
        'missing_permission' => 'Tots els usuaris assignats han de tindre el permís :permission.',
        'academic_scope' => 'Els validadors assignats han de pertànyer al context acadèmic de la plantilla.',
    ],

    'version' => [
        'publish_state' => 'Només es pot publicar una versió en esborrany o en revisió.',
        'already_snapshot' => 'La versió ja té un snapshot immutable publicat.',
        'number_min' => 'El número de versió ha de ser major o igual a 1.',
        'snapshot_required' => 'El snapshot de publicació és obligatori.',
    ],

    'share' => [
        'self' => 'No pots compartir el document amb tu mateix.',
        'owner_has_access' => 'El titular ja té accés complet al document.',
    ],

    'cover' => [
        'invalid_image' => 'L\'arxiu no és una imatge vàlida (PNG, JPG o WebP).',
    ],

    'theme_image' => [
        'url_invalid' => 'La URL no és vàlida.',
        'url_scheme' => 'Només es permeten URLs http/https.',
        'url_unreachable' => 'No es pot accedir a esta URL.',
        'private_network' => 'No es poden descarregar recursos de xarxes privades.',
        'download_failed' => 'No s\'ha pogut descarregar la imatge.',
        'not_image' => 'L\'arxiu no és una imatge vàlida.',
        'too_large' => 'La imatge és massa gran (màxim 10MB).',
        'svg_unsafe' => 'El SVG conté contingut potencialment perillós (scripts o event handlers).',
        'storage_failed' => 'No s\'ha pogut guardar la imatge. Torna a provar-ho.',
    ],

    'attributes' => [
        'rejection_reason' => 'motiu del rebuig',
    ],
];
