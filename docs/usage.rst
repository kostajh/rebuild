=====
Usage
=====

Getting Started
===============

Drush Rebuild uses Drush Aliases to get things done. Don't know what Drush alias
is? Read ``drush docs-aliases`` and come back to this page. It's just a shortcut
for pointing to a site. It lets us conveniently run commands like ``drush
@example.prod core-status`` to get status info from production site.

Let's consider a Drupal site with the following Drush alias file defined in
``~/.drush/example.aliases.drushrc.php``:

.. code-block:: php

  $aliases['local'] = array(
    'root' => '/home/kosta/sites/example/live-docs',
    'uri' => 'http://local.example.com',
  );

  $aliases['stage'] = array(
    'uri' => 'http://stage.example.com',
    'root' => '/var/www/html/example.com/live-docs',
    'remote-host' => 'stage.example.com',
    'remote-user' => 'kosta',
  );

  $aliases['prod'] = array(
    'uri' => 'http://www.example.com',
    'root' => '/var/www/html/example.com/live-docs',
    'remote-host' => 'example.com',
    'remote-user' => 'kosta',
  );

Pretty straightforward. Now, we want to rebuild our local environment based on
the latest DB in production.

The traditional way might involve runninng ``mysqldump`` in production and
downloading that, then importing via ``mysql``. Or, more advanced users might do
something like ``drush sql-sync @example.prod @example.local`` -- but wait, we
also have to change some variables, and enable ``reroute_email``, etc. By the
time you're done, you might have run a dozen or two commands.

Now try repeating the process or asking your colleague to do so. Or wait a couple months and come back to the project, and try to remember the steps you took. We need a way to simplify this process and make it repeatable. That's where Drush Rebuild comes in.

Configuring your aliases
========================

As mentioned at the beginning, Drush Rebuild hooks into your existing aliases.
So let's look at our local alias again:

.. code-block:: php

  $aliases['local'] = array(
    'root' => '/home/kosta/sites/example/live-docs',
    'uri' => 'http://local.example.com',
  );

We're going to make a very simple change to this alias:

.. code-block:: php

  $aliases['local'] = array(
    'root' => '/home/kosta/sites/example/live-docs',
    'uri' => 'http://local.example.com',
    'path-aliases' => array(
      '%rebuild' => '/home/kosta/sites/example/resources/rebuild.yaml',
    ),
  );

What we are doing is telling Drush Rebuild that a configuration file exists at
``examples/resources/rebuild.yaml``.

Writing the configuration file
==============================

Commands
========


