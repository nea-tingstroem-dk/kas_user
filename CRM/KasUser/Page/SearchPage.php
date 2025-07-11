<?php
use CRM_KasUser_ExtensionUtil as E;

class CRM_KasUser_Page_SearchPage extends CRM_Core_Page {

  public function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(E::ts('Search members'));

    // Example: Assign a variable for use in a template

    parent::run();
  }

}
