<?php

/**
 * @file
 * An example alias demonstrating the proper setup for Drush Rebuild.
 */

/**
 * This is an example of an alias for a local dev environment.
 *
 * You can learn quite a bit about Drush aliases by accessing
 * `drush docs-aliases`.
 */
$aliases['local'] = array(
  'root' => '/tmp/drush_rebuild/dev',
  'uri' => 'http://dev.drush.rebuild',
  'db-url' => 'mysql://root@localhost/drebuild_dev',
  'path-aliases' => array(
    // Under path aliases, you specify the full path to the rebuild config
    // for your local environment.
    '%rebuild' => '/tmp/drush_rebuild/rebuild.info',
  ),
  // In the rebuild section of your alias, you can define variables to replace
  // placeholders in your config file.
  //
  // For example, if you had variable[site_mail] = %email in your rebuild
  // config, then the value here would be swapped with the placeholder during
  // the rebuild.
  '#rebuild' => array(
    'email' => 'me@example.com',
  ),
);

$aliases['prod'] = array(
  'root' => '/tmp/drush_rebuild/prod',
  'uri' => 'http://prod.drush.rebuild',
  'db-url' => 'mysql://root@localhost/drebuild_prod',
);
