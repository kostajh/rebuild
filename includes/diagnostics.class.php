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

  /**
   * Ensure that the options provided in the loaded manifest are valid.
   *
   * @return array
   *   TRUE if valid, array of invalid keys otherwise.
   */
  public function validateManifest() {

  }

  /**
   * Make sure the target site is accessible over the web.
   */
  public function pingHost() {

  }

  /**
   * Ensure the DB credentials for the target are valid and that the DB exists.
   */
  public function checkDatabaseAccess() {

  }

  /**
   * Check if the database exists.
   */
  public function checkDatabaseExists() {

  }

}
