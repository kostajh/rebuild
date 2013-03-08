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
  public function __construct(Rebuilder $rebuilder) {
    $this->rebuilder = $rebuilder;
  }

  /**
   * Start the sql-sync.
   */
  public function start() {
    // Execute sql-sync.
    if (!empty($this->rebuilder->remotes)) {
      drush_log('Beginning sql-sync', 'ok');
      $ret = drush_invoke_process($this->rebuilder->environment, 'sql-sync', array($this->rebuilder->source, $this->rebuilder->target), array_merge($this->rebuilder->sql_sync_options, array('quiet' => TRUE)));
      if ($ret['error_status'] == 0) {
        drush_log(dt('Synced database from !source to !target', array('!source' => $this->rebuilder->source, '!target' => $this->rebuilder->target)), 'ok');
        return TRUE;
      }
      else {
        return drush_set_error(dt('An error occurred. %error', array('%error' => print_r($ret['error_log'], TRUE))));
      }
    }
    return TRUE;
  }
}
