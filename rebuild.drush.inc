<?php

/**
 * @file
 * Functions for rebuilding a local development environment.
 */

require_once 'includes/rebuild.class.php';
require_once 'includes/rebuilder.class.php';
require_once 'includes/sql_sync.inc';
require_once 'includes/drush_script.inc';
require_once 'includes/module.inc';
require_once 'includes/user_login.inc';
require_once 'includes/variable.inc';

/**
 * Implements hook_drush_help().
 */
function rebuild_drush_help($section) {
  switch ($section) {
    case 'meta:rebuild:title':
      return dt('Rebuild commands');
  }
}

/**
 * Implements hook_drush_command().
 */
function rebuild_drush_command() {
  // Set path to Drush Rebuild extension.
  $rebuild_dir = drush_server_home() . '/.drush/rebuild';

  $items['rebuild'] = array(
    'description' => 'Rebuilds a local Drupal environment.',
    'arguments' => array(),
    'options' => array(
      'source' => array(
        'description' => 'The drush alias to use as the source for the rebuild.',
        'example_value' => '@mysite.local',
      ),
      'view-manifest' => 'View the rebuild manifest.',
      'backup' => 'Backup the local dev environment before rebuilding.',
    ),
    'aliases' => array('rb'),
    'examples' => array(
      'drush rebuild @mysite.dev --source=@mysite.prod' => 'Rebuild the enviornment at @mysite.dev using the source --@mysite.prod',
      'drush rebuild @mysite.dev --view-manifest' => 'View the manifest for rebuilding @mysite.dev. Does not execute any tasks.',
      'drush rebuild @mysite.dev --backup' => 'Creates an archive dump of the environment @mysite.dev before rebuilding the environment.',
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
  );

  $items['rebuild-readme'] = array(
    'description' => 'Drush Rebuild README',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'aliases' => array('rebuild-docs'),
    'callback' => 'drush_print_file',
    'callback arguments' => array($rebuild_dir . '/README.md'),
  );

  $items['rebuild-example'] = array(
    'description' => 'Example Drush Rebuild file',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'callback' => 'drush_print_file',
    'callback arguments' => array($rebuild_dir . '/examples/example.rebuild.info'),
  );

  $items['rebuild-version'] = array(
    'description' => 'Show drush rebuild version.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
  );

  $items['rebuild-info'] = array(
    'description' => 'Displays rebuild info for an alias.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'callback' => 'drush_rebuild_info',
    'aliases' => array('rbi'),
  );

  return $items;
}

/**
 * Display rebuild info for an alias.
 */
function drush_rebuild_info($environment = NULL) {
  $drush_rebuild = new DrushRebuild($environment);
  return $drush_rebuild->getInfo($environment);
}

/**
 * Rebuild a local development environment.
 *
 * @param string $environment
 *   An alias for a developmented environment.
 */
function drush_rebuild($environment = NULL) {

  $drush_rebuild = new DrushRebuild($environment);

  // Output version information and return.
  if (drush_get_option('version')) {
    return $drush_rebuild->getVersion();
  }

  // Check if we are only viewing the script.
  if (drush_get_option('view-manifest')) {
    return $drush_rebuild->viewManifest();
  }

  $rebuild_manifest = $drush_rebuild->loadManifest();
  if (!$rebuild_manifest) {
    return drush_set_error(dt('There was an error loading the info file for !site', array('!site' => $environment)));
  }

  // Check backup options.
  if (!drush_get_option('backup')) {
    $backup_info = 'A backup of your current dev environment will not be created!';
    $status = 'warning';
  }
  else {
    $backup_info = 'A backup of your current dev environment will be created.';
    $status = 'ok';
  }
  drush_log($backup_info, $status);

  // Execute local tasks. The `tasks.php` file is responsible for calling
  // any additional scripts in the local tasks directory.
  drush_log(dt('The environment for !name at !dir will be rebuilt.', array('!name' => $environment, '!dir' => $drush_rebuild->environment['root'])), 'warning');

  // Load metadata.
  $data = $drush_rebuild->loadMetadata();
  if ($rebuild_manifest['type'] == 'remote') {
    $source = drush_get_option('source');
    if ($source) {
      if ($source == $environment) {
        return drush_set_error(dt('You cannot use the local alias as the source for a rebuild.'));
      }
      $drush_rebuild->source = $source;
      $data->source = $source;
    }
    else {
      return drush_set_error(dt('There was no source specified for the rebuild!'));
    }
  }

  // Show rebuild meta-data for the alias.
  $drush_rebuild->showMetadata();

  if (drush_confirm(dt('Proceed with rebuilding the environment for !name', array('!name' => $alias_name)))) {
    // Ensure that settings.php is in place before trying to generate a backup.
    // @todo make this a more robust check.
    if (drush_get_option('backup') && file_exists($drush_rebuild->environment['root'] . '/sites/default/settings.php')) {
      $drush_rebuild->backupEnvironment();
    }

    $begin_rebuild_time = time();
    $ret = $drush_rebuild->rebuild();
    if ($ret) {
      $rebuild_completed_time = time();
      $total_rebuild_time = $rebuild_completed_time - $begin_rebuild_time;
      // Update meta-data
      $drush_rebuild->updateMetadata($total_rebuild_time);
      drush_log(dt('Rebuild completed in !min minute(s) and !sec second(s).',
        array(
          '!min' => date("i", $total_rebuild_time),
          '!sec' => date("s", $total_rebuild_time))
          ),
        'ok'
      );
    }
    else {
      return drush_log(dt('Rebuild failed to complete successfully.'), 'error');
    }
  }
  else {
    drush_log(dt('Aborted rebuilding !name', array('!name' => $environment)), 'warning');
  }
}
