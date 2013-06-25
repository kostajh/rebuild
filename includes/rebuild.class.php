<?php

/**
 * @file
 * Contains methods for Drush Rebuild.
 */

use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * The main Drush Rebuild class.
 *
 * Terminology:
 *
 *  $target - The alias name (e.g. @mysite.local) for the environment that will
 *            be rebuilt.
 *  $source - The alias name (e.g. @mysite.prod) for the environment that will
 *            be the source data for the rebuild.
 *  $environment - The fully loaded site environment returned by
 *                 drush_sitealias_get_record().
 *  $config - The rebuild.info file for the target alias, loaded into an
 *              array.
 */
class DrushRebuild {

  /**
   * Constructor.
   *
   * @param string $target
   *   The alias of the environment to be rebuilt.
   */
  public function __construct($target) {
    $this->target = $target;
    $this->environment = $this->loadEnvironment($target);
  }

  /**
   * Handles rebuilding local environment.
   */
  public function rebuild() {
    $rebuilder = new Rebuilder($this);
    if (!$rebuilder->start()) {
      return FALSE;
    }
    $diagnostics = new Diagnostics($this);
    return $diagnostics->verifyCompletedRebuild();
  }

  /**
   * Outputs rebuild information for the alias loaded in the environment.
   */
  public function getInfo() {
    $data = $this->loadMetadata();
    if (!$data->data['last_rebuild']) {
      drush_log(dt('There isn\'t any rebuild info to display for !name', array('!name' => $this->target)), 'error');
    }
    else {
      $this->showMetadata();
    }
  }

  /**
   * Called for `drush rebuild version` or `drush rebuild --version`.
   */
  public function getVersion() {
    $drush_info_file = dirname(__FILE__) . '/../rebuild.info';
    $drush_rebuild_info = parse_ini_file($drush_info_file);
    define('DRUSH_REBUILD_VERSION', $drush_rebuild_info['drush_rebuild_version']);
    return DRUSH_REBUILD_VERSION;
  }

  /**
   * Load the Drush site alias based on a the alias name.
   *
   * @param string $target
   *   The site alias name.
   *
   * @return array
   *   The Drush environment array for the provided alias name.
   */
  public function loadEnvironment($target) {
    // If we are just loading the version, return.
    if (drush_get_option('version')) {
      return;
    }
    if (!$target) {
      // Enforce the syntax. `drush rebuild @target --source=@source`.
      return drush_set_error(dt('You must specify a drush alias with the rebuild command.'));
    }
    $env = drush_sitealias_get_record($target);
    if (!$env) {
      return drush_set_error(dt('Failed to load site alias for !name', array('!name' => $target)));
    }
    $this->environment = $env;
    return $env;
  }

  /**
   * View the rebuild info file.
   */
  public function viewConfig() {
    drush_log(dt('Loading config at !path', array('!path' => $this->environment['path-aliases']['%rebuild'])), 'success');
    drush_print();
    drush_print_file($this->environment['path-aliases']['%rebuild']);
  }

  /**
   * Returns the path the config overrides file.
   *
   * @return string
   *   Return a string containing the path to the config overrides file, or
   *   FALSE if the file could not be found.
   */
  protected function getConfigOverridesPath() {
    $rebuild_config = $this->config;
    // Check if the overrides file is defined as a full path.
    if (file_exists($rebuild_config['overrides'])) {
      return $rebuild_config['overrides'];
    }
    // If not a full path, check if it is in the same directory with the main
    // rebuild mainfest.
    $rebuild_config_path = $this->environment['path-aliases']['%rebuild'];
    // Get directory of rebuild.info
    $rebuild_config_directory = str_replace(basename($this->environment['path-aliases']['%rebuild']), '', $rebuild_config_path);
    if (file_exists($rebuild_config_directory . '/' . $rebuild_config['overrides'])) {
      return $rebuild_config_directory . '/' . $rebuild_config['overrides'];
    }
    // Could not find the file, return FALSE.
    return FALSE;
  }

