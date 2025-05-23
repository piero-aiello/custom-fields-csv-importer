<?php

/*
Plugin Name: Custom Fields CSV Importer
Description: Importa campi personalizzati per qualsiasi tipo di post da un file CSV.
Version: 1.0.0
Author: Piero Aiello
Author URI: https://pieroaiello.com
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: custom-fields-csv-importer
Domain Path: /languages
*/

if (!defined('ABSPATH')) {
    exit;
}

define('CFCI_PATH', plugin_dir_path(__FILE__));
define('CFCI_URL', plugin_dir_url(__FILE__));

require_once CFCI_PATH . 'includes/AdminPage.php';
require_once CFCI_PATH . 'includes/CSVHandle.php';
require_once CFCI_PATH . 'includes/Processor.php';

add_action('plugins_loaded', function () {
    if (class_exists('CFCI_AdminPage')) {
        CFCI_AdminPage::get_instance()->init();
    }
});
