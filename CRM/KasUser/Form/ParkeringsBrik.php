<?php

declare(strict_types=1);

use CRM_KasUser_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_KasUser_Form_ParkeringsBrik extends CRM_Core_Form {

  private $_map = [];

  /**
   * @throws \CRM_Core_Exception
   */
  public function buildQuickForm(): void {
    $currentUser = (int) CRM_Core_Session::singleton()->getLoggedInContactID();
    $superUser = CRM_Core_Permission::check('print all kas cards', $currentUser) ?: 0;
    if ($superUser) {
      $cid = (int) CRM_Utils_Request::retrieveValue('cid', 'Positive');
      if (!$cid) {
        $cid = $currentUser;
      }
    } else  {
      $cid = $currentUser;
    }
    $choices = [];
    $map = [];
    $name = null;

    $contacts = \Civi\Api4\Contact::get(TRUE)
      ->addSelect('display_name', 'external_identifier', 'bil.id', 'bil.Nummerplade')
      ->addJoin('Custom_Bil AS bil', 'LEFT', ['bil.entity_id', '=', 'id'])
      ->addWhere('id', '=', $cid)
      ->execute();
    foreach ($contacts as $c) {
      if (!$name) {
        $name = $c['display_name'] . ' - ' . $c['external_identifier'];
      }
      if ($c['bil.id']) {
        $choices[] = $c['bil.Nummerplade'];
        $map[$c['bil.Nummerplade']] = $c['bil.id'];
      }
    }
    $this->_map = $map;
    $this->add("hidden", 'cid', $cid);
    $this->add('static', 'name', $name);

    $this->add('text', 'plate', ts('Nummerplade'), [
      'class' => 'crm-select2 huge',
      'placeholder' => ts('- select or type new -'),
      'data-select-params' => json_encode([
        'tags' => $choices,
        'maximumSelectionSize' => 1, // remove to allow multiple
        'tokenSeparators' => [','],
      ]),
    ]);

    $buttons[] = [
      'type' => 'submit',
      'subName' => 'submit',
      'name' => E::ts('Submit'),
      'isDefault' => TRUE,
    ];
    $buttons[] = [
      'type' => 'cancel',
      'name' => E::ts('Cancel'),
    ];

    $this->addButtons($buttons);

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  #[\Override]
  public function postProcess() {
    $values = $this->exportValues();
    if (isset($values['plate'])) {
      $id = $this->_map[$values['plate']];
      if (!$id) {
        $result = \Civi\Api4\CustomValue::create('Bil', TRUE)
          ->addValue('entity_id', $values['cid'])
          ->addValue('Nummerplade', $values['plate'])
          ->execute()
          ->first();
        $id = $result['id'];
      }
      $url = CRM_Utils_System::url('civicrm/kas/brikker', ['profile' => 3, 'cid' => $values['cid'], 'car_id' => $id]);
      CRM_Core_Session::singleton()->replaceUserContext($url);

      parent::postProcess();
    }

    // ... save $value
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames(): array {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = [];
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

  private static function ts(string $text): string {
    return ts($text, ['domain' => 'kas_user']);
  }
}
