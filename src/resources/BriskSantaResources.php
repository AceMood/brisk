<?php

/**
 * @class Santa项目的静态资源
 */
final class BriskSantaResources extends BriskResourcesOnDisk {

    private $webdir = 'dist';
    private $mapPath = 'dist/resource.json';

    //项目名用作命名空间
    public function getName() {
        return 'santa';
    }

    //设置构建好的静态文件目录
    public function setPathToResources($dir) {
        return $this->webdir = $dir;
    }

    //获取所有构建好的静态文件目录
    public function getPathToResources() {
        return $this->getProjectPath($this->webdir);
    }

    //设置resource.json路径
    public function setPathToMap($path) {
        return $this->mapPath = $path;
    }

    //获取resource.json
    public function getPathToMap() {
        return $this->getProjectPath($this->mapPath);
    }

    //获取工程目录下文件的路径
    private function getProjectPath($to_file) {
        return dirname(dirname(dirname(dirname(__FILE__)))) . '/' . $to_file;
    }
}
