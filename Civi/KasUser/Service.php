<?php

namespace Civi\KasUser;

use Civi\Core\Service\AutoSubscriber;
use Civi\Token\Event\TokenRegisterEvent;
use Civi\Token\Event\TokenValueEvent;
use Civi\Token\TokenRow;
use CRM_KasUser_ExtensionUtil as E;

class Service  extends AutoSubscriber {

  public static function getSubscribedEvents() {
    return ['civi.token.list' => 'register',
            'civi.token.eval' => 'value'];
  }

  public function register(TokenRegisterEvent $e){
    $e->entity('contact')->register('mastebrik', E::ts('QR-code for mastebrik'));
    $e->entity('contact')->register('tilhører', E::ts('QR-code for property marker'));
    $e->entity('contact')->register('parkering', E::ts('QR-code for Parkeringsmærke'));
  }

  public function value(TokenValueEvent $e) {
    foreach ($e->getRows() as $row) {
      /* @var TokenRow $row */
      $row->format('text/html');
      if($row->context['contactId']) {
        $row->tokens('contact', 'reversename', $this->reversename($row->context['contactId']));
      }
    }
  }

  private function reversename($contactId): string {
    $contact = \Civi\Api4\Contact::get(FALSE)
      ->addSelect('sort_name')
      ->addWhere('id', '=', $contactId)
      ->execute()
      ->single();
    return $this->reverse($contact['sort_name']);
  }

  private function reverse($name) : string {
    if(empty($name)) {
      return '';
    } else {
      return $this->reverse(substr($name, 1)).$name[0];
    }
  }
}