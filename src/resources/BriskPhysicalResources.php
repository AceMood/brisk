<?php

/**
 * Defines the location of physical static resources which exist at build time
 * and are precomputed into a resource map.
 */
abstract class BriskPhysicalResources extends BriskResources {

    //resource.json转换来的资源表
    private $map;

    //获取resource.json所在位置
    abstract public function getPathToMap();

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
