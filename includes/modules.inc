<?php

/**
 * @file
 * Module related code.
 */

/**
 * Handles module enable/disable functions.
 */
class Modules extends Rebuilder {

  /**
   * Constructor.
   */
  public function __construct() {
    $this->config = parent::getConfig();
    $this->environment = parent::getEnvironment();
  }

  /**
   * Start the process of enabling / disabling modules.
   *
   * @param string $op
   *   Valid options are 'enable' or 'disable'.
   */
  protected function execute($op) {
    if ($op == 'enable') {
      // Enable modules.
      if (isset($this->config['drupal']['modules']['enable']) && is_array($this->config['drupal']['modules']['enable'])) {
        drush_log('Enabling modules', 'ok');
        $ret = parent::drushInvokeProcess($this->environment, 'pm-enable', $this->config['drupal']['modules']['enable']);
        if ($ret['error_status'] == 0) {
          drush_log(dt('- Enabled modules: !module.', array('!module' => implode(", ", $this->config['drupal']['modules']['enable']))), 'success');
        }
        else {
          return drush_set_error(dt('An error occurred. %error', array('%error' => print_r($ret['error_log'], TRUE))));
        }
      }
    }

    if ($op == 'disable') {
      // Disable modules.
      if (isset($this->config['drupal']['modules']['disable']) && is_array($this->config['drupal']['modules']['disable'])) {
        drush_log('Disabling modules', 'ok');
        // TODO: We shouldn't have to set 'strict' => 0, but something has
        // changed between Drush 6 beta 1 and Drush 6 rc1 that requires us to.
        $ret = parent::drushInvokeProcess($this->environment, 'pm-disable', $this->config['drupal']['modules']['disable']);
        if ($ret['error_status'] == 0) {
          drush_log(dt('- Disabled modules: !module.', array('!module' => implode(", ", $this->config['drupal']['modules']['disable']))), 'success');
        }
        else {
          return drush_set_error(dt('An error occurred. %error', array('%error' => print_r($ret['error_log'], TRUE))));
        }
      }
    }
    return TRUE;
  }
}
