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
          '%rebuild' => $this->getTestsDir() . '/rebuild.yaml',
          '%files' => 'sites/default/files',
        ),
        '#rebuild' => array(
          'email' => 'me@example.com',
        ),
      ),
      'prod' => array(
        'root' => $this->getTestsDir() . '/prod',
        'uri' => 'http://prod.drush.rebuild',
        'db-url' => 'mysql://root@localhost/drebuild_prod',
        'path-aliases' => array(
          '%files' => 'sites/default/files',
        ),
        '#rebuild' => array(
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
   * Copy the config to the working dir.
   */
  protected function copyConfig() {
    if (file_exists($this->getTestsDir() . '/rebuild.yaml')) {
      drupal_unlink($this->getTestsDir() . '/rebuild.yaml');
    }
    copy($this->getHomeDir() . '/.drush/rebuild/tests/rebuild.yaml', $this->getTestsDir() . '/rebuild.yaml');

  }

  /**
   * Copy the overrides to the working dir.
   */
  protected function copyOverrides() {
    if (file_exists($this->getTestsDir() . '/local.rebuild.yaml')) {
      drupal_unlink($this->getTestsDir() . '/local.rebuild.yaml');
    }
    copy($this->getHomeDir() . '/.drush/rebuild/tests/local.rebuild.yaml', $this->getTestsDir() . '/local.rebuild.yaml');
  }

  /**
   * Install test sites.
   */
  protected function installTestSites() {
    $options = array(
      'site-name' => 'Prod',
      'alias-path' => $this->getTestsDir(),
      'yes' => TRUE,
      'quiet' => TRUE,
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
      'yes' => TRUE,
      'quiet' => TRUE)
    );
    $this->drush('site-install', array('minimal'), $options, '@drebuild.prod');
    $this->log('Installed prod site');
    // Install dev site.
    $this->drush('dl', array('drupal'), array(
      'drupal-project-rename' => 'dev',
      'destination' => $this->getTestsDir(),
      'cache' => TRUE,
      'yes' => TRUE,
      'quiet' => TRUE)
    );
    $options['site-name'] = 'Dev';
    $this->drush('site-install', array('minimal'), $options, '@drebuild.dev');
    $this->log('Installed dev site');
    // Check that the name was set.
    $this->drush('variable-get', array('site_name'), array('alias-path' => $this->getTestsDir(), 'format' => 'json'), '@drebuild.dev');
    $this->assertEquals('{"site_name":"Dev"}', $this->getOutput());
    $this->drush('variable-get', array('site_name'), array('alias-path' => $this->getTestsDir(), 'format' => 'json'), '@drebuild.prod');
    $this->assertEquals('{"site_name":"Prod"}', $this->getOutput());
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
    $this->drush('cc', array('drush'));
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
    $this->assertEquals('{"site_name":"Prod"}', $this->getOutput());

    // Test that the reroute email address was set based on the alias value.
    $this->drush('variable-get', array('reroute_email_address'),
      array(
        'alias-path' => $this->getTestsDir(),
        'format' => 'json',
      ),
      '@drebuild.dev'
    );
    $aliases = $this->getAliases();
    $rebuild_email = $aliases['dev']['#rebuild']['email'];
    $this->assertEquals('{"reroute_email_address":"' . $rebuild_email . '"}', $this->getOutput());

    // Check if our overrides were set.
    $this->drush('variable-get', array('site_slogan'), array(
      'alias-path' => $this->getTestsDir(),
      'format' => 'json'), '@drebuild.dev');
    $this->assertEquals('{"site_slogan":"RebuildMe"}', $this->getOutput());
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
    copy($this->getHomeDir() . '/.drush/rebuild/tests/site_install.rebuild.yaml', $this->getTestsDir() . '/rebuild.yaml');
    // Run the rebuild.
    $this->drush('rebuild', array('@drebuild.dev'),
      array(
        'include' => $this->getHomeDir() . '/.drush/rebuild',
        'alias-path' => $this->getTestsDir(), 'debug' => TRUE,
        'yes' => TRUE,
      )
    );
    // Check if the install succeeded.
    $this->drush('variable-get', array('install_profile'), array('alias-path' => $this->getTestsDir(), 'format' => 'json'), '@drebuild.dev');
    $this->assertEquals('{"install_profile":"minimal"}', $this->getOutput());
  }

}
