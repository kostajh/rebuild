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
  protected function getAliases() {
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
  protected function prepareWorkingDir() {
    if (file_exists('/tmp/drush_rebuild')) {
      unish_file_delete_recursive('/tmp/drush_rebuild');
    }
    mkdir('/tmp/drush_rebuild');
  }

  /**
   * Copy the predefined aliases into the working directory.
   */
  protected function copyAliases() {
    touch('/tmp/drush_rebuild/drebuild.aliases.drushrc.php');
    file_put_contents('/tmp/drush_rebuild/drebuild.aliases.drushrc.php', $this->file_aliases($this->getAliases()));
  }

  /**
   * Returns an array of the config contents.
   */
  protected function loadConfig() {
    $config = $this->getConfig();
    return parse_ini_string($config);
  }

  /**
   * Get the overrides.
   *
   * @return string
   *   Return a rebuild overrides config.
   */
  protected function getOverrides() {
    return 'variables[site_slogan] = RebuildMe
    ';
  }

  /**
   * Get the site install config.
   *
   * @return string
   *   Return a rebuild config for a site install.
   */
  protected function getSiteInstallConfig() {
    return '
description = "Rebuilds the "minimal" install profile and installs some modules"
version = 1.0
site_install[profile] = minimal
site_install[account-mail] = %email
site_install[account-name] = SuperAdmin
site_install[site-name] = Local Install
variables[preprocess_js] = 0
variables[preprocess_css] = 0
variables[reroute_email_address] = %email
';
  }

  /**
   * Get the test config.
   *
   * @return string
   *   Return a rebuild info file config.
   */
  protected function getConfig() {
    return '
description = "Rebuilds test Drush Rebuild local development environment from test Drush Rebuild prod destination"
; Define what type of rebuild this is.
; Optional - specify a version of your rebuild script
version = 1.0
; Define options for database sync
sql_sync[] = "create-db"
sql_sync[sanitize] = "sanitize-email"
sql_sync[structure-tables-key] = "common"
; Define options for file sync
rsync[files_only] = TRUE
; rsync[exclude] = .htaccess
; Define variables to be set
variables[preprocess_js] = 0
variables[preprocess_css] = 0
variables[site_slogan] = HelloWorld
; Note that %email will load the variable specified in your drush alias
; under array("rebuild" => "email")
variables[reroute_email_address] = %email
; Specify if user should be logged in after running rebuild
uli = 0
; Modules to enable
modules_enable[] = syslog
; Modules to disable
modules_disable[] = overlay
; Overrides
overrides = /tmp/drush_rebuild/local.rebuild.info
';
  }

  /**
   * Copy the config to the working dir.
   */
  protected function copyConfig() {
    touch('/tmp/drush_rebuild/rebuild.info');
    file_put_contents('/tmp/drush_rebuild/rebuild.info', $this->getConfig());
  }

  /**
   * Copy the overrides to the working dir.
   */
  protected function copyOverrides() {
    touch('/tmp/drush_rebuild/local.rebuild.info');
    file_put_contents('/tmp/drush_rebuild/local.rebuild.info', $this->getOverrides());
  }

  /**
   * Install test sites.
   */
  protected function installTestSites() {
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
    // Add a file to sites/default/files in @prod
    touch('/tmp/drush_rebuild/prod/sites/default/files/hello.world');
  }

  /**
   * Test a basic rebuild.
   */
  public function testRebuild() {
    // Make an alias for the dev/prod sites.
    $this->prepareWorkingDir();
    $this->copyAliases();
    // Copy test rebuild file to /tmp/drush_rebuild/rebuild.info.
    $this->copyConfig();
    // Copy overrides file to /tmp/drush_rebuild/local.rebuild.info
    $this->copyOverrides();

    // @todo Copy test scripts to /tmp/drush_rebuild/.
    // Install Drupal on Prod with site name "Drush Rebuild Prod".
    $this->installTestSites();

    // Run the rebuild.
    $this->drush('rebuild', array('@drebuild.dev'),
      array(
        'include' => "/Users/" . get_current_user() . '/.drush/rebuild',
        'alias-path' => '/tmp/drush_rebuild', 'debug' => TRUE,
        'source' => '@drebuild.prod',
        'yes' => TRUE,
      )
    );

    // If site name for Dev is now Prod, the rebuild succeeded.
    $this->drush('variable-get', array('site_name'),
      array(
        'alias-path' => '/tmp/drush_rebuild',
        'format' => 'json',
      ),
      '@drebuild.dev'
    );
    $this->assertEquals('"Prod"', $this->getOutput());

    // Test that the reroute email address was set based on the alias value.
    $this->drush('variable-get', array('reroute_email_address'),
      array(
        'alias-path' => '/tmp/drush_rebuild',
        'format' => 'json',
      ),
      '@drebuild.dev'
    );
    $aliases = $this->getAliases();
    $rebuild_email = $aliases['dev']['rebuild']['email'];
    $this->assertEquals('"' . $rebuild_email . '"', $this->getOutput());

    // Check if our overrides were set.
    $this->drush('variable-get', array('site_slogan'), array('alias-path' => '/tmp/drush_rebuild', 'format' => 'json'), '@drebuild.dev');
    $this->assertEquals('"RebuildMe"', $this->getOutput());

    // Test that emails were sanitized during sql-sync.
    $this->drush('uinf', array('1'), array(
      'alias-path' => '/tmp/drush_rebuild',
      ),
      '@drebuild.dev'
    );
    $this->assertContains('user+1@localhost', $this->getOutput());
    // Check that hello.world file was rsynced from @prod
    $this->assertFileExists('/tmp/drush_rebuild/dev/sites/default/files/hello.world');
  }

  /**
   * Tests the view config option.
   */
  public function testViewConfig() {
    $this->drush('rebuild', array('@drebuild.dev'), array(
      'include' => "/Users/" . get_current_user() . '/.drush/rebuild',
      'alias-path' => '/tmp/drush_rebuild',
      'view-config' => TRUE,
      )
    );
    $this->assertContains('Rebuilds test Drush Rebuild local development environment from test Drush Rebuild prod destination', $this->getOutput());
  }

  /**
   * Tests the site install rebuild.
   */
  public function testSiteInstall() {
    touch('/tmp/drush_rebuild/rebuild.info');
    file_put_contents('/tmp/drush_rebuild/rebuild.info', $this->getSiteInstallConfig());
    // Run the rebuild.
    $this->drush('rebuild', array('@drebuild.dev'),
      array(
        'include' => "/Users/" . get_current_user() . '/.drush/rebuild',
        'alias-path' => '/tmp/drush_rebuild', 'debug' => TRUE,
        'source' => '@drebuild.prod',
        'yes' => TRUE,
      )
    );
    // Check if the install succeeded
    $this->drush('variable-get', array('install_profile'), array('alias-path' => '/tmp/drush_rebuild', 'format' => 'json'), '@drebuild.dev');
    $this->assertEquals('"minimal"', $this->getOutput());
  }

}
