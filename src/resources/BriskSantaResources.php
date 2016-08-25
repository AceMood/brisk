<?php

/**
 * @class Santa项目的静态资源
 * 这个类可根据具体的项目有所不同, 如取得资源表文件的逻辑,
 * 后续考虑抽离出去通过配置文件, 这样不用每次都修改源码
 * TODO
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
