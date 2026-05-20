#!/usr/bin/env php
<?php

declare(strict_types=1);

ini_set('display_errors', 'on');
ini_set('display_startup_errors', 'on');

error_reporting(E_ALL);
date_default_timezone_set('America/Sao_Paulo');

!defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));
!defined('SWOOLE_HOOK_FLAGS') && define('SWOOLE_HOOK_FLAGS', SWOOLE_HOOK_ALL);

require BASE_PATH . '/vendor/autoload.php';

// In hyperf/support v3.1.65+, env() lives in namespace Hyperf\Support.
// Config files use the global env(), so we bridge it here before ClassLoader scans them.
if (! function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return \Hyperf\Support\env($key, $default);
    }
}

(new Hyperf\Di\ClassLoader())->init();

$container = require BASE_PATH . '/config/container.php';

$application = $container->get(Hyperf\Contract\ApplicationInterface::class);
$application->run();
