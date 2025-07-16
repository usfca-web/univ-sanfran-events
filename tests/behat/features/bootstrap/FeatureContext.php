<?php

use Behat\Behat\Context\Context;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\user\Entity\User;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

class FeatureContext extends RawDrupalContext implements Context {

  /**
   * Initializes context.
   */
  public function __construct() {
  }

  /**
   * @BeforeScenario
   */
  public function cleanUpUsers(BeforeScenarioScope $scope) {
    $emails = [
      'joe@example.com',
      'test@example.com',
    ];
    foreach ($emails as $email) {
      $user = user_load_by_mail($email);
      if ($user) {
        user_cancel([], $user->id(), 'user_cancel_delete');
        \Drupal::entityTypeManager()->getStorage('user')->resetCache([$user->id()]);
      }
    }
  }
}
