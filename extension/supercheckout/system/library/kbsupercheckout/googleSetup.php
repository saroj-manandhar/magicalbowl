<?php

// Includes
$cwd = dirname(__FILE__);
set_include_path("$cwd" . PATH_SEPARATOR . get_include_path());

require_once 'googleSetup/google-api-php-client/apiClient.php';
require_once 'googleSetup/google-api-php-client/contrib/apiOauth2Service.php';
require_once 'googleSetup/idiorm.php';
require_once 'googleSetup/relativeTime.php';

// Session. Pass your own name if you wish.

//session_name('tzine_demo');

