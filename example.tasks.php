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

// Rsync files from production to local

$cmd = "drush rsync $prod_name $self_name --verbose";
drush_shell_exec_interactive($cmd);

// Copy the database from production to local

$cmd = "drush sql-sync $prod_name $self_name --verbose";
drush_shell_exec_interactive($cmd);

// Disable modules

$modules = array(
  'googleanalytics',
);

foreach ($modules as $module) {
  drush_shell_exec("drush pm-disable $module -y");
}

// Enable modules

$modules = array(
  'devel',
);

foreach ($modules as $module) {
  drush_shell_exec("drush en $module -y");
}

// Disable caching

drush_shell_exec("drush $self_name vset preprocess_js 0");
drush_shell_exec("drush $self_name vset cache 0");
drush_shell_exec("drush $self_name vset preprocess_css 0");

drush_log('Rebuild complete', 'success');
