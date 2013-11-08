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
   * @param string $state
   *   Where we are in the rebuild process. Valid options are 'pre_process',
   *   'post_process' and 'legacy'.
   * @param string $script
   *   Optional; provide the path to the script to execute.
   */
  public function __construct($state, $script = NULL) {
    $this->environment = parent::getEnvironment();
    $this->config = parent::getConfig();
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
      $ret = parent::drushInvokeProcess($this->environment, 'php-script', array($this->script));
      drush_log(dt('Executed !file script.', array('!file' => $this->script)), 'ok');
    }
    else {
      return drush_set_error(dt('An error occurred. %error', array('%error' => print_r($ret['error_log'], TRUE))));
    }
  }

  /**
   * Start executing drush scripts.
   */
  public function execute() {
    $state = $this->state;
    if (isset($this->config['drush_scripts'][$state])) {
      drush_log(dt('Executing !state scripts.', array('!state' => $this->state)), 'ok');
      if (!is_array($this->config['drush_scripts'][$state])) {
        $this->config['drush_scripts'][$state] = array($this->config['drush_scripts'][$state]);
      }
      foreach ($this->config['drush_scripts'][$state] as $filename) {
        $rebuild_filepath = $this->environment['path-aliases']['%rebuild'];
        $file = str_replace(basename($rebuild_filepath), $filename, $rebuild_filepath);
        if (file_exists($file)) {
          drush_log(dt('Executing !file script', array('!file' => $file)), 'ok');
          // If we are in the 'pre_process' state, assume that the Drupal site
          // should not be bootstrapped.
          $environment = ($this->state == 'pre_process') ? '@none' : $this->environment;
          $ret = parent::drushInvokeProcess($environment, 'php-script', array($file));
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
