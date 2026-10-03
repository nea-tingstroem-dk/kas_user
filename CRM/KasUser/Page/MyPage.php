<?php

declare(strict_types=1);

use CRM_KasUser_ExtensionUtil as E;

class CRM_KasUser_Page_MyPage extends CRM_Core_Page {

  public function run() {
    \Civi::service('angularjs.loader')->addModules('crmKasUser');
    \Civi::service('angularjs.loader')->useApp([
      'defaultRoute' => '/minside',
    ]);
    \Civi::service('angularjs.loader')->setPageName("Min Side");
//    $loader = new \Civi\Angular\AngularLoader();
//    $loader->addModules(array('crmKasUser'));
//    $loader->setPageName('civicrm/min-side');
//    $loader->useApp(array(
//      'defaultRoute' => '/minside',
//    ));
//    $loader->load();
    parent::run();
  }
}
