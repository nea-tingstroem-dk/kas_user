<?php

use Civi\Api4\Contact;
use Civi\Api4\Website;

/**
 * Loads contacts and turns them into card arrays for the renderer.
 */
class CRM_KasUser_CardData {

  /** Images larger than this (bytes) are skipped. */
  private const MAX_IMAGE_BYTES = 8 * 1024 * 1024;

  /** Logos are scaled down to this width (px) when GD is available. */
  private const LOGO_WIDTH = 800;

  /** @var array image cache, keyed by image URL */
  private static $images = [];

  /**
   * @param int[] $contactIds
   * @param array $options as from CRM_KasUser_Options::load()
   *
   * @return array[] cards in the order of $contactIds' sort names
   */
  public static function load(array $contactIds, array $options): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $contactIds))));
    if (!$ids) {
      return [];
    }

    $contacts = Contact::get(TRUE)
      ->addSelect(
        'id', 'contact_type', 'display_name', 'job_title',
        'employer_id', 'employer_id.display_name', 'employer_id.image_URL',
        'email_primary.email', 'phone_primary.phone',
        'address_primary.street_address', 'address_primary.supplemental_address_1',
        'address_primary.postal_code', 'address_primary.city'
      )
      ->addWhere('id', 'IN', $ids)
      ->addWhere('is_deleted', '=', FALSE)
      ->addOrderBy('sort_name')
      ->setLimit(0)
      ->execute();

    $websites = [];
    foreach (Website::get(TRUE)->addSelect('contact_id', 'url')->addWhere('contact_id', 'IN', $ids)
      ->addWhere('url', 'IS NOT EMPTY')->addOrderBy('id')->execute() as $site) {
      $websites[$site['contact_id']] = $websites[$site['contact_id']] ?? $site['url'];
    }

    $domainLogo = NULL;
    if (($options['logo_source'] ?? 'domain') !== 'none') {
      $domainLogo = self::image(self::domainImageUrl());
    }

    $cards = [];
    foreach ($contacts as $c) {
      $address = '';
      if (!empty($options['show_address'])) {
        $street = implode(', ', array_filter([$c['address_primary.street_address'] ?? '', $c['address_primary.supplemental_address_1'] ?? '']));
        $town = trim(($c['address_primary.postal_code'] ?? '') . ' ' . ($c['address_primary.city'] ?? ''));
        $address = implode(', ', array_filter([$street, $town]));
      }

      $logo = NULL;
      if (($options['logo_source'] ?? 'domain') === 'employer' && !empty($c['employer_id.image_URL'])) {
        $logo = self::image($c['employer_id.image_URL']);
      }
      $logo = $logo ?: $domainLogo;

      $card = [
        'name' => (string) $c['display_name'],
        'job_title' => (string) ($c['job_title'] ?? ''),
        'organization' => $c['contact_type'] === 'Individual' ? (string) ($c['employer_id.display_name'] ?? '') : '',
        'phone' => (string) ($c['phone_primary.phone'] ?? ''),
        'email' => (string) ($c['email_primary.email'] ?? ''),
        'website' => (string) ($websites[$c['id']] ?? ($options['website'] ?? '')),
        'address' => $address,
        'logo' => $logo['uri'] ?? NULL,
        'logo_w' => $logo['w'] ?? 0,
        'logo_h' => $logo['h'] ?? 0,
        'qr' => CRM_KasUser_Options::qrUrl((string) ($options['qr_target'] ?? 'none'), (string) ($options['qr_url'] ?? ''), (int) $c['id']),
      ];
      $cards[] = $card;
    }
    return $cards;
  }

  /**
   * Image URL of the organisation contact behind this CiviCRM domain
   * (Administer > Communications > Organization Address and Contact Info).
   */
  public static function domainImageUrl(): ?string {
    $cid = (int) CRM_Core_BAO_Domain::getDomain()->contact_id;
    if (!$cid) {
      return NULL;
    }
    $contact = Contact::get(FALSE)->addSelect('image_URL')->addWhere('id', '=', $cid)->execute()->first();
    return $contact['image_URL'] ?? NULL;
  }

  /**
   * Reads a contact image from CiviCRM's custom upload directory and returns
   * it as a data URI, so dompdf never fetches a (login-protected) URL.
   *
   * @return array|null ['uri' => ..., 'w' => px, 'h' => px]
   */
  public static function image(?string $imageUrl): ?array {
    if (!$imageUrl) {
      return NULL;
    }
    if (array_key_exists($imageUrl, self::$images)) {
      return self::$images[$imageUrl];
    }
    return self::$images[$imageUrl] = self::readImage($imageUrl);
  }

  private static function readImage(string $imageUrl): ?array {
    $query = parse_url(html_entity_decode($imageUrl), PHP_URL_QUERY);
    parse_str((string) $query, $params);
    if (empty($params['photo'])) {
      // External image (e.g. a gravatar) – not embedded.
      return NULL;
    }
    $dir = CRM_Core_Config::singleton()->customFileUploadDir;
    $path = rtrim((string) $dir, '/\\') . DIRECTORY_SEPARATOR . basename((string) $params['photo']);
    if (!is_file($path) || !is_readable($path) || filesize($path) > self::MAX_IMAGE_BYTES) {
      return NULL;
    }
    $info = @getimagesize($path);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], TRUE)) {
      return NULL;
    }
    [$w, $h] = $info;
    $bytes = (string) file_get_contents($path);

    // With GD: scale down and flatten onto white as a plain RGB PNG, which
    // keeps logos sharp and avoids dompdf's own GD needs for transparency.
    if (function_exists('imagecreatefromstring') && ($src = @imagecreatefromstring($bytes))) {
      $newW = min($w, self::LOGO_WIDTH);
      $newH = (int) max(1, round($h * $newW / $w));
      $dst = imagecreatetruecolor($newW, $newH);
      imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
      imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
      ob_start();
      imagepng($dst, NULL, 9);
      $png = ob_get_clean();
      imagedestroy($src);
      imagedestroy($dst);
      return ['uri' => 'data:image/png;base64,' . base64_encode($png), 'w' => $newW, 'h' => $newH];
    }

    // Without GD: JPEG, or PNG without an alpha channel / transparency chunk.
    if ($info[2] === IMAGETYPE_JPEG) {
      return ['uri' => 'data:image/jpeg;base64,' . base64_encode($bytes), 'w' => $w, 'h' => $h];
    }
    if ($info[2] === IMAGETYPE_PNG && in_array(ord($bytes[25] ?? "\x06"), [0, 2, 3], TRUE) && strpos($bytes, 'tRNS') === FALSE) {
      return ['uri' => 'data:image/png;base64,' . base64_encode($bytes), 'w' => $w, 'h' => $h];
    }
    return NULL;
  }

}
