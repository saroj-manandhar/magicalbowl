<?php
define('APPLICATION', 'Admin');
require_once __DIR__ . '/../msbadmin/config.php';
require_once DIR_SYSTEM . 'startup.php';

// Autoloader
$autoloader = new \Opencart\System\Engine\Autoloader();
$autoloader->register('Opencart\Admin', DIR_APPLICATION);
$autoloader->register('Opencart\Extension', DIR_EXTENSION);
$autoloader->register('Opencart\System', DIR_SYSTEM);

$db = new \Opencart\System\Library\DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);

$query = $db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE `value` LIKE '%#%'");
foreach ($query->rows as $row) {
    if (strlen($row['value']) < 200) {
        echo $row['code'] . " | " . $row['key'] . " => " . $row['value'] . "\n";
    } else {
        echo $row['code'] . " | " . $row['key'] . " => [LONG VALUE: " . strlen($row['value']) . " bytes]\n";
    }
}
