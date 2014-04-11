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

  protected function getTestsDir() {
    return $this->getHomeDir() . '/.drush/rebuild/tests';
  }

  protected function getHomeDir() {
    return $_SERVER['HOME'];
  }

  protected function installTestSites() {
    $options = array(
      'site-name' => 'Dev',
      'alias-path' => $this->getTestsDir() . '/fixtures/aliases/dev',
      'yes' => TRUE,
      'quiet' => TRUE,
    );
    $this->drush('site-install', array('minimal'), $options, '@drebuild.dev');
  }

  /**
   * Test a basic rebuild.
   */
  public function testRebuild() {
    // Clear drush cache before running tests.
    $this->drush('cc', array('drush'));
    // @todo Copy test scripts.
    // Install Drupal on Prod with site name "Drush Rebuild Prod".
    $this->installTestSites();

    // Run the rebuild.
    $this->drush('env-rebuild', array(),
      array(
        'include' => $this->getHomeDir() . '/.drush/rebuild',
        'alias-path' => $this->getTestsDir(),
        'debug' => TRUE,
        'source' => '@drebuild.prod',
        'yes' => TRUE,
      ),
      '@drebuild.dev'
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
    // $this->assertContains('1', $this->getOutput());
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
    $this->drush('env-rebuild', array(), array(
      'include' => $this->getHomeDir() . '/.drush/rebuild',
      'alias-path' => $this->getTestsDir(),
      'view-config' => TRUE,
      ),
      '@drebuild.dev'
    );
    $this->assertContains('Rebuilds test Drush Rebuild local development environment from test Drush Rebuild prod destination', $this->getOutput());
  }

  /**
   * Tests the site install rebuild.
   */
  public function testSiteInstall() {
    // Run the rebuild.
    $this->drush('env-rebuild', array(''),
      array(
        'include' => $this->getHomeDir() . '/.drush/rebuild',
        'alias-path' => $this->getTestsDir(), 'debug' => TRUE,
        'yes' => TRUE,
      ),
      '@drebuild.dev'
    );
    // Check if the install succeeded.
    $this->drush('variable-get', array('install_profile'), array('alias-path' => $this->getTestsDir(), 'format' => 'json'), '@drebuild.dev');
    $this->assertEquals('{"install_profile":"minimal"}', $this->getOutput());
  }

}
