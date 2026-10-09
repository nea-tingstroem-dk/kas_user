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

    $contacts = Contact::get(FALSE)
      ->addSelect(
        'id', 'external_identifier', 'contact_type', 'display_name',
        'email_primary.email', 'phone_primary.phone',
        'address_primary.street_address', 'address_primary.supplemental_address_1',
        'address_primary.postal_code', 'address_primary.city',
        'boat.display_name',
        'Ekstra_medlemsdata.Kontingent')
      ->addJoin('Contact AS boat', 'LEFT', 'RelationshipCache', ['boat.far_relation', '=', '"Bådejer af"'])
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
        $street = implode(', ', array_filter([$c['address_primary.street_address'] ?? '',
          $c['address_primary.supplemental_address_1'] ?? '']));
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
        'boat_name' => $c['boat.display_name'],
        'external_identifier' => (string) ($c['external_identifier'] ?? ''),
        'phone' => (string) ($c['phone_primary.phone'] ?? ''),
        'email' => (string) ($c['email_primary.email'] ?? ''),
        'address' => $address,
        'logo' => $logo['uri'] ?? NULL,
        'logo_w' => $logo['w'] ?? 0,
        'logo_h' => $logo['h'] ?? 0,
        'qr' => CRM_KasUser_Options::qrUrl((string) ($options['qr_target'] ?? 'none'), (string) ($options['qr_url'] ?? ''), $c),
      ];
      switch ((int) $options['profile']) {
        case 1: // Mastebrik
          $mbGroup = \Civi\Api4\Group::get(FALSE)
            ->addSelect('id')
            ->addWhere('name', 'LIKE', 'mastebr%')
            ->setLimit(25)
            ->execute()
            ->first();
          $isMember = CRM_Contact_BAO_GroupContact::isContactInGroup($c['id'], $mbGroup['id']);
          if (!$isMember) {
            $card['card_title'] = "Mast ej registreret";
          }
          break;
        case 2: // Medlemskort
          // Only one membership card!
          $card['layout'] = 'single';
          $card['copies'] = 1;
          if (empty($card['external_identifier'])) {
            $card['info'] = "IKKE MEDLEM";
            break;
          }
          $kontingent = \Civi\Api4\OptionValue::get(TRUE)
            ->addSelect('name')
            ->addWhere('id', '=', $c['Ekstra_medlemsdata.Kontingent'])
            ->execute()
            ->first();
          $k = $kontingent['name'] ?? '';
          if (!empty($k) && $k === 'Passiv') {
            $card['info'] = 'Passivt medlem';
            break;
          }
          $economicCustomer = \Civi\Api4\EconomicCustomer::get(FALSE)
              ->addWhere('customerNumber', '=', ($c['external_identifier']))
              ->setLimit(1)
              ->execute()
              ->first() ?? null;
          if ($economicCustomer) {
            $isMember = \Civi\Api4\GroupContact::get(FALSE)
                ->addWhere('contact_id', '=', $c['id'])
                ->addWhere('group_id:name', '=', 'KasMedlemmer_2')
                ->addWhere('status', '=', 'Added')
                ->execute()
                ->count() > 0;
            if (!$isMember) {
              $card['info'] = "IKKE MEDLEM";
            } else {
              $periode = self::halfYear();
              if ($economicCustomer['dueAmount'] <= 0.0 ||
                $periode['dage'] < 14) {
                $card['info'] = $periode['start']->format('Y-m-d') . ' til ' . $periode['slut']->format('Y-m-d');
              } else {
                $card['info'] = "Ubetalt udestående";
              }
            }
          }
          break;
        case 3: // Parkering
          $carId = (int) ($options['car_id'] ?? 0);
          if ($carId) {
            $bil = \Civi\Api4\CustomValue::get('Bil', TRUE)
                ->addWhere('id', '=', $carId)
                ->execute()
                ->first() ?? null;
            if ($bil) {
              $card['info'] = $bil['Nummerplade'];
            }
          }
          break;
        case 4: // Tilhører
          break;
      }

      $cards[] = array_merge($options, $card);
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

  private static function halfYear(?DateTimeImmutable $dato = null): array {
    $dato = $dato ?? new DateTimeImmutable('today');
    $aar = (int) $dato->format('Y');
    $maaned = (int) $dato->format('n');

    if ($maaned >= 4 && $maaned <= 9) {
// 1. april – 30. september samme år
      $start = new DateTimeImmutable("$aar-04-01");
      $slut = new DateTimeImmutable("$aar-09-30");
    } elseif ($maaned >= 10) {
// 1. oktober i år – 31. marts næste år
      $start = new DateTimeImmutable("$aar-10-01");
      $slut = new DateTimeImmutable(($aar + 1) . "-03-31");
    } else {
// januar–marts: 1. oktober sidste år – 31. marts i år
      $start = new DateTimeImmutable(($aar - 1) . "-10-01");
      $slut = new DateTimeImmutable("$aar-03-31");
    }

    $dageFraStart = $start->diff($dato)->days;

    return ['start' => $start, 'slut' => $slut, 'dage' => $dageFraStart];
  }
}
