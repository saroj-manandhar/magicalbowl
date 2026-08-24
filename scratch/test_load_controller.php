<?php
define('APPLICATION', 'Admin');
require_once __DIR__ . '/../config.php';
require_once DIR_SYSTEM . 'startup.php';

$autoloader = new \Opencart\System\Engine\Autoloader();
$autoloader->register('Opencart\Admin', DIR_APPLICATION);
$autoloader->register('Opencart\Extension', DIR_EXTENSION);
$autoloader->register('Opencart\System', DIR_SYSTEM);

$registry = new \Opencart\System\Engine\Registry();
$registry->set('autoloader', $autoloader);

$config = new \Opencart\System\Engine\Config();
$config->addPath(DIR_CONFIG);
$config->load('default');
$config->load('admin');
$registry->set('config', $config);

// Set default application just like framework.php does:
$config->set('application', APPLICATION);

$db = new \Opencart\System\Library\DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
$registry->set('db', $db);

// Log
$log = new \Opencart\System\Library\Log($config->get('error_filename'));
$registry->set('log', $log);

// Event
$event = new \Opencart\System\Engine\Event($registry);
$registry->set('event', $event);

// Factory
$factory = new \Opencart\System\Engine\Factory($registry);
$registry->set('factory', $factory);

// Loader
$loader = new \Opencart\System\Engine\Loader($registry);
$registry->set('load', $loader);

// Run startups just like OpenCart does
$results = $db->query("SELECT * FROM " . DB_PREFIX . "extension_install");
foreach ($results->rows as $result) {
    $extension = str_replace(['_', '/'], ['', '\\'], ucwords($result['code'], '_/'));
    $autoloader->register('Opencart\Admin\Controller\Extension\\' . $extension, DIR_EXTENSION . $result['code'] . '/admin/controller/');
    $autoloader->register('Opencart\Admin\Model\Extension\\' . $extension, DIR_EXTENSION . $result['code'] . '/admin/model/');
    $autoloader->register('Opencart\System\Library\Extension\\' . $extension, DIR_EXTENSION . $result['code'] . '/system/library/');
}

$route = 'extension/mastercard/payment/mastercard';
$app = $config->get('application');
$class = 'Opencart\\' . $app . '\Controller\\' . str_replace(['_', '/'], ['', '\\'], ucwords($route, '_/'));

echo "Application name: " . $app . "\n";
echo "Generated class name: " . $class . "\n";

if (class_exists($class)) {
    echo "Class EXISTS!\n";
} else {
    echo "Class DOES NOT EXIST!\n";
}
