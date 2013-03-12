<?php

/**
 * @file
 * PHPUnit Tests for Drush Rebuild command.
 */

/**
 * This uses Drush's own test framework, based on PHPUnit.  To run the tests:
 *
 *      ./runtests.sh .
 *
 *   This is equivalent to:
 *
 *     phpunit --bootstrap=/path/to/drush/tests/drush_testcase.inc .
 *
 *   Note that we are pointing to the drush_testcase.inc file under /tests
 *   directory in drush.
 */
class RebuildTestCase extends Drush_CommandTestCase {

  /**
   * Get a predefined set of aliases for our tests.
   *
   * @return array
   *   An array of aliases.
   */
  function getAliases() {
    return array(
      'dev' => array(
        'root' => '/tmp/drush_rebuild/dev',
        'uri' => 'http://dev.drush.rebuild',
        'db-url' => 'mysql://root@localhost/drebuild_dev',
        'path-aliases' => array(
          '%rebuild' => '/tmp/drush_rebuild/rebuild.info',
        ),
        'rebuild' => array(
          'email' => 'me@example.com',
        ),
      ),
      'prod' => array(
        'root' => '/tmp/drush_rebuild/prod',
        'uri' => 'http://prod.drush.rebuild',
        'db-url' => 'mysql://root@localhost/drebuild_prod',
        'rebuild' => array(
          'email' => 'me@example.com',
        ),
      ),
    );
  }

  /**
   * Prepare the working directory for our tests.
   */
  function prepareWorkingDir() {
    if (file_exists('/tmp/drush_rebuild')) {
      unish_file_delete_recursive('/tmp/drush_rebuild');
    }
    mkdir('/tmp/drush_rebuild');
  }

  /**
   * Copy the predefined aliases into the working directory.
   */
  function copyAliases() {
    touch('/tmp/drush_rebuild/drebuild.aliases.drushrc.php');
    file_put_contents('/tmp/drush_rebuild/drebuild.aliases.drushrc.php', $this->file_aliases($this->getAliases()));
  }

  /**
   * Load the test manifest.
   *
   * @return string
   *   Return a rebuild info file manifest.
   */
  function loadManifest() {
    return '
description = "Rebuilds test Drush Rebuild local development environment from test Drush Rebuild prod destination"
; Define what type of rebuild this is.
; Options are: install_profile, remote
type = remote
; Optional - specify a version of your rebuild script
version = 1.0
; Define valid remotes for a rebuild. By default, any alias in the group will be
; available.
remotes[] = prod
; Define options for database sync
sql_sync[] = "create-db"
sql_sync[sanitize] = "sanitize-email"
sql_sync[structure-tables-key] = "common"
; Define options for file sync
; rsync[type] = files
; rsync[exclude] = .htaccess
; Define variables to be set
variables[preprocess_js] = 0
variables[preprocess_css] = 0
; Note that %email will load the variable specified in your drush alias
; under array("rebuild" => "email")
variables[reroute_email_address] = %email
; Specify if user should be logged in after running rebuild
; uli = 1
; Modules to enable
modules_enable[] = syslog
; Modules to disable
modules_disable[] = overlay';
  }

  /**
   * Copy the manifest to the working dir.
   */
  function copyManifest() {
    touch('/tmp/drush_rebuild/rebuild.info');
    file_put_contents('/tmp/drush_rebuild/rebuild.info', $this->loadManifest());
  }

  /**
   * Install test sites.
   */
  function installTestSites() {
    $options = array(
      'site-name' => 'Prod',
      'alias-path' => '/tmp/drush_rebuild',
      'yes' => TRUE,
    );
    if (!file_exists('/tmp/drush_rebuild/prod')) {
      mkdir('/tmp/drush_rebuild/prod');
    }
    else {
      unish_file_delete_recursive('/tmp/drush_rebuild/prod');
    }
    if (!file_exists('/tmp/drush_rebuild/dev')) {
      mkdir('/tmp/drush_rebuild/dev');
    }
    else {
      unish_file_delete_recursive('/tmp/drush_rebuild/dev');
    }
    // Install prod site.
    $this->drush('dl', array('drupal'), array(
      'drupal-project-rename' => 'prod',
      'destination' => '/tmp/drush_rebuild',
      'cache' => TRUE,
      'yes' => TRUE)
    );
    $this->drush('site-install', array('minimal'), $options, '@drebuild.prod');
    $this->log('Installed prod site');
    // Install dev site.
    $this->drush('dl', array('drupal'), array(
      'drupal-project-rename' => 'dev',
      'destination' => '/tmp/drush_rebuild',
      'cache' => TRUE,
      'yes' => TRUE)
    );
    $options['site-name'] = 'Dev';
    $this->drush('site-install', array('minimal'), $options, '@drebuild.dev');
    $this->log('Installed dev site');
    // Check that the name was set.
    $this->drush('variable-get', array('site_name'), array('alias-path' => '/tmp/drush_rebuild', 'format' => 'json'), '@drebuild.dev');
    $this->assertEquals('"Dev"', $this->getOutput());
    $this->drush('variable-get', array('site_name'), array('alias-path' => '/tmp/drush_rebuild', 'format' => 'json'), '@drebuild.prod');
    $this->assertEquals('"Prod"', $this->getOutput());

  }

  /**
   * Test a basic rebuild.
   */
  public function testRebuild() {
    // Make an alias for the dev/prod sites.
    $this->prepareWorkingDir();
    $this->copyAliases();
    // Copy test rebuild file to /tmp/drush_rebuild/rebuild.info.
    $this->copyManifest();

    // @todo Copy test scripts to /tmp/drush_rebuild/.
    // Install Drupal on Prod with site name "Drush Rebuild Prod".
    $this->installTestSites();

    // Run the rebuild. If site name for Dev is now Prod, the rebuild succeeded.
    $this->drush('rebuild', array('@drebuild.dev'), array(
      'include' => "/Users/" . get_current_user() . '/.drush/rebuild',
      'alias-path' => '/tmp/drush_rebuild', 'debug' => TRUE,
      'source' => '@drebuild.prod',
      'yes' => TRUE)
    );
    $this->drush('variable-get', array('site_name'), array(
      'alias-path' => '/tmp/drush_rebuild',
      'format' => 'json'),
      '@drebuild.dev'
    );
    $this->assertEquals('"Prod"', $this->getOutput());
  }

}
