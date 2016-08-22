<?php

function __autoload($className) {
    $dir = dirname(__FILE__);
    $subdirs = ['/lib/', '/', '/resources/', '/response/'];
    $path = '';
    foreach ($subdirs as $subdir) {
        if (file_exists($dir . $subdir . $className . '.php')) {
            $path = $dir . $subdir . $className . '.php';
            require $path;
            return;
        }
    }
}

spl_autoload_register('__autoload');