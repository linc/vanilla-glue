<?php 

// Add Glue's config info (where's Vanilla at?)
require_once(dirname(__FILE__).'/config.php');

// Fake Vanilla's index.php (sans Dispatcher)
define('APPLICATION', 'Vanilla');
define('APPLICATION_VERSION', '2.3');

// Report and track all errors.
error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR);
ini_set('display_errors', 'on');
ini_set('track_errors', 1);

ob_start();

// Define the constants we need to get going.
define('DS', DIRECTORY_SEPARATOR);
define('PATH_ROOT', VANILLA_PATH); // Previously defined by config; do not recalculate

// Include the bootstrap to configure the framework.
require_once(PATH_ROOT.'/bootstrap.php');

$Dispatcher = Gdn::dispatcher();


// 4. Fake the DiscussionsController
$Dispatcher->start();
$Controller = new DiscussionsController();
Gdn::controller($Controller);

// Define Vanilla's database prefix for easy access
$Prefix = Gdn::config('Database.DatabasePrefix');
define('VANILLA_PREFIX', $Prefix);

Gdn::request()->webRoot('');