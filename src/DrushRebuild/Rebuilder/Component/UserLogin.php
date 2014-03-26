<?php

/**
 * @file
 * User Login functionality.
 */

/**
 * Handles user-login code.
 */
class UserLogin implements DrushRebuilderInterface {

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
    return dt('Logging you in to the site');
  }

  /**
   * {@inheritdoc}
   */
  public function completionMessage() {
    return dt('- Successfully logged you in.');
  }

  /**
   * {@inheritdoc}
   */
  public function commands() {
    return array(
      array(
        'alias' => $this->environment,
        'command' => 'uli',
      ),
    );
  }


}
