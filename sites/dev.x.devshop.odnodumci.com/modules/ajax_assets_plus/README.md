Ajax Assets Plus
================

The module provides additional functionality for handling css and javascript
assets in ajax requests. It adds all the necessary ajax commands and libraries
to the render array.

Main benefits of the module:
- Cacheable Ajax requests.
- Resolve assets when transferring requests using Views REST export or some
  middleware(e.g. web-sockets).

Example of usage
----------------
Use the ajax_assets_plus_example module as an example of usage. You can find the
test controller and javascript there. Also see the `/tests` folder.

Running javascript tests
------------------------
To run the javascript tests the `PhantomJS` is required.

Instructions:
1. Install the [PhantomJS](http://phantomjs.org/download.html) on your computer.
1. Start the PhantomJS browser in the root folder of your Drupal 8 checkout:
   ```
   /path/to/phantomjs --ssl-protocol=any --ignore-ssl-errors=true vendor/jcalderonzumba/gastonjs/src/Client/main.js 8510 1024 768
   ```
1. Start tests using PHPUnit.

Requirements
------------

For the views integration the next Drupal core patch is required:
- Patch: https://www.drupal.org/files/issues/drupal_views-2895584-empty_request-3.patch
- Issue: https://www.drupal.org/node/2895584#comment-12172734
