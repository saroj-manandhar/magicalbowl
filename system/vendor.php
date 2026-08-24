<?php
// aws/aws-crt-php

// aws/aws-sdk-php
$autoloader->register('Aws', DIR_STORAGE . 'vendor/aws/aws-sdk-php/src/', true);
if (is_file(DIR_STORAGE . 'vendor/aws/aws-sdk-php/src/functions.php')) {
	require_once(DIR_STORAGE . 'vendor/aws/aws-sdk-php/src/functions.php');
}

// braintree/braintree_php
$autoloader->register('Braintree', DIR_STORAGE . 'vendor/braintree/braintree_php/lib/', true);

// cardinity/cardinity-sdk-php
$autoloader->register('Cardinity', DIR_STORAGE . 'vendor/cardinity/cardinity-sdk-php/src/', true);

// divido/divido-php
$autoloader->register('Divido', DIR_STORAGE . 'vendor/divido/divido-php/lib/', true);

// guzzlehttp/guzzle
$autoloader->register('GuzzleHttp', DIR_STORAGE . 'vendor/guzzlehttp/guzzle/src/', true);

// guzzlehttp/log-subscriber
$autoloader->register('GuzzleHttp\Subscriber\Log', DIR_STORAGE . 'vendor/guzzlehttp/log-subscriber/src/', true);

// guzzlehttp/oauth-subscriber
$autoloader->register('GuzzleHttp\Subscriber\Oauth', DIR_STORAGE . 'vendor/guzzlehttp/oauth-subscriber/src/', true);

// guzzlehttp/promises
$autoloader->register('GuzzleHttp\Promise', DIR_STORAGE . 'vendor/guzzlehttp/promises/src/', true);

// guzzlehttp/psr7
$autoloader->register('GuzzleHttp\Psr7', DIR_STORAGE . 'vendor/guzzlehttp/psr7/src/', true);

// guzzlehttp/ringphp
$autoloader->register('GuzzleHttp\Ring', DIR_STORAGE . 'vendor/guzzlehttp/ringphp/src/', true);

// guzzlehttp/streams
$autoloader->register('GuzzleHttp\Stream', DIR_STORAGE . 'vendor/guzzlehttp/streams/src/', true);

// klarna/kco_rest
$autoloader->register('', DIR_STORAGE . 'vendor/klarna/kco_rest/src/', true);

// leafo/scssphp

// mtdowling/jmespath.php
$autoloader->register('JmesPath', DIR_STORAGE . 'vendor/mtdowling/jmespath.php/src/', true);
if (is_file(DIR_STORAGE . 'vendor/mtdowling/jmespath.php/src/JmesPath.php')) {
	require_once(DIR_STORAGE . 'vendor/mtdowling/jmespath.php/src/JmesPath.php');
}

// psr/http-client
$autoloader->register('Psr\Http\Client', DIR_STORAGE . 'vendor/psr/http-client/src/', true);

// psr/http-factory
$autoloader->register('Psr\Http\Message', DIR_STORAGE . 'vendor/psr/http-factory/src/', true);

// psr/http-message
$autoloader->register('Psr\Http\Message', DIR_STORAGE . 'vendor/psr/http-message/src/', true);

// psr/log
$autoloader->register('Psr\Log', DIR_STORAGE . 'vendor/psr/log/Psr/Log/', true);

// ralouphie/getallheaders
if (is_file(DIR_STORAGE . 'vendor/ralouphie/getallheaders/src/getallheaders.php')) {
	require_once(DIR_STORAGE . 'vendor/ralouphie/getallheaders/src/getallheaders.php');
}

// react/promise
$autoloader->register('React\Promise', DIR_STORAGE . 'vendor/react/promise/src/', true);
if (is_file(DIR_STORAGE . 'vendor/react/promise/src/functions_include.php')) {
	require_once(DIR_STORAGE . 'vendor/react/promise/src/functions_include.php');
}

// scssphp/scssphp
$autoloader->register('ScssPhp\ScssPhp', DIR_STORAGE . 'vendor/scssphp/scssphp/src/', true);

// symfony/deprecation-contracts
if (is_file(DIR_STORAGE . 'vendor/symfony/deprecation-contracts/function.php')) {
	require_once(DIR_STORAGE . 'vendor/symfony/deprecation-contracts/function.php');
}

// symfony/polyfill-ctype
$autoloader->register('Symfony\Polyfill\Ctype', DIR_STORAGE . 'vendor/symfony/polyfill-ctype//', true);
if (is_file(DIR_STORAGE . 'vendor/symfony/polyfill-ctype/bootstrap.php')) {
	require_once(DIR_STORAGE . 'vendor/symfony/polyfill-ctype/bootstrap.php');
}

// symfony/polyfill-mbstring
$autoloader->register('Symfony\Polyfill\Mbstring', DIR_STORAGE . 'vendor/symfony/polyfill-mbstring//', true);
if (is_file(DIR_STORAGE . 'vendor/symfony/polyfill-mbstring/bootstrap.php')) {
	require_once(DIR_STORAGE . 'vendor/symfony/polyfill-mbstring/bootstrap.php');
}

// symfony/polyfill-php81
$autoloader->register('Symfony\Polyfill\Php81', DIR_STORAGE . 'vendor/symfony/polyfill-php81//', true);
if (is_file(DIR_STORAGE . 'vendor/symfony/polyfill-php81/bootstrap.php')) {
	require_once(DIR_STORAGE . 'vendor/symfony/polyfill-php81/bootstrap.php');
}

// symfony/translation
$autoloader->register('Symfony\Component\Translation', DIR_STORAGE . 'vendor/symfony/translation//', true);

// symfony/validator
$autoloader->register('Symfony\Component\Validator', DIR_STORAGE . 'vendor/symfony/validator//', true);

// twig/twig
$autoloader->register('Twig', DIR_STORAGE . 'vendor/twig/twig/src/', true);
if (is_file(DIR_STORAGE . 'vendor/twig/twig/src/Resources/core.php')) {
	require_once(DIR_STORAGE . 'vendor/twig/twig/src/Resources/core.php');
}
if (is_file(DIR_STORAGE . 'vendor/twig/twig/src/Resources/debug.php')) {
	require_once(DIR_STORAGE . 'vendor/twig/twig/src/Resources/debug.php');
}
if (is_file(DIR_STORAGE . 'vendor/twig/twig/src/Resources/escaper.php')) {
	require_once(DIR_STORAGE . 'vendor/twig/twig/src/Resources/escaper.php');
}
if (is_file(DIR_STORAGE . 'vendor/twig/twig/src/Resources/string_loader.php')) {
	require_once(DIR_STORAGE . 'vendor/twig/twig/src/Resources/string_loader.php');
}

// zoujingli/wechat-php-sdk
$autoloader->register('Wechat', DIR_STORAGE . 'vendor/zoujingli/wechat-php-sdk/./Wechat/', true);