<?php

/**
 * @file
 * Interface for Drush Rebuild classes.
 */

interface DrushRebuilder {
  /**
   * Constructor.
   */
  public function __construct(array $config, array $environment, array $options = array());

  /**
   * The command callback to pass to DrushInvokeProcess().
   */
  public function command();

  /**
   * The message to log before starting.
   */
  public function startMessage();

  /**
   * The message to log when finished.
   */
  public function completionMessage();
}
