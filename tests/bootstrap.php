<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Throw-away JWT key pair for the test environment (see .env.test); never the real keys.
$jwtDir = dirname(__DIR__).'/var/jwt';
if (!is_file($jwtDir.'/test-private.pem') || !is_file($jwtDir.'/test-public.pem')) {
    if (!is_dir($jwtDir)) {
        mkdir($jwtDir, 0o775, true);
    }
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
    if (false === $key) {
        throw new RuntimeException('Cannot generate the test JWT key pair: '.openssl_error_string());
    }
    openssl_pkey_export($key, $privateKey, 'test');
    file_put_contents($jwtDir.'/test-private.pem', $privateKey);
    file_put_contents($jwtDir.'/test-public.pem', openssl_pkey_get_details($key)['key']);
}
