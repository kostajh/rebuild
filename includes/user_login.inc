<?php

/**
 * @file
 * User Login functionality.
 */

/**
 * Handles user-login code.
 */
class UserLogin extends Rebuilder {

  /**
   * Constructor.
   */
  public function __construct(Rebuilder $rebuilder) {
    $this->rebuilder = $rebuilder;
  }

  /**
   * Start the process of logging a user in.
   */
  public function start() {
    if ($this->rebuilder->uli) {
      drush_log('Logging you in to the site.', 'ok');
      $ret = drush_invoke_process($this->rebuilder->environment, 'uli', array('quiet' => TRUE));
      if ($ret['error_status'] == 0) {
        drush_log('Successful in logging you in.', 'ok');
      }
      else {
        return drush_set_error(dt('An error occurred. %error', array('%error' => print_r($ret['error_log'], TRUE))));
      }
    }
    return TRUE;
  }
}
