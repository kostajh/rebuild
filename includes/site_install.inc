<?php

/**
 * @file
 * Site install code.
 */

/**
 * Handles site-install component of rebuild.
 */
class SiteInstall extends Rebuilder {

  /**
   * Constructor.
   */
  public function __construct() {
    $this->config = parent::getConfig();
    $this->environment = parent::getEnvironment();
    if ($this->config['site_install']) {
      $this->profile = $this->config['site_install']['profile'];
      $this->site_install_options = $this->config['site_install'];
      // Unset the profile from the options group.
      unset($this->site_install_options['profile']);
      // Swap placeholder values.
      foreach ($this->site_install_options as $key => &$value) {
        // If the value starts with "%" then we are referencing a variable
        // defined in the Drush alias.
        if (strpos($value, '%') === 0) {
          if (isset($this->environment['#rebuild'][substr($value, 1)])) {
            $value = $this->environment['#rebuild'][substr($value, 1)];
          }
          elseif (isset($this->environment['rebuild'][substr($value, 1)])) {
            $value = $this->environment['rebuild'][substr($value, 1)];
            drush_log(dt("Please update your Drush alias. The 'rebuild' element should be changed to '#rebuild'."), 'warning');
          }
          else {
            return drush_set_error(dt('Attempted to reference an undefined variable in your Drush alias.'));
          }
        }
      }
    }
  }

  /**
   * Start the site install.
   */
  public function execute() {
    if (!empty($this->site_install_options)) {
      drush_log('Beginning site-install', 'ok');
      $ret = parent::drushInvokeProcess($this->environment, 'site-install', array($this->profile), $this->site_install_options);
      if ($ret['error_status'] == 0) {
        drush_log(dt('- Successfully installed profile "!profile"', array('!profile' => $this->profile)), 'ok');
        return TRUE;
      }
      else {
        return drush_set_error(dt('An error occurred. %error %log',
          array(
            '%error' => print_r($ret['error_log'], TRUE),
            '%log' => print_r($ret['output'], TRUE),
          )
          )
        );
      }
    }
    return TRUE;
  }
}
