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
   *
   * @return bool
   *   Return TRUE/FALSE on success/error.
   */
  protected function execute($op) {
    if ($op == 'enable') {
      // Enable modules.
      if (isset($this->config['drupal']['modules']['enable']) && is_array($this->config['drupal']['modules']['enable'])) {
        drush_log('Enabling modules', 'ok');
        parent::drushInvokeProcess($this->environment, 'pm-enable', $this->config['drupal']['modules']['enable']);
        drush_log(dt('- Enabled modules: !module.', array('!module' => implode(", ", $this->config['drupal']['modules']['enable']))), 'success');
      }
    }

    if ($op == 'disable') {
      // Disable modules.
      if (isset($this->config['drupal']['modules']['disable']) && is_array($this->config['drupal']['modules']['disable'])) {
        drush_log('Disabling modules', 'ok');
        // TODO: We shouldn't have to set 'strict' => 0, but something has
        // changed between Drush 6 beta 1 and Drush 6 rc1 that requires us to.
        parent::drushInvokeProcess($this->environment, 'pm-disable', $this->config['drupal']['modules']['disable']);
        drush_log(dt('- Disabled modules: !module.', array('!module' => implode(", ", $this->config['drupal']['modules']['disable']))), 'success');
      }
    }
    return TRUE;
  }
}
