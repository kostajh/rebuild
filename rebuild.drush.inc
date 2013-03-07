<?php

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
  // Set path to Drush Rebuild extension
  $rebuild_dir = drush_server_home() . '/.drush/rebuild';

  $items['rebuild'] = array(
    'description' => 'Rebuilds a local Drupal environment.',
    'arguments' => array(
    ),
    'options' => array(
      'source' => 'The drush alias to use as the source for the rebuild.',
      'view-manifest' => 'View the rebuild manifest.',
      'backup' => 'Backup the local dev environment before rebuilding.',
    ),
    'aliases' => array('rb'),
    'examples' => array(
      'drush rebuild @mysite.dev' => 'Rebuild the enviornment at @mysite.dev',
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
  } else {
    _drush_rebuild_show_rebuild_data($data);
  }
}

/**
 * Implements hook_COMMAND_init().
 */
function drush_rebuild_init() {
  if ((int)DRUSH_VERSION < 5) {
    drush_die(dt('Drush Rebuild requires Drush 5 or higher.'));
  }
}

/**
 * Called for `drush rebuild version` or `drush rebuild --version`
 */
function drush_rebuild_version() {
  $drush_info_file = dirname(__FILE__) . '/rebuild.info';
  $drush_rebuild_info = parse_ini_file($drush_info_file);
  define('DRUSH_REBUILD_VERSION', $drush_rebuild_info['drush_rebuild_version']);
  drush_print(dt("drush rebuild version: !version", array('!version' => DRUSH_REBUILD_VERSION)));
}

/**
 * Loads rebuild meta-data for an alias. If no data is found, a new entry is
 * added to the data file.
 *
 * @param string $alias
 * @param return array
 *        An array of rebuild meta-data for a given alias.
 */
function _drush_rebuild_load_metadata($alias) {
  $data = drush_cache_get($alias, 'rebuild');
  if (!$data) {
    $data = array(
      'last_rebuild' => NULL,
      'rebuild_times' => NULL,
    );
    return drush_cache_set($alias, $data, 'rebuild', DRUSH_CACHE_PERMANENT);
  }
  return $data;
}

/**
 * Displays rebuild data for the alias.
 *
 * @param array $data
 */
function _drush_rebuild_show_rebuild_data($data) {
  if (!$data->data['last_rebuild']) {
    return;
  }
  // Display time of last rebuild and average time for rebuilding site
  $average_time = array_sum($data->data['rebuild_times']) / count($data->data['rebuild_times']);
  drush_log(dt("Rebuild info for !name:\n- Environment was last rebuilt on !date.\n- Average time for a rebuild is !min minutes and !sec seconds.\n- Environment has been rebuilt !count time(s).\n!source",
      array('!name' => $data->cid, '!date' => date(DATE_RFC822, $data->data['last_rebuild']),
        '!min' => date('i', $average_time),
        '!sec' => date('s', $average_time),
        '!count' => count($data->data['rebuild_times']),
        '!source' => isset($data->source) ? '- Source for current rebuild: ' . $data->source : NULL,
        )), 'ok');
}

