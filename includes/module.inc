<?php

/**
*
*/
class Module extends Rebuilder {

  public function __construct(Rebuilder $Rebuilder) {
    $this->rebuilder = $Rebuilder;
  }

  public function start($op) {
    if ($this->op == 'enable') {
      // Enable modules.
      if (is_array($this->rebuilder->modules_enable)) {
        drush_log('Enabling modules', 'ok');
        $ret = drush_invoke_process($this->rebuilder->environment, 'pm-enable', $this->rebuilder->modules_enable, array('quiet' => TRUE));
        if ($ret['error_status'] == 0) {
          drush_log(dt('Enabled modules: !module.', array('!module' => implode(",", $this->rebuilder->modules_enable))), 'ok');
        }
        else {
          drush_set_error(dt('An error occurred. %error', array('%error' => print_r($ret['error_log'], TRUE))));
        }
      }
    }

    if ($this->op == 'disable') {
      // Disable modules.
      if (is_array($this->rebuilder->modules_disable)) {
        drush_log('Disabling modules', 'ok');
        $ret = drush_invoke_process($this->rebuilder->environment, 'pm-disable', $this->rebuilder->modules_disable, array('quiet' => TRUE));
        if ($ret['error_status'] == 0) {
          drush_log(dt('Disabled modules: !module.', array('!module' => implode(", ", $this->rebuilder->modules_disable))), 'ok');
        }
        else {
          drush_set_error(dt('An error occurred. %error', array('%error' => print_r($ret['error_log'], TRUE))));
        }
      }
    }
  }
}
