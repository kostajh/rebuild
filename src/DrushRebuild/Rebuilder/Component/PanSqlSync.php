<?php

/**
 * @file
 * Pantheon SQL Sync code.
 */

/**
 * Handles pan-sql-sync component of rebuild.
 */
class PanSqlSync extends SqlSync {

  /**
   * {@inheritdoc}
   */
  public function commands() {
    return array(
      array(
        'alias' => $this->environment,
        'command' => 'pan-sql-sync',
        'arguments' => array($this->config['sync']['source'], $this->config['general']['target']),
        'progress-message' => dt('- Syncing database from Pantheon.'),
      ),
    );
  }
}
