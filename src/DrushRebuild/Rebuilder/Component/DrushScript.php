<?php

/**
 * @file
 * Drush script related code.
 */

require_once dirname(__DIR__) . '/Rebuilder.php';

/**
 * Handles executing drush scripts.
 */
class DrushScript implements DrushRebuilderInterface {

  protected $config = array();
  protected $environment = array();
  protected $options = array();

  /**
   * {@inheritdoc}
   */
  public function __construct(array $config, array $environment, array $options = array()) {
    $this->config = $config;
    $this->environment = $environment;
    $this->options = $options;
  }

  /**
   * {@inheritdoc}
   */
  public function startMessage() {
    return dt('Executing !state Drush scripts.', array('!state' => $this->options['state']));
  }

  /**
   * {@inheritdoc}
   */
  public function completionMessage() {
    return dt('Finished executing !state Drush scripts.', array('!state' => $this->options['state']));
  }

  /**
   * {@inheritdoc}
   */
  public function commands() {
    $state = $this->options['state'];
    $commands = array();
    if (!is_array($this->config['drush_scripts'][$state])) {
      $this->config['drush_scripts'][$state] = array($this->config['drush_scripts'][$state]);
    }
    foreach ($this->config['drush_scripts'][$state] as $filename) {
      $rebuild_filepath = $this->environment['path-aliases']['%rebuild'];
      $file = str_replace(basename($rebuild_filepath), $filename, $rebuild_filepath);
      $environment = ($this->state == 'pre_process') ? '@none' : $this->environment;
      $commands[] = array(
        'alias' => $environment,
        'command' => 'php-script',
        'arguments' => array($file),
        'progress-message' => dt('Executing !file script', array('!file' => $file)),
      );
    }
    return $commands;
  }
}