/**
 * Validate the manifest against the allowed keys.
 *
 * @param array $manifest
 * @return TRUE if manifest is valid, array of invalid keys otherwise.
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

function _drush_rebuild_load_env($environment, $alias_name) {
  if (!$environment) {
    // Enforce the syntax. `drush rebuild @target --source=@source`
    return drush_set_error(dt('You must specify a drush alias with the rebuild command.'));
  }
  $env = drush_sitealias_get_record($environment);
  if (!$env) {
    return drush_set_error(dt('Failed to load site alias for !name', array('!name' => $alias_name)));
  }
  return $env;
}

function _drush_rebuild_load_manifest($env) {
  // Check if we can load the local tasks file.
  if (!isset($env['path-aliases']['%rebuild'])) {
    return drush_set_error(dt('Please add a %rebuild entry to the path-aliases section of the Drush alias for !name', array('!name' => $alias_name)));
  }
  // Check if the file exists.
  $rebuild_manifest_path = $env['path-aliases']['%rebuild'];
  if (!file_exists($rebuild_manifest_path)) {
    return drush_set_error(dt('Could not load rebuild.info file at !path', array('!path' => $rebuild_manifest_path)));
  }
  $rebuild_manifest = parse_ini_file($rebuild_manifest_path);
  return $rebuild_manifest;
}

function _drush_rebuild_view_manifest($env) {
  $rebuild_manifest = $env['path-aliases']['%rebuild'];
  drush_log(dt('Loading manifest at !path', array('!path' => $rebuild_manifest)), 'success');
  drush_print();
  return drush_print_file($rebuild_manifest);
}

function _drush_rebuild($target_alias, $manifest, $source, $env) {
  $description = $manifest['description'];
  $type = $manifest['type'];
  $version = $manifest['version'];
  $remotes = isset($manifest['remotes']) ? $manifest['remotes'] : NULL;
  $pre_process = isset($manifest['pre_process']) ? $manifest['pre_process'] : NULL;
  $post_process = isset($manifest['post_process']) ? $manifest['post_process'] : NULL;

  if ($remotes) {
    $sql_sync_options = array();
    if (isset($manifest['sql_sync'])) {
      $sql_sync_options_raw = $manifest['sql_sync'];
      $sql_sync_options = array();
      foreach ($sql_sync_options_raw as $key => $value) {
        if (is_int($key)) {
          $sql_sync_options[] = '--' . $value;
        } else {
          $sql_sync_options[] = '--' . $key . '=' . $value;
        }
      }
    }

    if (isset($manifest['rsync']['type'])) {
      // Two types supported: files only, or entire directory
      $rsync_type = $manifest['rsync']['type'];
    }
  }
  if (isset($manifest['variables'])) {
    $variables = $manifest['variables'];
  }
  if (isset($manifest['uli'])) {
    $uli = $manifest['uli'];
  }
  if (isset($manifest['modules_enable'])) {
    $modules_enable = $manifest['modules_enable'];
  }
  if (isset($manifest['modules_disable'])) {
    $modules_disable = $manifest['modules_disable'];
  }

  // Pre Process
  if (isset($pre_process)) {
    drush_log('Executing pre process files', 'ok');
    foreach ($pre_process as $filename) {
      try {
        $rebuild_filepath = $env['path-aliases']['%rebuild'];
        $file = str_replace('rebuild.info', $filename, $rebuild_filepath);
        if (file_exists($file)) {
          $ret = drush_invoke_process($env, 'php-script', array($file));
          drush_log(dt('Executed !file script.', array('!file' => $file)), 'ok');
        }
      } catch (Exception $e) {
        return drush_set_error(dt('Failed to execute !file script.', array('!file' => $file)));
      }
    }
  }

  // SQL Sync.
  drush_log('Beginning sql-sync', 'ok');
  try {
    $ret = drush_invoke_process($env, 'sql-sync', array($source, $target_alias), array_merge($sql_sync_options, array('--quiet')));
    drush_log(dt('Synced database from !source to !target', array('!source' => $source, '!target' => $target_alias)), 'ok');
  } catch (Exception $e) {
    return drush_set_error(dt('There was an error in sql-sync. %e', array('%e' => print_r($e, TRUE))));
  }

  // Rsync.
  drush_log('Starting rsync');
  try {
    // $ret = drush_invoke_process($env, 'rsync', array("$source:%files/", "$target_alias:%files"), array(), array('interactive' => TRUE));
    drush_log(dt('Rsynced directory from !source to !target', array('!source' => $source, '!target' => $target_alias)), 'ok');
  } catch (Exception $e) {
    return drush_set_error(dt('There was an error in rsync. %e', array('%e' => print_r($e, TRUE))));
  }

  // Variables
  if (is_array($variables)) {
    drush_log('Setting variables', 'ok');
    foreach ($variables as $key => $value) {
      try {
        $ret = drush_invoke_process($env, 'variable-set', array($key, $value), array('--quiet'));
        drush_log(dt('Setting variable !var to !value', array('!var' => $key, '!value' => $value)), 'ok');
      } catch (Exception $e) {
        return drush_set_error(dt('There was an error while attempting to set variables.'));
      }
    }
  }
  // Modules
  if (is_array($modules_enable)) {
    drush_log('Enabling modules', 'ok');
    try {
      $ret = drush_invoke_process($env, 'pm-enable', $modules_enable, array('--quiet'));
      drush_log(dt('Enabled modules: !module.', array('!module' => implode(",", $modules_enable))), 'ok');
    } catch (Exception $e) {
      return drush_set_error(dt('There was an error while attempting to enable modules'));
    }
  }
  if (is_array($modules_disable)) {
    drush_log('Disabling modules', 'ok');
    try {
      $ret = drush_invoke_process($env, 'pm-disable', $modules_disable, array('--quiet'));
      drush_log(dt('Disabled modules: !module.', array('!module' => implode(", ", $modules_disable))), 'ok');
    } catch (Exception $e) {
      return drush_set_error(dt('There was an error while attempting to disable modules'));
    }
  }

  // Post Process
  if (isset($post_process)) {
    drush_log('Executing post process files', 'ok');
    foreach ($post_process as $filename) {
      try {
        $rebuild_filepath = $env['path-aliases']['%rebuild'];
        $file = str_replace('rebuild.info', $filename, $rebuild_filepath);
        if (file_exists($file)) {
          $ret = drush_invoke_process($env, 'php-script', array($file));
          drush_log(dt('Executed !file script.', array('!file' => $file)), 'ok');
        }
      } catch (Exception $e) {
        return drush_set_error(dt('Failed to execute !file script.', array('!file' => $file)));
      }
    }
  }

  // Log the user in
  if ($uli) {
    drush_log('Logging you in to the site.', 'ok');
    drush_invoke_process($env, 'uli', array('--quiet'));
  }
  return TRUE;
}

/**
 * Rebuild a local development environment.
 *
 * @param string $environment
 *   An alias for a developmented environment.
 */
