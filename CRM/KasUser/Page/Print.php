<?php

/**
 * civicrm/business-card?cid=N – downloads business cards for one contact,
 * using the options last chosen in "Print business cards". Linked from the
 * Actions menu on the contact summary page.
 *
 * A4 layout gives a full sheet of 10 copies; single-card layout gives one.
 */
class CRM_KasUser_Page_Print extends CRM_Core_Page {

  public function run() {
    $cid = (int) CRM_Utils_Request::retrieveValue('cid', 'Positive', 0, FALSE, 'GET');
    if (!$cid) {
      $cid = (int) CRM_Core_Session::singleton()->getLoggedInContactID();
    }
    $profileList = CRM_Core_OptionGroup::values('briktyper');
    $profile = (int) CRM_Utils_Request::retrieveValue('profile', 'Positive', 0, FALSE, 'GET');
    $options = CRM_KasUser_Options::load($profile);
    $copies = ($options['layout'] ?? 'a4') === 'single' ? 1 : 10;
    foreach ([
      'copies' => 'Positive',
      'layout' => 'String'] as $key => $type) {
      $val = CRM_Utils_Request::retrieveValue($key, $type);
      if ($val) {
        switch ($key) {
          case 'copies':
            $copies = $val;
            break;
          case 'layout':
            $options['layout'] = $val;
            $copies = ($options['layout'] ?? 'a4') === 'single' ? 1 : 10;
            break;
        }
      }
    }

    $pdf = NULL;
    try {
      // Contact::get with permission checks: returns nothing if the user may not view this contact.
      $pdf = CRM_KasUser_Form_Task_PrintCards::buildPdf([$cid], $options, $copies, 0);
    }
    catch (Throwable $e) {
      Civi::log()->error('Business cards: ' . $e->getMessage(), ['exception' => $e]);
      CRM_Core_Error::statusBounce(ts('Could not create the cards: %1', [1 => $e->getMessage(), 'domain' => 'kas_user']));
    }
    if ($pdf === NULL) {
      CRM_Core_Error::statusBounce(ts('This contact could not be found, or you do not have permission to view it.', ['domain' => 'kas_user']));
    }

    $name = CRM_Utils_String::munge($profileList[$profile] . '-' . $cid, '-', 0);
    CRM_Utils_System::download($name . '.pdf', 'application/pdf', $pdf);
  }

}
