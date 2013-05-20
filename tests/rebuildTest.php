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
   * Gets the current users home directory.
   */
  protected function getHomeDir() {
    $info = posix_getpwuid(getmyuid());
    return $info['dir'];
  }

  /**
   * Get the test directory.
   */
  protected function getTestsDir() {
    return $this->getHomeDir() . '/.drush/drush_rebuild_tests';
  }

  /**
   * Get a predefined set of aliases for our tests.
   *
   * @return array
   *   An array of aliases.
   */
  protected function getAliases() {
    return array(
      'dev' => array(
        'root' => $this->getTestsDir() . '/dev',
        'uri' => 'http://dev.drush.rebuild',
        'db-url' => 'mysql://root@localhost/drebuild_dev',
        'path-aliases' => array(
          '%rebuild' => $this->getTestsDir() . '/rebuild.info',
        ),
        'rebuild' => array(
          'email' => 'me@example.com',
        ),
      ),
      'prod' => array(
        'root' => $this->getTestsDir() . '/prod',
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
    if (file_exists($this->getHomeDir() . '/.drush/drush_rebuild_tests')) {
      unish_file_delete_recursive($this->getHomeDir() . '/.drush/drush_rebuild_tests');
    }
    mkdir($this->getHomeDir() . '/.drush/drush_rebuild_tests');
  }

  /**
   * Copy the predefined aliases into the working directory.
   */
  protected function copyAliases() {
    touch($this->getTestsDir() . '/drebuild.aliases.drushrc.php');
    file_put_contents($this->getTestsDir() . '/drebuild.aliases.drushrc.php', $this->file_aliases($this->getAliases()));
  }

  /**
   * Returns an array of the config contents.
   */
  protected function loadConfig() {
    $config = $this->getConfig();
    $yaml = new Parser();
    return $yaml->parse($config);
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
; Permissions to grant
permissions_grant["anonymous user"] = "access site in maintenance mode, access administration pages"
; Permissions to revoke
permissions_revoke["administrator"] = "administer comments"
; Overrides
overrides = ' . $this->getTestsDir() . '/local.rebuild.info
';
  }

  /**
   * Copy the config to the working dir.
   */
  protected function copyConfig() {
    touch($this->getTestsDir() . '/rebuild.info');
    file_put_contents($this->getTestsDir() . '/rebuild.info', $this->getConfig());
  }

  /**
   * Copy the overrides to the working dir.
   */
  protected function copyOverrides() {
    touch($this->getTestsDir() . '/local.rebuild.info');
    file_put_contents($this->getTestsDir() . '/local.rebuild.info', $this->getOverrides());
  }

  /**
   * Install test sites.
   */
  protected function installTestSites() {
    $options = array(
      'site-name' => 'Prod',
      'alias-path' => $this->getTestsDir(),
      'yes' => TRUE,
    );
    if (!file_exists($this->getTestsDir() . '/prod')) {
      mkdir($this->getTestsDir() . '/prod');
    }
    else {
      unish_file_delete_recursive($this->getTestsDir() . '/prod');
    }
    if (!file_exists($this->getTestsDir() . '/dev')) {
      mkdir($this->getTestsDir() . '/dev');
    }
    else {
      unish_file_delete_recursive($this->getTestsDir() . '/dev');
    }
    // Install prod site.
    $this->drush('dl', array('drupal'), array(
      'drupal-project-rename' => 'prod',
      'destination' => $this->getTestsDir(),
      'cache' => TRUE,
      'yes' => TRUE)
    );
    $this->drush('site-install', array('minimal'), $options, '@drebuild.prod');
    $this->log('Installed prod site');
    // Install dev site.
    $this->drush('dl', array('drupal'), array(
      'drupal-project-rename' => 'dev',
      'destination' => $this->getTestsDir(),
      'cache' => TRUE,
      'yes' => TRUE)
    );
    $options['site-name'] = 'Dev';
    $this->drush('site-install', array('minimal'), $options, '@drebuild.dev');
    $this->log('Installed dev site');
    // Check that the name was set.
    $this->drush('variable-get', array('site_name'), array('alias-path' => $this->getTestsDir(), 'format' => 'json'), '@drebuild.dev');
    $this->assertEquals('"Dev"', $this->getOutput());
    $this->drush('variable-get', array('site_name'), array('alias-path' => $this->getTestsDir(), 'format' => 'json'), '@drebuild.prod');
    $this->assertEquals('"Prod"', $this->getOutput());
    // Add a file to sites/default/files in @prod
    touch($this->getTestsDir() . '/prod/sites/default/files/hello.world');
  }

  /**
   * Test a basic rebuild.
   */
  public function testRebuild() {
    // Make an alias for the dev/prod sites.
    $this->prepareWorkingDir();
    $this->copyAliases();
    // Copy test rebuild file.
    $this->copyConfig();
    // Copy overrides file.
    $this->copyOverrides();

    // Clear drush cache before running tests.
    // @FIXME. The build fails because `drush composer` can't be found.
    $this->drush('dl composer-8.x-1.0-alpha5', array(),
      array('destination' => UNISH_SANDBOX, 'yes' => TRUE));
    $this->drush('cc', array('drush'));
    // Install Symfony YAML component.
    $this->drush('composer', array('install'),
      array(
        'yes' => TRUE,
        'include' => UNISH_SANDBOX,
        'debug' => TRUE),
      NULL,
      $this->getHomeDir() . '/.drush/rebuild');
    // @todo Copy test scripts.
    // Install Drupal on Prod with site name "Drush Rebuild Prod".
    $this->installTestSites();

    // Run the rebuild.
    $this->drush('rebuild', array('@drebuild.dev'),
      array(
        'include' => $this->getHomeDir() . '/.drush/rebuild',
        'alias-path' => $this->getTestsDir(), 'debug' => TRUE,
        'source' => '@drebuild.prod',
        'yes' => TRUE,
      )
    );

    // If site name for Dev is now Prod, the rebuild succeeded.
    $this->drush('variable-get', array('site_name'),
      array(
        'alias-path' => $this->getTestsDir(),
        'format' => 'json',
      ),
      '@drebuild.dev'
    );
    $this->assertEquals('"Prod"', $this->getOutput());

    // Test that the reroute email address was set based on the alias value.
    $this->drush('variable-get', array('reroute_email_address'),
      array(
        'alias-path' => $this->getTestsDir(),
        'format' => 'json',
      ),
      '@drebuild.dev'
    );
    $aliases = $this->getAliases();
    $rebuild_email = $aliases['dev']['rebuild']['email'];
    $this->assertEquals('"' . $rebuild_email . '"', $this->getOutput());

    // Check if our overrides were set.
    $this->drush('variable-get', array('site_slogan'), array('alias-path' => $this->getTestsDir(), 'format' => 'json'), '@drebuild.dev');
    $this->assertEquals('"RebuildMe"', $this->getOutput());

    // Test that emails were sanitized during sql-sync.
    $this->drush('uinf', array('1'), array(
      'alias-path' => $this->getTestsDir(),
      ),
      '@drebuild.dev'
    );
    $this->assertContains('user+1@localhost', $this->getOutput());
    // Check that hello.world file was rsynced from @prod
    $this->assertFileExists($this->getTestsDir() . '/dev/sites/default/files/hello.world');

    // Test permissions grant.
    $this->drush('sql-query', array(
      'SELECT rid FROM role_permission WHERE rid = 1 AND permission = "access site in maintenance mode"',
      ),
      array(
        'alias-path' => $this->getTestsDir(),
      ),
      '@drebuild.dev'
    );
    $this->assertContains('1', $this->getOutput());
    // Test permissions revoke.
    $this->drush('sql-query', array(
      'SELECT rid FROM role_permission WHERE rid = 3 AND permission = "administer comments"',
      ),
      array(
        'alias-path' => $this->getTestsDir(),
      ),
      '@drebuild.dev'
    );
    $this->assertEmpty($this->getOutput());
  }

  /**
   * Tests the view config option.
   */
  public function testViewConfig() {
    $this->drush('rebuild', array('@drebuild.dev'), array(
      'include' => $this->getHomeDir() . '/.drush/rebuild',
      'alias-path' => $this->getTestsDir(),
      'view-config' => TRUE,
      )
    );
    $this->assertContains('Rebuilds test Drush Rebuild local development environment from test Drush Rebuild prod destination', $this->getOutput());
  }

  /**
   * Tests the site install rebuild.
   */
  public function testSiteInstall() {
    touch($this->getTestsDir() . '/rebuild.info');
    file_put_contents($this->getTestsDir() . '/rebuild.info', $this->getSiteInstallConfig());
    // Run the rebuild.
    $this->drush('rebuild', array('@drebuild.dev'),
      array(
        'include' => $this->getHomeDir() . '/.drush/rebuild',
        'alias-path' => $this->getTestsDir(), 'debug' => TRUE,
        'source' => '@drebuild.prod',
        'yes' => TRUE,
      )
    );
    // Check if the install succeeded.
    $this->drush('variable-get', array('install_profile'), array('alias-path' => $this->getTestsDir(), 'format' => 'json'), '@drebuild.dev');
    $this->assertEquals('"minimal"', $this->getOutput());
  }

}
