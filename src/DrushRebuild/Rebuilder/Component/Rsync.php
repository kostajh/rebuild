<?php

/**
 * @file
 * Rsync functionality.
 */

require_once dirname(__DIR__) . '/Rebuilder.php';

/**
 * Handles rsync options for the rebuild.
 */
class Rsync implements DrushRebuilder {

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
    return dt('Beginning rsync');
  }

  /**
   * {@inheritdoc}
   */
  public function command() {
    return array(
      'alias' => NULL,
      'command' => 'rsync',
      'arguments' => array($this->config['sync']['source'] . ':%files', $this->config['general']['target'] . ':%files'),
      'options' => array('yes' => TRUE, 'quiet' => TRUE, 'verbose' => FALSE),
      'backend-options' => array(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function completionMessage() {
    return dt('Rsynced files from !source to !target', array('!source' => $this->config['sync']['source'], '!target' => $this->config['general']['target']));
  }

}
