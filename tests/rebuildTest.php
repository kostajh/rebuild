<?php

/*
 * @file
 *   PHPUnit Tests for Drush Rebuild command. This uses Drush's own test
 *   framework, based on PHPUnit.  To run the tests, use:
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
class rebuildTestCase extends Drush_CommandTestCase {

  public function testRebuild() {
    // Make an alias for the dev/prod sites
    $aliases = array(
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
    if (file_exists('/tmp/drush_rebuild')) {
      unish_file_delete_recursive('/tmp/drush_rebuild');
    }
    mkdir('/tmp/drush_rebuild');
    touch('/tmp/drush_rebuild/drebuild.aliases.drushrc.php');
    file_put_contents('/tmp/drush_rebuild/drebuild.aliases.drushrc.php', $this->file_aliases($aliases));
    // Copy test rebuild file to /tmp/drush_rebuild/rebuild.info
    $rebuild_info = '
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
    touch('/tmp/drush_rebuild/rebuild.info');
    file_put_contents('/tmp/drush_rebuild/rebuild.info', $rebuild_info);
    // Copy test scripts to /tmp/drush_rebuild/
    // Install Drupal on Prod with site name "Drush Rebuild Prod"
    $options = array(
      'site-name' => 'Prod',
      'alias-path' => '/tmp/drush_rebuild',
      'yes' => TRUE,
    );
    if (!file_exists('/tmp/drush_rebuild/prod')) {
      mkdir('/tmp/drush_rebuild/prod');
    } else {
      unish_file_delete_recursive('/tmp/drush_rebuild/prod');
    }
    if (!file_exists('/tmp/drush_rebuild/dev')) {
      mkdir('/tmp/drush_rebuild/dev');
    } else {
      unish_file_delete_recursive('/tmp/drush_rebuild/dev');
    }
    // Install prod site
    $this->drush('dl', array('drupal'), array('drupal-project-rename' => 'prod', 'destination' => '/tmp/drush_rebuild', 'cache' => TRUE, 'yes' => TRUE));
    $this->drush('site-install', array('minimal'), $options, '@drebuild.prod');
    $this->log('Installed prod site');
    // Install dev site
    $this->drush('dl', array('drupal'), array('drupal-project-rename' => 'dev', 'destination' => '/tmp/drush_rebuild', 'cache' => TRUE, 'yes' => TRUE));
    $options['site-name'] = 'Dev';
    $this->drush('site-install', array('minimal'), $options, '@drebuild.dev');
    $this->log('Installed dev site');
    // Check that the name was set
    $this->drush('variable-get', array('site_name'), array('alias-path' => '/tmp/drush_rebuild', 'format' => 'json'), '@drebuild.dev');
    $this->assertEquals('"Dev"', $this->getOutput());
    $this->drush('variable-get', array('site_name'), array('alias-path' => '/tmp/drush_rebuild', 'format' => 'json'), '@drebuild.prod');
    $this->assertEquals('"Prod"', $this->getOutput());
    // Run the rebuild. If the site name for Dev is now Prod, the rebuild succeeded.
    $this->drush('rebuild', array('@drebuild.dev'), array('include' => '/Users/kosta/src/drupal/rebuild', 'alias-path' => '/tmp/drush_rebuild', 'debug' => TRUE, 'source' => '@drebuild.prod', 'yes' => TRUE), '@drebuild.dev');
    $this->drush('variable-get', array('site_name'), array('alias-path' => '/tmp/drush_rebuild', 'format' => 'json'), '@drebuild.dev');
    $this->assertEquals('"Prod"', $this->getOutput());
  }

}
