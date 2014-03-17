<?php

/**
 * @file
 * Diagnostics related code.
 */

require_once 'DrushRebuild.php';

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
    if (isset($drush_rebuild->source)) {
      $this->source = $drush_rebuild->source;
    }
    if (isset($drush_rebuild->config)) {
      $this->config = $drush_rebuild->config;
    }
  }

  /**
   * Run through diagnostics checks.
   *
   * @return bool
   *   TRUE if successful, array of errors if not.
   */
  public function execute() {
  }

  /**
   * Ensure that the options provided in the loaded config are valid.
   *
   * @return array
   *   TRUE if valid, array of invalid keys otherwise.
   */
  public function validateConfig() {

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

  /**
   * Verifies a completed rebuild.
   */
  public function verifyCompletedRebuild() {
    // TODO: Check to see if we can bootstrap to the site.
    return TRUE;
  }

}
