<?php

/**
 * @file
 * Interface for Drush Rebuild classes.
 */

interface DrushRebuilder {
  public function __construct(array $config, array $environment, array $options = array());
  public function command();
  public function startMessage();
  public function completionMessage();
}
