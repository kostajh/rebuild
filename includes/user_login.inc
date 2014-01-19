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
      parent::drushInvokeProcess($this->environment, 'uli');
      drush_log('- Successfully logged you in.', 'success');
    }
    return TRUE;
  }
}
