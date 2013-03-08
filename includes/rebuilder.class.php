<?php

/**
 * @file
 * Rebuilder class code.
 */

/**
 * Handles the work of rebuilding.
 */
class Rebuilder extends DrushRebuild {

  /**
   * Constructor.
   *
   * @param DrushRebuild $drush_rebuild
   *   The Drush Rebuild class object.
   */
  public function __construct(DrushRebuild $drush_rebuild) {
    $this->environment = $drush_rebuild->environment;
    $this->manifest = $drush_rebuild->manifest;
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
