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
   *
   * @param Rebuilder $rebuilder
   *   The Rebuilder class object.
   */
  public function __construct(Rebuilder $rebuilder) {
    $this->rebuilder = $rebuilder;
  }

  /**
   * Set the variables.
   */
  public function Set() {
    // Execute sql-sync.
    $variables = $this->rebuilder->variables;
    if (is_array($variables)) {
      drush_log('Setting variables', 'ok');
      foreach ($variables as $key => $value) {
        // If the value starts with "%" then we are referencing a variable
        // defined in the Drush alias.
        if (strpos($value, '%') === 0) {
          if (isset($this->rebuilder->environment['rebuild'][substr($value, 1)])) {
            $value = $this->rebuilder->environment['rebuild'][substr($value, 1)];
          }
          else {
            drush_set_error(dt('Attempted to reference an undefined variable in your Drush alias.'));
            continue;
          }
        }

        $ret = drush_invoke_process($this->rebuilder->environment, 'variable-set', array($key, $value), array('quiet' => TRUE));
        if ($ret['error_status'] == 0) {
          drush_log(dt('Setting variable !var to !value', array('!var' => $key, '!value' => $value)));
        }
        else {
          return drush_set_error(dt('An error occurred. %error', array('%error' => print_r($ret['error_log'], TRUE))));
        }
      }
    }
    return TRUE;
  }
}
