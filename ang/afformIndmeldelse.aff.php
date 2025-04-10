<?php
use CRM_KasUser_ExtensionUtil as E;

return [
  'type' => 'form',
  'title' => E::ts('Indmeldelse'),
  'icon' => 'fa-list-alt',
  'server_route' => 'civicrm/indmeldelse',
  'is_public' => TRUE,
  'permission' => [
    '*always allow*',
  ],
  'create_submission' => TRUE,
];
