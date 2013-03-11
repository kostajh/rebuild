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
   *
   * @param Rebuilder $rebuilder
   *   The Rebuilder class object.
   * @param string $state
   *   Where we are in the rebuild process. Valid options are 'pre_process',
   *   'post_process' and 'legacy'.
   * @param string $script
   *   Optional; provide the path to the script to execute.
   */
  public function __construct(Rebuilder $rebuilder, $state, $script = NULL) {
    $this->rebuilder = $rebuilder;
    $this->state = $state;
    if ($state == 'legacy' && $script) {
      $this->script = $script;
      $this->legacyMode();
    }
  }

  /**
   * Operate in legacy mode. Execute a drush-script to rebuild a site.
   */
  public function legacyMode() {
    if ($ret['error_status'] == 0) {
      $ret = drush_invoke_process($this->rebuilder->environment, 'php-script', array($this->script));
      drush_log(dt('Executed !file script.', array('!file' => $this->script)), 'ok');
    }
    else {
      return drush_set_error(dt('An error occurred. %error', array('%error' => print_r($ret['error_log'], TRUE))));
    }
  }

  /**
   * Start executing drush scripts.
   */
  public function start() {
    if (isset($this->rebuilder->$this->state)) {
      drush_log(dt('Executing !state scripts.', array('!state' => $this->state)), 'ok');

      foreach ($this->rebuilder->$this->state as $filename) {
        $rebuild_filepath = $this->rebuilder->environment['path-aliases']['%rebuild'];
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
