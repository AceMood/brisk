<?php

/**
 * @class Santa项目的静态资源
 */
final class SantaResources extends BriskResourcesOnDisk {

    //项目名用作命名空间
    public function getName() {
        return 'santa';
    }

    //获取所有构建好的静态文件目录
    public function getPathToResources() {
        return $this->getProjectPath('webroot/');
    }

    //获取resource.json
    public function getPathToMap() {
        return $this->getProjectPath('dist/resource.json');
    }

    //获取工程目录下文件的路径
    private function getProjectPath($to_file) {
        return dirname(dirname(__FILE__)) . '/' . $to_file;
    }
}
