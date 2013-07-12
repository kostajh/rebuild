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
   */
  public function __construct() {
    $this->config = parent::getConfig();
    $this->environment = parent::getEnvironment();
  }

  /**
   * Rebuild the local environment.
   */
  protected function execute() {
    $pre_process = new DrushScript('pre_process');
    if (!$pre_process->execute()) {
      return FALSE;
    }
    // Run the site-install if defined.
    if (isset($this->config['site_install']['profile'])) {
      $site_install = new SiteInstall();
      if (!$site_install->execute()) {
        return FALSE;
      }
    }
    else {
      // Otherwise use sql sync and rsync commands.
      $sql_sync = new SqlSync();
      if (!$sql_sync->execute()) {
        return FALSE;
      }
      if (isset($this->config['sync']['pan_sql_sync'])) {
        $pan_sql_sync = new PanSqlSync();
        if (!$pan_sql_sync->execute()) {
          return FALSE;
        }
      }
      $rsync = new Rsync();
      if (!$rsync->execute()) {
        return FALSE;
      }
    }

    $variable = new Variable();
    if (!$variable->execute()) {
      return FALSE;
    }
    $modules = new Modules();
    if (!$modules->execute('enable')) {
      return FALSE;
    }
    if (!$modules->execute('disable')) {
      return FALSE;
    }
    $permissions = new Permissions();
    if (!$permissions->execute('grant')) {
      return FALSE;
    }
    if (!$permissions->execute('revoke')) {
      return FALSE;
    }
    $post_process = new DrushScript('post_process');
    if (!$post_process->execute('post_process')) {
      return FALSE;
    }
    $uli = new UserLogin();
    if (!$uli->execute()) {
      return FALSE;
    }
    return TRUE;
  }

}
