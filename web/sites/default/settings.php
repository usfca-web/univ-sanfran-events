<?php

/**
 * Load services definition file.
 */
$settings['container_yamls'][] = __DIR__ . '/services.yml';

/**
 * Include the Pantheon-specific settings file.
 *
 * n.b. The settings.pantheon.php file makes some changes
 *      that affect all environments that this site
 *      exists in.  Always include this file, even in
 *      a local development environment, to ensure that
 *      the site settings remain consistent.
 */
include __DIR__ . "/settings.pantheon.php";

/**
 * Skipping permissions hardening will make scaffolding
 * work better, but will also raise a warning when you
 * install Drupal.
 *
 * https://www.drupal.org/project/drupal/issues/3091285
 */
// $settings['skip_permissions_hardening'] = TRUE;

/**
 * If there is a local settings file, then include it
 */
$local_settings = __DIR__ . "/settings.local.php";
if (file_exists($local_settings)) {
  include $local_settings;
}

// CAS Hostname settings
if (isset($_ENV['PANTHEON_ENVIRONMENT']) && php_sapi_name() != 'cli') {
  // If it's the live environment, set the CAS hostname to point to prod
  if ($_ENV['PANTHEON_ENVIRONMENT'] === 'live') {
      $config['cas.settings']['server']['hostname'] = 'usfcas.usfca.edu';
  }
  else {
      // Use test server on every other Pantheon environment.
      $config['cas.settings']['server']['hostname'] = 'amidala.usfca.edu';
  }
}

// Temp file setting for containerized env
// Make sure to FTP and add directory to file structure in test and live
// Newest verion of feeds 3.0 beta removed this requirement
// if (($_ENV['PANTHEON_ENVIRONMENT'] === 'live') || ($_ENV['PANTHEON_ENVIRONMENT'] === 'test')) {
//   $config['system.file']['path']['temporary'] = __DIR__ . '/files/tmp';
//   $settings['file_temp_path'] = __DIR__ . '/files/tmp';
// }

// $config['system.logging']['error_level'] = 'verbose';
// ini_set('display_errors', TRUE);
// ini_set('display_startup_errors', TRUE);
// error_reporting(E_ALL);


$settings['state_cache'] = TRUE;