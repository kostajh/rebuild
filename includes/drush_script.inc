<?php

/**
 * @file
 * Drush script related code.
 */

/**
 * Handles executing drush scripts.
 */
class DrushScript extends Rebuilder {

  /**
   * Constructor.
   */
  public function __construct(Rebuilder $rebuilder, $state) {
    $this->rebuilder = $rebuilder;
    $this->state = $state;
  }

  /**
   * Start executing drush scripts.
   */
  public function start() {
    if (isset($this->rebuilder->$this->state)) {
      drush_log(dt('Executing !state scripts.', array('!state' => $this->state)), 'ok');

      foreach ($this->rebuilder->$this->state as $filename) {
        $rebuild_filepath = $env['path-aliases']['%rebuild'];
        $file = str_replace('rebuild.info', $filename, $rebuild_filepath);
        if (file_exists($file)) {
          $ret = drush_invoke_process($this->rebuilder->environment, 'php-script', array($file), array('quiet' => TRUE));
          if ($ret['error_status'] == 0) {
            drush_log(dt('Executed !file script.', array('!file' => $file)), 'ok');
          }
          else {
            return drush_set_error(dt('An error occurred. %error', array('%error' => print_r($ret['error_log'], TRUE))));
          }
        }
        else {
          return drush_set_error(dt('Could not load script !file.', array('!file' => $file)));
        }
      }
    }
    return TRUE;
  }

}
