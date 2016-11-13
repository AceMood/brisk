<?php

/**
 * @file 定义SR的基类.
 * @author AceMood
 * @email zmike86@gmail.com
 */

//---------------

abstract class BriskResources {
  private static $typeMap = array(
    'jsx' => 'js',
    'js' => 'js',
    'coffee' => 'js',
    'ts' => 'js',
    'less' => 'css',
    'css' => 'css',
    'scss' => 'css',
    'png' => 'img',
    'jpg' => 'img',
    'jpeg' => 'img',
    'webp' => 'img',
    'bmp' => 'img',
    // Windows和Mac系统最常用的字体格式, 它们比基于矢量的字体更容易处理,
    // 保证了屏幕与打印输出的一致性. 同时这类字体和矢量字体一样可以随意缩放,
    // 旋转而不必担心会出现锯齿
    'ttf' => 'font',
    // 嵌入字体格式EOT是微软开发的一种技术, 允许OpenType字体嵌入到网页并
    // 可以下载至浏览器渲染. 浏览器根据CSS中@font-face 的定义,下载渲染这种字体文件.
    // 这些文件只在当前页活动的状态下, 临时安装在用户的系统中
    'eot' => 'font',

    'otf' => 'font',

    'woff' => 'font',

    'svg' => 'font'
  );

  // 项目名称作为命名空间
  abstract public function getProjectName();

  // 获取文件内容
  abstract public function getResourceData($name);

  // 获取文件mtime
  public function getResourceModifiedTime($name) {
    return 0;
  }

  // 获取资源类型 如js,css,img
  public function getResourceType($path) {
    $arr = explode('.', $path);
    // `end`只能传变量
    $suffix = end($arr);
    return self::$typeMap[$suffix];
  }

  // 加载资源表
  public function loadResourceMap() {
    return array();
  }

  // 加载打包信息
  public function loadPackages() {
    return array();
  }
}