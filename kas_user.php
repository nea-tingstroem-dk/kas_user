<?php

require_once 'kas_user.civix.php';
// phpcs:disable
use CRM_KasUser_ExtensionUtil as E;
// phpcs:enable

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function kas_user_civicrm_config(&$config): void {
  _kas_user_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function kas_user_civicrm_install(): void {
  _kas_user_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function kas_user_civicrm_enable(): void {
  _kas_user_civix_civicrm_enable();
}


/**
 * Implements hook_civicrm_searchTasks().
 *
 * Adds "Print business cards" to the Actions menu of contact searches.
 */
function kas_user_civicrm_searchTasks($objectName, &$tasks) {
  if ($objectName !== 'contact') {
    return;
  }
  $tasks['kas_user_print'] = [
    'title' => ts('Print business cards', ['domain' => 'kas_user']),
    'class' => 'CRM_KasUser_Form_Task_PrintCards',
    'result' => FALSE,
  ];
}

/**
 * Implements hook_civicrm_summaryActions().
 *
 * Adds "Business cards (PDF)" to the Actions menu on a contact's summary page.
 */
function kas_user_civicrm_summaryActions(&$actions, $contactID) {
  if (!$contactID) {
    return;
  }
  $actions['otherActions']['kas_user'] = [
    'title' => ts('Business cards (PDF)', ['domain' => 'kas_user']),
    'description' => ts('Download business cards for this contact', ['domain' => 'kas_user']),
    'weight' => 60,
    'ref' => 'crm-contact-kas_user',
    'key' => 'kas_user',
    'class' => 'no-popup',
    'href' => CRM_Utils_System::url('civicrm/business-card', ['cid' => (int) $contactID, 'reset' => 1]),
    'icon' => 'crm-i fa-id-card-o',
  ];
}

/**
 * Implements hook_civicrm_permission().
 */
function kas_user_civicrm_permission(&$permissions) {
  $prefix = E::ts('KAS') . ': ';
  $permissions['print own kas cards'] = [
      'label' => $prefix . E::ts('Print egne KAS brikker'),
      'description' => E::ts('Mastebrik, medlemskort, parkeringskort, mærkebrik'),
    ];
  $permissions['print all kas cards'] = [
      'label' => $prefix . E::ts('Print alles KAS brikker'),
      'description' => E::ts('Mastebrik, medlemskort, parkeringskort, mærkebrik'),
    ];
}

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_preProcess
 */
//function kas_user_civicrm_preProcess($formName, &$form): void {
//
//}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 */
//function kas_user_civicrm_navigationMenu(&$menu): void {
//  _kas_user_civix_insert_navigation_menu($menu, 'Mailings', [
//    'label' => E::ts('New subliminal message'),
//    'name' => 'mailing_subliminal_message',
//    'url' => 'civicrm/mailing/subliminal',
//    'permission' => 'access CiviMail',
//    'operator' => 'OR',
//    'separator' => 0,
//  ]);
//  _kas_user_civix_navigationMenu($menu);
//}
