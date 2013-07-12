<?php

/**
 * @file
 * Permissions related code.
 */

/**
 * Handles permission grant/revoke functions.
 *
 * Compatible with Drupal 7 only.
 */
class Permissions extends Rebuilder {

  /**
   * Constructor.
   */
  public function __construct() {
    $this->config = parent::getConfig();
    $this->environment = parent::getEnvironment();
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
   * Grant permissions to a role.
   *
   * @param string $role_name
   *   The name of the role to grant permissions to.
   * @param int $rid
   *   The role ID to grant permissions to.
   * @param string $permission
   *   The permission to grant.
   *
   * @return bool
   *   Return TRUE if successful, FALSE otherwise.
   */
  protected function grantPermission($role_name, $rid, $permission) {

    // Check if permission exists. If not, insert.
    $permission_exists = drush_invoke_process($this->environment,
      'sql-query',
      array(
        sprintf("SELECT rid FROM role_permission WHERE permission = '%s' AND rid = %s", $permission, $rid),
      ),
      array(),
      array(
        'quiet' => TRUE,
        'integrate' => FALSE,
      )
    );
    if (!$permission_exists['output']) {
      $ret = drush_invoke_process($this->environment, 'sql-query',
        array(sprintf("SELECT module FROM role_permission WHERE permission = '%s'", $permission)), array(), array('quiet' => TRUE, 'integrate' => FALSE));
      $module = preg_replace('/^.+\n/', '', trim($ret['output']));
      $ret = drush_invoke_process($this->environment, 'sql-query',
        array(
          sprintf("INSERT INTO role_permission (rid, permission, module) VALUES (%s, '%s', '%s')", $rid, $permission, $module),
        ),
        array(),
        array('quiet' => TRUE)
      );
      if ($ret['error_status'] == 0) {
        drush_log(dt('- Granted permission "!permission" (!module) to role "!role"',
          array(
            '!permission' => $permission,
            '!module' => $module,
            '!role' => $role_name,
          )
          ),
          'success'
        );
      }
      else {
        return drush_set_error(dt('An error occurred. %error', array('%error' => print_r($ret['error_log'], TRUE))));
      }
    }
    return TRUE;
  }

  /**
   * Revoke permission for a role.
   *
   * @param string $role_name
   *   The name of the role to revoke permissions for.
   * @param int $rid
   *   The role ID to revoke permissions for.
   * @param string $permission
   *   The permission to revoke.
   *
   * @return bool
   *   Return TRUE if successful, FALSE otherwise.
   */
  protected function revokePermission($role_name, $rid, $permission) {
    // Check if permission exists. If not, insert.
    $permission_exists = drush_invoke_process($this->environment, 'sql-query',
      array(
        sprintf("SELECT rid FROM role_permission WHERE permission = '%s' AND rid = %s", $permission, $rid),
      ),
      array(),
      array(
        'quiet' => TRUE,
        'integrate' => FALSE,
      )
    );
    if ($permission_exists['output']) {
      $ret = drush_invoke_process($this->environment, 'sql-query',
        array(
          sprintf("SELECT module FROM role_permission WHERE permission = '%s' LIMIT 1", $permission),
        ),
        array(),
        array(
          'quiet' => TRUE,
          'integrate' => FALSE,
        )
      );
      $module = preg_replace('/^.+\n/', '', trim($ret['output']));
      $ret = drush_invoke_process($this->environment, 'sql-query',
        array(
          sprintf("DELETE FROM role_permission WHERE rid = %s AND permission = '%s' AND module = '%s'", $rid, $permission, $module),
        ),
        array(),
        array('quiet' => TRUE)
      );
      if ($ret['error_status'] == 0) {
        drush_log(dt('- Revoked permission "!permission" (!module) from role "!role"',
          array(
            '!permission' => $permission,
            '!module' => $module,
            '!role' => $role_name,
          )),
          'success'
        );
      }
      else {
        return drush_set_error(dt('An error occurred. %error', array('%error' => print_r($ret['error_log'], TRUE))));
      }
    }
    else {
      drush_log(dt('Permission "!permission" cannot be revoked because it was never granted to role "!role"', array('!permission' => $permission, '!role' => $role_name)), 'warning');
    }
    return TRUE;
  }

  /**
   * Start the process of granting / revoking permissions.
   *
   * @param string $op
   *   Valid options are 'grant' or 'revoke'.
   */
  protected function execute($op) {
    if ($op == 'grant') {
      // Grant permissions.
      if (isset($this->permissions_grant) && !empty($this->permissions_grant)) {
        drush_log('Granting permissions', 'ok');

        // Get the role ID for each role name.
        $role_names = array_keys($this->permissions_grant);
        $roles = array();
        foreach ($role_names as $name) {
          $ret = drush_invoke_process($this->environment, 'sql-query',
            array(sprintf("SELECT rid FROM role WHERE name = '%s'", $name)),
            array(), array('quiet' => TRUE, 'integrate' => FALSE));
          $roles[$name] = preg_replace('/^.+\n/', '', trim($ret['output']));
        }

        // Loop through the user roles and build an array of permissions.
        $perms = array();
        foreach ($this->permissions_grant as $role => $permissions_string) {
          // Check if multiple permission strings are defined for the role.
          $permission = '';
          if (strpos($permissions_string, ",") > 0) {
            $permissions = explode(",", $permissions_string);
            foreach ($permissions as $perm) {
              // Grant the permission.
              if (!$this->grantPermission($role, $roles[$role], trim($perm))) {
                return FALSE;
              }
            }
          }
          else {
            // Grant the permission.
            if (!$this->grantPermission($role, $roles[$role], trim($permissions_string))) {
              return FALSE;
            }
          }
        }

      }
    }

    if ($op == 'revoke') {
      // Revoke permissions.
      if (isset($this->permissions_revoke) && !empty($this->permissions_revoke)) {
        drush_log('Revoking permissions', 'ok');
        // Get the role ID for each role name.
        $role_names = array_keys($this->permissions_revoke);
        $roles = array();
        foreach ($role_names as $name) {
          $ret = drush_invoke_process($this->environment, 'sql-query',
            array(sprintf("SELECT rid FROM role WHERE name = '%s'", $name)),
            array(), array('quiet' => TRUE, 'integrate' => FALSE));
          $roles[$name] = preg_replace('/^.+\n/', '', trim($ret['output']));
        }

        // Loop through the user roles and build an array of permissions.
        $perms = array();
        foreach ($this->permissions_revoke as $role => $permissions_string) {
          // Check if multiple permission strings are defined for the role.
          $permission = '';
          if (strpos($permissions_string, ",") > 0) {
            $permissions = explode(",", $permissions_string);
            foreach ($permissions as $perm) {
              // Revoke the permission.
              if (!$this->revokePermission($role, $roles[$role], trim($perm))) {
                return FALSE;
              }
            }
          }
          else {
            // Revoke the permission.
            if (!$this->revokePermission($role, $roles[$role], trim($permissions_string))) {
              return FALSE;
            }
          }
        }
      }
    }
    return TRUE;
  }
}
