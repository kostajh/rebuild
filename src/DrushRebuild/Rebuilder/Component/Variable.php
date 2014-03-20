<?php

/**
 * @file
 * Variable-set related code.
 */

require_once dirname(__DIR__) . '/Rebuilder.php';

/**
 * Handles variable-set functionality.
 */
class Variable implements DrushRebuilderInterface {

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
    return dt('Setting variables');
  }

  /**
   * {@inheritdoc}
   */
  public function commands() {
    $variables = $this->config['drupal']['variables'];
    $commands = array();
    // Set variables.
    // TODO: Implement deleting variables.
    if (isset($variables['set']) && is_array($variables['set'])) {
      foreach ($variables['set'] as $key => $value) {
        // If the value starts with "%" then we are referencing a variable
        // defined in the Drush alias.
        if (strpos($value, '%') === 0) {
          if (isset($this->environment['#rebuild'][substr($value, 1)])) {
            $value = $this->environment['#rebuild'][substr($value, 1)];
          }
          elseif (isset($this->environment['rebuild'][substr($value, 1)])) {
            $value = $this->environment['rebuild'][substr($value, 1)];
            drush_log(dt("Please update your Drush alias. The 'rebuild' element should be changed to '#rebuild'."), 'warning');
          }
          else {
            return drush_set_error(dt('Attempted to reference an undefined variable in your Drush alias.'));
          }
        }
        $commands[] = array(
          'alias' => $this->environment,
          'command' => 'variable-set',
          'arguments' => array($key, $value),
          'progress-message' => dt('- Set "!var" to "!value"', array('!var' => $key, '!value' => $value)),
        );
      }
    }
    return $commands;
  }

  /**
   * {@inheritdoc}
   */
  public function completionMessage() {
    return dt('Finished setting variables.');
  }
}
