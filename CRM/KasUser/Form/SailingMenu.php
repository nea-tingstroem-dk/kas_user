<?php

use CRM_KasUser_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_KasUser_Form_SailingMenu extends CRM_Core_Form {

  private $_calendarId = 0;
  private $_userId = 0;
  private $_superUser = false;
  private $_eventId = 0;
  private $_event = null;
  private $_calendarSettings = null;
  private $_isParticipant = false;
  private $_isResponsible = false;
  private $_isSuperUser = false;

  public function preProcess() {
    $this->_userId = (int) CRM_Core_Session::singleton()->getLoggedInContactID();
    $this->_eventId = CRM_Utils_Request::retrieve('event_id', 'Integer');
    $this->_event = CRM_Event_DAO_Event::findById($this->_eventId);
    $this->_calendarId = CRM_Utils_Request::retrieve('calendar_id', 'Integer');
    $this->_calendarSettings = CRM_ResourceManagement_Page_AJAX::getResourceCalendarSettings($this->_calendarId);
    $this->_isSuperUser = CRM_Core_Permission::check('edit all events', $this->_userId);

    parent::preProcess();
  }

  /**
   * @throws \CRM_Core_Exception
   */
  public function buildQuickForm(): void {

    $this->add('hidden', 'event_id', $this->_eventId);
    $this->add('hidden', 'calendar_id', $this->_calendarId);

    $participants = [];

    $parts = \Civi\Api4\Participant::get(TRUE)
      ->addSelect('contact_id.external_identifier', 'role_id', 'contact_id.display_name', 'contact_id')
      ->addWhere('event_id', '=', $this->_eventId)
      ->execute();
    foreach ($parts as $part) {
      $contactId = $part['contact_id'];
      if ($contactId === $this->_userId) {
        $this->_isParticipant = true;
      }
      if (in_array($this->_calendarSettings['resource_role_id'], $part['role_id'])) {
        $this->assign('resource_name', $part['contact_id.display_name']);
        CRM_Utils_System::setTitle(E::ts("Details for {$this->_event->title} with {$part['contact_id.display_name']}"));
      } else if (in_array($this->_calendarSettings['host_role_id'], $part['role_id'])) {
        $this->assign('responsible', $part['contact_id.display_name']);
      } else {
        $participants[$contactId] = [
          'konto' => $part['contact_id.external_identifier'],
          'name' => $part['contact_id.display_name'],
          ];
      }
    }

    $this->assign('participants', $participants);
    $buttons = [];
    if ($this->_isParticipant) {
      $buttons[] = [
        'type' => 'submit',
        'subName' => 'confirm',
        'name' => E::ts('Confirm'),
        'icon' => 'fa-check',
      ];
      $buttons[] = [
        'type' => 'submit',
        'subName' => 'cancellation',
        'name' => E::ts('Cancel'),
        'icon' => 'fa-times',
      ];
    }
    if ($this->_isSuperUser) {
      $buttons[] = [
        'type' => 'submit',
        'subName' => 'delete-event',
        'name' => E::ts('Delete'),
        'icon' => 'fa-trash',
      ];
      $buttons[] = [
        'type' => 'submit',
        'subName' => 'edit-event',
        'name' => E::ts('Edit Event'),
        'icon' => 'fa-pencil',
      ];
      $buttons[] = [
        'type' => 'submit',
        'subName' => 'advanced',
        'name' => E::ts('Advanced'),
      ];
    }

    $this->addButtons($buttons);
    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public function postProcess(): void {
    $buttonName = $this->controller->getButtonName();
    $action = substr($buttonName, strrpos($buttonName, '_') + 1);
    switch ($action) {
      case 'edit-event':
        CRM_Utils_JSON::output(['openpage' => 'civicrm/event/manage/settings?' .
          'reset=1&action=update&id=' . $this->_eventId]);
        break;
      case 'delete-event':
        $this->_event->delete();
        CRM_Utils_JSON::output(['result' => 'OK']);
        break;
      case 'advanced':
        CRM_Utils_JSON::output(['openpage' => "civicrm/a/#/resource/manage-event?" .
          "event_id={$this->_eventId}&calendar_id={$this->_calendarId}"]);
        break;
    }
    $values = $this->exportValues();
    if (substr_compare($buttonName, 'advanced', -8) === 0 ||
      substr_compare($buttonName, 'expand', -6) === 0) {
      CRM_Utils_JSON::output(['openpage' => "civicrm/a/#/resource/manage-event?" .
        "event_id={$event->id}&calendar_id={$this->_calendar_id}"]);
    } else if (substr_compare($buttonName, 'edit_event', -10) === 0) {
      CRM_Utils_JSON::output(['openpage' => 'civicrm/event/manage/settings?' .
        'reset=1&action=update&id=' . $this->_eventId]);
    } else {
      CRM_Utils_JSON::output(['result' => 'OK']);
    }
    parent::postProcess();
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

}
