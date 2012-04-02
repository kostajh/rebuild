<?php

#!/usr/bin/env drush

// Example rebuild script

// local alias
$self_record = drush_sitealias_get_record('@self');
$self_name = '@' . $self_record['#name'];

if (empty($self_record)) {
  return drush_set_error('No bootstrapped site!');
} else {
  drush_print(dt('Rebuilding site for !name', array('!name' => $self_record['#name'])));
}

// prod alias
$prod_record = drush_sitealias_get_record('@' . $self_record['#group'] . '.prod');
$prod_name = '@' . $prod_record['#name'];

// stage alias
$stage_record = drush_sitealias_get_record('@' . $self_record['#group'] . '.stage');
$stage_name = '@' . $stage_record['#name'];

// Rsync files from staging to local
drush_invoke_process('@self', 'rsync', array($stage_name, $self_name), array('verbose'), array('interactive'));

// Copy the database from staging to local
drush_invoke_process('@self', 'sql-sync', array($stage_name, $self_name), array('verbose'), array('interactive'));

// Disable modules

$modules = array(
  'googleanalytics',
);

module_disable($modules);

// Enable modules

$modules = array(
  'devel',
);

module_enable($modules);

// Disable caching

variable_set('preprocess_js', 0);
variable_set('cache', 0);
variable_set('preprocess_css',0);

// Set site name
variable_set('site_name', 'Dev Environment');

drush_log('Rebuild complete', 'success');