  /**
   * Sets overrides for the rebuild config.
   *
   * @param array $rebuild_config
   *   The rebuild config, loaded as an array.
   */
  protected function setConfigOverrides(&$rebuild_config) {
    if ($overrides_path = $this->getConfigOverridesPath()) {
      $yaml = new Parser();
      if ($rebuild_config_overrides = $yaml->parse(file_get_contents($overrides_path))) {
        drush_log(dt('Loading config overrides from !file', array('!file' => $rebuild_config['overrides'])), 'success');
        foreach ($rebuild_config_overrides as $key => $override) {
          if (is_array($override)) {
            foreach ($override as $k => $v) {
              $rebuild_config[$key][$k] = $v;
              $this->config[$key][$k] = $v;
              drush_log(dt('- Overriding "!parent[!key]" with value "!override"', array(
                '!parent' => $key,
                '!key' => $k,
                '!override' => $v,
                  )
                ), 'success'
              );
            }
          }
          else {
            $this->config[$key] = $override;
            $rebuild_config[$key] = $override;
            drush_log(dt('- Overriding "!key" with value "!override"', array(
                '!key' => $key,
                '!override' => $override,
                )
              ), 'success'
            );
          }

        }
        drush_print();
        return TRUE;
      }
      else {
        return drush_set_error(dt('Failed to load overrides file! Check that it is valid YAML format.'));
      }
    }
    else {
      return drush_set_error(dt('Could not load the overrides file.'));
    }
  }

  /**
   * Load the rebuild info config.
   *
   * @return array
   *   An array generated by parsing the rebuild info file.
   */
  public function loadConfig() {
    // Check if we can load the local tasks file.
    if (!isset($this->environment['path-aliases']['%rebuild'])) {
      drush_set_error(dt('Your Drush alias is not properly configured for Drush Rebuild!'));
      drush_set_error(dt('Please add a %rebuild entry to the path-aliases section of the Drush alias for !name', array('!name' => $this->target)));
      if (drush_confirm('Would you like to view the example Drush rebuild alias for tips on how to configure your alias?')) {
        drush_set_error(dt('Please review the example alias and documentation on how to configure your alias for Drush Rebuild: !example',
        array('!example' => drush_print_file(drush_server_home() . '/.drush/rebuild/examples/example.drebuild.aliases.drushrc.php'))));
      }
      return FALSE;
    }
    // Check if the file exists.
    $rebuild_config_path = $this->environment['path-aliases']['%rebuild'];
    if (!file_exists($rebuild_config_path)) {
      return drush_set_error(dt('Could not load the config file at !path', array('!path' => $rebuild_config_path)));
    }

    // Check if file is YAML format.
    $yaml = new Parser();
    try {
      $config = $yaml->parse(file_get_contents($rebuild_config_path));
      // We need to make a few adjustments to the config to match the expected
      // structure from parsing an INI file above.
      // @TODO this is quite ugly and should be refactored.
      $config['description'] = $config['general']['description'];
      $config['version'] = $config['general']['version'];
      $config['uli'] = $config['general']['uli'];
      $config['overrides'] = $config['general']['overrides'];
      $config['pre_process'] = $config['drush_scripts']['pre_process'];
      $config['post_process'] = $config['drush_scripts']['post_process'];
      $config['variables'] = $config['drupal']['variables']['set'];
      $config['modules_enable'] = $config['drupal']['modules']['enable'];
      $config['modules_disable'] = $config['drupal']['modules']['disable'];
      $config['permissions_grant'] = array();
      $config['permissions_revoke'] = array();
      if (isset($config['drupal']['permissions'])) {
        foreach ($config['drupal']['permissions'] as $role => $permissions) {
          if (isset($permissions['grant'])) {
            $config['permissions_grant'][$role] = implode(", ", $permissions['grant']);
          }
          if (isset($permissions['revoke'])) {
            $config['permissions_revoke'][$role] = implode(", ", $permissions['revoke']);
          }
        }
      }
      if (isset($config['sync'])) {
        if (isset($config['sync']['default_source'])) {
          $config['default_source'] = $config['sync']['default_source'];
        }
        if (isset($config['sync']['sql_sync'])) {
          $config['sql_sync'] = $config['sync']['sql_sync'];
        }
        
        if (isset($config['sync']['pan_sql_sync'])) {
          $config['pan_sql_sync'] = $config['sync']['pan_sql_sync'];
        }
        if (isset($config['sync']['rsync'])) {
          $config['rsync'] = $config['sync']['rsync'];
        }
      }
      drush_log(dt('Loading the rebuild config for !site', array('!site' => $this->target)), 'success');
      drush_log(dt('- Docroot: !path', array('!path' => $this->environment['root'])), 'ok');
      if (isset($config['description'])) {
        drush_log(dt('- Description: !desc', array('!desc' => $config['description'])), 'ok');
      }
      if (isset($config['version'])) {
        drush_log(dt('- Config Version: !version', array('!version' => $config['version'])), 'ok');
      }
      if (isset($config['general']['authors'])) {
        drush_log(dt('- Author(s): !authors', array('!authors' => implode(",", $config['general']['authors']))), 'ok');
      }
      drush_print();
      $this->config = $config;
      // Load overrides.
      $this->setConfigOverrides($this->config);
      return $config;
    }
    catch (ParseException $e) {
      drush_set_error(dt("Unable to parse the YAML string: %s", array('%s' => $e->getMessage())));
    }
    return TRUE;
  }

