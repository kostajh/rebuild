<?php

/**
*
*/
class SqlSync extends Rebuilder {

  public function __construct() {
    parent::__construct();
  }

  public function start() {
    // Execute sql-sync.
    if (!empty($this->remotes)) {
      drush_print('startingzz');
      drush_log('Beginning sql-sync', 'ok');
      $ret = drush_invoke_process($this->environment, 'sql-sync', array($this->source, $this->target), array_merge($this->sql_sync_options, array('--quiet')));
      if ($ret['error_status'] == 0) {
        drush_log(dt('Synced database from !source to !target', array('!source' => $this->source, '!target' => $this->target)), 'ok');
      }
      else {
        drush_set_error(dt('An error occurred. %error', array('%error' => print_r($ret['error_log'], TRUE))));
      }
    }
  }
}
