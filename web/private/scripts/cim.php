<?php

/**
 * @file
 * Pantheon Quicksilver configuration import workflow.
 */

$command = 'drush -y cim';

echo "Executing command '$command':\n";

passthru($command);

echo "Command completed.\n";
