<?php

/**
 * @file Defines the location of physical static resources which exist at build time
 * and are precomputed into a resource map.
 * @author AceMood
 * @email zmike86@gmail.com
 */

//-------------

abstract class BriskResourcesOnDisk extends BriskResources {
  // resource.json转换来的资源表
  private $map;

  // packages.json转换来的资源表
  private $packages;

  // 获取resource.json所在位置
  abstract public function getPathToMap();

  // 获取packages.json位置
  abstract public function getPathToPackageMap();

  // return source code directory
  abstract public function getPathToResources();

  // according to name as 'static/a.js', get the real file-system path
  private function getPathToResource($name) {
    $symbolMap = idx($this->map, 'resource', array());
    $nameMap = idx($this->map, 'paths', array());
    $symbol = $nameMap[$name];
    $type = $this->getResourceType($name);
    // todo 这里假设没有通过编译工具指定cdn, 或者是通过pageview设置的cdn, 此时uri是编译目录的相对路径
    $uri = preg_replace('/^\//', '', $symbolMap[$type][$symbol]['uri']);

    return $this->getPathToResources() . DIRECTORY_SEPARATOR . $uri;
  }

  /**
   * 读取文件内容
   * @param $name
   * @return string
   * @throws FilesystemException
   */
  public function getResourceData($name) {
    return BriskFilesystem::readFile($this->getPathToResource($name));
  }

  function getResourceModifiedTime($name) {
    return (int)filemtime($this->getPathToResource($name));
  }

  function loadMap() {
    if ($this->map === null) {
      $mapPath = $this->getPathToMap();
      $data = BriskFilesystem::readFile($mapPath);
      $this->map = json_decode($data, true);
    }
    return $this->map;
  }

  function loadPackages() {
    if ($this->packages === null) {
      $mapPath = $this->getPathToPackageMap();
      $data = BriskFilesystem::readFile($mapPath);
      $this->packages = json_decode($data, true);
    }
    return $this->packages;
  }
}
