<?php

/**
 * @file
 * Functions for rebuilding a local development environment.
 */

require_once 'includes/rebuild.class.php';
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
  // @todo if environment isn't set, show info about all aliases
  $args = func_get_args();
  $alias_name = $args[0];
  $data = _drush_rebuild_load_metadata($alias_name);
  if (!$data->data['last_rebuild']) {
    drush_log(dt('There isn\'t any rebuild info to display for !name', array('!name' => $alias_name)), 'error');
  }
  else {
    _drush_rebuild_show_rebuild_data($data);
  }
}

/**
 * Validate the manifest against the allowed keys.
 *
 * @param array $manifest
 *   The rebuild info file as an array.
 *
 * @return bool
 *   TRUE if manifest is valid, array of invalid keys otherwise.
 */
function _drush_rebuild_validate_manifest($manifest) {
  $valid_options = array(
    'description',
    'version',
    'type',
    'remotes',
    'sql_sync',
    'variables',
    'uli',
    'modules_enable',
    'modules_disable',
    'pre_process',
    'post_process',
  );
}



/**
 * Rebuild a local development environment.
 *
 * @param string $target_alias
 *   The environment to be rebuilt.
 * @param array $manifest
 *   The rebuild info file options.
 * @param string $source
 *   The site environment to use as the source for the rebuild.
 * @param array $env
 *   The Drush site environment for the target site.
 */
function _drush_rebuild($target_alias, $manifest, $source, $env) {


  // Execute pre-process scripts.


  // Execute rsync.
  // drush_log('Starting rsync');
  // @todo Implement.
  // drush_log(dt('Rsynced directory from !source to !target', array('!source' => $source, '!target' => $target_alias)), 'ok');




  return TRUE;
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

  $args = func_get_args();
  $alias_name = $args[0];

  $env = $drush_rebuild->loadEnvironment($environment);

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
  drush_log(dt('The environment for !name at !dir will be rebuilt.', array('!name' => $alias_name, '!dir' => $env['root'])), 'warning');

  // Load metadata.
  $data = $drush_rebuild->loadMetadata();
  if ($rebuild_manifest['type'] == 'remote') {
    $source = drush_get_option('source');
    if ($source) {
      if ($source == $alias_name) {
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
    if (drush_get_option('backup') && file_exists($env['root'] . '/sites/default/settings.php')) {
      $drush_rebuild->backupEnvironment();
    }

    $begin_rebuild_time = time();
    $ret = $drush_rebuild->rebuild();
    if ($ret) {
      $rebuild_completed_time = time();
      $total_rebuild_time = $rebuild_completed_time - $begin_rebuild_time;
      // Update meta-data
      _drush_rebuild_update_metadata($alias_name, $total_rebuild_time);
      drush_log(dt('Rebuild completed in !min minute(s) and !sec second(s).',
        array(
          '!min' => date("i", $total_rebuild_time),
          '!sec' => date("s", $total_rebuild_time))
          ),
        'ok'
      );
    }
    else
    {
      return drush_log(dt('Rebuild failed to complete successfully.'), 'error');
    }
  }
  else {
    drush_log(dt('Aborted rebuilding !name', array('!name' => $alias_name)), 'warning');
  }
}

/**
 * Update the meta-data for an alias.
 *
 * Meta-data will be updated with the last date of last rebuild and time elapsed
 * for last rebuild.
 *
 * @param string $alias
 *   The site alias to update.
 * @param int $total_rebuild_time
 *   The amount of time elapsed in seconds for the rebuild.
 */
function _drush_rebuild_update_metadata($alias, $total_rebuild_time) {
  $cache = drush_cache_get($alias, 'rebuild');
  $rebuild_times = $cache->data['rebuild_times'];
  $rebuild_times[] = $total_rebuild_time;
  $data = array();
  $data['last_rebuild'] = time();
  $data['rebuild_times'] = $rebuild_times;
  drush_cache_set($alias, $data, 'rebuild', DRUSH_CACHE_PERMANENT);
}

