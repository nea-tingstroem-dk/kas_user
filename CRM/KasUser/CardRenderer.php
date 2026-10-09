<?php

/**
 * Builds the HTML for business cards. Has no CiviCRM dependencies apart from
 * ts(), so the layout can be rendered and tested on its own.
 *
 * Each card is an array with keys (all optional except name):
 *   name, job_title, organization, phone, email, website, address,
 *   logo (data URI), logo_w, logo_h (pixels), qr (text to encode)
 */
class CRM_KasUser_CardRenderer {

  public const CARD_W = 85.0;
  public const CARD_H = 55.0;
  public const SHEET_COLS = 2;
  public const SHEET_ROWS = 5;
  public const SHEET_W = 210.0;
  public const SHEET_H = 297.0;

  /** Left margin inside the card (mm). */
  private const PAD = 5.0;

  /** QR code position and size (mm). */
  private const QR_X = 62.0;
  private const QR_Y = 5.5;
  private const QR_SIZE = 18.0;

  /** dompdf spaces lines at ~1.45 × font size (measured), whatever line-height says. */
  private const LINE = 1.45 * 0.3528;

  /** @var string a4|single */
  private $layout = 'a4';

  /** @var string */
  private $accent = '#1f4e79';

  /** @var int */
  private $skip = 0;

  /** @var string */
  private $orgName = '';

  /** @var string */
  private $qrCaption = '';

  /** @var array cache of QR data URIs by text */
  private $qrCache = [];

  public function __construct(array $options = []) {
    if (($options['layout'] ?? '') === 'single') {
      $this->layout = 'single';
    }
    if (!empty($options['accent']) && preg_match('/^#[0-9a-fA-F]{6}$/', $options['accent'])) {
      $this->accent = strtolower($options['accent']);
    }
    $this->skip = max(0, min(9, (int) ($options['skip'] ?? 0)));
    $this->orgName = (string) ($options['org_name'] ?? '');
    $this->qrCaption = (string) ($options['qr_caption'] ?? '');
  }

  public function getLayout(): string {
    return $this->layout;
  }

  public function render(array $cards): string {
    $body = $this->layout === 'single' ? $this->renderSingles($cards) : $this->renderSheets($cards);
    return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>' . $this->css() . '</style></head><body>'
      . $body . '</body></html>';
  }

  private function renderSheets(array $cards): string {
    $marginX = (self::SHEET_W - self::SHEET_COLS * self::CARD_W) / 2;
    $marginY = (self::SHEET_H - self::SHEET_ROWS * self::CARD_H) / 2;
    $slots = array_merge(array_fill(0, $this->skip, NULL), array_values($cards));
    $sheets = array_chunk($slots, self::SHEET_COLS * self::SHEET_ROWS);

    $html = '';
    foreach ($sheets as $s => $sheet) {
      $html .= '<div class="sheet' . ($s === count($sheets) - 1 ? ' last' : '') . '">';
      foreach ($sheet as $i => $card) {
        if ($card === NULL) {
          continue;
        }
        $left = $marginX + ($i % self::SHEET_COLS) * self::CARD_W;
        $top = $marginY + intdiv($i, self::SHEET_COLS) * self::CARD_H;
        $html .= $this->renderCard($card, $left, $top, TRUE);
      }
      $html .= '</div>';
    }
    return $html;
  }

  private function renderSingles(array $cards): string {
    $html = '';
    $cards = array_values($cards);
    foreach ($cards as $i => $card) {
      $html .= '<div class="single' . ($i === count($cards) - 1 ? ' last' : '') . '">' . $this->renderCard($card, 0, 0, FALSE) . '</div>';
    }
    return $html;
  }

