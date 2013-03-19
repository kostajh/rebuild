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
   *
   * @param Rebuilder $rebuilder
   *   The Rebuilder class object.
   */
  public function __construct(Rebuilder $rebuilder) {
    $this->rebuilder = $rebuilder;
  }

  /**
   * Start the site install.
   */
  public function execute() {
    if (!empty($this->rebuilder->site_install_options)) {
      drush_log('Beginning site-install', 'ok');
      $ret = drush_invoke_process($this->rebuilder->environment, 'site-install', array($this->rebuilder->profile), array_merge($this->rebuilder->site_install_options, array('quiet' => TRUE)));
      if ($ret['error_status'] == 0) {
        drush_log(dt('Successfully installed profile "!profile"', array('!profile' => $this->rebuilder->profile)), 'ok');
        return TRUE;
      }
      else {
        return drush_set_error(dt('An error occurred. %error', array('%error' => print_r($ret['error_log'], TRUE))));
      }
    }
    return TRUE;
  }
}
