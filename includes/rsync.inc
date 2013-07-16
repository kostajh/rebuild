<?php

/**
 * @file
 * Rsync functionality.
 */

/**
 * Handles rsync options for the rebuild.
 */
class Rsync extends Rebuilder {

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
    if (isset($this->config['sync']['rsync']) && $this->config['sync']['rsync']['files_only']) {
      drush_log('Beginning rsync', 'ok');
      // Don't bootstrap to the current environment to avoid
      // drush_invoke_process() adding extra slashes to the rsync call.
      $ret = parent::drushInvokeProcess(NULL,
                                  'rsync',
                                  array(
                                    $this->config['sync']['source'] . ':%files',
                                    $this->config['general']['target'] . ':%files',
                                  ),
                                  array(
                                    'yes' => TRUE,
                                    'quiet' => TRUE,
                                    'verbose' => FALSE,
                                  ),
                                  array(
                                    'integrate' => FALSE,
                                    'interactive' => FALSE,
                                  ));
      if ($ret['error_status'] == 0) {
        drush_log(dt('Rsynced files from !source to !target', array('!source' => $this->config['sync']['source'], '!target' => $this->config['general']['target'])), 'ok');
        return TRUE;
      }
      else {
        return drush_set_error(dt('An error occurred. %error', array('%error' => print_r($ret['error_log'], TRUE))));
      }
    }
    return TRUE;
  }

}
