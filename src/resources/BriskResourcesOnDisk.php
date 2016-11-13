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
  abstract public function getPathToResourceMap();

  // 获取packages.json位置
  abstract public function getPathToPackageMap();

  // 获取编译后代码目录
  abstract public function getPathToResources();

  /**
   * 读取文件内容
   * @param string $name 资源的工程路径
   * @return string
   * @throws Exception
   */
  public function getResourceData($name) {
    return BriskFilesystem::readFile($this->getPathToResource($name));
  }

  public function getResourceModifiedTime($name) {
    return (int)filemtime($this->getPathToResource($name));
  }

  public function loadResourceMap() {
    if ($this->map === null) {
      $mapPath = $this->getPathToResourceMap();
      $data = BriskFilesystem::readFile($mapPath);
      $this->map = json_decode($data, true);
    }
    return $this->map;
  }

  public function loadPackages() {
    if ($this->packages === null) {
      $mapPath = $this->getPathToPackageMap();
      $data = BriskFilesystem::readFile($mapPath);
      $this->packages = json_decode($data, true);
    }
    return $this->packages;
  }

  // 根据工程路径名 'static/a.js', 获得文件系统真实路径
  private function getPathToResource($name) {
    // 如`getPathToResources`所解释的那样, 希望直接读取编译后的最终代码.
    // 要知道两个参数:
    // a. 编译代码在本地的存放目录
    // b. 代码的相对路径
    // 其中第二项在编译时由每个资源的`localPathName`已经指定
    $symbolMap = idx($this->map, 'resource', array());
    $nameMap = idx($this->map, 'paths', array());
    $symbol = $nameMap[$name];
    $type = $this->getResourceType($name);
    // 这里假设没有通过编译工具指定cdn, 或者是通过pageview设置的cdn, 此时uri是编译目录的相对路径
    $path = preg_replace('/^\//', '', $symbolMap[$type][$symbol]['localPathName']);

    return $this->getPathToResources() . DIRECTORY_SEPARATOR . $path;
  }
}
