## Drush Rebuild

`Drush Rebuild` is a Drush extension that facilitates rebuilding local development
environments.

## Installation

Type `drush dl rebuild`, and Drush Rebuild should download to `~/.drush/rebuild`.

Type `drush help rebuild` for an overview of available options.

Type `drush topic rebuild-readme` for usage and `drush topic rebuild-example` for an example.

## Usage

Drush Rebuild relies on Drush aliases. Your Drush alias must have an entry under
`path-aliases` for the path to your `rebuild.info` file.

Any additional values can be defined in a `rebuild` array in your alias.

For example:

	<?php

	$aliases['local'] = array(
	  'root' => '/path/to/site',
	  'path-aliases' => array(
  	  '%rebuild' => '/path/to/rebuild.info',
	  ),
	  'rebuild' => array(
    	'email' => 'you@youremail.com', // Useful for setting an email with Reroute Email module
	  ),
	);

	?>

As an extension, Drush Rebuild doesn't make many assumptions about your development workflow
(i.e. Drush Make, entire codebase is in the Git repo, symlinks setup from a repo
to another directory, etc), nor does it care about extra steps you need to take
when configuring a development environment, like disabling caching, adjusting
connections with 3rd party services, and so on. All of that should be defined in
`rebuild.info`, and any pre-process or post-process scripts executed by your
custom `rebuild.info` file.

This means that you can create rebuild task scripts for your different sites, yet
have a single mechanism to trigger a rebuild. So your themer or site builder
doesn't have to know about `drush rsync` or `drush sql-sync`, they can just run
the rebuild command and have a working local development environment.

## Creating your own rebuild.info file

`examples/example.rebuild.info` is a helpful example of how to set up a file.

## Example usage

`drush rebuild @mysite.local --source=@mysite.prod`

## Meta-data

Drush Rebuild stores information about each site in a drush cache file in the
`rebuild` bin. Each alias gets its own cache ID in the bin. This is used to
display some statistics during a rebuild process.
