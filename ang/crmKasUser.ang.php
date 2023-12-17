<?php
// This file declares an Angular module which can be autoloaded
// in CiviCRM. See also:
// \https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_angularModules/n
return [
  'js' => [
    'ang/crmKasUser.js',
    'ang/crmKasUser/*.js',
    'ang/crmKasUser/*/*.js',
  ],
  'css' => [
    'ang/crmKasUser.css',
  ],
  'partials' => [
    'ang/crmKasUser',
  ],
  'requires' => [
    'crmUi',
    'crmUtil',
    'ngRoute',
  ],
  'settings' => [],
];
