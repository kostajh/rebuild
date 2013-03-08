<?php

/**
 * @file
 * Diagnostics related code.
 */

/**
 * Provides methods for diagnosing rebuild configuration.
 */
class Diagnostics extends DrushRebuild {

  /**
   * Constructor.
   */
  public function __construct(DrushRebuild $drush_rebuild) {
    $this->environment = $drush_rebuild->environment;
    $this->target = $drush_rebuild->target;
    $this->source = $drush_rebuild->source;
    $this->manifest = $drush_rebuild->manifest;
  }

}
