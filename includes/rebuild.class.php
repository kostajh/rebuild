<?php

/**
 * @file
 * Contains methods for Drush Rebuild.
 */

define('DRUSH_REBUILD_EXTENSION', str_replace('/includes', '', __DIR__));
if (file_exists(DRUSH_REBUILD_EXTENSION . '/vendor/autoload.php')) {
  require_once DRUSH_REBUILD_EXTENSION . '/vendor/autoload.php';
}

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

  public static $rebuildConfig = array();
  public static $rebuildEnvironment = array();
  public static $dryRun = FALSE;


  /**
   * Wrapper around parent::drushInvokeProcess().
   */
  public function drushInvokeProcess($site_alias_record, $command_name, $commandline_args = array(), $commandline_options = array(), $backend_options = NULL) {
    $options = array();
    if (is_array($backend_options)) {
      $options = $backend_options;
      $backend_options = array_merge($this->drushInvokeProcessBackendOptions(), $options);
    }
    else {
      $backend_options = $this->drushInvokeProcessBackendOptions();
    }
    $commandline_options = array_merge($this->drushInvokeProcessOptions(), $commandline_options);
    $drush_path = DRUSH_COMMAND;
    // Pass in global options. When a user executes `drush rebuild`, they may
    // have passed `--debug` or `--alias-path` options, etc, this needs to get
    // passed on to the next command.
    // TODO: Surely there is a better way.
    $global_options = drush_get_merged_options();
    unset(
      $global_options['rsync-version'],
      $global_options['context-path'],
      $global_options['php-options'],
      $global_options['notify'],
      $global_options['#name'],
      $global_options['#file'],
      $global_options['#group'],
      $global_options['#loaded-config'],
      $global_options['db-url']
    );

    $commandline_options = array_merge($global_options, $commandline_options);
    $commandline_options['strict'] = '0';
    foreach ($commandline_options as $key => $value) {
      if (!is_array($value)) {
        $options[] = sprintf('--%s=%s', $key, $value);
      }
    }

    // Not all commands should have the alias name.
    $target = ' ';
    if ($backend_options['dispatch-using-alias']) {
      // Check if we were passed a complete site alias record.
      if (isset($site_alias_record) && is_array($site_alias_record) && isset($site_alias_record['#name'])) {
        $target = ' @' . $site_alias_record['#name'];
      }
      elseif (isset($site_alias_record)) {
        $target = ' ' . $site_alias_record;
      }
      else {
        $target = ' ' . self::$rebuildConfig['general']['target'];
      }
    }
    $cmd = sprintf('%s%s %s %s %s --backend', $drush_path, $target, $command_name, implode(' ', $commandline_args), implode(' ', $options));
    // Dry run, just print command and return.
    if ($this->getDryRun() == TRUE) {
      return drush_log(dt('DRY RUN: !cmd', array('!cmd' => $cmd)), 'ok');
    }
    // Run command.
    $ret = drush_shell_exec($cmd);
    $output = drush_shell_exec_output();
    $backend_output = array_pop($output);
    $parsed = drush_backend_parse_output($backend_output);

    if (!$ret) {
      drush_print();
      if (isset($parsed['error_log'])) {
        foreach ($parsed['error_log'] as $type => $message) {
          drush_set_error(sprintf('%s', $type), dt('!type: !msg', array('!type' => $type, '!msg' => array_shift($message))));
        }
      }
      else {
        // Sometimes we don't get output that is parsed properly.
        drush_set_error(dt('!msg', array('!msg' => trim(array_values($backend_output)))));
        $parsed = $backend_output;
      }
      drush_set_error(dt('Drush Rebuild encountered an error while running command "!cmd".', array('!cmd' => $cmd)));
      // Write to the error log.
      drush_cache_set(str_replace('@', '', self::$rebuildConfig['general']['target']), $parsed, 'rebuild-error-log', DRUSH_CACHE_PERMANENT);
      drush_print();
      drush_die(dt('To view the errors from the rebuild, run `drush !alias env-rebuild-error-log`', array('!alias' => self::$rebuildConfig['general']['target'])));
    }
    return TRUE;
  }

  /**
   * Return the rebuild configuration.
   * @return array
   *   The rebuild configuration if set or FALSE otherwise.
   */
  public function getConfig() {
    return (self::$rebuildConfig) ? self::$rebuildConfig : FALSE;
  }

  /**
   * Returns options that should be used for all drushInvokeProcess() calls.
   */
  public function drushInvokeProcessOptions() {
    return array(
      'yes' => TRUE,
      'quiet' => TRUE,
    );
  }

  /**
   * Returns default backend options for drushInvokeProcess().
   */
  public function drushInvokeProcessBackendOptions() {
    return array(
      'dispatch-using-alias' => TRUE,
      'integrate' => FALSE,
      'interactive' => FALSE,
      'backend' => 0,
    );
  }

  /**
   * Return the rebuild environment.
   * @return array
   *   The rebuild environment if set or FALSE otherwise.
   */
  public function getEnvironment() {
    return (self::$rebuildEnvironment) ? self::$rebuildEnvironment : FALSE;
  }

  /**
   * Set the rebuild environment in memory.
   *
   * @param array $environment
   *   The rebuild environment array.
   */
  protected function setEnvironment($environment) {
    self::$rebuildEnvironment = $environment;
  }

  /**
   * Set the rebuild configuration in memory.
   *
   * @param array $config
   *   The rebuild configuration array.
   */
  public function setConfig($config) {
    self::$rebuildConfig = $config;
  }

  /**
   * Setter for DryRun.
   */
  public function setDryRun() {
    self::$dryRun = TRUE;
  }

  /**
   * Getter for DryRun.
   */
  public function getDryRun() {
    return self::$dryRun;
  }

  /**
   * Constructor.
   *
   * @param string $alias
   *   The alias of the environment to be rebuilt.
   */
  public function __construct($alias) {
    $this->target = '@' . $alias['#name'];
    $env = drush_sitealias_get_record($this->target);
    $this->environment = $env;
    $this->setEnvironment($env);
  }

  /**
   * Handles rebuilding local environment.
   */
  public function rebuild() {
    $rebuilder = new Rebuilder();
    if (!$rebuilder->execute()) {
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
    define('DRUSH_REBUILD_VERSION', $drush_rebuild_info['version']);
    return DRUSH_REBUILD_VERSION;
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
    $rebuild_config = $this->getConfig();
    // Check if the overrides file is defined as a full path.
    if (file_exists($rebuild_config['general']['overrides'])) {
      return $rebuild_config['general']['overrides'];
    }
    // If not a full path, check if it is in the same directory with the main
    // rebuild mainfest.
    $rebuild_config_path = $this->environment['path-aliases']['%rebuild'];
    // Get directory of rebuild.info
    $config_directory = str_replace(basename($this->environment['path-aliases']['%rebuild']), '', $rebuild_config_path);
    if (file_exists($config_directory . '/' . $rebuild_config['general']['overrides'])) {
      return $config_directory . '/' . $rebuild_config['general']['overrides'];
    }
    // Could not find the file, return FALSE.
    return FALSE;
  }

  /**
   * Sets overrides for the rebuild config.
   *
   * @param array $rebuild_config
   *   The rebuild config, loaded as an array.
   *
   * @return bool
   *   TRUE on success; FALSE on error.
   */
  protected function setConfigOverrides(&$rebuild_config) {
    if (!isset($rebuild_config['general']['overrides'])) {
      return TRUE;
    }
    if ($overrides_path = $this->getConfigOverridesPath()) {
      $yaml = new Parser();
      if ($config_overrides = $yaml->parse(file_get_contents($overrides_path))) {
        drush_log(dt('Loading config overrides from !file', array('!file' => $rebuild_config['general']['overrides'])), 'success');
        $rebuild_config = array_merge_recursive($rebuild_config, $config_overrides);
        drush_log(dt('%overrides', array(
          '%overrides' => file_get_contents($overrides_path))), 'success');
        $this->setConfig($rebuild_config);
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
      $example_alias = file_get_contents(drush_server_home() . '/.drush/rebuild/examples/example.drebuild.aliases.drushrc.php');
      if (drush_confirm('Would you like to view the example Drush rebuild alias for tips on how to configure your alias?')) {
        drush_set_error(dt('Please review the example alias and documentation on how to configure your alias for Drush Rebuild: !example',
        array('!example' => $example_alias)));
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
      $config['general']['target'] = $this->target;
      drush_log(dt('Loading the rebuild config for !site', array('!site' => $this->target)), 'success');
      drush_log(dt('- Docroot: !path', array('!path' => $this->environment['root'])), 'ok');
      if (isset($config['general']['description'])) {
        drush_log(dt('- Description: !desc', array('!desc' => $config['general']['description'])), 'ok');
      }
      if (isset($config['general']['version'])) {
        drush_log(dt('- Config Version: !version', array('!version' => $config['general']['version'])), 'ok');
      }
      if (isset($config['general']['authors'])) {
        drush_log(dt('- Author(s): !authors', array('!authors' => implode(",", $config['general']['authors']))), 'ok');
      }
      drush_print();
      // Load overrides.
      $this->setConfig($config);
      $this->setConfigOverrides($config);
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
    $archive_dump = $this->drushInvokeProcess($this->target, 'archive-dump');
    $backup_path = $archive_dump['object'];
    if (!file_exists($backup_path)) {
      if (!drush_confirm(dt('Backing up your development environment failed. Are you sure you want to continue?'))) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Check if the source specified is a valid Drush alias.
   */
  public function isValidSource($source) {
    // Check if target is the same as the source.
    if ($source == $this->target) {
      return drush_set_error(dt('You cannot use the local alias as the source for a rebuild.'));
    }
    // Check that we can connect to the source.
    drush_log(dt('Checking that we can access the SQL database for !site', array('!site' => $source)), 'ok');
    $this->drushInvokeProcess($source, 'sql-query', array('"DESC system"'));
    drush_log(dt('Established connection with SQL database for !site!', array('!site' => $source)), 'success');
    return drush_sitealias_get_record($source) ? TRUE : drush_set_error(dt('Could not load an alias for !source!', array('!source' => $source)));
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
    return TRUE;
  }
}
