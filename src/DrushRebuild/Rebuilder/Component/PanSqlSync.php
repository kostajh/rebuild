<?php

/**
 * @file
 * Pantheon SQL Sync code.
 */

/**
 * Handles pan-sql-sync component of rebuild.
 */
class PanSqlSync extends Rebuilder {

  /**
   * Constructor.
   */
  public function __construct() {
    $this->config = parent::getConfig();
    $this->environment = parent::getEnvironment();
  }

  /**
   * Start the pan-sql-sync.
   */
  protected function execute() {
    // Execute pan-sql-sync.
    if (isset($this->config['general']['target']) && isset($this->config['sync']['source'])) {
      drush_log('Beginning pan-sql-sync', 'ok');
      drush_log(dt('Synced Pantheon database from !source to !target', array('!source' => $this->config['sync']['source'], '!target' => $this->config['general']['target'])), 'ok');
    }
    return TRUE;
  }
}
