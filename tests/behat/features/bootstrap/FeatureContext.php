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
   *
   * Every scenario gets its own context instance.
   * You can also pass arbitrary arguments to the
   * context constructor through behat.yml.
   */
  public function __construct() {
  }

  /**
   * Runs an arbitrary shell command.
   *
   * Example usage in .feature file:
   * Given I run "drush eval 'if (\$u = user_load_by_mail(\"joe@example.com\")) { user_delete(\$u); }'"
   *
   * @Given I run :command
   */
  public function iRun($command) {
    $output = [];
    $status = 0;
    exec($command . ' 2>&1', $output, $status);

    if ($status !== 0) {
      throw new \Exception("Command failed: $command\nOutput:\n" . implode("\n", $output));
    }
  }
}