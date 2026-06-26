<?php

declare(strict_types=1);

return [
    'accepted' => 'The :attribute field must be accepted.',
    'active_url' => 'The :attribute field must be a valid URL.',
    'after' => 'The :attribute field must be a date after :date.',
    'after_or_equal' => 'The :attribute field must be a date after or equal to :date.',
    'alpha' => 'The :attribute field must only contain letters.',
    'alpha_dash' => 'The :attribute field must only contain letters, numbers, dashes, and underscores.',
    'alpha_num' => 'The :attribute field must only contain letters and numbers.',
    'array' => 'The :attribute field must be an array.',
    'before' => 'The :attribute field must be a date before :date.',
    'before_or_equal' => 'The :attribute field must be a date before or equal to :date.',
    'between' => [
        'array' => 'The :attribute field must have between :min and :max items.',
        'file' => 'The :attribute field must be between :min and :max kilobytes.',
        'numeric' => 'The :attribute field must be between :min and :max.',
        'string' => 'The :attribute field must be between :min and :max characters.',
    ],
    'boolean' => 'The :attribute field must be true or false.',
    'confirmed' => 'The :attribute field confirmation does not match.',
    'date' => 'The :attribute field must be a valid date.',
    'date_equals' => 'The :attribute field must be a date equal to :date.',
    'date_format' => 'The :attribute field must match the format :format.',
    'different' => 'The :attribute field and :other must be different.',
    'digits' => 'The :attribute field must be :digits digits.',
    'digits_between' => 'The :attribute field must be between :min and :max digits.',
    'email' => 'The :attribute field must be a valid email address.',
    'exists' => 'The selected :attribute is invalid.',
    'file' => 'The :attribute field must be a file.',
    'filled' => 'The :attribute field must have a value.',
    'gt' => [
        'array' => 'The :attribute field must have more than :value items.',
        'file' => 'The :attribute field must be greater than :value kilobytes.',
        'numeric' => 'The :attribute field must be greater than :value.',
        'string' => 'The :attribute field must be greater than :value characters.',
    ],
    'gte' => [
        'array' => 'The :attribute field must have :value items or more.',
        'file' => 'The :attribute field must be greater than or equal to :value kilobytes.',
        'numeric' => 'The :attribute field must be greater than or equal to :value.',
        'string' => 'The :attribute field must be greater than or equal to :value characters.',
    ],
    'image' => 'The :attribute field must be an image.',
    'in' => 'The selected :attribute is invalid.',
    'integer' => 'The :attribute field must be an integer.',
    'ip' => 'The :attribute field must be a valid IP address.',
    'json' => 'The :attribute field must be a valid JSON string.',
    'lt' => [
        'array' => 'The :attribute field must have less than :value items.',
        'file' => 'The :attribute field must be less than :value kilobytes.',
        'numeric' => 'The :attribute field must be less than :value.',
        'string' => 'The :attribute field must be less than :value characters.',
    ],
    'lte' => [
        'array' => 'The :attribute field must not have more than :value items.',
        'file' => 'The :attribute field must be less than or equal to :value kilobytes.',
        'numeric' => 'The :attribute field must be less than or equal to :value.',
        'string' => 'The :attribute field must be less than or equal to :value characters.',
    ],
    'max' => [
        'array' => 'The :attribute field must not have more than :max items.',
        'file' => 'The :attribute field must not be greater than :max kilobytes.',
        'numeric' => 'The :attribute field must not be greater than :max.',
        'string' => 'The :attribute field must not be greater than :max characters.',
    ],
    'mimes' => 'The :attribute field must be a file of type: :values.',
    'mimetypes' => 'The :attribute field must be a file of type: :values.',
    'min' => [
        'array' => 'The :attribute field must have at least :min items.',
        'file' => 'The :attribute field must be at least :min kilobytes.',
        'numeric' => 'The :attribute field must be at least :min.',
        'string' => 'The :attribute field must be at least :min characters.',
    ],
    'not_in' => 'The selected :attribute is invalid.',
    'numeric' => 'The :attribute field must be a number.',
    'present' => 'The :attribute field must be present.',
    'regex' => 'The :attribute field format is invalid.',
    'required' => 'The :attribute field is required.',
    'required_if' => 'The :attribute field is required when :other is :value.',
    'required_unless' => 'The :attribute field is required unless :other is in :values.',
    'required_with' => 'The :attribute field is required when :values is present.',
    'required_without' => 'The :attribute field is required when :values is not present.',
    'same' => 'The :attribute field and :other must match.',
    'size' => [
        'array' => 'The :attribute field must contain :size items.',
        'file' => 'The :attribute field must be :size kilobytes.',
        'numeric' => 'The :attribute field must be :size.',
        'string' => 'The :attribute field must be :size characters.',
    ],
    'string' => 'The :attribute field must be a string.',
    'unique' => 'The :attribute has already been taken.',
    'url' => 'The :attribute field must be a valid URL.',
    'uuid' => 'The :attribute field must be a valid UUID.',

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain validation messages (maya_dms)
    |--------------------------------------------------------------------------
    */

    'changelog' => [
        'required_submit' => 'A changelog is required when submitting for validation.',
        'required_publish_document' => 'A changelog is required when publishing a document.',
        'required_publish_template' => 'A changelog is required when publishing a template.',
        'max' => 'The changelog may not be greater than :max characters.',
    ],

    'rejection_reason' => [
        'required' => 'You must provide a reason for the rejection or leave a comment on a block of the document.',
        'min' => 'The rejection reason must be at least :min characters.',
        'max' => 'The rejection reason may not be greater than :max characters.',
    ],

    'block_ids' => [
        'required' => 'At least one block ID is required.',
        'uuid' => 'Each block ID must be a valid UUID.',
        'not_found' => 'One or more blocks do not exist.',
        'reorder_required' => 'You must send at least one block to reorder.',
        'duplicate' => 'The block list may not contain duplicate IDs.',
        'all_required' => 'You must send all blocks of the template.',
        'mismatch' => 'The submitted list does not match the actual blocks of the template.',
    ],

    'block_state' => [
        'required' => 'You must send block_state to update.',
        'in' => 'The block state must be one of: :values. Received value: :input.',
    ],

    'blocks' => [
        'complete_editable' => 'You must complete all editable blocks before submitting for review.',
        'edit_modifiable' => 'You must edit all modifiable blocks before submitting for review.',
    ],

    'document' => [
        'edit_state' => 'Only metadata of draft or rejected documents can be edited.',
        'delete_published' => 'A published document cannot be deleted without an active working version.',
        'new_version_state' => 'Only a published document can move to draft for a new version.',
        'migrate_state' => 'The document must be in draft (new version) to migrate the template.',
        'title_required' => 'The document title is required.',
        'deadline_required' => 'The document deadline is required.',
        'submit_state' => 'Only draft or rejected documents can be submitted for review.',
        'publish_state' => 'Only a draft or in-review document can be published.',
        'reviews_pending' => 'The document has assigned reviewers. The review must be completed before publishing.',
        'new_owner_distinct' => 'The new owner must be different from the current one.',
    ],

    'template' => [
        'no_published' => 'The template has no published versions; a document cannot be created.',
        'no_blocks' => 'The template version contains no blocks.',
        'select_required' => 'You must select a template when several options exist.',
        'delete_published' => 'A published template cannot be deleted without an active working version.',
        'new_version_state' => 'Only a published template can move to draft for a new version.',
        'name_required' => 'The template name is required.',
        'deadline_required' => 'The template deadline is required.',
        'document_deadline_required' => 'The document validation deadline is required on the template.',
        'document_deadline_before_template' => 'The document deadline cannot be earlier than the template validation deadline.',
        'visibility_required' => 'The template visibility is required.',
    ],

    'template_version' => [
        'invalid' => 'The published version does not exist or does not belong to this template.',
        'unavailable' => 'The selected version is not available for the module.',
    ],

    'module' => [
        'no_templates' => 'The module has no published templates available.',
        'not_found' => 'The module does not exist.',
    ],

    'process' => [
        'mismatch' => 'The process does not match the template selected for the module.',
    ],

    'migrate' => [
        'target_invalid' => 'The target template version does not exist or is not published.',
        'target_older' => 'The target template version must be more recent than the current one.',
        'target_no_blocks' => 'The target template version contains no blocks.',
        'source_not_anchored' => 'The source document is not anchored to a published template version.',
        'no_newer_version' => 'There is no template version more recent than the source document.',
    ],

    'review' => [
        'only_in_review' => 'Reviews only apply to documents under review.',
        'already_processed' => 'This review has already been processed.',
        'sequential_order' => 'In sequential review, only the lowest pending stage can act.',
    ],

    'comment' => [
        'resource_not_allowed' => 'Resource type not allowed for comments.',
        'block_type_id' => 'The block must include type and identifier together.',
        'block_template' => 'The block must be of template type.',
        'block_not_template' => 'The block does not belong to the indicated template.',
        'block_document' => 'The block must be of document type.',
        'block_not_document' => 'The block does not belong to the indicated document.',
        'parent_not_found' => 'The parent comment does not exist.',
        'parent_unavailable' => 'The parent comment is not available.',
        'parent_same_resource' => 'The parent comment must belong to the same resource and version.',
        'parent_same_block' => 'The parent comment must belong to the same block.',
    ],

    'template_context' => [
        'team_no_team' => 'The team template has no valid team associated.',
        'personal_no_context_change' => 'Personal templates do not allow changing the academic context when creating documents.',
        'module_no_team' => 'Module templates do not allow assigning a team to the document.',
        'module_no_module' => 'The module template has no valid module associated.',
        'module_same_module' => 'The document must be created in the same module as the template.',
        'study_no_team' => 'Study templates do not allow assigning a team to the document.',
        'study_no_study' => 'The study template has no valid study associated.',
        'study_same_study' => 'The document must be created in the same study or in a module of that study.',
        'study_module_same_study' => 'The module must belong to the same study as the template.',
        'study_type_no_team' => 'Study-type templates do not allow assigning a team to the document.',
        'study_type_no_study_type' => 'The study-type template has no valid study_type associated.',
        'study_type_same_type' => 'The document must be created in the same study type or in lower levels.',
        'study_type_module_same_type' => 'The module must belong to a study of the same type as the template.',
        'study_not_match_module' => 'The indicated study does not match the selected module.',
        'study_same_study_type' => 'The study must belong to the same study type as the template.',
        'global_team_or_context' => 'In global templates, select a team or academic context, but not both at once.',
        'global_team_member' => 'Only members of the selected team can create this document in that team.',
        'global_module_not_found' => 'The selected module does not exist.',
        'global_study_type_not_match_module' => 'The indicated study type does not match the selected module.',
        'global_study_not_found' => 'The selected study does not exist.',
        'global_study_type_not_match_study' => 'The indicated study type does not match the selected study.',
    ],

    'template_review' => [
        'submit_state' => 'Only draft or rejected templates can be submitted for review.',
        'min_blocks' => 'The template must have at least one block before being submitted for review.',
        'editable_block' => 'The template must have at least one editable or modifiable block.',
        'modifiable_not_empty' => 'Modifiable blocks cannot be empty: default content is required.',
        'locked_not_empty' => 'Locked blocks cannot be empty.',
        'reviewers_required' => 'Non-personal templates require at least one assigned reviewer before being submitted for review.',
        'document_reviewers_required' => 'Non-personal templates require at least one assigned document reviewer before being submitted for review.',
        'reject_state' => 'Only a template under review can be rejected.',
        'approve_state' => 'Only a template under review can be approved.',
        'not_assigned' => 'You are not assigned as a reviewer of this template.',
        'already_approved_reject' => 'You cannot reject a template you have already approved.',
        'already_approved' => 'You have already approved this template.',
        'sequential_order' => 'You must wait for the reviewers of previous stages to approve first.',
    ],

    'template_publish' => [
        'state' => 'Only a draft or in-review template can be published.',
        'min_blocks' => 'The template must have at least one block before publishing.',
        'editable_block' => 'The template must have at least one editable or modifiable block.',
        'locked_not_empty' => 'Locked blocks cannot be empty.',
    ],

    'reviewers' => [
        'duplicate_ids' => 'The reviewer list contains duplicate user IDs.',
        'duplicate_document_ids' => 'The document reviewer list contains duplicate user IDs.',
        'sequential_max' => 'A template in sequential mode allows a maximum of :max reviewer(s).',
        'missing_permission' => 'All assigned users must have the permission :permission.',
        'academic_scope' => 'Assigned reviewers must belong to the academic context of the template.',
    ],

    'version' => [
        'publish_state' => 'Only a draft or in-review version can be published.',
        'already_snapshot' => 'The version already has a published immutable snapshot.',
        'number_min' => 'The version number must be greater than or equal to 1.',
        'snapshot_required' => 'The publication snapshot is required.',
    ],

    'share' => [
        'self' => 'You cannot share the document with yourself.',
        'owner_has_access' => 'The owner already has full access to the document.',
    ],

    'cover' => [
        'invalid_image' => 'The file is not a valid image (PNG, JPG or WebP).',
    ],

    'theme_image' => [
        'url_invalid' => 'The URL is not valid.',
        'url_scheme' => 'Only http/https URLs are allowed.',
        'url_unreachable' => 'This URL cannot be accessed.',
        'private_network' => 'Resources from private networks cannot be downloaded.',
        'download_failed' => 'The image could not be downloaded.',
        'not_image' => 'The file is not a valid image.',
        'too_large' => 'The image is too large (maximum 10MB).',
        'svg_unsafe' => 'The SVG contains potentially dangerous content (scripts or event handlers).',
        'storage_failed' => 'The image could not be saved. Please try again.',
    ],

    'attributes' => [
        'rejection_reason' => 'rejection reason',
    ],
];
