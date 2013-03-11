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
  public function testIssueQueue() {
    $this->doIssueQueueTests(array(), array('package-handler' => 'git_drupalorg'));
    $this->assertFileExists(UNISH_SANDBOX . '/devel/.git');
  }

  public function testIssueQueueNoGitMode() {
    $this->setUpFreshSandBox();
    $this->doIssueQueueTests(array('no-git' => NULL), array('package-handler' => 'git_drupalorg'));
    $this->assertFileExists(UNISH_SANDBOX . '/devel/.git');
  }

  public function testIssueQueuePlainDownload() {
    $this->setUpFreshSandBox();
    $this->doIssueQueueTests(array(), array());
  }

  public function doIssueQueueTests($iq_apply_patch_options, $iq_dl_options = array()) {
    $iq_include = array('include' => dirname(__FILE__));
    $iq_dl_options += array(
      'cache' => NULL,
      'yes' => NULL,
    );
    // Download an old version of devel to insulate ourselves from changes
    $this->drush('pm-download', array('devel-7.x-1.2'), $iq_dl_options);
    $this->assertFileExists(UNISH_SANDBOX . '/devel/README.txt');

    chdir(UNISH_SANDBOX . '/devel');
    $this->drush('iq-apply-patch', array('1262694'), $iq_apply_patch_options + $iq_include + array('base' => 'devel-test'));
    $this->drush('iq-diff', array(), $iq_include);
    $output = $this->getOutput();
    $this->assertContains("white-space: pre;", $output, 'Line added by patch');
    $this->drush('iq-reset', array(), array('hard' => NULL, 'yes' => NULL) + $iq_include);
    $this->drush('iq-diff', array(), $iq_include);
    $output = $this->getOutput();
    //$this->assertEmpty($output, 'Reset removed changes');
    $this->assertEquals('', $output);
  }
}
