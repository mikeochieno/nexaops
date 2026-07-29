<?php
// Minimal autoloader (no Composer required)
spl_autoload_register(function ($class) {
    $base = dirname(__DIR__);
    $map = [
        'Core\\'      => $base . '/src/Core/',
        'Models\\'    => $base . '/src/Models/',
        'Services\\'  => $base . '/src/Services/',
        'Collectors\\'=> $base . '/src/Collectors/',
    ];
    foreach ($map as $prefix => $dir) {
        if (strpos($class, $prefix) === 0) {
            $file = $dir . str_replace($prefix, '', $class) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});
