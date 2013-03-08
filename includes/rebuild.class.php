<?php

/**
 * @file
 * Contains methods for Drush Rebuild.
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
  }

  /**
   * Handles rebuilding local environment.
   */
  public function rebuild() {
    $rebuilder = new Rebuilder($this);
    if (!$rebuilder->start()) {
      return FALSE;
    }
    return $this->verifyCompletedRebuild();
  }

  /**
   * Called for `drush rebuild version` or `drush rebuild --version`.
   */
  public function getVersion() {
    $drush_info_file = dirname(__FILE__) . '/../rebuild.info';
    $drush_rebuild_info = parse_ini_file($drush_info_file);
    define('DRUSH_REBUILD_VERSION', $drush_rebuild_info['drush_rebuild_version']);
    drush_print(dt("drush rebuild version: !version", array('!version' => DRUSH_REBUILD_VERSION)));
  }

  /**
   * Load the Drush site alias based on a the alias name.
   *
   * @param string $alias_name
   *   The site alias name.
   *
   * @return array
   *   The Drush environment array for the provided alias name.
   */
  public function loadEnvironment($alias_name) {
    if (!$alias_name) {
      // Enforce the syntax. `drush rebuild @target --source=@source`.
      return drush_set_error(dt('You must specify a drush alias with the rebuild command.'));
    }
    $env = drush_sitealias_get_record($alias_name);
    if (!$env) {
      return drush_set_error(dt('Failed to load site alias for !name', array('!name' => $alias_name)));
    }
    $this->environment = $env;
    return $env;
  }

  /**
   * View the rebuild info file.
   */
  public function viewManifest() {
    $env = $this->environment;
    $rebuild_manifest = $env['path-aliases']['%rebuild'];
    drush_log(dt('Loading manifest at !path', array('!path' => $rebuild_manifest)), 'success');
    drush_print();
    drush_print_file($rebuild_manifest);
  }

  /**
   * Load the rebuild info manifest.
   *
   * @return array
   *   An array generated by parsing the rebuild info file.
   */
  public function loadManifest() {
    $env = $this->environment;
    // Check if we can load the local tasks file.
    if (!isset($env['path-aliases']['%rebuild'])) {
      return drush_set_error(dt('Please add a %rebuild entry to the path-aliases section of the Drush alias for !name', array('!name' => $alias_name)));
    }
    // Check if the file exists.
    $rebuild_manifest_path = $env['path-aliases']['%rebuild'];
    if (!file_exists($rebuild_manifest_path)) {
      return drush_set_error(dt('Could not load rebuild.info file at !path', array('!path' => $rebuild_manifest_path)));
    }
    if ($rebuild_manifest = parse_ini_file($rebuild_manifest_path)) {
      $this->manifest = $rebuild_manifest;
      return $rebuild_manifest;
    }
    else {
      drush_set_error(dt('Could not load the info file. Make sure your rebuild.info file is valid INI format.'));
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
    if (!$data->data['last_rebuild']) {
      return;
    }
    // Display time of last rebuild and average time for rebuilding site.
    $average_time = array_sum($data->data['rebuild_times']) / count($data->data['rebuild_times']);
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
    $alias_name = $this->target;
    $archive_dump = drush_invoke_process($alias_name, 'archive-dump');
    $backup_path = $archive_dump['object'];
    if (!file_exists($backup_path)) {
      if (!drush_confirm(dt('Backing up your development environment failed. Are you sure you want to continue?'))) {
        return;
      }
    }
  }

  /**
   * Check requirements before rebuilding.
   */
  public function checkRequirements() {

  }

  /**
   * Verifies a completed rebuild.
   */
  public function verifyCompletedRebuild() {
    // Check to see if we can bootstrap to the site.
    return TRUE;
  }

}

/**
 * Handles the work of rebuilding.
 */
class Rebuilder extends DrushRebuild {

  /**
   * Constructor.
   */
  public function __construct(DrushRebuild $drush_rebuild) {
    $this->environment = $drush_rebuild->environment;
    $this->target = $drush_rebuild->target;
    $this->source = $drush_rebuild->source;
    $this->description = $drush_rebuild->manifest['description'];
    $this->type = $drush_rebuild->manifest['type'];
    $this->version = $drush_rebuild->manifest['version'];
    $this->remotes = isset($drush_rebuild->manifest['remotes']) ? $drush_rebuild->manifest['remotes'] : NULL;
    $this->pre_process = isset($drush_rebuild->manifest['pre_process']) ? $drush_rebuild->manifest['pre_process'] : NULL;
    $this->post_process = isset($drush_rebuild->manifest['post_process']) ? $drush_rebuild->manifest['post_process'] : NULL;
    if ($this->remotes) {
      $sql_sync_options = array();
      if (isset($manifest['sql_sync'])) {
        $sql_sync_options_raw = $manifest['sql_sync'];
        $sql_sync_options = array();
        foreach ($sql_sync_options_raw as $key => $value) {
          if (is_int($key)) {
            $sql_sync_options[] = '--' . $value;
          }
          else {
            $sql_sync_options[] = '--' . $key . '=' . $value;
          }
        }
      }
      $this->sql_sync_options = $sql_sync_options;

      if (isset($manifest['rsync']['type'])) {
        // Two types supported: files only, or entire directory.
        $this->rsync_type = $drush_rebuild->manifest['rsync']['type'];
      }
    }
    if (isset($drush_rebuild->manifest['variables'])) {
      $this->variables = $drush_rebuild->manifest['variables'];
    }
    if (isset($drush_rebuild->manifest['uli'])) {
      $this->uli = $drush_rebuild->manifest['uli'];
    }
    if (isset($drush_rebuild->manifest['modules_enable'])) {
      $this->modules_enable = $drush_rebuild->manifest['modules_enable'];
    }
    if (isset($drush_rebuild->manifest['modules_disable'])) {
      $this->modules_disable = $drush_rebuild->manifest['modules_disable'];
    }
  }

  /**
   * Start the rebuild.
   */
  public function start() {
    $pre_process = new DrushScript($this, 'pre_process');
    if (!$pre_process->start()) {
      return FALSE;
    }
    $sql_sync = new SqlSync($this);
    if (!$sql_sync->start()) {
      return FALSE;
    }
    $variable = new Variable($this);
    if (!$variable->Set()) {
      return FALSE;
    }
    $module = new Module($this);
    if (!$module->start('enable')) {
      return FALSE;
    }
    if (!$module->start('disable')) {
      return FALSE;
    }
    $post_process = new DrushScript($this, 'pre_process');
    if (!$post_process->start($this, 'post_process')) {
      return FALSE;
    }
    $uli = new UserLogin($this);
    if (!$uli->start()) {
      return FALSE;
    }
    return TRUE;
  }

}
