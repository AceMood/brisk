<?php

/**
 * Defines the location of static resources.
 */
abstract class BriskResources extends Phobject {
    //文件后缀类型映射
    private static $typeMap = array(
        'jsx' => 'js',
        'js' => 'js',
        'coffee' => 'js',
        'ts' => 'js',
        'less' => 'css',
        'css' => 'css',
        'scss' => 'css'
    );

    //项目名称作为命名空间
    abstract public function getName();

    //获取文件内容
    abstract public function getResourceData($name);

    //获取mtime
    public function getResourceModifiedTime($name) {
        return 0;
    }

    //获取资源类型 如js,css
    public function getResourceType($path) {
        $suffix = last(explode('.', $path));
        return self::$typeMap[$suffix];
    }

    //加载资源表
    public function loadMap() {
        return array();
    }
}