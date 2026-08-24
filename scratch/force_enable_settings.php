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

// Clean up existing settings if any
$db->query("DELETE FROM " . DB_PREFIX . "setting WHERE code = 'payment_mastercard'");

// Insert settings
$settings = [
    'payment_mastercard_status' => '1',
    'payment_mastercard_test' => '1',
    'payment_mastercard_title' => 'Mastercard Gateway',
    'payment_mastercard_test_merchant_id' => 'TEST1234567890',
    'payment_mastercard_test_api_password' => 'abcdef1234567890',
    'payment_mastercard_api_gateway_other' => 'https://test-gateway.com',
    'payment_mastercard_integration_model' => 'hostedcheckout',
    'payment_mastercard_hc_type' => 'redirect',
    'payment_mastercard_approved_status_id' => '2',
    'payment_mastercard_declined_status_id' => '8',
    'payment_mastercard_pending_status_id' => '1',
    'payment_mastercard_sort_order' => '1'
];

foreach ($settings as $key => $value) {
    $db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '0', code = 'payment_mastercard', `key` = '" . $db->escape($key) . "', `value` = '" . $db->escape($value) . "', serialized = '0'");
}

echo "Mastercard Gateway settings inserted successfully and enabled!\n";
