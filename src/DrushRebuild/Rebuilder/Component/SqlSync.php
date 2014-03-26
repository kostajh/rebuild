<?php

/**
 * @file
 * SQL Sync code.
 */

require_once dirname(__DIR__) . '/Rebuilder.php';

/**
 * Handles sql-sync component of rebuild.
 */
class SqlSync implements DrushRebuilderInterface {

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
    return dt('Beginning sql-sync');
  }

  /**
   * {@inheritdoc}
   */
  public function completionMessage() {
    return dt('Finished syncing database.');
  }

  /**
   * {@inheritdoc}
   */
  public function commands() {
    return array(
      array(
        'alias' => $this->environment,
        'command' => 'sql-sync',
        'arguments' => array($this->config['sync']['source'], $this->config['general']['target']),
        'options' => $this->config['sync']['sql_sync'],
        'backend-options' => array('dispatch-using-alias' => FALSE),
        'progress-message' => dt('- Syncing database from !source to !target', array(
          '!source' => $this->config['sync']['source'],
          '!target' => $this->config['general']['target'])),
      ),
    );
  }
}
