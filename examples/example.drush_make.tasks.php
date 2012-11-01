<?php

#!/usr/bin/env drush

// local alias
$self_record = drush_sitealias_get_record('@self');
$self_name = '@' . $self_record['#name'];

if (empty($self_record)) {
  return drush_set_error('No bootstrapped site!');
} else {
  drush_print(dt('Rebuilding site for !name', array('!name' => $self_record['#name'])));
}

