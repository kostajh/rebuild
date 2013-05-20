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
   *
   * @param Rebuilder $rebuilder
   *   The Rebuilder class object.
   */
  public function __construct(Rebuilder $rebuilder) {
    $this->rebuilder = $rebuilder;
  }

  /**
   * Start the process of logging a user in.
   */
  public function execute() {
    if (isset($this->rebuilder->rsync) && $this->rebuilder->rsync['files_only']) {
      drush_log('Beginning rsync', 'ok');
      // Don't bootstrap to the current environment to avoid
      // drush_invoke_process() adding extra slashes to the rsync call.
      $ret = drush_invoke_process(array(), 'rsync',
        array(
          $this->rebuilder->source . ':%files', $this->rebuilder->target . ':%files',
        ),
        array('quiet' => TRUE, 'verbose' => FALSE, 'yes' => TRUE)
      );
      if ($ret['error_status'] == 0) {
        drush_log(dt('Rsynced files from !source to !target', array('!source' => $this->rebuilder->source, '!target' => $this->rebuilder->target)), 'ok');
        return TRUE;
      }
      else {
        return drush_set_error(dt('An error occurred. %error', array('%error' => print_r($ret['error_log'], TRUE))));
      }
    }
    return TRUE;
  }

}
