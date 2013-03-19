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
    $this->version = $drush_rebuild->manifest['version'];
    $this->remotes = isset($drush_rebuild->manifest['remotes']) ? $drush_rebuild->manifest['remotes'] : NULL;
    $this->pre_process = isset($drush_rebuild->manifest['pre_process']) ? $drush_rebuild->manifest['pre_process'] : NULL;
    $this->post_process = isset($drush_rebuild->manifest['post_process']) ? $drush_rebuild->manifest['post_process'] : NULL;
    if ($this->manifest['site_install']) {
      $this->profile = $this->manifest['site_install']['profile'];
      $this->site_install_options = $this->manifest['site_install'];
      // Unset the profile from the options group.
      unset($this->site_install_options[$this->profile]);
      // Swap placeholder values.
      foreach ($this->site_install_options as $key => &$value) {
        // If the value starts with "%" then we are referencing a variable
        // defined in the Drush alias.
        if (strpos($value, '%') === 0) {
          if (isset($this->environment['rebuild'][substr($value, 1)])) {
            $value = $this->environment['rebuild'][substr($value, 1)];
          }
          else {
            drush_print($value);
            drush_print($key);
            drush_set_error(dt('Attempted to reference an undefined variable in your Drush alias.'));
            continue;
          }
        }
      }
    }
    if ($this->manifest['remotes']) {
      if (isset($drush_rebuild->manifest['sql_sync'])) {
        // @TODO - Add validation of options.
        $this->sql_sync_options = $drush_rebuild->manifest['sql_sync'];
      }
      if (isset($this->manifest['rsync']['files_only'])) {
        $this->rsync['files_only'] = $drush_rebuild->manifest['rsync']['files_only'];
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
   * Rebuild the local environment.
   */
  public function start() {

    $pre_process = new DrushScript($this, 'pre_process');
    if (!$pre_process->execute()) {
      return FALSE;
    }
    // Run the site-install if defined.
    if (isset($this->profile)) {
      drush_print('site install');
      $site_install = new SiteInstall($this);
      if (!$site_install->execute()) {
        return FALSE;
      }
    }
    else {
      // Otherwise use sql sync and rsync commands.
      $sql_sync = new SqlSync($this);
      if (!$sql_sync->execute()) {
        return FALSE;
      }
      $rsync = new Rsync($this);
      if (!$rsync->execute()) {
        return FALSE;
      }
    }
    $variable = new Variable($this);
    if (!$variable->set()) {
      return FALSE;
    }
    $modules = new Modules($this);
    if (!$modules->execute('enable')) {
      return FALSE;
    }
    if (!$modules->execute('disable')) {
      return FALSE;
    }
    $post_process = new DrushScript($this, 'pre_process');
    if (!$post_process->execute($this, 'post_process')) {
      return FALSE;
    }
    $uli = new UserLogin($this);
    if (!$uli->execute()) {
      return FALSE;
    }
    return TRUE;
  }

}
