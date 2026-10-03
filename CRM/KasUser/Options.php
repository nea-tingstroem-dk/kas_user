<?php

use Civi\Api4\Afform;
use Civi\Api4\UFGroup;

/**
 * Saved print options, and the list of forms the QR code can open.
 */
class CRM_KasUser_Options {

  private const KEYS = [
    'layout', 'logo_source', 'qr_target', 'qr_url', 'qr_caption', 'website', 'show_address', 'accent',
  ];

  public static function load(): array {
    $values = [];
    foreach (self::KEYS as $key) {
      $values[$key] = Civi::settings()->get('kas_user_' . $key);
    }
    if (!$values['qr_caption']) {
      $values['qr_caption'] = ts('Scan to get in touch', ['domain' => 'kas_user']);
    }
    if (!$values['qr_target']) {
      $targets = self::qrTargets();
      // Default to the first real form, if there is one.
      $values['qr_target'] = (string) (array_keys(array_diff_key($targets, ['url' => 1, 'none' => 1]))[0] ?? 'url');
    }
    return $values;
  }

  public static function save(array $values): void {
    foreach (self::KEYS as $key) {
      if (array_key_exists($key, $values)) {
        Civi::settings()->set('kas_user_' . $key, $key === 'show_address' ? !empty($values[$key]) : (string) $values[$key]);
      }
    }
  }

  /**
   * Forms the QR code can point at: public Form Builder forms, profiles,
   * a custom URL, or nothing.
   *
   * @return string[] key => label
   */
  public static function qrTargets(): array {
    $targets = [];

    if (self::afformActive()) {
      $forms = Afform::get(FALSE)
        ->addSelect('name', 'title', 'server_route', 'is_public')
        ->addWhere('type', '=', 'form')
        ->addWhere('server_route', 'IS NOT EMPTY')
        ->addWhere('is_public', '=', TRUE)
        ->addOrderBy('title')
        ->execute();
      foreach ($forms as $form) {
        $targets['afform:' . $form['name']] = ts('Form Builder: %1', [1 => $form['title'] ?: $form['name'], 'domain' => 'kas_user']);
      }
    }

    $profiles = UFGroup::get(FALSE)
      ->addSelect('id', 'title', 'frontend_title')
      ->addWhere('is_active', '=', TRUE)
      ->addWhere('is_reserved', '=', FALSE)
      ->addOrderBy('title')
      ->execute();
    foreach ($profiles as $profile) {
      $targets['profile:' . $profile['id']] = ts('Profile: %1', [1 => $profile['title'], 'domain' => 'kas_user']);
    }

    $targets['url'] = ts('Another web address…', ['domain' => 'kas_user']);
    $targets['none'] = ts('No QR code', ['domain' => 'kas_user']);
    return $targets;
  }

  /**
   * The web address encoded in the QR code for one contact, or NULL.
   */
  public static function qrUrl(string $target, string $customUrl, int $contactId): ?string {
    if (strpos($target, 'profile:') === 0) {
      $gid = (int) substr($target, 8);
      return CRM_Utils_System::url('civicrm/profile/create', ['gid' => $gid, 'reset' => 1], TRUE, NULL, FALSE, TRUE);
    }
    if (strpos($target, 'afform:') === 0 && self::afformActive()) {
      $form = Afform::get(FALSE)
        ->addSelect('server_route', 'is_public')
        ->addWhere('name', '=', substr($target, 7))
        ->execute()
        ->first();
      if (!empty($form['server_route'])) {
        return CRM_Utils_System::url($form['server_route'], NULL, TRUE, NULL, FALSE, !empty($form['is_public']));
      }
      return NULL;
    }
    if ($target === 'url' && self::isWebAddress($customUrl)) {
      return str_replace('{contact_id}', (string) $contactId, trim($customUrl));
    }
    return NULL;
  }

  public static function isWebAddress(string $url): bool {
    return (bool) preg_match('#^https?://[^\s/$.?\#].[^\s]*$#i', trim($url));
  }

  private static function afformActive(): bool {
    return class_exists('\Civi\Api4\Afform')
      && CRM_Extension_System::singleton()->getMapper()->isActiveModule('afform');
  }

}