  private function renderCard(array $c, float $left, float $top, bool $cutLine): string {
    $hasQr = !empty($c['qr']);
    $textW = ($hasQr ? self::QR_X - 1.5 : self::CARD_W - self::PAD) - self::PAD;

    $h = '<div class="card' . ($cutLine ? ' cut' : '') . '" style="left:' . self::mm($left) . ';top:' . self::mm($top) . '">';
    $h .= '<div class="bar" style="background:' . $this->accent . '"></div>';

    // Logo, or the organisation name when there is no logo.
    if (!empty($c['logo']) && !empty($c['logo_w']) && !empty($c['logo_h'])) {
      [$w, $lh] = self::fit((int) $c['logo_w'], (int) $c['logo_h'], 34, 12);
      $h .= '<img class="logo" src="' . self::e($c['logo']) . '" style="width:' . self::mm($w) . ';height:' . self::mm($lh)
        . ';top:' . self::mm(5.5 + (12 - $lh) / 2) . '">';
    } elseif ($this->orgName !== '') {
      $size = self::fitFont($this->orgName, 74, [10, 9, 8, 7], 0.25);
      $h .= self::text($this->orgName, self::PAD, 8, 74, $size, 'bold', $this->accent);
    }
    // QR code.
    if ($hasQr) {
      $uri = $this->qrUri((string) $c['qr']);
      $h .= '<a href="' . $uri . '">';
      $h .= '<img class="qr" src="' . $uri . '" style="top:' . self::mm(5.5 + (12 - $lh) / 2) . '">';
      $h .= '</a>';
      if ($this->qrCaption !== '') {
        $capSize = self::fitFont($this->qrCaption, 22, [4.6, 4.2, 3.8], 0.2);
        $h .= '<div class="qrcap" style="font-size:' . $capSize . 'pt;color:' . '">' .
          self::e($this->qrCaption) . '</div>';
      }
    }
    // Name, job title, organisation.
    $y = 20.5;
    $sizeValues = [11.5, 10.5, ];
    $title = (string) ($c['card_title'] ?? '');
    $titleSize = self::fitFont($title, $textW, $sizeValues, 0.225, 0);

    $h .= self::text($title, self::PAD, $y, $textW, $titleSize, 'bold', '#101828', FALSE);
    $y += $titleSize * self::LINE + 0.4;

    if ((int) $c['profile'] === 1) {
      $boat = (string) ($c['boat_name'] ?? '');
      if (!empty($boat)) {
        $boatSize = self::fitFont($boat, $textW, $sizeValues, 0.225, 0);
        $h .= self::text($boat, self::PAD, $y, $textW, $boatSize, 'bold', '#101828');
        $y += $boatSize * self::LINE + 0.4;
        $sizeValues = [9.5, 8.5, 7.5];
      }
    }
    $info = (string) ($c['info'] ?? '');
    if (!empty($info)) {
      $infoSize = self::fitFont($info, $textW, [8.5, 7.5, 6.0, 5.5, 5.0], 0.225, 0);
      $h .= self::text($info, self::PAD, $y, $textW, $infoSize, 'bold', '#101828');
      $y += $infoSize * self::LINE + 0.4;
    }

    $nameLines = 1;
    $name = (string) ($c['name'] ?? '') . ' - ' . (string) ($c['external_identifier']);
    $nameSize = self::fitFont($name, $textW, [9.5, 8.5, 7.5, 6.0, 5.5, 5.0], 0.225, 0);
    $h .= self::text($name, self::PAD, $y, $textW, $nameSize, 'bold', '#101828');
    $y += $nameLines * $nameSize * self::LINE + 0.4;




    // Contact lines along the bottom, dropping the last ones if space runs out.
    $lines = [];
    foreach ([
    'phone' => self::t('T'),
    'email' => self::t('E'),
    'website' => self::t('W'),
    'address' => self::t('A'),
    ] as $key => $label) {
      $value = trim((string) ($c[$key] ?? ''));
      if ($value !== '') {
        $lines[] = [$label, $key === 'website' ? preg_replace('#^https?://(www\.)?#i', '', rtrim($value, '/')) : $value];
      }
    }
    $pitch = 3.25;
    $lineTop = max(35.0, $y + 1.4);
    $room = (int) floor((51.0 - 2.2 - $lineTop) / $pitch) + 1;
    $lines = array_slice($lines, 0, max(0, $room));
    // Bottom-align the block so short cards don't leave a gap above the edge.
    $lineTop = max($lineTop, 51.0 - 2.2 - (count($lines) - 1) * $pitch);
    foreach ($lines as $i => [$label, $value]) {
      $ly = $lineTop + $i * $pitch;
      $size = self::fitFont($value, $textW - 3.6, [6.0, 5.5, 5.0, 4.6], 0.2);
      $h .= self::text($label, self::PAD, $ly + 0.15, 3, 4.8, 'bold', $this->accent);
      $h .= self::text($value, self::PAD + 3.6, $ly, $textW - 3.6, $size, 'normal', '#344054');
    }


    return $h . '</div>';
  }

