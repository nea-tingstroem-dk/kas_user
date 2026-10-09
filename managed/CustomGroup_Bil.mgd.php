<?php
use CRM_KasUser_ExtensionUtil as E;

return [
  [
    'name' => 'CustomGroup_Bil',
    'entity' => 'CustomGroup',
    'cleanup' => 'never',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Bil',
        'title' => E::ts('Bil'),
        'extends' => 'Individual',
        'collapse_display' => TRUE,
        'weight' => 22,
        'is_multiple' => TRUE,
        'max_multiple' => 2,
        'collapse_adv_display' => TRUE,
        'icon' => 'fa-car-side',
      ],
      'match' => ['name'],
    ],
  ],
  [
    'name' => 'CustomGroup_Bil_CustomField_Nummerplade',
    'entity' => 'CustomField',
    'cleanup' => 'never',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Bil',
        'name' => 'Nummerplade',
        'label' => E::ts('Nummerplade'),
        'html_type' => 'Text',
        'is_searchable' => TRUE,
        'text_length' => 20,
        'note_columns' => 60,
        'note_rows' => 4,
        'in_selector' => TRUE,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
];
