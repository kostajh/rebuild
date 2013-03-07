<?php

/**
*
*/
class SqlSync extends Rebuilder {

  public function __construct(Rebuilder $Rebuilder) {
    $this->rebuilder = $Rebuilder;
  }

  public function start() {
    // Execute sql-sync.
    if (!empty($this->rebuilder->remotes)) {
      drush_log('Beginning sql-sync', 'ok');
      $ret = drush_invoke_process($this->rebuilder->environment, 'sql-sync', array($this->rebuilder->source, $this->rebuilder->target), array_merge($this->rebuilder->sql_sync_options, array('quiet' => TRUE)));
      if ($ret['error_status'] == 0) {
        drush_log(dt('Synced database from !source to !target', array('!source' => $this->rebuilder->source, '!target' => $this->rebuilder->target)), 'ok');
      }
      else {
        drush_set_error(dt('An error occurred. %error', array('%error' => print_r($ret['error_log'], TRUE))));
      }
    }
  }
}
