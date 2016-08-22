<?php

function __autoload($className) {
    $dir = dirname(__FILE__);
    $path = '';
    if (file_exists($dir . '/lib/' . $className . '.php')) {
        $path = $dir . '/lib/' . $className . '.php';
    } else if (file_exists($dir . '/' . $className . '.php')) {
        $path = $dir . '/' . $className . '.php';
    } else if (file_exists($dir . '/resources/' . $className . '.php')) {
        $path = $dir . '/resources/' . $className . '.php';
    }

    require $path;
}

spl_autoload_register('__autoload');