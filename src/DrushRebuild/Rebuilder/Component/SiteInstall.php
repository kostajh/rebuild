<?php

/**
 * @file
 * Site install code.
 */

require_once dirname(__DIR__) . '/Rebuilder.php';

/**
 * Handles site-install component of rebuild.
 */
class SiteInstall implements DrushRebuilderInterface {

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
    $this->profile = $this->config['site_install']['profile'];
    $this->site_install_options = $this->config['site_install'];
    // Unset the profile from the options group.
    unset($this->site_install_options['profile']);
    // Swap placeholder values.
    foreach ($this->site_install_options as &$value) {
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
          drush_set_error(dt('Attempted to reference an undefined variable in your Drush alias.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function startMessage() {
    return 'Beginning site-install';
  }

  /**
   * {@inheritdoc}
   */
  public function completionMessage() {
    return 'Finished site-install';
  }

  /**
   * {@inheritdoc}
   */
  public function commands() {
    return array(
      array(
        'alias' => $this->environment,
        'command' => 'site-install',
        'arguments' => array($this->profile),
        'options' => $this->site_install_options,
        'prgoress-message' => dt('Beginning site-install with !profile', array('!profile' => $this->profile)),
      ),
    );
  }
}