  /**
   * Loads rebuild meta-data for an alias.
   *
   * If no data is found, a new entry is added to the data file.
   *
   * @return array
   *   An array of rebuild meta-data for a given alias.
   */
  public function loadMetadata() {
    $alias = $this->target;
    $data = drush_cache_get($alias, 'rebuild');
    if (!$data) {
      $data = array(
        'last_rebuild' => NULL,
        'rebuild_times' => NULL,
      );
      return drush_cache_set($alias, $data, 'rebuild', DRUSH_CACHE_PERMANENT);
    }
    $this->metadata = $data;
    return $data;
  }

  /**
   * Displays rebuild data for the alias.
   */
  public function showMetadata() {
    $data = $this->metadata;
    if (!isset($data->data['last_rebuild'])) {
      return;
    }
    // Display time of last rebuild and average time for rebuilding site.
    $average_time = array_sum($data->data['rebuild_times']) / count($data->data['rebuild_times']);
    drush_log(dt("Rebuild info for !name:\n- Environment was last rebuilt on !date.\n- Average time for a rebuild is !min minutes and !sec seconds.\n- Environment has been rebuilt !count time(s).\n!source",
        array(
          '!name' => $data->cid, '!date' => date(DATE_RFC822, $data->data['last_rebuild']),
          '!min' => gmdate('i', $average_time),
          '!sec' => gmdate('s', $average_time),
          '!count' => count($data->data['rebuild_times']),
          '!source' => isset($data->source) ? '- Source for current rebuild: ' . $data->source : NULL,
        )),
          'ok'
        );
  }

  /**
   * Update the meta-data for an alias.
   *
   * Meta-data will be updated with the last date of last rebuild and time
   * elapsed for last rebuild.
   *
   * @param int $total_rebuild_time
   *   The amount of time elapsed in seconds for the rebuild.
   */
  public function updateMetadata($total_rebuild_time) {
    $cache = drush_cache_get($this->target, 'rebuild');
    $rebuild_times = $cache->data['rebuild_times'];
    $rebuild_times[] = $total_rebuild_time;
    $data = array();
    $data['last_rebuild'] = time();
    $data['rebuild_times'] = $rebuild_times;
    drush_cache_set($this->target, $data, 'rebuild', DRUSH_CACHE_PERMANENT);
  }

  /**
   * Backup the local environment using Drush archive-dump.
   */
  public function backupEnvironment() {
    $archive_dump = drush_invoke_process($this->target, 'archive-dump');
    $backup_path = $archive_dump['object'];
    if (!file_exists($backup_path)) {
      if (!drush_confirm(dt('Backing up your development environment failed. Are you sure you want to continue?'))) {
        return;
      }
    }
  }

  /**
   * Check if the source specified is a valid Drush alias.
   */
  public function isValidSource($source) {
    // Check if target is the same as the source.
    if ($source == $this->target) {
      return drush_set_error(dt('You cannot use the local alias as the source for a rebuild.'));
    }
    $alias_name = drush_invoke_process($this->environment, 'site-alias', array($source), array('short' => TRUE), array('integrate' => FALSE));
    if (empty($alias_name['output'])) {
      return drush_set_error(dt('Could not load an alias for !source.', array('!source' => $source)));
    }
    else {
      return TRUE;
    }
  }