  private function qrUri(string $text): string {
    if (!isset($this->qrCache[$text])) {
      $matrix = CRM_KasUser_QrCode::encode($text, 'M');
      $this->qrCache[$text] = CRM_KasUser_QrCode::toPngDataUri($matrix, 8, 2);
    }
    return $this->qrCache[$text];
  }

  private function css(): string {
    $w = self::CARD_W;
    $h = self::CARD_H;
    $qrX = self::QR_X;
    $qrY = self::QR_Y;
    $qrS = self::QR_SIZE;
    $capX = self::QR_X - 2;
    $capY = self::QR_Y + self::QR_SIZE + 0.6;
    $capW = self::QR_SIZE + 4;
    return <<<CSS
@page { margin: 0; }
html, body { margin: 0; padding: 0; }
body { font-family: "DejaVu Sans", sans-serif; color: #101828; }
.sheet { position: relative; width: 210mm; height: 296mm; page-break-after: always; }
.single { position: relative; width: {$w}mm; height: 54.5mm; page-break-after: always; }
.sheet.last, .single.last { page-break-after: auto; }
.card { position: absolute; width: {$w}mm; height: {$h}mm; overflow: hidden; background: #ffffff; }
.card.cut { width: 84.8mm; height: 54.8mm; border: 0.1mm solid #c3c8cf; }
.bar { position: absolute; left: 0; top: 0; width: {$w}mm; height: 1.6mm; }
.logo { position: absolute; left: 5mm; }
.t { position: absolute; }
.nw { white-space: nowrap; }
.qr { position: absolute; left: {$qrX}mm; top: {$qrY}mm; width: {$qrS}mm; height: {$qrS}mm; }
.qrcap { position: absolute; left: {$capX}mm; top: {$capY}mm; width: {$capW}mm; text-align: center; white-space: nowrap; }
CSS;
  }

  // ---- Helpers ------------------------------------------------------------

  private static function text(string $value, float $x, float $y, float $w, float $size, string $weight, string $colour, bool $nowrap = TRUE): string {
    return '<div class="t' . ($nowrap ? ' nw' : '') . '" style="left:' . self::mm($x) . ';top:' . self::mm($y) . ';width:' . self::mm($w)
      . ';font-size:' . $size . 'pt;font-weight:' . $weight . ';color:' . $colour . '">' . self::e($value) . '</div>';
  }

  /**
   * Largest font size (pt) at which the text is estimated to fit the width.
   */
  private static function fitFont(string $text, float $widthMm, array $sizes, float $mmPerPt, ?float $fallback = NULL): float {
    $len = max(1, mb_strlen($text));
    foreach ($sizes as $size) {
      if ($len * $size * $mmPerPt <= $widthMm) {
        return $size;
      }
    }
    return $fallback ?? end($sizes);
  }

  /**
   * Fit an image inside a box keeping its aspect ratio; returns [w, h] in mm.
   */
  private static function fit(int $pxW, int $pxH, float $boxW, float $boxH): array {
    $scale = min($boxW / $pxW, $boxH / $pxH);
    return [$pxW * $scale, $pxH * $scale];
  }

  private static function mm(float $value): string {
    return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . 'mm';
  }

  private static function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }

  private static function t(string $s): string {
    return function_exists('ts') ? ts($s, ['domain' => 'kas_user']) : $s;
  }
}
