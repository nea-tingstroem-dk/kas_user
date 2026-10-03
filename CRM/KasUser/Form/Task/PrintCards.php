<?php

/**
 * Search action: "Print business cards".
 *
 * Available from Find Contacts, Advanced Search and other contact searches,
 * under the Actions menu. The choices made here are remembered.
 */
class CRM_KasUser_Form_Task_PrintCards extends CRM_Contact_Form_Task {

  public function buildQuickForm() {
    $this->setTitle(self::ts('Print business cards'));

    $this->add('select', 'layout', self::ts('Layout'), [
      'a4' => self::ts('A4 sheet, 10 cards (2 × 5) – desk printer'),
      'single' => self::ts('One card per page, 85 × 55 mm – card printer'),
    ], TRUE);
    $this->add('text', 'copies', self::ts('Cards per contact'), ['size' => 2, 'maxlength' => 2]);
    $this->addRule('copies', self::ts('Enter a number from 1 to 50.'), 'regex', '/^([1-9]|[1-4][0-9]|50)$/');
    $this->add('text', 'skip', self::ts('Skip positions'), ['size' => 2, 'maxlength' => 1]);
    $this->addRule('skip', self::ts('Enter a number from 0 to 9.'), 'regex', '/^[0-9]?$/');

    $this->add('select', 'logo_source', self::ts('Logo'), [
      'domain' => self::ts('Your organisation\'s logo'),
      'employer' => self::ts('Each person\'s employer logo (falls back to yours)'),
      'none' => self::ts('No logo – show the organisation name'),
    ], TRUE);

    $this->add('select', 'qr_target', self::ts('QR code opens'), CRM_KasUser_Options::qrTargets(), TRUE, ['class' => 'crm-select2 huge']);
    $this->add('text', 'qr_url', self::ts('Web address'), ['class' => 'huge', 'placeholder' => 'https://']);
    $this->add('text', 'qr_caption', self::ts('Text under the QR code'), ['class' => 'big', 'maxlength' => 40]);
    $this->add('text', 'website', self::ts('Website'), ['class' => 'big', 'placeholder' => 'www.example.org']);
    $this->add('checkbox', 'show_address', self::ts('Show address'));
    $this->add('color', 'accent', self::ts('Accent colour'), [], TRUE);

    $this->addFormRule([__CLASS__, 'formRule']);

    $this->assign('contactCount', count($this->_contactIds));
    $this->assign('logoHelp', $this->logoHelp());
    $this->assign('contactIdToken', '{contact_id}');
    $this->addDefaultButtons(self::ts('Download PDF'), 'done');
  }

  public static function formRule($values) {
    $errors = [];
    if (($values['qr_target'] ?? '') === 'url' && !CRM_KasUser_Options::isWebAddress((string) ($values['qr_url'] ?? ''))) {
      $errors['qr_url'] = self::ts('Enter a full web address starting with https://');
    }
    return $errors ?: TRUE;
  }

  public function setDefaultValues() {
    $defaults = CRM_KasUser_Options::load();
    $defaults['copies'] = count($this->_contactIds) === 1 && $defaults['layout'] === 'a4' ? 10 : 1;
    $defaults['skip'] = 0;
    $defaults['show_address'] = $defaults['show_address'] ? 1 : 0;
    if (!array_key_exists($defaults['qr_target'], CRM_KasUser_Options::qrTargets())) {
      $defaults['qr_target'] = 'url';
    }
    return $defaults;
  }

  public function postProcess() {
    $values = $this->exportValues();
    $values['show_address'] = !empty($values['show_address']);
    CRM_KasUser_Options::save($values);

    try {
      $pdf = self::buildPdf(
        $this->_contactIds,
        $values,
        max(1, min(50, (int) ($values['copies'] ?? 1))),
        (int) ($values['skip'] ?? 0)
      );
    }
    catch (Throwable $e) {
      Civi::log()->error('Business cards: ' . $e->getMessage(), ['exception' => $e]);
      CRM_Core_Session::setStatus($e->getMessage(), self::ts('Could not create the cards'), 'error');
      return;
    }
    if ($pdf === NULL) {
      CRM_Core_Session::setStatus(self::ts('None of the selected contacts could be loaded.'), self::ts('No cards'), 'alert');
      return;
    }
    // Sends the file and exits.
    CRM_Utils_System::download('business-cards-' . date('Y-m-d') . '.pdf', 'application/pdf', $pdf);
  }

  /**
   * Shared by the search action and the contact summary link.
   *
   * @return string|null PDF bytes, or NULL if no contacts could be loaded
   */
  public static function buildPdf(array $contactIds, array $options, int $copies, int $skip): ?string {
    $cards = CRM_KasUser_CardData::load($contactIds, $options);
    if (!$cards) {
      return NULL;
    }
    $repeated = [];
    foreach ($cards as $card) {
      for ($i = 0; $i < $copies; $i++) {
        $repeated[] = $card;
      }
    }
    $layout = ($options['layout'] ?? 'a4') === 'single' ? 'single' : 'a4';
    $renderer = new CRM_KasUser_CardRenderer([
      'layout' => $layout,
      'skip' => $skip,
      'accent' => (string) ($options['accent'] ?? ''),
      'org_name' => (string) CRM_Core_BAO_Domain::getDomain()->name,
      'qr_caption' => (string) ($options['qr_caption'] ?? ''),
    ]);
    return CRM_KasUser_Pdf::render($renderer->render($repeated), $layout);
  }

  /**
   * Explains where the logo comes from, with a link to edit that contact.
   */
  private function logoHelp(): string {
    $cid = (int) CRM_Core_BAO_Domain::getDomain()->contact_id;
    $link = $cid ? CRM_Utils_System::url('civicrm/contact/add', ['reset' => 1, 'action' => 'update', 'cid' => $cid]) : '';
    if (CRM_KasUser_CardData::image(CRM_KasUser_CardData::domainImageUrl())) {
      return self::ts('Uses the image on your organisation\'s contact record.')
        . ($link ? ' <a href="' . htmlspecialchars($link) . '" target="_blank">' . self::ts('Change it') . '</a>' : '');
    }
    return self::ts('Your organisation\'s contact record has no image yet, so cards will show the organisation name instead.')
      . ($link ? ' <a href="' . htmlspecialchars($link) . '" target="_blank">' . self::ts('Add a logo') . '</a>' : '');
  }

  private static function ts(string $text): string {
    return ts($text, ['domain' => 'kas_user']);
  }

}
