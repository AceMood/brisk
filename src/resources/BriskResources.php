<?php

/**
 * Defines the location of static resources.
 */
abstract class BriskResources extends Phobject {
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
        return last(explode('.', $path));
    }

    //加载资源表
    public function loadMap() {
        return array();
    }
}