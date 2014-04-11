<?php

$aliases['dev'] = array(
  'root' => $_SERVER['HOME'] . '/.drush/rebuild/tests/fixtures/drupal_sites/dev',
  'uri' => 'http://dev.drush.rebuild',
  'path-aliases' => array(
    '%rebuild' => $_SERVER['HOME'] . '/.drush/rebuild/tests/fixtures/config/rebuild.sync.yaml',
  ),
  '#rebuild' => array(
    'email' => 'me@example.com',
  ),
);

$aliases['prod'] = array(
  'root' => $_SERVER['HOME'] . '/.drush/rebuild/tests/fixtures/drupal_sites/prod',
  'uri' => 'http://prod.drush.rebuild',
);
