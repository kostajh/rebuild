<?php

/**
 * @file
 * SQL Sync code.
 */

/**
 * Handles sql-sync component of rebuild.
 */
class SqlSync extends Rebuilder {

  /**
   * Constructor.
   */
  public function __construct() {
    $this->config = parent::getConfig();
    $this->environment = parent::getEnvironment();
  }

  /**
   * Start the sql-sync.
   */
  public function execute() {
    // Execute sql-sync.
    if (isset($this->config['general']['target']) && isset($this->config['sync']['source'])) {
      drush_log('Beginning sql-sync', 'ok');
      drush_log(dt('Syncing database from !source to !target', array('!source' => $this->config['sync']['source'], '!target' => $this->config['general']['target'])), 'ok');
      $ret = parent::drushInvokeProcess($this->environment, 'sql-sync', array($this->config['sync']['source'], $this->config['general']['target']), $this->config['sync']['sql_sync']);
      if ($ret['error_status'] == 0) {
        drush_log(dt('Synced database from !source to !target', array('!source' => $this->config['sync']['source'], '!target' => $this->config['general']['target'])), 'ok');
        return TRUE;
      }
      else {
        return drush_set_error(dt('An error occurred. %error', array('%error' => print_r($ret['error_log'], TRUE))));
      }
    }
    return TRUE;
  }
}
