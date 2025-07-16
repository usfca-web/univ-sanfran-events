<?php

use Behat\Behat\Context\Context;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\user\Entity\User;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

class FeatureContext extends RawDrupalContext implements Context {

  public function __construct() {}

  /**
   * @BeforeScenario
   */
  public function cleanUpUsers(BeforeScenarioScope $scope) {
    $emails = [
      'joe@example.com',
      'test@example.com',
    ];

    $user_storage = \Drupal::entityTypeManager()->getStorage('user');

    foreach ($emails as $email) {
      $users = $user_storage->loadByProperties(['mail' => $email]);
      foreach ($users as $user) {
        // Cancel user and delete their content.
        user_cancel([], $user->id(), 'user_cancel_delete');
        $user_storage->resetCache([$user->id()]);
      }
    }
  }
}
