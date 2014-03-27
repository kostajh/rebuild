========
Overview
========

Requirements
============

#. PHP 5.3
#. Drush 6 or later

.. note::

  Drush Rebuild requires that you have installed Drush 6 or 7 using the
  `composer install method
  <https://github.com/drush-ops/drush#installupdate---composer>`_.

Installation
============

The recommended install method is via Drush:

.. code-block:: bash

   drush dl rebuild
   # Project rebuild (7.x-1.x) downloaded to $HOME/rebuild.

Use the same method to update to the latest stable release.

For a development version, install via git:

.. code-block:: bash

   cd ~/.drush
   git clone --branch 7.x-1.x http://git.drupal.org/project/rebuild.git

Runnning the tests
==================

Drush Rebuild contains a suite of tests. To run them, do:

.. code-block:: bash

   cd ~/.drush/rebuild/tests
   ./runtests.sh
