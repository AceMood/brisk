<?php

/**
 * @class 项目的静态资源
 * @author AceMood
 * @email zmike86@gmail.com
 */

//-------------

final class BriskStaticResources extends BriskResourcesOnDisk {

  private $mapPath = 'dist/resource.json';

  // 项目名用作命名空间
  function getName() {
    return 'brisk';
  }

  // 设置resource.json路径
  public function setPathToMap($path) {
    return $this->mapPath = $path;
  }

  // 获取resource.json
  function getPathToMap() {
    return $this->getProjectPath($this->mapPath);
  }

  // 获取packages.json的位置
  function getPathToPackageMap() {
    $dir = dirname($this->getProjectPath($this->mapPath));
    return $dir . DIRECTORY_SEPARATOR . 'packages.json';
  }

  //获取工程目录下文件的路径 todo
  private function getProjectPath($to_file) {
    return dirname(dirname(dirname(dirname(__FILE__)))) . '/' . $to_file;
  }
}
