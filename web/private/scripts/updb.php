<?php

/**
 * @file
 * Pantheon Quicksilver database update workflow.
 */

$command = 'drush -y updb';

echo "Executing command '$command':\n";

passthru($command);

echo "Command completed.\n";
