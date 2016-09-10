<?php

function __autoload($className) {
    $dir = dirname(__FILE__);
    $subdirs = ['/', '/resources/', '/response/', '/view/'];
    $path = '';
    foreach ($subdirs as $subdir) {
        if (file_exists($dir . $subdir . $className . '.php')) {
            $path = $dir . $subdir . $className . '.php';
            require $path;
            return;
        }
    }
}

//加载libphutil
require_once '../lib/__phutil_library_init__.php';

spl_autoload_register('__autoload');