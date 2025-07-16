<?php

use Behat\Behat\Tester\Exception\PendingException;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;

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
   * @Given I run :command
   */
  public function iRun($command): void {
    $output = shell_exec($command);
    if ($output === null) {
      throw new \Exception("Command failed or returned null: $command");
    }
    print_r($output);
  }

}