function drush_rebuild($environment = NULL) {
  // Output version information and return.
  if (drush_get_option('version')) {
    return drush_rebuild_version();
  }

  $args = func_get_args();
  $alias_name = $args[0];

  $env = _drush_rebuild_load_env($environment);

  // Check if we are only viewing the script.
  if (drush_get_option('view-manifest')) {
    return _drush_rebuild_view_manifest($env);
  }

  $rebuild_manifest = _drush_rebuild_load_manifest($env);

  // Check backup options
  if (drush_get_option('no-backup')) {
    $backup_info = 'A backup of your current dev environment will not be created!';
    $status = 'warning';
  } else {
    $backup_info = 'A backup of your current dev environment will be created.';
    $status = 'ok';
  }
  drush_log($backup_info, $status);

  // Execute local tasks. The `tasks.php` file is responsible for calling
  // any additional scripts in the local tasks directory.
  drush_log(dt('The environment for !name at !dir will be rebuilt.', array('!name' => $alias_name, '!dir' => $env['root'])), 'warning');

  // Load metadata
  $data = _drush_rebuild_load_metadata($alias_name);
  if ($rebuild_manifest['type'] == 'remote') {
    $source = drush_get_option('source');
    if ($source) {
      if ($source == $alias_name) {
        return drush_set_error(dt('You cannot use the local alias as the source for a rebuild.'));
      }
      $data->source = $source;
    } else {
      return drush_set_error(dt('There was no source specified for the rebuild!'));
    }
  }

  // Show rebuild meta-data for the alias
  _drush_rebuild_show_rebuild_data($data);

  if (drush_confirm(dt('Proceed with rebuilding the environment for !name', array('!name' => $alias_name)))) {
    // Ensure that settings.php is in place before trying to generate a backup.
    // @todo make this a more robust check
    if (drush_get_option('backup') && file_exists($env['root'] . '/sites/default/settings.php')) {
      _drush_rebuild_backup_environment($alias_name);
    }

    $begin_rebuild_time = time();
    try {
      $ret = _drush_rebuild($alias_name, $rebuild_manifest, $source, $env);
      if ($ret) {
        $rebuild_completed_time = time();
        $total_rebuild_time = $rebuild_completed_time - $begin_rebuild_time;
        // Update meta-data
        _drush_rebuild_update_metadata($alias_name, $total_rebuild_time);
        drush_log(dt('Rebuild completed in !min minute(s) and !sec second(s).',
        array('!min' => date("i", $total_rebuild_time), '!sec' => date("s", $total_rebuild_time))), 'ok');
      }
    } catch (Exception $e) {
      return drush_log(dt('Rebuild failed to complete successfully.'), 'error');
    }
  } else {
    drush_log(dt('Aborted rebuilding !name', array('!name' => $alias_name)), 'warning');
  }
}

function _drush_rebuild_backup_environment($alias_name) {
  $archive_dump = drush_invoke_process($alias_name, 'archive-dump');
  $backup_path = $archive_dump['object'];
  if (!file_exists($backup_path)) {
    if (!drush_confirm(dt('Backing up your development environment failed. Are you sure you want to continue?'))) {
      return;
    }
  }
}

/**
 * Update the meta-data for an alias with the last date of last rebuild and
 * time elapsed for last rebuild.
 *
 * @param string $alias
 * @param int $total_rebuild_time
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
