<?php

/**
 * @file
 * Variable-set related code.
 */

/**
 * Handles variable-set functionality.
 */
class Variable extends Rebuilder {

  /**
   * Constructor.
   */
  public function __construct() {
    $this->config = parent::getConfig();
    $this->environment = parent::getEnvironment();
  }

  /**
   * Set the variables.
   */
  protected function execute() {
    $variables = $this->config['drupal']['variables'];
    // Set variables.
    // TODO: Implement deleting variables.
    if (isset($variables['set']) && is_array($variables['set'])) {
      drush_log('Setting variables', 'ok');
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
        parent::drushInvokeProcess($this->environment, 'variable-set', array($key, $value));
        drush_log(dt('- Set "!var" to "!value"', array('!var' => $key, '!value' => $value)), 'success');
      }
    }
    return TRUE;
  }
}
