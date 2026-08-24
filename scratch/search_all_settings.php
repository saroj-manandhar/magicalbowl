<?php
define('APPLICATION', 'Admin');
require_once __DIR__ . '/../msbadmin/config.php';
require_once DIR_SYSTEM . 'startup.php';

$autoloader = new \Opencart\System\Engine\Autoloader();
$autoloader->register('Opencart\Admin', DIR_APPLICATION);
$autoloader->register('Opencart\Extension', DIR_EXTENSION);
$autoloader->register('Opencart\System', DIR_SYSTEM);

$db = new \Opencart\System\Library\DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);

$query = $db->query("SELECT DISTINCT code FROM " . DB_PREFIX . "setting WHERE `code` LIKE 'payment_%'");
echo "Payment settings codes found:\n";
foreach ($query->rows as $row) {
    print_r($row);
}
