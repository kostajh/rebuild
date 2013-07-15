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
   * Checks if the rebuild file is a drush script (pre-7.x-1.1).
   */
  public function isLegacy() {
    if (isset($this->environment['path-aliases']['%local-tasks']) &&
      file_exists($this->environment['path-aliases']['%local-tasks'] . '/tasks.php') &&
      !isset($this->environment['path-aliases']['%rebuild']) && !file_exists($this->environment['path-aliases']['%rebuild'])) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Checks if the rebuild file is in INI format.
   *
   * @return array
   *   Return a $config array or FALSE if not INI format.
   */
  public function isIni() {
    if (isset($this->environment['path-aliases']['%rebuild'])) {
      try {
        $config = parse_ini_file($this->environment['path-aliases']['%rebuild']);
        if (!empty($config)) {
          return $config;
        }
      }
      catch (Exception $e) {
        return FALSE;
      }
    }
    return FALSE;
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
    // Check to see if we can bootstrap to the site.
    return TRUE;
  }

}
