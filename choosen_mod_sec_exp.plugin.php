<?php
/**
 * Plugin Name: Choosen ModSec
 * Plugin URI: https://github.com/erwansetyobudi/slims-chosen-modsec/
 * Description: Module Security SLiMS 9.72
 * Version: 2.0
 * Author: Drajat Hasan and Erwan Setyo Budi
 * Author URI: https://github.com/erwansetyobudi
 */

// get plugin instance
$plugin = \SLiMS\Plugins::getInstance();

$plugin->register('bibliography_init', function() {
   if (!isset($_GET['inPopUp'])) {
      global $dbs,$sysconf;
      include __DIR__ . '/index.php';
      exit;
   }
});
