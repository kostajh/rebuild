<?php

/**
 * Implements hook_drush_command().
 */
function rebuild_drush_command() {
  $items['rebuild'] = array(
    'description' => 'Rebuilds a local Drupal environment.',
    'arguments' => array(
    ),
    'options' => array(
      'view-script' => 'View the rebuild script.',
      'no-backup' => 'Do not backup the local dev environment before rebuilding. Not recommended.',
    ),
    'aliases' => array('rb'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
  );
  return $items;
}

/**
 * Rebuild a local development environment.
 */
function drush_rebuild($environment = NULL) {
  $args = func_get_args();
  $alias_name = $args[0];
  if (!$environment) {
    return drush_set_error(dt('You must specify a drush alias with the rebuild command.'));
  }
  $env = drush_sitealias_get_record($environment);
  if (!$env) {
    return drush_set_error(dt('Failed to load site alias for !name', array('!name' => $alias_name)));
  } else {
    drush_log(dt('Loaded alias for !name', array('!name' => $alias_name)), 'success');
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

  // Execute local tasks. The `tasks.script` file is responsible for calling
  // any additional scripts in the local tasks directory.
  if (drush_confirm(dt('Proceed with rebuilding the environment for !name?', array('!name' => $alias_name)))) {
    // Get path to local task drush script, define command to execute
    $command = 'drush ' . $alias_name . ' php-script ' . $local_tasks;
    if (!drush_get_option('no-backup')) {
      $backup_path = drush_archive_dump($alias_name);
    }
    drush_shell_exec_interactive('drush ' . $alias_name . ' php-script ' . $local_tasks);
    if (file_exists($backup_path)) {
      drush_print(dt('Please review your rebuilt environment at !path.', array('!path' => $env['uri'])));
      drush_print(dt('If you are not satisfied, you can restore your previous environment from the archive at !path', array('!path' => $backup_path)));
    }
  } else {
    drush_log(dt('Aborted rebuilding !name', array('!name' => $alias_name)), 'warning');
  }
}

/**
 * Check to see if local tasks directory exists, otherwise show an error.
 */
function drush_rebuild_get_local_tasks($env) {
  $local_tasks = $env['path-aliases']['%local-tasks'];
  if (!$local_tasks) {
    return drush_set_error(dt('Please define a local tasks directory in your environment\'s alias.'));
  }
  if (!file_exists($local_tasks)) {
    return drush_set_error(dt('No local tasks directory found.'));
  }
  if ($local_tasks && !file_exists($local_tasks . '/tasks.script')) {
    return drush_set_error(dt('Local tasks directory exists, but could not find a `tasks.script` file.'));
  }
  return $local_tasks . '/tasks.script';
}
