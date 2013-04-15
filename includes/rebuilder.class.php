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
    $this->config = $drush_rebuild->config;
    $this->target = $drush_rebuild->target;
    $this->source = $drush_rebuild->source;
    $this->description = $drush_rebuild->config['description'];
    $this->version = $drush_rebuild->config['version'];
    $this->pre_process = isset($drush_rebuild->config['pre_process']) ? $drush_rebuild->config['pre_process'] : NULL;
    $this->post_process = isset($drush_rebuild->config['post_process']) ? $drush_rebuild->config['post_process'] : NULL;
    if ($this->config['site_install']) {
      $this->profile = $this->config['site_install']['profile'];
      $this->site_install_options = $this->config['site_install'];
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

    if (isset($drush_rebuild->config['sql_sync'])) {
      // @TODO - Add validation of options.
      $this->sql_sync_options = $drush_rebuild->config['sql_sync'];
    }
    if (isset($this->config['rsync']['files_only'])) {
      $this->rsync['files_only'] = $drush_rebuild->config['rsync']['files_only'];
    }
    if (isset($drush_rebuild->config['variables'])) {
      $this->variables = $drush_rebuild->config['variables'];
    }
    if (isset($drush_rebuild->config['uli'])) {
      $this->uli = $drush_rebuild->config['uli'];
    }
    if (isset($drush_rebuild->config['modules_enable'])) {
      $this->modules_enable = $drush_rebuild->config['modules_enable'];
    }
    if (isset($drush_rebuild->config['modules_disable'])) {
      $this->modules_disable = $drush_rebuild->config['modules_disable'];
    }
    if (isset($drush_rebuild->config['permissions_grant'])) {
      $this->permissions_grant = $drush_rebuild->config['permissions_grant'];
    }
    if (isset($drush_rebuild->config['permissions_revoke'])) {
      $this->permissions_revoke = $drush_rebuild->config['permissions_revoke'];
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
    $permissions = new Permissions($this);
    if (!$permissions->execute('grant')) {
      return FALSE;
    }
    if (!$permissions->execute('revoke')) {
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
