=============
Configuration
=============

The core of Drush Rebuild revolves around the configuration file. Consider the
command ``drush @example.local rebuild``. In this command, Drush Rebuild is
going to rebuild the local development environment for ``@example.local``, and
it is going to do so based on the tasks defined in the configuration file.

To start with, you should know that the rebuild config has three main sections: ``general``, ``sync/site_install``, and ``drupal``.

general
=======

.. code-block:: yaml

   general:
     description: 'Rebuilds local development environment from remote destination'
     uli: true
     overrides: 'local.rebuild.yaml'
     default_source: '@example.prod'
     drush_scripts:
       pre_process: ['example.php', 'another.php']
       post_process: 'after_rebuild.php'

description
-----------

This key is self-explanatory. It is displayed to the user when
they run ``drush @example.local rebuild``.

uli
---

This key tells Drush to run ``drush @example.local uli`` after completing
all the rebuild tasks. Set this to ``false``, or leave it out entirely, if you don't
want that.

overrides
---------

This key is used to specify the path to a local overrides
configuration file. For example, if your main configuration was at
``/home/kosta/sites/example/resources/rebuild.yaml``, your overrides would be at
``/home/kosta/sites/example/resources/local.rebuild.yaml``.

This is useful when a team is using the same rebuild file. You can define
a local overrides file, and exclude it from version control, so that each team
member can customize the rebuild process to their liking.

default_source
--------------

Drush Rebuild lets you rebuild your local environment based on any source
defined in your drush alias, so you could rebuild based on ``@staging`` or ``@prod`` aliases for example. But more often than not, you want your local
development environment to match one remote environment. By defining
default_source you can save yourself some typing. You'll be able to run ``drush
@example.local rebuild`` instead of ``drush @example.local rebuild --source=@example.prod``.

drush_scripts
-------------

This section tells Drush to run ``drush @example.local
php-script`` on the files specified in the ``pre_process`` and ``post_process``
sections. ``pre_process`` runs before any other step, while ``post_process``
comes at the very end. Note that returning ``FALSE`` from a Drush script will
halt the rebuild process.

sync /site_install
==================

The ``sync`` or ``site_install`` section tells Drush that we are either using
``sql-sync`` or ``site-install`` for rebuilding a local site. They are mutually
exclusive; you can't have both in the same config.

sql_sync
--------

This section lets you define options for syncing a remote database to
your local environment.

.. code-block:: yaml

  sql_sync:
    create-db: 'TRUE'
    sanitize: sanitize-email
    structure-tables-key: common

If you just wanted database syncing without any
additional options, you could write:

.. code-block:: yaml

  sync:
   sql_sync: true

Note that any option listed in ``drush help sql-sync`` can be defined in your
rebuild config file.

site_install
------------

If you are rebuilding by re-installing an install profile, you can set options like:

.. code-block:: yaml

  site_install:
    profile: 'standard'
    account-mail: 'admin@localhost'
    account-pass: 'admin'
    account-name: 'admin'
    site-name: 'Local install'

Any option listed in ``drush help site-install`` can be defined in the config file.

Review
======

Let's take a look at the entire file now:

.. code-block:: yaml

  general:
    description: 'Rebuilds local development environment from remote destination'
    uli: true
    overrides: 'local.rebuild.yaml'
    drush_scripts:
      pre_process: ['example.php', 'another.php']
      post_process: 'after_rebuild.php'

  sync:
    default_source: '@example.prod'
    sql_sync:
      create-db: 'TRUE'
      sanitize: 'sanitize-email'
      structure-tables-key: 'common'
    rsync:
      files_only: 'TRUE'

  drupal:
    variables:
      set:
        preprocess_js: 0
        preprocess_css: 0

    modules:
      enable:
        - devel
        - devel_node_access
        - dblog
        - views_ui
      disable:
        - overlay
        - syslog
      uninstall:
        - google_analytics

    permissions:
      anonymous user:
        grant:
          - access devel information
          - switch users
        revoke:
          - search content
      authenticated user:
        grant:
          - access devel information
        revoke:
          - search content


