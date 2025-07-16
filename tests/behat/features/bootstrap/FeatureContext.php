<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Tester\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Behat\Behat\Context\SnippetAcceptingContext;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends RawDrupalContext implements SnippetAcceptingContext {

  /**
   * Initializes context.
   */
  public function __construct() {
  }

  /**
   * Run a shell command, such as Drush.
   *
   * Example:
   * Given I run "drush eval 'user_delete(user_load_by_mail(\"test@example.com\"));'"
   *
   * @Given /^I run "(.*)"$/
   */
  public function iRun($command) {
    $output = [];
    $status = 0;
    exec($command . ' 2>&1', $output, $status);

    if ($status !== 0) {
      throw new \Exception("Command failed with status $status:\nCommand: $command\nOutput:\n" . implode("\n", $output));
    }
  }
}