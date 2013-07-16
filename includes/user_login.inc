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
  public function __construct() {
    $this->config = parent::getConfig();
    $this->environment = parent::getEnvironment();
  }

  /**
   * Start the process of logging a user in.
   */
  protected function execute() {
    if (isset($this->config['general']['uli']) && $this->config['general']['uli'] === TRUE) {
      drush_log('Logging you in to the site', 'ok');
      $ret = parent::drushInvokeProcess($this->environment, 'uli');
      if ($ret['error_status'] == 0) {
        drush_log('- Successfully logged you in.', 'success');
      }
      else {
        return drush_set_error(dt('An error occurred. %error', array('%error' => print_r($ret['error_log'], TRUE))));
      }
    }
    return TRUE;
  }
}
