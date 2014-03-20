<?php

/**
 * @file
 * Interface for Drush Rebuild classes.
 */

interface DrushRebuilderInterface {

  /**
   * Constructor.
   */
  public function __construct(array $config, array $environment, array $options = array());

  /**
   * The command callback to pass to drushInvokeProcess().
   *
   * @return array
   *   An array of command callbacks to pass to drushInvokeProcess().
   */
  public function commands();

  /**
   * The message to log before starting.
   */
  public function startMessage();

  /**
   * The message to log when finished.
   */
  public function completionMessage();
}
