<?php

/**
 * @file
 * Functions for rebuilding a local development environment.
 */

// Main classes.
require_once 'includes/rebuild.class.php';
require_once 'includes/rebuilder.class.php';
require_once 'includes/diagnostics.class.php';

// Helpers.
require_once 'includes/site_install.inc';
require_once 'includes/sql_sync.inc';
require_once 'includes/pan_sql_sync.inc';
require_once 'includes/rsync.inc';
require_once 'includes/drush_script.inc';
require_once 'includes/modules.inc';
require_once 'includes/user_login.inc';
require_once 'includes/variable.inc';
require_once 'includes/permissions.inc';

require_once 'vendor/autoload.php';

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
 * Implements hook_init().
 *
 * Require Drush 8.x-6.x.
 */
function drush_rebuild_init() {
  $drush_major_version = substr(DRUSH_VERSION, 0, 1);
  if ((int) $drush_major_version < 6) {
    drush_log(dt('You are using Drush !version. Upgrading to Drush 6 is required.', array('!version' => $drush_major_version)), 'error');
    drush_die();
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
      'view-config' => 'View the rebuild config.',
      'backup' => 'Backup the local dev environment before rebuilding.',
    ),
    'aliases' => array('rb'),
    'examples' => array(
      'drush rebuild @mysite.dev --source=@mysite.prod' => 'Rebuild the enviornment at @mysite.dev using the source --@mysite.prod',
      'drush rebuild @mysite.dev --view-config' => 'View the config for rebuilding @mysite.dev. Does not execute any tasks.',
      'drush rebuild @mysite.dev --backup' => 'Creates an archive dump of the environment @mysite.dev before rebuilding the environment.',
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
  );

  $items['rebuild-init'] = array(
    'description' => 'Create a rebuild config file in the current directory from a template.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'arguments' => array(
      'template' => 'The template to use for creating a rebuild file. Options are "remote" or "site-install".',
    ),
    'callback' => 'drush_rebuild_create',
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
    'description' => 'Example Drush Rebuild file.',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'callback' => 'drush_print_file',
    'callback arguments' => array($rebuild_dir . '/examples/example.rebuild.yaml'),
  );

  $items['rebuild-version'] = array(
    'description' => 'Show Drush Rebuild version.',
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
function drush_rebuild_info($target = NULL) {
  $drush_rebuild = new DrushRebuild($target);
  return $drush_rebuild->getInfo($target);
}

/**
 * Create a rebuild file from a template.
 *
 * @param string $template
 *   The template to use.
 *
 * @return bool
 *   TRUE if successful, FALSE otherwise.
 */
function drush_rebuild_create($template = NULL) {
  $template_options = array('remote', 'site-install');
  if (!$template || !in_array($template, $template_options)) {
    return drush_set_error('You must use the "remote" or "site-install" template with this command.');
  }

  $config_filename = 'rebuild.yaml';

  $rebuild_dir = drush_server_home() . '/.drush/rebuild';
  $filename = '';
  switch ($template) {
    case 'remote':
      $filename = 'example.rebuild.yaml';
      break;

    case 'site-install':
      $filename = 'example.site_install.rebuild.yaml';
      break;
  }
  $current_dir = drush_cwd();
  $ret = copy($rebuild_dir . '/examples/' . $filename, $current_dir . '/' . $config_filename);
  if ($ret) {
    drush_log(dt('Successfully created a rebuild config file for you in !path', array('!path' => $current_dir . '/' . $config_filename)), 'success');
    return TRUE;
  }
  else {
    return drush_set_error(dt('Failed to create the rebuild config file for you.'));
  }
}

/**
 * Rebuild a local development environment.
 *
 * @param string $target
 *   The alias name for the environment to be rebuilt.
 */
function drush_rebuild($target = NULL) {

  $drush_rebuild = new DrushRebuild($target);

  // Output version information and return.
  if (drush_get_option('version')) {
    return drush_print(dt("Drush Rebuild version: !version", array('!version' => $drush_rebuild->getVersion())));
  }

  // Check if we are only viewing the script.
  if (drush_get_option('view-config')) {
    return $drush_rebuild->viewConfig();
  }
  // Return if no target was specified. Error is handled in constructor for
  // DrushRebuild.
  if (!$target) {
    return;
  }
  // Check requirements.
  if (!$drush_rebuild->checkRequirements()) {
    return drush_set_error(dt('Requirements check failed for !target', array('!target' => $target)));
  }

  // Load the rebuild config.
  $drush_rebuild->loadConfig();
  $rebuild_config = $drush_rebuild->getConfig();
  if ($rebuild_config === FALSE) {
    return drush_set_error(dt('There was an error loading the rebuild config for !site', array('!site' => $target)));
  }

  // Load metadata.
  $data = $drush_rebuild->loadMetadata();
  if (!isset($rebuild_config['site_install'])) {
    $source = NULL;
    $source = drush_get_option('source');
    if (!$source) {
      // Check if there is a default source specified in the config.
      $source = isset($rebuild_config['sync']['default_source']) ? $rebuild_config['sync']['default_source'] : NULL;
    }
    if ($source && $drush_rebuild->isValidSource($source)) {
      $rebuild_config['sync']['source'] = $source;
      $drush_rebuild->setConfig($rebuild_config);
    }
    elseif (!isset($rebuild_config['sync'])) {
      // Neither remote sync nor site install are used. Assume that the user
      // has created a Drush script for rebuilding their site.
      drush_log("Neither 'sync' nor 'site_install' are defined in your rebuild config. Proceeding with the assumption that you are rebuilding in a custom script. If you did not expect to see this, then check your rebuild config file.", 'warning');
    }
    else {
      return drush_set_error(dt('There was no source specified for the rebuild!'));
    }
  }

  // Show rebuild meta-data for the alias.
  $drush_rebuild->showMetadata();
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
  drush_print();

  if (drush_confirm(dt('Proceed with rebuilding the environment for !name', array('!name' => $target)))) {
    // Ensure that settings.php is in place before trying to generate a backup.
    // @todo make this a more robust check.
    if (drush_get_option('backup') && file_exists($drush_rebuild->environment['root'] . '/sites/default/settings.php')) {
      if (!$drush_rebuild->backupEnvironment()) {
        return FALSE;
      }
    }

    $begin_rebuild_time = time();
    $ret = $drush_rebuild->rebuild();
    drush_print();
    if ($ret) {
      $rebuild_completed_time = time();
      $total_rebuild_time = $rebuild_completed_time - $begin_rebuild_time;
      // Update meta-data.
      $drush_rebuild->updateMetadata($total_rebuild_time);
      drush_log(dt('Rebuild completed in !min minute(s) and !sec second(s).',
        array(
          '!min' => gmdate("i", $total_rebuild_time),
          '!sec' => gmdate("s", $total_rebuild_time))
          ),
        'ok'
      );
    }
    else {
      return drush_log(dt('Rebuild failed to complete successfully.'), 'error');
    }
  }
  else {
    drush_log(dt('Aborted rebuild for !name', array('!name' => $target)), 'warning');
  }
}
