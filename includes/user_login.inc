<?php

/**
*
*/
class UserLogin extends Rebuilder {

  public function __construct(Rebuilder $Rebuilder) {
    $this->rebuilder = $Rebuilder;
  }

  public function start() {
    if ($this->rebuilder->uli) {
      drush_log('Logging you in to the site.', 'ok');
      $ret = drush_invoke_process($this->rebuilder->environment, 'uli', array('quiet' => TRUE));
      if ($ret['error_status'] == 0) {
        drush_log('Successful in logging you in.', 'ok');
      }
      else {
        drush_set_error(dt('An error occurred. %error', array('%error' => print_r($ret['error_log'], TRUE))));
      }
    }
  }
}
