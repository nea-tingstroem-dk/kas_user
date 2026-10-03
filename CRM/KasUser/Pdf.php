<?php

/**
 * Turns card HTML into a PDF with the dompdf library that ships with CiviCRM.
 */
class CRM_KasUser_Pdf {

  public static function render(string $html, string $layout): string {
    if (!class_exists('\Dompdf\Dompdf')) {
      throw new RuntimeException('dompdf is not available. It normally ships with CiviCRM.');
    }

    $options = new \Dompdf\Options();
    $options->set(self::civiSettings());
    // Every image is embedded as a data URI, so no remote fetching is needed.
    $options->set('isRemoteEnabled', FALSE);
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('dpi', 96);

    $dompdf = new \Dompdf\Dompdf($options);
    if ($layout === 'single') {
      $dompdf->setPaper([0, 0, self::mmToPt(CRM_KasUser_CardRenderer::CARD_W), self::mmToPt(CRM_KasUser_CardRenderer::CARD_H)]);
    }
    else {
      $dompdf->setPaper('A4', 'portrait');
    }
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->render();
    return (string) $dompdf->output();
  }

  /**
   * Font directory, chroot and font cache as CiviCRM configures them for its
   * own PDFs (mirrors CRM_Utils_PDF_Utils::getDompdfOptions(), which is private).
   */
  private static function civiSettings(): array {
    if (!class_exists('Civi') || !class_exists('CRM_Core_Config')) {
      return [];
    }
    $settings = [];
    foreach (['dompdf_font_dir', 'dompdf_chroot', 'dompdf_log_output_file'] as $setting) {
      $path = \Civi::settings()->get($setting);
      if (!empty($path)) {
        $settings[substr($setting, 7)] = $path;
      }
    }
    if (isset($settings['font_dir']) && is_writable($settings['font_dir'])) {
      $cacheDir = $settings['font_dir'] . DIRECTORY_SEPARATOR . 'font_cache';
    }
    else {
      $cacheDir = CRM_Core_Config::singleton()->uploadDir . '/font_cache';
    }
    if (is_dir($cacheDir) || @mkdir($cacheDir)) {
      $settings['font_cache'] = $cacheDir;
    }
    return $settings;
  }

  private static function mmToPt(float $mm): float {
    return $mm * 72 / 25.4;
  }

}
