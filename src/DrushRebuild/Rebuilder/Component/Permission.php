<?php

/**
 * @file
 * Permissions related code.
 */

/**
 * Handles permission grant/revoke functions.
 *
 * Compatible with Drupal 6/7/8.
 */
class Permission implements DrushRebuilderInterface {

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
    // Build permissions_grant and permissions_revoke arrays.
    $this->permissions_grant = array();
    $this->permissions_revoke = array();
    if (isset($this->config['drupal']['permissions'])) {
      foreach ($this->config['drupal']['permissions'] as $role => $permissions) {
        if (isset($permissions['grant'])) {
          $this->permissions_grant[$role] = implode(", ", $permissions['grant']);
        }
        if (isset($permissions['revoke'])) {
          $this->permissions_revoke[$role] = implode(", ", $permissions['revoke']);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function startMessage() {
    return dt('Setting permissions');
  }

  /**
   * {@inheritdoc}
   */
  public function completionMessage() {
    return dt('Finished setting permissions');
  }

  /**
   * {@inheritdoc}
   */
  public function commands() {
    $op = $this->options['op'];
    $commands = array();
    if ($op == 'grant') {
      // Grant permissions.
      if (isset($this->permissions_grant) && !empty($this->permissions_grant)) {
        // Loop through the user roles and build an array of permissions.
        foreach ($this->permissions_grant as $role => $permissions_string) {
          // Check if multiple permission strings are defined for the role.
          if (strpos($permissions_string, ",") > 0) {
            $permissions = explode(",", $permissions_string);
            foreach ($permissions as $perm) {
              // Grant the permission.
              $commands[] = array(
                'alias' => $this->environment,
                'command' => 'role-add-perm',
                'arguments' => array(
                  sprintf('"%s"', $role),
                  sprintf('"%s"', trim($perm)),
                ),
                'progress-message' => dt('Granting "!perm" to the "!role" role.', array('!perm' => trim($perm), '!role' => $role)),
              );
            }
          }
          else {
            // Grant the permission.
            $commands[] = array(
              'alias' => $this->environment,
              'command' => 'role-add-perm',
              'arguments' => array(
                sprintf('"%s"', $role),
                sprintf('"%s"', trim($permissions_string)),
              ),
              'progress-message' => dt('Granting "!perm" to the "!role" role.', array('!perm' => trim($permissions_string), '!role' => $role)),
            );
          }
        }

      }
    }

    if ($op == 'revoke') {
      // Revoke permissions.
      if (isset($this->permissions_revoke) && !empty($this->permissions_revoke)) {
        // Loop through the user roles and build an array of permissions.
        foreach ($this->permissions_revoke as $role => $permissions_string) {
          // Check if multiple permission strings are defined for the role.
          if (strpos($permissions_string, ",") > 0) {
            $permissions = explode(",", $permissions_string);
            foreach ($permissions as $perm) {
              // Revoke the permission.
              $commands[] = array(
                'alias' => $this->environment,
                'command' => 'role-remove-perm',
                'arguments' => array(
                  sprintf('"%s"', $role),
                  sprintf('"%s"', trim($perm)),
                ),
                'progress-message' => dt('Revoking "!perm" for the "!role" role.', array('!perm' => trim($perm), '!role' => $role)),
              );
            }
          }
          else {
            // Revoke the permission.
            $commands[] = array(
              'alias' => $this->environment,
              'command' => 'role-remove-perm',
              'arguments' => array(
                sprintf('"%s"', $role),
                sprintf('"%s"', trim($permissions_string)),
              ),
              'progress-message' => dt('Revoking "!perm" for the "!role" role.', array('!perm' => trim($permissions_string), '!role' => $role)),
            );
          }
        }
      }
    }
    return $commands;
  }
}
