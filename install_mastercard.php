<?php
define('APPLICATION', 'Admin');
require_once __DIR__ . '/config.php';
require_once DIR_SYSTEM . 'startup.php';

// Autoloader
$autoloader = new \Opencart\System\Engine\Autoloader();
$autoloader->register('Opencart\Admin', DIR_APPLICATION);
$autoloader->register('Opencart\Extension', DIR_EXTENSION);
$autoloader->register('Opencart\System', DIR_SYSTEM);

try {
    $db = new \Opencart\System\Library\DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
    
    // Check if already installed
    $check = $db->query("SELECT * FROM " . DB_PREFIX . "extension_install WHERE `code` = 'mastercard'");
    if ($check->num_rows == 0) {
        $db->query("INSERT INTO " . DB_PREFIX . "extension_install (extension_id, extension_download_id, name, code, version, author, link, status, date_added) VALUES (0, 0, 'Mastercard Gateway', 'mastercard', '1.3.4', 'Fingent', 'https://www.opencart.com', 1, NOW())");
        echo "<h3>Success: Mastercard Gateway has been registered in the database!</h3>";
    } else {
        echo "<h3>Notice: Mastercard Gateway is already registered in the database.</h3>";
    }
} catch (\Exception $e) {
    echo "<h3>Error: " . $e->getMessage() . "</h3>";
}
