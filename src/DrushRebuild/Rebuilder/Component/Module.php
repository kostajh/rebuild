<?php

/**
 * @file
 * Module related code.
 */

require_once dirname(__DIR__) . '/Rebuilder.php';

/**
 * Handles module enable/disable functions.
 */
class Module implements DrushRebuilderInterface {

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
    return 'Enabling/disabling modules';
  }

  /**
   * {@inheritdoc}
   */
  public function completionMessage() {
    return 'Finished enabling/disabling modules.';
  }

  /**
   * {@inheritdoc}
   */
  public function commands() {
    $commands = array();
    // Enable modules.
    if (isset($this->config['drupal']['modules']['enable']) && is_array($this->config['drupal']['modules']['enable'])) {
      foreach ($this->config['drupal']['modules']['enable'] as $module) {
        $commands[] = array(
          'alias' => $this->environment,
          'command' => 'pm-enable',
          'arguments' => $module,
          'progress-message' => dt('- !module', array('!module' => $module)),
        );
      }
    }
    // Disable modules.
    if (isset($this->config['drupal']['modules']['disable']) && is_array($this->config['drupal']['modules']['disable'])) {
      foreach ($this->config['drupal']['modules']['disable'] as $module) {
        $commands[] = array(
          'alias' => $this->environment,
          'command' => 'pm-disable',
          'arguments' => $module,
          'progress-message' => dt('- !module', array('!module' => $module)),
        );
      }
    }
    return $commands;
  }
}
