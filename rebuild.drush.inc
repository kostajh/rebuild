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
    'callback' => 'drush_print_file',
    'callback arguments' => array('README.md'),
  );

  $items['rebuild-example'] = array(
    'description' => 'Example Drush Rebuild file',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'callback' => 'drush_print_file',
    'callback arguments' => array('example.tasks.php'),
  );

  return $items;
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
 * Rebuild a local development environment.
 *
 * @param string $environment
 *   An alias for a developmented environment.
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

  // Execute local tasks. The `tasks.php` file is responsible for calling
  // any additional scripts in the local tasks directory.
  drush_log(dt('The environment for !name at !dir will be rebuilt.', array('!name' => $alias_name, '!dir' => $env['root'])), 'warning');
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
    drush_shell_exec_interactive($command);

    // Show information about restoring the previous environment.
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
