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
    // TODO: This needs a lot of work.
    $this->environment = $drush_rebuild->environment;
    $this->config = $drush_rebuild->getConfig();
    $this->target = $drush_rebuild->target;
    $this->source = $drush_rebuild->source;
    $this->description = $this->config['description'];
    $this->version = $this->config['version'];
    $this->pre_process = isset($this->config['pre_process']) ? $this->config['pre_process'] : NULL;
    $this->post_process = isset($this->config['post_process']) ? $this->config['post_process'] : NULL;
    if ($this->config['site_install']) {
      $this->profile = $this->config['site_install']['profile'];
      $this->site_install_options = $this->config['site_install'];
      // Unset the profile from the options group.
      unset($this->site_install_options['profile']);
      // Swap placeholder values.
      foreach ($this->site_install_options as $key => &$value) {
        // If the value starts with "%" then we are referencing a variable
        // defined in the Drush alias.
        if (strpos($value, '%') === 0) {
          if (isset($this->environment['rebuild'][substr($value, 1)])) {
            $value = $this->environment['rebuild'][substr($value, 1)];
          }
          else {
            drush_set_error(dt('Attempted to reference an undefined variable in your Drush alias.'));
            continue;
          }
        }
      }
    }

    if (isset($this->config['sql_sync'])) {
      // @TODO - Add validation of options.
      $this->sql_sync_options = $this->config['sql_sync'];
    }
    if (isset($this->config['pan_sql_sync'])) {
      // @TODO - Add validation of options.
      $this->pan_sql_sync_options = $this->config['pan_sql_sync'];
    }
    if (isset($this->config['rsync']['files_only'])) {
      $this->rsync['files_only'] = $this->config['rsync']['files_only'];
    }
    if (isset($this->config['drupal']['variables'])) {
      $this->variables = $this->config['drupal']['variables'];
    }
    if (isset($this->config['uli'])) {
      $this->uli = $this->config['uli'];
    }
    if (isset($this->config['modules_enable'])) {
      $this->modules_enable = $this->config['modules_enable'];
    }
    if (isset($this->config['modules_disable'])) {
      $this->modules_disable = $this->config['modules_disable'];
    }
    if (isset($this->config['permissions_grant'])) {
      $this->permissions_grant = $this->config['permissions_grant'];
    }
    if (isset($this->config['permissions_revoke'])) {
      $this->permissions_revoke = $this->config['permissions_revoke'];
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
      if (isset($this->config['pan_sql_sync'])) {
        $pan_sql_sync = new PanSqlSync($this);
        if (!$pan_sql_sync->execute()) {
          return FALSE;
        }
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
    $post_process = new DrushScript($this, 'post_process');
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
