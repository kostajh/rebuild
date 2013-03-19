<?php

/**
 * @file
 * Contains methods for Drush Rebuild.
 */

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
 *  $manifest - The rebuild.info file for the target alias, loaded into an
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
    drush_print(dt("Drush Rebuild version: !version", array('!version' => DRUSH_REBUILD_VERSION)));
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
  public function viewManifest() {
    drush_log(dt('Loading manifest at !path', array('!path' => $this->environment['path-aliases']['%rebuild'])), 'success');
    drush_print();
    drush_print_file($this->environment['path-aliases']['%rebuild']);
  }

  /**
   * Returns the path the manifest overrides file.
   *
   * @return string
   *   Return a string containing the path to the manifest overrides file, or
   *   FALSE if the file could not be found.
   */
  protected function getManifestOverridesPath() {
    $rebuild_manifest = $this->manifest;
    // Check if the overrides file is defined as a full path.
    if (file_exists($rebuild_manifest['overrides'])) {
      return $rebuild_manifest['overrides'];
    }
    // If not a full path, check if it is in the same directory with the main
    // rebuild mainfest.
    $rebuild_manifest_path = $this->environment['path-aliases']['%rebuild'];
    // Get directory of rebuild.info
    $rebuild_manifest_directory = str_replace('rebuild.info', '', $rebuild_manifest_path);
    if (file_exists($rebuild_manifest_directory . $rebuild_manifest['overrides'])) {
      return $rebuild_manifest_directory . $rebuild_manifest['overrides'];
    }
    // Return false if other checks have failed.
    return drush_set_error(dt('Could not load the overrides file at path !path', array('!path' => $rebuild_manifest['overrides'])));
  }

  /**
   * Sets overrides for the rebuild manifest.
   *
   * @param array $rebuild_manifest
   *   The rebuild manifest, loaded as an array.
   */
  protected function setManifestOverrides(&$rebuild_manifest) {
    if ($rebuild_manifest_overrides = parse_ini_file($this->getManifestOverridesPath())) {
      drush_log(dt('Loading manifest overrides from !file', array('!file' => $rebuild_manifest['overrides'])), 'success');
      foreach ($rebuild_manifest_overrides as $key => $override) {
        if (is_array($override)) {
          foreach ($override as $k => $v) {
            $rebuild_manifest[$key][$k] = $v;
            $this->manifest[$key][$k] = $v;
            drush_log(dt('- Overriding !parent[!key] with value !override', array(
              '!parent' => $key,
              '!key' => $k,
              '!override' => $v,
                )
              ), 'success'
            );
          }
        }
        else {
          $this->manifest[$key] = $override;
          $rebuild_manifest[$key] = $override;
          drush_log(dt('- Overriding "!key" with value !override', array(
              '!key' => $key,
              '!override' => $override,
              )
            ), 'success'
          );
        }

      }
      drush_print();
    }
    else {
      return drush_set_error(dt('Failed to load overrides file.'));
    }
  }

  /**
   * Load the rebuild info manifest.
   *
   * @return array
   *   An array generated by parsing the rebuild info file.
   */
  public function loadManifest() {
    // Check if we can load the local tasks file.
    if (!isset($this->environment['path-aliases']['%rebuild'])) {
      return drush_set_error(dt('Please add a %rebuild entry to the path-aliases section of the Drush alias for !name', array('!name' => $this->target)));
    }
    // Check if the file exists.
    $rebuild_manifest_path = $this->environment['path-aliases']['%rebuild'];
    if (!file_exists($rebuild_manifest_path)) {
      return drush_set_error(dt('Could not load rebuild.info file at !path', array('!path' => $rebuild_manifest_path)));
    }
    if ($rebuild_manifest = parse_ini_file($rebuild_manifest_path)) {
      $this->manifest = $rebuild_manifest;
      drush_log(dt('Loaded the rebuild manifest for !site', array('!site' => $this->target)), 'success');
      drush_log(dt('- Docroot: !path', array('!path' => $this->environment['root'])), 'ok');
      if (isset($rebuild_manifest['description'])) {
        drush_log(dt('- Description: !desc', array('!desc' => $rebuild_manifest['description'])), 'ok');
      }
      if (isset($rebuild_manifest['version'])) {
        drush_log(dt('- Version: !version', array('!version' => $rebuild_manifest['version'])), 'ok');
      }
      drush_print();
      // Set overrides.
      if (isset($rebuild_manifest['overrides'])) {
        $this->setManifestOverrides($rebuild_manifest);
      }
      $this->manifest = $rebuild_manifest;
      return $rebuild_manifest;
    }
    else {
      return drush_set_error(dt('Could not load the info file. Make sure your rebuild.info file is valid INI format.'));
    }
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
    drush_print();
    drush_log(dt("Rebuild info for !name:\n- Environment was last rebuilt on !date.\n- Average time for a rebuild is !min minutes and !sec seconds.\n- Environment has been rebuilt !count time(s).\n!source",
        array(
          '!name' => $data->cid, '!date' => date(DATE_RFC822, $data->data['last_rebuild']),
          '!min' => date('i', $average_time),
          '!sec' => date('s', $average_time),
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
    drush_log('Checking if source alias is valid', 'ok');
    $alias_name = drush_invoke_process($this->environment, 'site-alias', array($source), array('short' => TRUE));
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
          '!file' => $this->environment['path-aliases']['%local-tasks'],
          '!alias' => $this->environment['#file'])), 'ok');
      if (drush_confirm('Are you sure you want to continue?')) {
        $ret = new DrushScript($this, 'legacy', $this->environment['path-aliases']['%local-tasks'] . '/tasks.php');
        return TRUE;
      }
      else {
        drush_die();
      }
    }
    return TRUE;
  }

}
