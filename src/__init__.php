<?php

// 渲染层的代码尽可能简单, 这里直接遍历包含源码的目录
function __autoload($className) {
  $dir = dirname(__FILE__);
  $sub_dirs = ['/', '/resources/', '/page/', '/dom/'];

  foreach ($sub_dirs as $sub_dir) {
    $path = $dir . $sub_dir . $className . '.php';
    $path = preg_replace('/\//', DIRECTORY_SEPARATOR, $path);
    if (file_exists($path)) {
      // 当文件有语法错误抛出异常
      $old = error_reporting(E_ALL & ~E_WARNING);
      $okay = include_once $path;
      error_reporting($old);

      // 只有当文件确实存在本库的目录中却在`include`失败后抛出异常, 其他情况交给
      // 注册的其他加载器实现
      if (!$okay) {
        throw new Exception("Include of '{$path}' failed!");
      }
      break;
    }
  }
}

// 加载全局函数的文件
include_once 'api.php';
include_once 'const.php';

// 注册自动加载
spl_autoload_register('__autoload');