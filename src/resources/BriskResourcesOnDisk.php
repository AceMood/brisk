<?php

/**
 * Defines the location of physical static resources which exist at build time
 * and are precomputed into a resource map.
 */
abstract class BriskResourcesOnDisk extends BriskResources {

    //resource.json转换来的资源表
    private $map;

    //获取resource.json所在位置
    abstract public function getPathToMap();

    // return source code directory
    abstract public function getPathToResources();

    // according to name as 'static/a.js', get the real file-system path
    private function getPathToResource($name) {
        return $this->getPathToResources() . DIRECTORY_SEPARATOR . $name;
    }

    //读取文件内容
    public function getResourceData($name) {
        return Filesystem::readFile($this->getPathToResource($name));
    }

    //获取资源mtime
    public function getResourceModifiedTime($name) {
        return (int)filemtime($this->getPathToResource($name));
    }

    //加载resource.json并转化成php数组
    public function loadMap() {
        if ($this->map === null) {
            $mapPath = $this->getPathToMap();
            $data = Filesystem::readFile($mapPath);
            $this->map = json_decode($data, true);
        }
        return $this->map;
    }
}