  /**
   * Check requirements before rebuilding.
   *
   * If a legacy rebuild file is discovered, allow user to proceed but ask them
   * to upgrade to the latest INI format.
   *
   * @todo Re-organize this functionality.
   */
  public function checkRequirements() {
    $diagnostics = new Diagnostics($this);
    if ($diagnostics->isLegacy()) {
      // Skip other diagnostics checks, execute a rebuild using drush script.
      drush_log(dt("#########################################################\n# WARNING: You are using a legacy Drush Rebuild script. #\n#########################################################\n\nPlease rewrite !file to use the new Drush Rebuild INI format and !alias to reference the new Rebuild file.\nSee `drush rebuild-readme` for more information.",
        array(
          '!file' => $this->environment['path-aliases']['%local-tasks'] . '/tasks.php',
          '!alias' => $this->environment['#file'])), 'ok');
      if (drush_confirm('Are you sure you want to continue?')) {
        $ret = new DrushScript($this, 'legacy', $this->environment['path-aliases']['%local-tasks'] . '/tasks.php');
        return TRUE;
      }
      else {
        drush_die();
      }
    }
    // Prompt to convert INI file to YAML.
    if ($config = $diagnostics->isIni()) {
      if (drush_confirm('Your rebuild config file is written in the PHP INI format. Drush Rebuild now uses YAML for its configuration. Do you want me to convert your config file to YAML?')) {
        // Convert file.
        if ($this->convertIniToYaml($config)) {
          drush_log('Successfully converted your config file to YAML. Make sure you review the changes.', 'success');
          return TRUE;
        }
        else {
          return drush_set_error("An automated attempt to convert your config file to YAML failed.");
        }
      }
      else {
        return drush_set_error('You must convert your config file to YAML format to continue.');
      }
    }
    return TRUE;
  }

  /**
   * Convert an INI config file to YAML.
   *
   * @return bool
   *   Returns TRUE if successful, FALSE otherwise.
   */
  public function convertIniToYaml($config) {
    $dumper = new Dumper();
    // General section.
    $yaml = array();
    if (isset($config['description'])) {
      $yaml['general']['description'] = $config['description'];
    }
    if (isset($config['version'])) {
      $yaml['general']['version'] = $config['version'];
    }
    if (isset($config['uli'])) {
      $yaml['general']['uli'] = $config['uli'];
    }
    if (isset($config['overrides'])) {
      $yaml['general']['overrides'] = $config['overrides'];
    }
    if (isset($config['pre_process'])) {
      $yaml['general']['drush_scripts']['pre_process'] = $config['pre_process'];
    }
    if (isset($config['post_process'])) {
      $yaml['general']['drush_scripts']['post_process'] = $config['post_process'];
    }
    // Sync options.
    if (isset($config['sql_sync'])) {
      $yaml['sync']['sql_sync'] = $config['sql_sync'];
    }
    if (isset($config['pan_sql_sync'])) {
      $yaml['sync']['pan_sql_sync'] = $config['pan_sql_sync'];
    }
    if (isset($config['rsync'])) {
      $yaml['sync']['rsync'] = $config['rsync'];
    }
    if (isset($config['default_source'])) {
      $yaml['sync']['default_source'] = $config['default_source'];
    }
    // Site Install options.
    if (isset($config['site_install'])) {
      $yaml['site_insttall'] = $config['site_install'];
    }
    // Drupal settings.
    $yaml['drupal'] = array();
    if (isset($config['variables'])) {
      $yaml['drupal']['variables']['set'] = $config['variables'];
    }
    if (isset($config['modules_enable'])) {
      $yaml['drupal']['modules']['enable'] = $config['modules_enable'];
    }
    if (isset($config['modules_disable'])) {
      $yaml['drupal']['modules']['disable'] = $config['modules_disable'];
    }
    // Permissions.
    if (isset($config['permissions_grant'])) {
      foreach ($config['permissions_grant'] as $role => $permission_string) {
        $yaml['drupal']['permissions'][$role]['grant'] = $permission_string;
      }
    }
    if (isset($config['permissions_revoke'])) {
      foreach ($config['permissions_revoke'] as $role => $permission_string) {
        $yaml['drupal']['permissions'][$role]['revoke'] = $permission_string;
      }
    }
    // Write to YAML.
    $yaml_config = $dumper->dump($yaml, 5);
    // Overwrite old file.
    file_put_contents($this->environment['path-aliases']['%rebuild'], $yaml_config);
    // FIXME: Add error handling.
    return TRUE;
  }

}
