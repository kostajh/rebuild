<?php

/**
 * @file
 * Pantheon SQL Sync code.
 */

/**
 * Handles pan-sql-sync component of rebuild.
 */
class PanSqlSync extends Rebuilder {

  /**
   * Constructor.
   */
  public function __construct() {
    $this->config = parent::getConfig();
    $this->environment = parent::getEnvironment();
  }

  /**
   * Start the pan-sql-sync.
   */
  protected function execute() {
    // Execute pan-sql-sync.
    if (isset($this->config['general']['target']) && isset($this->config['sync']['source'])) {
      drush_log('Beginning pan-sql-sync', 'ok');
      $ret = parent::drushInvokeProcess($this->environment, 'pan-sql-sync', array($this->config['sync']['source'], $this->config['general']['target']), array_merge($this->config['sync']['pan_sql_sync']));
      if ($ret['error_status'] == 0) {
        drush_log(dt('Synced Pantheon database from !source to !target', array('!source' => $this->config['sync']['source'], '!target' => $this->config['general']['target'])), 'ok');
        return TRUE;
      }
      else {
        return drush_set_error(dt('An error occurred. %error', array('%error' => print_r($ret['error_log'], TRUE))));
      }
    }
    return TRUE;
  }
}
