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
      'view-script' => 'View the rebuild script.',
      'no-backup' => 'Do not backup the local dev environment before rebuilding. Not recommended.',
      'no-bootstrap' => 'Do not bootstrap the local dev site. Helpful if your local site database is empty.',
    ),
    'aliases' => array('rb'),
    'examples' => array(
      'drush rebuild @mysite.dev' => 'Rebuild the enviornment at @mysite.dev',
      'drush rebuild @mysite.dev --view-script' => 'View the script for rebuilding @mysite.dev. Does not execute any tasks.',
      'drush rebuild @mysite.dev --no-backup' => 'Skips creating an archive dump of the environment @mysite.dev before rebuilding the environment.',
      'drush rebuild @mysite.dev --no-bootstrap' => 'Executes rebuild tasks for @mysite.dev without bootstrapping.',
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
    'callback arguments' => array($rebuild_dir . '/example.tasks.php'),
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
  drush_log(dt("Rebuild info for !name:\n- Environment was last rebuilt on !date.\n- Average time for a rebuild is !min minutes and !sec seconds.\n- Environment has been rebuilt !count time(s).",
      array('!name' => $data->cid, '!date' => date(DATE_RFC822, $data->data['last_rebuild']),
        '!min' => date('i', $average_time),
        '!sec' => date('s', $average_time),
        '!count' => count($data->data['rebuild_times']),
        )), 'ok');
}

/**
 * Rebuild a local development environment.
 *
 * @param string $environment
 *   An alias for a developmented environment.
 */
function drush_rebuild($environment = NULL) {
  // Output version informatino and return
  if (drush_get_option('version')) {
    return drush_rebuild_version();
  }
  $args = func_get_args();
  $alias_name = $args[0];
  if (!$environment) {
    return drush_set_error(dt('You must specify a drush alias with the rebuild command.'));
  }
  $env = drush_sitealias_get_record($environment);
  if (!$env) {
    return drush_set_error(dt('Failed to load site alias for !name', array('!name' => $alias_name)));
  }

  // Check if we can load the local tasks file
  $local_tasks = drush_rebuild_get_local_tasks($env);
  if (!file_exists($local_tasks)) {
    return;
  }

  // Check if we are only viewing the script
  if (drush_get_option('view-script')) {
    drush_log(dt('Loading script at !path', array('!path' => $local_tasks)), 'success');
    drush_print();
    drush_print_file($local_tasks);
    return;
  }

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

  // Show rebuild meta-data for the alias
  _drush_rebuild_show_rebuild_data($data);

  if (drush_confirm(dt('Proceed with rebuilding the environment for !name', array('!name' => $alias_name)))) {
    // Get path to local task drush script, define command to execute
    $command = 'drush ' . $alias_name . ' php-script ' . $local_tasks;
    // Pass alias name if no bootstrap requested
    if (drush_get_option('no-bootstrap')) {
      $command = 'drush php-script ' . $local_tasks . ' ' . $alias_name;
    }
    // Ensure that settings.php is in place before trying to generate a backup.
    // @todo make this a more robust check
    if (!drush_get_option('no-backup') && file_exists($env['root'] . '/sites/default/settings.php')) {
      $archive_dump = drush_invoke_process($alias_name, 'archive-dump');
      $backup_path = $archive_dump['object'];
      if (!file_exists($backup_path)) {
        if (!drush_confirm(dt('Backing up your development environment failed. Are you sure you want to continue?'))) {
          return;
        }
      }
    }

    // @TODO: Replace with drush_invoke_process()
    // @see https://drupal.org/node/1507310
    $begin_rebuild_time = time();
    $ret = drush_shell_exec_interactive($command);
    $rebuild_completed_time = time();
    $total_rebuild_time = $rebuild_completed_time - $begin_rebuild_time;

    // Update meta-data
    _drush_rebuild_update_metadata($alias_name, $total_rebuild_time);

    // Show information about restoring the previous environment.
    if (file_exists($backup_path)) {
      drush_print(dt('Please review your rebuilt environment at !path.', array('!path' => $env['uri'])));
      drush_print(dt('If you are not satisfied, you can restore your previous environment from the archive at !path', array('!path' => $backup_path)));
    }
    if ($ret) {
      drush_log(dt('Rebuild completed in !min minute(s) and !sec second(s).',
        array('!min' => date("i", $total_rebuild_time), '!sec' => date("s", $total_rebuild_time))), 'ok');
    } else {
      drush_log(dt('Rebuild failed to complete successfully.'), 'error');
    }
  } else {
    drush_log(dt('Aborted rebuilding !name', array('!name' => $alias_name)), 'warning');
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

/**
 * Check to see if local tasks directory exists, otherwise show an error.
 *
 * @param array $env
 *   A fully loaded site alias record.
 * @return string $local_tasks_path
 *   A path to the tasks.php file for the alias.
 */
function drush_rebuild_get_local_tasks($env) {
  $local_tasks = $env['path-aliases']['%local-tasks'];
  if (!$local_tasks) {
    return drush_set_error(dt('Please define a local tasks directory in your environment\'s alias.'));
  }
  if (!file_exists($local_tasks)) {
    return drush_set_error(dt('No local tasks directory found.'));
  }
  if ($local_tasks && !file_exists($local_tasks . '/tasks.php')) {
    return drush_set_error(dt('Local tasks directory exists, but could not find a `tasks.php` file.'));
  }
  $local_tasks_path = $local_tasks . '/tasks.php';
  return $local_tasks_path;
}
