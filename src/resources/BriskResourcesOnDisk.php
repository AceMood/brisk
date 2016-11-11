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

  // 编译后资源的存放目录, 对应编译时工具设置的`dir`属性.
  private $distDirectory = '';

  // 获取resource.json所在位置
  abstract public function getPathToMap();

  // 获取packages.json位置
  abstract public function getPathToPackageMap();

  // 这个方法在原类库中取的是源码文件的目录, 请求时动态用一个php的工具对代码做压缩.
  // 觉得这样比较低效, 编译时产出了压缩后的代码, 所以这里应该加载最终代码的所处目录.
  function getPathToResources() {
    return $this->distDirectory;
  }

  /**
   * 读取文件内容
   * @param $name
   * @return string
   * @throws Exception
   */
  function getResourceData($name) {
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
      // 设置编译资源的目标目录
      if (isset(id($this->map)['root'])) {
        $this->distDirectory = id($this->map)['root'];
      }
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

  // 根据工程路径名 'static/a.js', 获得文件系统真实路径
  private function getPathToResource($name) {
    // 如`getPathToResources`所解释的那样, 希望直接读取编译后的最终代码.
    // 要知道两个参数:
    // a. 代码在本地的目录
    // b. 代码的最终名字
    $symbolMap = idx($this->map, 'resource', array());
    $nameMap = idx($this->map, 'paths', array());
    $symbol = $nameMap[$name];
    $type = $this->getResourceType($name);
    // 这里假设没有通过编译工具指定cdn, 或者是通过pageview设置的cdn, 此时uri是编译目录的相对路径
    $path = preg_replace('/^\//', '', $symbolMap[$type][$symbol]['localPathName']);

    return $this->getPathToResources() . DIRECTORY_SEPARATOR . $path;
  }
}
