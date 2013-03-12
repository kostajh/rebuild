<?php

$aliases['dev'] = array(
  'root' => '/tmp/drush_rebuild/dev',
  'uri' => 'http://dev.drush.rebuild',
  'db-url' => 'mysql://root@localhost/drebuild_dev',
  'path-aliases' => array(
    '%rebuild' => '/tmp/drush_rebuild/rebuild.info',
  ),
  'rebuild' => array(
    'email' => 'me@example.com',
  ),
);

$aliases['prod'] = array(
  'root' => '/tmp/drush_rebuild/prod',
  'uri' => 'http://prod.drush.rebuild',
  'db-url' => 'mysql://root@localhost/drebuild_prod',
  'rebuild' => array(
    'email' => 'me@example.com',
  ),
);
