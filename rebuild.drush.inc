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
require_once 'includes/rsync.inc';
require_once 'includes/drush_script.inc';
require_once 'includes/modules.inc';
require_once 'includes/user_login.inc';
require_once 'includes/variable.inc';
require_once 'includes/permissions.inc';
// Load composer's autoload.php if it exists.
if (file_exists('vendor/autoload.php')) {
  require_once 'vendor/autoload.php';
}


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
    'description' => 'Create a rebuild file from a template',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'arguments' => array(
      'template' => 'The template to use for creating a rebuild file. Options are "remote" or "site-install".',
    ),
    'options' => array(
      'format' => 'The format to use for the template. Valid options are "ini" and "yaml".',
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
 * Implements hook_init().
 */
function drush_rebuild_init() {
  // Check for YAML component.
  if (!class_exists('Symfony\Component\Yaml\Parser')) {
    // Attempt to install the YAML component.
    drush_log('Could not find the Symfony YAML component. Attempting to install it for you...', 'warning');
    drush_shell_exec("curl -sS https://getcomposer.org/installer | php");
    if (!file_exists('composer.phar')) {
      return drush_set_error('Failed to download Composer. Please consult the README.');
    }
    drush_shell_exec("php composer.phar install");
    if (file_exists('vendor/autoload.php')) {
      require_once 'vendor/autoload.php';
    }
    if (!class_exists('Symfony\Component\Yaml\Parser')) {
      return drush_set_error('An automated attempt at downloading the Symfony YAML component failed. Please consult the README.');
    }
    else {
      drush_log('Successfully installed Symfony YAML component!', 'success');
    }
  }
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
  $format = drush_get_option('format');
  if (!$format) {
    $format = 'yaml';
  }
  $valid_formats = array('ini', 'yaml');
  if (!in_array($format, $valid_formats)) {
    return drush_set_error(dt('The format "%format" is not valid!.', array('%format' => $format)));
  }
  $config_filename = ($format == 'ini') ? 'rebuild.info' : 'rebuild.yaml';

  $rebuild_dir = drush_server_home() . '/.drush/rebuild';
  $filename = '';
  switch ($template) {
    case 'remote':
      $filename = ($format == 'ini') ? 'example.rebuild.info' : 'example.rebuild.yaml';
      break;

    case 'site-install':
      $filename = ($format == 'ini') ? 'example.site_install.rebuild.info' : 'example.site_install.rebuild.yaml';
      break;
  }
  $current_dir = drush_cwd();
  $ret = copy($rebuild_dir . '/examples/' . $filename, $current_dir . '/' . $config_filename);
  if ($ret) {
    drush_log(dt('Successfully created a rebuild.info file for you in !path', array('!path' => $current_dir . '/' . $config_filename)), 'success');
    return TRUE;
  }
  else {
    return drush_set_error(dt('Failed to create the rebuild.info file for you.'));
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
  $rebuild_config = $drush_rebuild->loadConfig();
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
      $source = isset($rebuild_config['default_source']) ? $rebuild_config['default_source'] : NULL;
    }
    if ($source && $drush_rebuild->isValidSource($source)) {
      $drush_rebuild->source = $source;
      $data->source = $source;
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
      $drush_rebuild->backupEnvironment();
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
