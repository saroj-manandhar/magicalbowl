<?php
// APPLICATION
define('APPLICATION', 'Admin');

if (getenv('DOCKER_ENV') === 'true' || isset($_SERVER['DOCKER_ENV']) || isset($_ENV['DOCKER_ENV']) || (is_dir('/var/www/html/new_site') && !file_exists('/Applications/MAMP'))) {
	// HTTP
	define('HTTP_SERVER', (getenv('HTTP_SERVER') ?: 'http://localhost:8080/new_site/') . 'msbadmin/');
	define('HTTP_CATALOG', getenv('HTTP_SERVER') ?: 'http://localhost:8080/new_site/');

	// DIR
	define('DIR_OPENCART', '/var/www/html/new_site/');
	define('DIR_APPLICATION', DIR_OPENCART . 'msbadmin/');
	define('DIR_EXTENSION', DIR_OPENCART . 'extension/');
	define('DIR_IMAGE', DIR_OPENCART . 'image/');
	define('DIR_SYSTEM', DIR_OPENCART . 'system/');
	define('DIR_CATALOG', DIR_OPENCART . 'catalog/');
	define('DIR_STORAGE', '/var/www/storage/');
	define('DIR_LANGUAGE', DIR_APPLICATION . 'language/');
	define('DIR_TEMPLATE', DIR_APPLICATION . 'view/template/');
	define('DIR_CONFIG', DIR_SYSTEM . 'config/');
	define('DIR_CACHE', DIR_STORAGE . 'cache/');
	define('DIR_DOWNLOAD', DIR_STORAGE . 'download/');
	define('DIR_LOGS', DIR_STORAGE . 'logs/');
	define('DIR_SESSION', DIR_STORAGE . 'session/');
	define('DIR_UPLOAD', DIR_STORAGE . 'upload/');

	// DB
	define('DB_DRIVER', getenv('DB_DRIVER') ?: 'mysqli');
	define('DB_HOSTNAME', getenv('DB_HOSTNAME') ?: 'localhost');
	define('DB_USERNAME', getenv('DB_USERNAME') ?: 'root');
	define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'root');
	define('DB_DATABASE', getenv('DB_DATABASE') ?: 'neww_msb');
	define('DB_PORT', getenv('DB_PORT') ?: '8889');
	define('DB_PREFIX', getenv('DB_PREFIX') ?: 'oc_');
	define('DB_SSL_KEY', '');
	define('DB_SSL_CERT', '');
	define('DB_SSL_CA', '');
} else {
	// HTTP
	define('HTTP_SERVER', 'http://localhost:8888/neww_magicalsingingbowls/msbadmin/');
	define('HTTP_CATALOG', 'http://localhost:8888/neww_magicalsingingbowls/');

	// DIR
	define('DIR_OPENCART', '/Users/sarojmanandhar/Sites/localhost/neww_magicalsingingbowls/');
	define('DIR_APPLICATION', DIR_OPENCART . 'msbadmin/');
	define('DIR_EXTENSION', DIR_OPENCART . 'extension/');
	define('DIR_IMAGE', DIR_OPENCART . 'image/');
	define('DIR_SYSTEM', DIR_OPENCART . 'system/');
	define('DIR_CATALOG', DIR_OPENCART . 'catalog/');
	define('DIR_STORAGE', '/Users/sarojmanandhar/Sites/neww_storage_oc4/');
	define('DIR_LANGUAGE', DIR_APPLICATION . 'language/');
	define('DIR_TEMPLATE', DIR_APPLICATION . 'view/template/');
	define('DIR_CONFIG', DIR_SYSTEM . 'config/');
	define('DIR_CACHE', DIR_STORAGE . 'cache/');
	define('DIR_DOWNLOAD', DIR_STORAGE . 'download/');
	define('DIR_LOGS', DIR_STORAGE . 'logs/');
	define('DIR_SESSION', DIR_STORAGE . 'session/');
	define('DIR_UPLOAD', DIR_STORAGE . 'upload/');

	// DB
	define('DB_DRIVER', 'mysqli');
	define('DB_HOSTNAME', 'localhost');
	define('DB_USERNAME', 'root');
	define('DB_PASSWORD', 'root');
	define('DB_DATABASE', 'neww_msb');
	define('DB_PORT', '8889');
	define('DB_PREFIX', 'oc_');
	define('DB_SSL_KEY', '');
	define('DB_SSL_CERT', '');
	define('DB_SSL_CA', '');
}

// OpenCart API
define('OPENCART_SERVER', 'https://www.opencart.com/');
